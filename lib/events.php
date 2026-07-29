<?php

/**
 * Log peristiwa terperinci, satu baris JSON per kejadian.
 *
 * Agregat harian di stats.json tetap dipakai untuk kartu di dashboard karena
 * murah dibaca. Berkas ini menyimpan lapisan detailnya: tanggal + jam persis,
 * perangkat, sistem operasi, browser, sumber trafik, dan perujuk.
 *
 * Disimpan per bulan agar berkas tidak membengkak dan gampang dipangkas:
 *   data/events/{slug}/2026-07.jsonl
 *
 * Kunci sengaja dipendekkan (t, e, s, d, o, b, w, r, v) karena berulang di
 * setiap baris; pada trafik besar selisih ukurannya nyata.
 */

define('EVENT_DIR', DATA_DIR . '/events');
define('EVENT_RETENTION_MONTHS', 12);
define('EVENT_RECENT_LIMIT', 300);

function event_dir($slug)
{
    return EVENT_DIR . '/' . $slug;
}

function event_file($slug, $ym)
{
    return event_dir($slug) . '/' . $ym . '.jsonl';
}

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
    $s = settings_all();
    if (empty($s['visitor_salt'])) {
        $s = settings_save(['visitor_salt' => bin2hex(random_bytes(16))]);
    }
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]
        ?? '';
    if (trim((string) $ip) === '') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return $hash = substr(hash('sha256', $s['visitor_salt'] . '|' . today() . '|' . trim($ip) . '|' . $ua), 0, 12);
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
        $ref = is_string($host) ? $host : '';
    }

    $row = [
        't' => date('Y-m-d H:i:s'),
        'e' => $event,
        's' => $src,
        'd' => $p['device'],
        'o' => $p['os'],
        'b' => $p['browser'],
        'w' => $p['webview'] ? 1 : 0,
        'r' => $ref,
        'v' => event_visitor_hash(),
    ];

    $dir = event_dir($slug);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $line = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    @file_put_contents(event_file($slug, date('Y-m')), $line, FILE_APPEND | LOCK_EX);
}

/** Daftar bulan (YYYY-MM) yang tersentuh rentang tanggal. */
function event_months($from, $to)
{
    $months = [];
    $cur = strtotime(substr($from, 0, 7) . '-01');
    $end = strtotime(substr($to, 0, 7) . '-01');
    while ($cur <= $end) {
        $months[] = date('Y-m', $cur);
        $cur = strtotime('+1 month', $cur);
    }
    return $months;
}

/**
 * Baca log baris demi baris tanpa memuat seluruh berkas ke memori.
 * $fn dipanggil untuk tiap baris yang lolos rentang tanggal.
 */
function event_scan($slug, $from, $to, callable $fn)
{
    foreach (event_months($from, $to) as $ym) {
        $file = event_file($slug, $ym);
        if (!is_file($file)) {
            continue;
        }
        $fh = @fopen($file, 'rb');
        if (!$fh) {
            continue;
        }
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (!is_array($row) || empty($row['t'])) {
                continue;
            }
            $day = substr($row['t'], 0, 10);
            if ($day < $from || $day > $to) {
                continue;
            }
            $fn($row);
        }
        fclose($fh);
    }
}

function event_row_passes(array $row, array $f)
{
    if (!empty($f['hide_bots']) && ($row['d'] ?? '') === 'bot') {
        return false;
    }
    if (!empty($f['event']) && ($row['e'] ?? '') !== $f['event']) {
        return false;
    }
    if (!empty($f['device']) && ($row['d'] ?? '') !== $f['device']) {
        return false;
    }
    if (!empty($f['source']) && ($row['s'] ?? '') !== $f['source']) {
        return false;
    }
    return true;
}

/**
 * Satu kali baca menghasilkan seluruh angka yang dibutuhkan halaman analitik.
 *
 * @return array
 */
function event_report($slug, $from, $to, array $filters = [])
{
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

    $visitors = [];

    event_scan($slug, $from, $to, function (array $row) use (&$rep, &$visitors, $filters) {
        if (($row['d'] ?? '') === 'bot') {
            $rep['bots']++;
        }
        if (!event_row_passes($row, $filters)) {
            return;
        }

        $rep['count']++;
        $ev = $row['e'] ?? '';
        if (isset($rep['events'][$ev])) {
            $rep['events'][$ev]++;
        }

        $day = substr($row['t'], 0, 10);
        if (isset($rep['daily'][$day]) && isset($rep['daily'][$day][$ev])) {
            $rep['daily'][$day][$ev]++;
        }

        $ts = strtotime($row['t']);
        if ($ts) {
            $rep['hourly'][(int) date('G', $ts)]++;
            $rep['weekday'][(int) date('w', $ts)]++;
        }

        foreach ([['device', 'd'], ['os', 'o'], ['browser', 'b'], ['source', 's']] as $pair) {
            $key = $row[$pair[1]] ?? '';
            if ($key === '') {
                $key = '(tidak diketahui)';
            }
            $rep[$pair[0]][$key] = ($rep[$pair[0]][$key] ?? 0) + 1;
        }

        $ref = $row['r'] ?? '';
        if ($ref !== '') {
            $rep['referrer'][$ref] = ($rep['referrer'][$ref] ?? 0) + 1;
        }

        if (!empty($row['w'])) {
            $rep['webview']++;
        }
        if (!empty($row['v'])) {
            $visitors[$row['v']] = true;
        }

        $rep['recent'][] = $row;
        if (count($rep['recent']) > EVENT_RECENT_LIMIT * 2) {
            $rep['recent'] = array_slice($rep['recent'], -EVENT_RECENT_LIMIT);
        }
    });

    $rep['unique'] = count($visitors);
    $rep['recent'] = array_slice(array_reverse($rep['recent']), 0, EVENT_RECENT_LIMIT);

    foreach (['device', 'os', 'browser', 'source', 'referrer'] as $k) {
        arsort($rep[$k]);
    }

    return $rep;
}

/** Kirim log terfilter sebagai CSV, dialirkan langsung tanpa ditampung. */
function event_export_csv($slug, $from, $to, array $filters = [])
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="analitik-' . $slug . '-' . $from . '_' . $to . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8 dengan benar
    fputcsv($out, ['Tanggal', 'Jam', 'Peristiwa', 'Sumber', 'Perangkat', 'Sistem Operasi', 'Browser', 'Dalam Aplikasi', 'Perujuk', 'Pengunjung']);

    event_scan($slug, $from, $to, function (array $row) use ($out, $filters) {
        if (!event_row_passes($row, $filters)) {
            return;
        }
        fputcsv($out, [
            substr($row['t'], 0, 10),
            substr($row['t'], 11, 8),
            $row['e'] ?? '',
            $row['s'] ?? '',
            $row['d'] ?? '',
            $row['o'] ?? '',
            $row['b'] ?? '',
            !empty($row['w']) ? 'ya' : 'tidak',
            $row['r'] ?? '',
            $row['v'] ?? '',
        ]);
    });

    fclose($out);
    exit;
}

function event_delete($slug)
{
    $dir = event_dir($slug);
    if (!is_dir($dir)) {
        return;
    }
    foreach ((array) glob($dir . '/*.jsonl') as $f) {
        @unlink($f);
    }
    @rmdir($dir);
}

function event_rename($oldSlug, $newSlug)
{
    if ($oldSlug === $newSlug || !is_dir(event_dir($oldSlug))) {
        return;
    }
    @rename(event_dir($oldSlug), event_dir($newSlug));
}

/** Buang berkas bulanan yang sudah lewat masa simpan. */
function event_prune($slug)
{
    $cutoff = date('Y-m', strtotime('-' . EVENT_RETENTION_MONTHS . ' months'));
    foreach ((array) glob(event_dir($slug) . '/*.jsonl') as $f) {
        if (basename($f, '.jsonl') < $cutoff) {
            @unlink($f);
        }
    }
}

/** Ukuran log di disk, ditampilkan di halaman analitik. */
function event_storage_size($slug)
{
    $total = 0;
    foreach ((array) glob(event_dir($slug) . '/*.jsonl') as $f) {
        $total += (int) @filesize($f);
    }
    return $total;
}

function event_format_size($bytes)
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' KB';
    }
    return $bytes . ' B';
}
