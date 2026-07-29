<?php

/**
 * Log peristiwa terperinci di tabel `events`, satu baris per kejadian.
 *
 * Kolom `ym` ('2026-07') sengaja disimpan terpisah agar daftar bulan dan
 * penghapusan manual per rentang bulan berjalan lewat indeks, tanpa memaksa
 * MySQL menghitung fungsi tanggal pada setiap baris.
 *
 * Data TIDAK pernah dihapus otomatis.
 */

/**
 * Penanda pengunjung yang berganti tiap hari dan tidak bisa dikembalikan
 * menjadi alamat IP. Cukup untuk menghitung pengunjung unik harian.
 */
function event_visitor_hash()
{
    static $hash = null;
    if ($hash !== null) {
        return $hash;
    }

    // Ambil satu baris saja. settings_all() menarik seluruh tabel pengaturan
    // padahal jalur publik hanya butuh salt ini.
    $salt = db_val('SELECT v FROM settings WHERE k = ?', ['visitor_salt'], '');
    if ($salt === '' || $salt === null) {
        $salt = bin2hex(random_bytes(16));
        db_run('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)',
            ['visitor_salt', $salt]);
        mem_clear('settings');
    }

    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]
        ?? '';
    if (trim((string) $ip) === '') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    return $hash = substr(hash('sha256', $salt . '|' . today() . '|' . trim($ip) . '|' . $ua), 0, 12);
}

function event_log($slug, $event, $src = '')
{
    if ($slug === '') {
        return;
    }

    $p = ua_parse($_SERVER['HTTP_USER_AGENT'] ?? '');

    $ref = '';
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        $ref = is_string($host) ? mb_substr($host, 0, 190) : '';
    }

    $now = date('Y-m-d H:i:s');

    db_run(
        'INSERT INTO events (slug, occurred_at, ym, event, source, device, os, browser, webview, referrer, visitor)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [
            $slug, $now, substr($now, 0, 7), $event, $src,
            $p['device'], mb_substr($p['os'], 0, 40), mb_substr($p['browser'], 0, 40),
            $p['webview'] ? 1 : 0, $ref, event_visitor_hash(),
        ]
    );
}

/* ------------------------------------------------------- Kueri analitik */

/** Bangun potongan WHERE + parameter dari filter yang aktif. */
function event_where($slug, $from, $to, array $f)
{
    $sql = ' WHERE slug = ? AND occurred_at >= ? AND occurred_at <= ?';
    $par = [$slug, $from . ' 00:00:00', $to . ' 23:59:59'];

    if (!empty($f['hide_bots'])) {
        $sql .= " AND device <> 'bot'";
    }
    if (!empty($f['event'])) {
        $sql .= ' AND event = ?';
        $par[] = $f['event'];
    }
    if (!empty($f['device'])) {
        $sql .= ' AND device = ?';
        $par[] = $f['device'];
    }
    if (!empty($f['source'])) {
        $sql .= ' AND source = ?';
        $par[] = $f['source'];
    }
    return [$sql, $par];
}

/**
 * Seluruh angka untuk halaman analitik, dihitung dari SATU kali baca.
 *
 * Versi sebelumnya menjalankan dua belas kueri agregat terpisah, yang berarti
 * dua belas kali memindai rentang yang sama. Pada 63 ribu baris cara itu
 * memakan 1.656 ms, sedangkan satu lintasan yang diagregasi di PHP hanya 335 ms.
 *
 * Pengecualian: COUNT(DISTINCT visitor) tetap dikerjakan MySQL. Menampung
 * ratusan ribu penanda pengunjung dalam array PHP berisiko menembus memory_limit,
 * sementara MySQL menghitungnya tanpa membebani memori PHP.
 */
function event_report($slug, $from, $to, array $filters = [])
{
    // Bot disaring di PHP, bukan di SQL, supaya jumlahnya tetap bisa dilaporkan
    // tanpa perlu satu pemindaian tambahan.
    $sqlFilters = $filters;
    unset($sqlFilters['hide_bots']);
    [$w, $p] = event_where($slug, $from, $to, $sqlFilters);

    $rep = [
        'count' => 0,
        'events' => array_fill_keys(STAT_EVENTS, 0),
        'daily' => [],
        'hourly' => array_fill(0, 24, 0),
        'weekday' => array_fill(0, 7, 0),
        'device' => [],
        'os' => [],
        'browser' => [],
        'source' => [],
        'referrer' => [],
        'webview' => 0,
        'bots' => 0,
        'recent' => [],
        'unique' => 0,
    ];

    // Kerangka hari supaya grafik tetap lengkap termasuk hari bernilai nol
    $cur = strtotime($from);
    $end = strtotime($to);
    while ($cur <= $end) {
        $rep['daily'][date('Y-m-d', $cur)] = array_fill_keys(STAT_EVENTS, 0);
        $cur = strtotime('+1 day', $cur);
    }

    $sembunyikanBot = !empty($filters['hide_bots']);
    $hariCache = [];

    $st = db_stream(
        "SELECT occurred_at, event, source, device, os, browser, webview, referrer FROM events$w",
        $p
    );

    while ($r = $st->fetch(PDO::FETCH_NUM)) {
        // 0=occurred_at 1=event 2=source 3=device 4=os 5=browser 6=webview 7=referrer
        if ($r[3] === 'bot') {
            $rep['bots']++;
            if ($sembunyikanBot) {
                continue;
            }
        }

        $rep['count']++;
        if (isset($rep['events'][$r[1]])) {
            $rep['events'][$r[1]]++;
        }

        $hari = substr($r[0], 0, 10);
        if (isset($rep['daily'][$hari][$r[1]])) {
            $rep['daily'][$hari][$r[1]]++;
        }

        // Jam diambil langsung dari string; memanggil strtotime per baris mahal
        $rep['hourly'][(int) substr($r[0], 11, 2)]++;

        if (!isset($hariCache[$hari])) {
            $hariCache[$hari] = (int) date('w', strtotime($hari));
        }
        $rep['weekday'][$hariCache[$hari]]++;

        $dev = $r[3] !== '' ? $r[3] : '(tidak diketahui)';
        $os = $r[4] !== '' ? $r[4] : '(tidak diketahui)';
        $br = $r[5] !== '' ? $r[5] : '(tidak diketahui)';
        $src = $r[2] !== '' ? $r[2] : '(tidak diketahui)';
        $rep['device'][$dev] = ($rep['device'][$dev] ?? 0) + 1;
        $rep['os'][$os] = ($rep['os'][$os] ?? 0) + 1;
        $rep['browser'][$br] = ($rep['browser'][$br] ?? 0) + 1;
        $rep['source'][$src] = ($rep['source'][$src] ?? 0) + 1;

        if ($r[7] !== '') {
            $rep['referrer'][$r[7]] = ($rep['referrer'][$r[7]] ?? 0) + 1;
        }
        if ($r[6]) {
            $rep['webview']++;
        }
    }
    db_stream_end($st);

    foreach (['device', 'os', 'browser', 'source', 'referrer'] as $k) {
        arsort($rep[$k]);
    }
    $rep['referrer'] = array_slice($rep['referrer'], 0, 12, true);

    // Dihitung MySQL agar memori PHP tidak menampung ratusan ribu penanda
    [$uw, $up] = event_where($slug, $from, $to, $filters);
    $rep['unique'] = (int) db_val("SELECT COUNT(DISTINCT visitor) FROM events$uw", $up, 0);

    [$w, $p] = [$uw, $up];
    $rep['recent'] = array_map(function ($r) {
        return [
            't' => $r['occurred_at'], 'e' => $r['event'], 's' => $r['source'],
            'd' => $r['device'], 'o' => $r['os'], 'b' => $r['browser'],
            'w' => (int) $r['webview'], 'r' => $r['referrer'], 'v' => $r['visitor'],
        ];
    }, db_all("SELECT * FROM events$w ORDER BY id DESC LIMIT " . (int) EVENT_RECENT_LIMIT, $p));

    return $rep;
}

/** Alirkan log terfilter sebagai CSV tanpa menampung seluruhnya di memori. */
function event_export_csv($slug, $from, $to, array $filters = [])
{
    [$w, $p] = event_where($slug, $from, $to, $filters);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="analitik-' . $slug . '-' . $from . '_' . $to . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8 dengan benar
    fputcsv($out, ['Tanggal', 'Jam', 'Peristiwa', 'Sumber', 'Perangkat', 'Sistem Operasi', 'Browser', 'Dalam Aplikasi', 'Perujuk', 'Pengunjung']);

    $st = db()->prepare("SELECT * FROM events$w ORDER BY id ASC");
    $st->execute($p);
    while ($r = $st->fetch()) {
        fputcsv($out, [
            substr($r['occurred_at'], 0, 10),
            substr($r['occurred_at'], 11, 8),
            $r['event'], $r['source'], $r['device'], $r['os'], $r['browser'],
            $r['webview'] ? 'ya' : 'tidak', $r['referrer'], $r['visitor'],
        ]);
    }

    fclose($out);
    exit;
}

/* ------------------------------------------------ Pemeliharaan (manual) */

/**
 * Daftar bulan yang punya data, lengkap dengan jumlah barisnya.
 * @param string $slug Kosong berarti seluruh PWA
 */
function event_months_available($slug = '')
{
    if ($slug !== '') {
        return db_all(
            'SELECT ym, COUNT(*) AS n, MIN(occurred_at) AS awal, MAX(occurred_at) AS akhir
             FROM events WHERE slug = ? GROUP BY ym ORDER BY ym DESC',
            [$slug]
        );
    }
    return db_all(
        'SELECT ym, COUNT(*) AS n, MIN(occurred_at) AS awal, MAX(occurred_at) AS akhir
         FROM events GROUP BY ym ORDER BY ym DESC'
    );
}

function event_count_in_range($fromYm, $toYm, $slug = '')
{
    if ($slug !== '') {
        return (int) db_val(
            'SELECT COUNT(*) FROM events WHERE slug = ? AND ym >= ? AND ym <= ?',
            [$slug, $fromYm, $toYm],
            0
        );
    }
    return (int) db_val('SELECT COUNT(*) FROM events WHERE ym >= ? AND ym <= ?', [$fromYm, $toYm], 0);
}

/**
 * Hapus log pada rentang bulan tertentu, dipecah per batch agar tabel besar
 * tidak terkunci lama dan redo log tidak membengkak.
 *
 * @return array{events:int, daily:int}
 */
function event_delete_range($fromYm, $toYm, $slug = '', $alsoDaily = true)
{
    $hapus = 0;
    $awal = $fromYm . '-01';
    $akhir = date('Y-m-t', strtotime($toYm . '-01'));

    do {
        if ($slug !== '') {
            // Rentang tanggal, bukan ym: hanya bentuk ini yang memakai idx_slug_time
            $n = db_run(
                'DELETE FROM events WHERE slug = ? AND occurred_at >= ? AND occurred_at <= ?
                 LIMIT ' . (int) EVENT_DELETE_CHUNK,
                [$slug, $awal . ' 00:00:00', $akhir . ' 23:59:59']
            );
        } else {
            // Tanpa slug, idx_ym yang paling selektif
            $n = db_run(
                'DELETE FROM events WHERE ym >= ? AND ym <= ? LIMIT ' . (int) EVENT_DELETE_CHUNK,
                [$fromYm, $toYm]
            );
        }
        $hapus += $n;
    } while ($n > 0);

    $harian = 0;
    if ($alsoDaily) {
        if ($slug !== '') {
            $harian = db_run(
                'DELETE FROM stats_daily WHERE slug = ? AND day >= ? AND day <= ?',
                [$slug, $awal, $akhir]
            );
        } else {
            $harian = db_run('DELETE FROM stats_daily WHERE day >= ? AND day <= ?', [$awal, $akhir]);
        }
    }

    mem_clear('stats.');
    return ['events' => $hapus, 'daily' => $harian];
}

function event_delete($slug)
{
    do {
        $n = db_run('DELETE FROM events WHERE slug = ? LIMIT ' . (int) EVENT_DELETE_CHUNK, [$slug]);
    } while ($n > 0);
}

function event_rename($oldSlug, $newSlug)
{
    if ($oldSlug === $newSlug) {
        return;
    }
    db_run('UPDATE events SET slug = ? WHERE slug = ?', [$newSlug, $oldSlug]);
}

/** Jumlah baris log milik satu PWA. */
function event_count($slug)
{
    return (int) db_val('SELECT COUNT(*) FROM events WHERE slug = ?', [$slug], 0);
}
