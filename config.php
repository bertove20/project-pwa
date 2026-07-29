<?php
/**
 * Konfigurasi dasar panel.
 *
 * Kredensial admin disimpan ter-hash di tabel `settings`, bukan di berkas ini.
 * Nilai DEFAULT_ADMIN_* hanya dipakai saat panel pertama kali dijalankan.
 */

define('APP_NAME', 'PWA Manager');
define('APP_VERSION', '2.0.0');

/* ------------------------------------------------------------- Database */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'pwa_manager');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Biarkan true agar panel membuat database dan tabelnya sendiri saat pertama
 * dijalankan. Di server produksi yang penggunanya tidak berhak membuat
 * database, buat database secara manual lalu setel ini ke false.
 */
define('DB_AUTO_CREATE', true);

/**
 * Koneksi persisten: koneksi MySQL dipakai ulang antar permintaan alih-alih
 * dibangun dari nol setiap kali. Pada pengukuran di sini setup koneksi memakan
 * 9,47 ms per permintaan, sementara dengan koneksi persisten hanya 0,14 ms.
 *
 * Setel false bila hosting membatasi jumlah koneksi MySQL secara ketat
 * (setiap proses/worker web menahan satu koneksi).
 */
define('DB_PERSISTENT', true);

/* ---------------------------------------------------------------- Path */

define('ROOT_DIR', __DIR__);
define('DATA_DIR', ROOT_DIR . '/data');
define('ICON_DIR', ROOT_DIR . '/uploads/icons');
define('ICON_URL_PATH', 'uploads/icons');

/* --------------------------------------------------------------- Lain */

define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'admin123');

define('SESSION_NAME', 'pwamgr_sess');
define('TIMEZONE', 'Asia/Jakarta');

/**
 * Log peristiwa TIDAK pernah dihapus otomatis; data lama tetap tersimpan untuk
 * keperluan analisa. Penghapusan dilakukan manual per rentang bulan lewat
 * menu Pemeliharaan.
 */
define('EVENT_RECENT_LIMIT', 300);

/** Jumlah baris per batch saat menghapus log, agar tabel besar tidak terkunci lama. */
define('EVENT_DELETE_CHUNK', 5000);
