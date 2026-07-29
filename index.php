<?php
/**
 * PWA Manager - front controller.
 *
 * Semua request non-file diarahkan ke sini oleh .htaccess, lalu dipetakan:
 *   /                 -> panel admin
 *   /admin/...        -> panel admin
 *   /p/{slug}/...     -> endpoint publik PWA
 */

require __DIR__ . '/config.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/store.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/icons.php';
require __DIR__ . '/lib/stats.php';
require __DIR__ . '/lib/useragent.php';
require __DIR__ . '/lib/events.php';
require __DIR__ . '/lib/embed.php';
require __DIR__ . '/app/pwa.php';
require __DIR__ . '/app/admin.php';

date_default_timezone_set(TIMEZONE);
ensure_dirs();
auth_start();

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
        pwa_route($seg);
        break;
    case 'admin':
        admin_route($seg);
        break;
    default:
        not_found();
}
