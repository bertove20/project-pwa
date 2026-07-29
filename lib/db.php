<?php

/**
 * Lapisan database (MySQL/MariaDB lewat PDO).
 *
 * Skema dibuat sendiri saat panel pertama kali dijalankan, dan data lama dari
 * penyimpanan flat-file JSON ikut dipindahkan bila berkasnya masih ada.
 */

function db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $opt = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Prepare sisi server berarti dua perjalanan ke MySQL untuk setiap
        // kueri sekali pakai. Hampir semua kueri di sini sekali pakai, jadi
        // emulasi memangkas separuh perjalanan tanpa mengurangi keamanan:
        // parameter tetap di-quote PDO, bukan disambung manual.
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::ATTR_PERSISTENT => DB_PERSISTENT,
    ];
    $dsnBase = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsnBase . ';dbname=' . DB_NAME, DB_USER, DB_PASS, $opt);
    } catch (PDOException $e) {
        // 1049 = database belum ada
        if (!DB_AUTO_CREATE || (int) $e->getCode() !== 1049) {
            db_fail($e);
        }
        try {
            $root = new PDO($dsnBase, DB_USER, DB_PASS, $opt);
            $root->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', DB_NAME)
                . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo = new PDO($dsnBase . ';dbname=' . DB_NAME, DB_USER, DB_PASS, $opt);
        } catch (PDOException $e2) {
            db_fail($e2);
        }
    }

    db_reset_state($pdo);
    db_ensure_schema($pdo);
    return $pdo;
}

/**
 * Koneksi persisten dipakai ulang apa adanya, termasuk sisa keadaan dari
 * permintaan sebelumnya yang mungkin berhenti di tengah jalan. Dua hal yang
 * bisa menular dan harus dibereskan di awal:
 *
 *   - mode tak-terbuffer yang belum sempat dikembalikan oleh db_stream_end()
 *   - transaksi yang belum di-commit maupun di-rollback
 */
function db_reset_state(PDO $pdo)
{
    if (!DB_PERSISTENT) {
        return;
    }
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    if ($pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (PDOException $e) {
            // Tidak ada transaksi yang benar-benar terbuka; abaikan
        }
    }
}

/**
 * Versi skema. Naikkan bila struktur tabel berubah, agar pemeriksaan
 * dijalankan ulang sekali di setiap instalasi.
 */
define('SCHEMA_VERSION', 2);

/**
 * Pemeriksaan skema hanya sekali seumur instalasi, ditandai berkas penanda.
 *
 * Sebelumnya SHOW TABLES dan SHOW INDEX dijalankan pada SETIAP permintaan,
 * termasuk pada redirect /go yang paling sering diakses. Memeriksa keberadaan
 * satu berkas jauh lebih murah daripada dua perjalanan bolak-balik ke MySQL.
 */
function db_ensure_schema(PDO $pdo)
{
    $penanda = DATA_DIR . '/.skema-v' . SCHEMA_VERSION;
    if (is_file($penanda)) {
        return;
    }

    db_install($pdo);

    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0775, true);
    }
    @file_put_contents($penanda, "Skema versi " . SCHEMA_VERSION . " dipasang " . date('c') . "\n"
        . "Hapus berkas ini bila struktur database perlu diperiksa ulang.\n");
}

function db_fail(PDOException $e)
{
    http_response_code(500);
    if (function_exists('is_ajax') && is_ajax()) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Database tidak dapat dihubungi.']);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Database tidak siap</title>'
        . '<style>body{font-family:system-ui,sans-serif;max-width:640px;margin:12vh auto;padding:0 24px;'
        . 'line-height:1.65;color:#0f172a}code{background:#f1f5f9;padding:2px 6px;border-radius:4px}'
        . 'h1{font-size:1.3rem}li{margin-bottom:6px}</style>'
        . '<h1>Panel tidak bisa terhubung ke database</h1>'
        . '<p>Pesan dari MySQL: <code>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</code></p>'
        . '<p>Periksa hal berikut di <code>config.php</code>:</p><ul>'
        . '<li>Layanan MySQL sudah berjalan</li>'
        . '<li><code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_USER</code>, dan <code>DB_PASS</code> benar</li>'
        . '<li>Database <code>' . htmlspecialchars(DB_NAME, ENT_QUOTES) . '</code> sudah ada, '
        . 'atau <code>DB_AUTO_CREATE</code> bernilai true dan pengguna berhak membuat database</li>'
        . '</ul>';
    exit;
}

/** Buat tabel bila belum ada. Murah dipanggil: hanya sekali per request. */
function db_install(PDO $pdo)
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $exists = $pdo->query("SHOW TABLES LIKE 'pwa'")->fetchColumn();
    if ($exists) {
        db_fix_indexes($pdo);
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            k VARCHAR(64) NOT NULL PRIMARY KEY,
            v TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pwa (
            id CHAR(16) NOT NULL PRIMARY KEY,
            slug VARCHAR(64) NOT NULL,
            name VARCHAR(120) NOT NULL,
            short_name VARCHAR(40) NOT NULL DEFAULT '',
            description VARCHAR(300) NOT NULL DEFAULT '',
            target_url TEXT NOT NULL,
            theme_color CHAR(7) NOT NULL DEFAULT '#0f172a',
            background_color CHAR(7) NOT NULL DEFAULT '#ffffff',
            display VARCHAR(16) NOT NULL DEFAULT 'standalone',
            orientation VARCHAR(16) NOT NULL DEFAULT 'any',
            active TINYINT(1) NOT NULL DEFAULT 1,
            icon_svg TINYINT(1) NOT NULL DEFAULT 0,
            icon_ver INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Tabel utama yang tumbuh besar. Kolom ym mempercepat daftar bulan dan
    // penghapusan manual per rentang bulan.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(64) NOT NULL,
            occurred_at DATETIME NOT NULL,
            ym CHAR(7) NOT NULL,
            event VARCHAR(10) NOT NULL,
            source VARCHAR(10) NOT NULL DEFAULT '',
            device VARCHAR(10) NOT NULL DEFAULT '',
            os VARCHAR(40) NOT NULL DEFAULT '',
            browser VARCHAR(40) NOT NULL DEFAULT '',
            webview TINYINT(1) NOT NULL DEFAULT 0,
            referrer VARCHAR(190) NOT NULL DEFAULT '',
            visitor CHAR(12) NOT NULL DEFAULT '',
            KEY idx_slug_time (slug, occurred_at),
            KEY idx_ym (ym)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    // Catatan: JANGAN tambahkan indeks (slug, ym). Kolom depannya sama dengan
    // idx_slug_time sehingga optimizer sempat memilihnya untuk kueri analitik,
    // lalu memindai seluruh baris milik slug tersebut alih-alih range scan
    // pada rentang tanggal. Penyaringan per bulan memakai occurred_at.

    // Ringkasan harian untuk kartu dashboard; dibaca lintas semua PWA sekaligus
    // sehingga tidak layak dihitung ulang dari tabel events tiap kali.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stats_daily (
            slug VARCHAR(64) NOT NULL,
            day DATE NOT NULL,
            event VARCHAR(10) NOT NULL,
            hits INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (slug, day, event)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db_migrate_from_json($pdo);
}

/**
 * Perbaikan skema untuk instalasi yang sudah berjalan.
 * Dijalankan sekali per request dan sangat murah bila tidak ada yang perlu diubah.
 */
function db_fix_indexes(PDO $pdo)
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    // idx_slug_ym membuat optimizer memilih pemindaian seluruh baris milik satu
    // slug ketimbang range scan tanggal pada idx_slug_time. Dibuang bila ada.
    $ada = $pdo->query("SHOW INDEX FROM events WHERE Key_name = 'idx_slug_ym'")->fetch();
    if ($ada) {
        $pdo->exec('ALTER TABLE events DROP INDEX idx_slug_ym');
    }
}

/**
 * Pindahkan data dari penyimpanan flat-file lama, lalu tandai berkasnya
 * agar tidak diimpor dua kali.
 */
function db_migrate_from_json(PDO $pdo)
{
    $report = ['pwa' => 0, 'stats' => 0, 'events' => 0, 'settings' => 0];

    // --- settings
    $f = DATA_DIR . '/settings.json';
    if (is_file($f)) {
        $s = json_decode((string) file_get_contents($f), true);
        if (is_array($s)) {
            $st = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
            foreach ($s as $k => $v) {
                $st->execute([$k, is_scalar($v) ? (string) $v : json_encode($v)]);
                $report['settings']++;
            }
        }
    }

    // --- pwa
    $f = DATA_DIR . '/pwa.json';
    if (is_file($f)) {
        $d = json_decode((string) file_get_contents($f), true);
        $items = $d['items'] ?? [];
        $st = $pdo->prepare(
            'INSERT IGNORE INTO pwa (id, slug, name, short_name, description, target_url,
             theme_color, background_color, display, orientation, active, icon_svg, icon_ver,
             created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        foreach ($items as $i) {
            $st->execute([
                $i['id'], $i['slug'], $i['name'], $i['short_name'] ?? '', $i['description'] ?? '',
                $i['target_url'], $i['theme_color'] ?? '#0f172a', $i['background_color'] ?? '#ffffff',
                $i['display'] ?? 'standalone', $i['orientation'] ?? 'any',
                !empty($i['active']) ? 1 : 0, !empty($i['icon_svg']) ? 1 : 0, (int) ($i['icon_ver'] ?? 0),
                db_dt($i['created_at'] ?? null), db_dt($i['updated_at'] ?? null),
            ]);
            $report['pwa']++;
        }
    }

    // --- agregat harian
    $f = DATA_DIR . '/stats.json';
    if (is_file($f)) {
        $d = json_decode((string) file_get_contents($f), true);
        $st = $pdo->prepare(
            'INSERT INTO stats_daily (slug, day, event, hits) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits)'
        );
        foreach ((array) $d as $slug => $row) {
            foreach ((array) ($row['daily'] ?? []) as $day => $counts) {
                foreach ((array) $counts as $ev => $n) {
                    if ((int) $n > 0) {
                        $st->execute([$slug, $day, $ev, (int) $n]);
                        $report['stats']++;
                    }
                }
            }
        }
    }

    // --- log peristiwa
    foreach ((array) glob(DATA_DIR . '/events/*', GLOB_ONLYDIR) as $dir) {
        $slug = basename($dir);
        foreach ((array) glob($dir . '/*.jsonl') as $file) {
            $report['events'] += db_import_jsonl($pdo, $slug, $file);
        }
    }

    // Tandai berkas lama sebagai sudah dipindahkan (tidak dihapus, biar bisa dicek)
    foreach (['settings', 'pwa', 'stats'] as $name) {
        $f = DATA_DIR . '/' . $name . '.json';
        if (is_file($f)) {
            @rename($f, $f . '.migrated');
        }
    }
    if (is_dir(DATA_DIR . '/events')) {
        @rename(DATA_DIR . '/events', DATA_DIR . '/events.migrated');
    }

    if (array_sum($report) > 0) {
        @file_put_contents(
            DATA_DIR . '/migrasi-' . date('Ymd-His') . '.log',
            "Pemindahan flat-file ke database\n" . json_encode($report, JSON_PRETTY_PRINT) . "\n"
        );
    }

    return $report;
}

function db_import_jsonl(PDO $pdo, $slug, $file)
{
    $fh = @fopen($file, 'rb');
    if (!$fh) {
        return 0;
    }
    $sql = 'INSERT INTO events (slug, occurred_at, ym, event, source, device, os, browser, webview, referrer, visitor)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)';
    $st = $pdo->prepare($sql);

    $n = 0;
    $pdo->beginTransaction();
    while (($line = fgets($fh)) !== false) {
        $row = json_decode(trim($line), true);
        if (!is_array($row) || empty($row['t'])) {
            continue;
        }
        $st->execute([
            $slug, $row['t'], substr($row['t'], 0, 7), $row['e'] ?? '', $row['s'] ?? '',
            $row['d'] ?? '', $row['o'] ?? '', $row['b'] ?? '', !empty($row['w']) ? 1 : 0,
            mb_substr((string) ($row['r'] ?? ''), 0, 190), $row['v'] ?? '',
        ]);
        $n++;
        if ($n % 2000 === 0) {
            $pdo->commit();
            $pdo->beginTransaction();
        }
    }
    $pdo->commit();
    fclose($fh);
    return $n;
}

function db_dt($iso)
{
    $ts = $iso ? strtotime($iso) : false;
    return date('Y-m-d H:i:s', $ts ?: time());
}

/* ------------------------------------------------------------- Pembantu */

/**
 * Jalankan kueri; bila tabel ternyata hilang (mis. database dibuat ulang
 * sementara berkas penanda skema masih ada), skema dipasang ulang sekali
 * lalu kueri diulang.
 */
function db_query($sql, array $params = [])
{
    try {
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st;
    } catch (PDOException $e) {
        static $sudahDiperbaiki = false;
        if ($sudahDiperbaiki || (int) $e->errorInfo[1] !== 1146) {
            throw $e;
        }
        $sudahDiperbaiki = true;
        @unlink(DATA_DIR . '/.skema-v' . SCHEMA_VERSION);
        db_install(db());
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st;
    }
}

function db_all($sql, array $params = [])
{
    return db_query($sql, $params)->fetchAll();
}

function db_row($sql, array $params = [])
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_val($sql, array $params = [], $default = null)
{
    $v = db_query($sql, $params)->fetchColumn();
    return $v === false ? $default : $v;
}

function db_run($sql, array $params = [])
{
    return db_query($sql, $params)->rowCount();
}

/**
 * Kueri yang hasilnya dibaca baris demi baris tanpa ditampung seluruhnya.
 *
 * PDO membuffer hasil secara bawaan: satu laporan analitik pada rentang lebar
 * akan menarik ratusan ribu baris ke memori PHP sekaligus. Mode tak-terbuffer
 * membuat pemakaian memori tetap datar berapa pun besar rentangnya.
 *
 * Selama hasilnya belum habis dibaca, koneksi tidak boleh dipakai kueri lain.
 */
function db_stream($sql, array $params = [])
{
    db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function db_stream_end(PDOStatement $st)
{
    $st->closeCursor();
    db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
}

/** Pasangan kolom => jumlah, dari kueri dua kolom. */
function db_pairs($sql, array $params = [])
{
    $out = [];
    foreach (db_all($sql, $params) as $r) {
        $vals = array_values($r);
        $out[(string) $vals[0]] = (int) $vals[1];
    }
    return $out;
}

/**
 * Segarkan statistik InnoDB agar ukuran tabel tidak dilaporkan basi.
 * Hasil ANALYZE berupa baris dan wajib dikonsumsi, kalau tidak kueri
 * berikutnya gagal dengan "unbuffered queries are active".
 */
function db_refresh_stats($table)
{
    try {
        db()->query('ANALYZE TABLE `' . str_replace('`', '', $table) . '`')->fetchAll();
    } catch (PDOException $e) {
        // Bukan kesalahan fatal; ukuran sekadar tampil kurang akurat
    }
}

/** Ukuran tabel di disk menurut information_schema. */
function db_table_size($table)
{
    // Kolom information_schema dikembalikan dengan huruf besar oleh MySQL 8,
    // jadi keduanya wajib diberi alias agar terbaca konsisten.
    $r = db_row(
        'SELECT data_length + index_length AS bytes, table_rows AS rows_est
         FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
        [DB_NAME, $table]
    );
    return ['bytes' => (int) ($r['bytes'] ?? 0), 'rows' => (int) ($r['rows_est'] ?? 0)];
}

function db_format_size($bytes)
{
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' KB';
    }
    return $bytes . ' B';
}
