<?php
/**
 * Konfigurasi dasar panel.
 * Kredensial admin sebenarnya disimpan (ter-hash) di data/settings.json,
 * nilai di bawah hanya dipakai saat pertama kali panel dijalankan.
 */

define('APP_NAME', 'PWA Manager');
define('APP_VERSION', '1.0.0');

define('ROOT_DIR', __DIR__);
define('DATA_DIR', ROOT_DIR . '/data');
define('ICON_DIR', ROOT_DIR . '/uploads/icons');
define('ICON_URL_PATH', 'uploads/icons');

define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'admin123');

define('SESSION_NAME', 'pwamgr_sess');
define('TIMEZONE', 'Asia/Jakarta');

// Berapa hari data statistik harian disimpan
define('STATS_RETENTION_DAYS', 120);
