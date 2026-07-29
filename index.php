<?php
/**
 * PWA Manager - front controller.
 *
 * Semua request non-file diarahkan ke sini oleh .htaccess, lalu dipetakan:
 *   /                 -> panel admin
 *   /admin/...        -> panel admin
 *   /p/{slug}/...     -> endpoint publik PWA
 *
 * Berkas dimuat sesuai kebutuhan rute, bukan sekaligus. Panel admin memerlukan
 * app/admin.php dan lib/embed.php yang bersama-sama hampir separuh dari seluruh
 * kode; jalur publik seperti redirect /go tidak pernah menyentuh keduanya dan
 * tidak perlu ikut menanggung waktu parsing-nya.
 */

require __DIR__ . '/config.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/store.php';

date_default_timezone_set(TIMEZONE);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

$path = request_path();
$seg = $path === '' ? [] : explode('/', $path);
$root = $seg[0] ?? '';

switch ($root) {
    case '':
        redirect(url('admin'));
        break;

    case 'p':
        // Endpoint publik: tanpa sesi, tanpa kode panel admin.
        lib('stats');
        lib('useragent');
        lib('events');
        require __DIR__ . '/app/pwa.php';
        pwa_route($seg);
        break;

    case 'admin':
        ensure_dirs();
        lib('auth');
        lib('icons');
        lib('stats');
        lib('useragent');
        lib('events');
        lib('embed');
        require __DIR__ . '/app/admin.php';
        auth_start();
        admin_route($seg);
        break;

    default:
        not_found();
}
