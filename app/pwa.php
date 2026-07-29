<?php

/**
 * Endpoint publik tiap PWA.
 *
 *   /p/{slug}/                        landing page promosi install
 *   /p/{slug}/manifest.webmanifest    manifest dinamis
 *   /p/{slug}/config.json             sumber data untuk landing page di domain lain
 *   /p/{slug}/sw.js                   service worker dinamis
 *   /p/{slug}/go                      start_url -> redirect ke target terbaru
 *   /p/{slug}/offline                 halaman fallback offline
 *   /p/{slug}/track                   POST beacon statistik (landing bawaan)
 *   /p/{slug}/track.gif               GET pixel statistik (landing di domain lain)
 */
function pwa_route(array $seg)
{
    $slug = $seg[1] ?? '';
    $action = $seg[2] ?? '';

    if (!is_valid_slug($slug)) {
        not_found('Alamat PWA tidak valid.');
    }

    $pwa = pwa_find($slug);
    if (!$pwa) {
        not_found('PWA "' . e($slug) . '" tidak terdaftar.');
    }

    switch ($action) {
        case '':
            pwa_landing($pwa);
            break;
        case 'manifest.webmanifest':
            pwa_manifest($pwa);
            break;
        case 'config.json':
            pwa_config($pwa);
            break;
        case 'sw.js':
            pwa_service_worker($pwa);
            break;
        case 'go':
            pwa_go($pwa);
            break;
        case 'offline':
            pwa_offline($pwa);
            break;
        case 'track':
            pwa_track($pwa);
            break;
        case 'track.gif':
            pwa_track_pixel($pwa);
            break;
        default:
            not_found();
    }
}

function pwa_landing(array $pwa)
{
    // Scope service worker butuh trailing slash supaya path relatif konsisten
    if (!request_has_trailing_slash()) {
        redirect(url('p/' . $pwa['slug'] . '/'), 301);
    }

    if (empty($pwa['active'])) {
        http_response_code(503);
        view('inactive', ['pwa' => $pwa]);
        exit;
    }

    stat_hit($pwa['slug'], 'view');
    header('Cache-Control: no-cache, must-revalidate');
    view('landing', ['pwa' => $pwa]);
}

function pwa_manifest(array $pwa)
{
    $slug = $pwa['slug'];
    $scope = url('p/' . $slug . '/');

    $manifest = [
        'id' => $scope,
        'name' => $pwa['name'],
        'short_name' => $pwa['short_name'] !== '' ? $pwa['short_name'] : $pwa['name'],
        'description' => $pwa['description'] ?? '',
        'start_url' => $scope . 'go?s=pwa',
        'scope' => $scope,
        'display' => $pwa['display'] ?? 'standalone',
        'orientation' => $pwa['orientation'] ?? 'any',
        'background_color' => $pwa['background_color'] ?? '#ffffff',
        'theme_color' => $pwa['theme_color'] ?? '#0f172a',
        'lang' => 'id',
        'dir' => 'ltr',
        'categories' => ['utilities'],
        'icons' => icon_manifest_entries($pwa),
    ];

    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Sumber data tunggal untuk landing page yang dipasang di domain lain.
 * Selalu dilayani apa adanya dari panel, sehingga perubahan nama, deskripsi,
 * warna, atau ikon di panel langsung terbaca oleh situs eksternal.
 */
function pwa_config(array $pwa)
{
    $config = [
        'slug' => $pwa['slug'],
        'name' => $pwa['name'],
        'short_name' => $pwa['short_name'] !== '' ? $pwa['short_name'] : $pwa['name'],
        'description' => $pwa['description'] ?? '',
        'display' => $pwa['display'] ?? 'standalone',
        'orientation' => $pwa['orientation'] ?? 'any',
        'theme_color' => $pwa['theme_color'] ?? '#0f172a',
        'background_color' => $pwa['background_color'] ?? '#ffffff',
        'active' => (bool) $pwa['active'],
        'icons' => icon_manifest_entries($pwa),
        'icon' => icon_abs_url($pwa, 512),
        'go_url' => abs_url('p/' . $pwa['slug'] . '/go'),
        'pixel_url' => abs_url('p/' . $pwa['slug'] . '/track.gif'),
        'updated_at' => $pwa['updated_at'] ?? '',
    ];

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function pwa_service_worker(array $pwa)
{
    $slug = $pwa['slug'];
    $ver = substr(md5(($pwa['updated_at'] ?? '') . ($pwa['target_url'] ?? '')), 0, 8);
    $offline = json_encode(url('p/' . $slug . '/offline'), JSON_UNESCAPED_SLASHES);
    $cache = json_encode('pwa-' . $slug . '-' . $ver, JSON_UNESCAPED_SLASHES);

    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Service-Worker-Allowed: ' . url('p/' . $slug . '/'));

    echo <<<JS
// Service worker untuk "{$pwa['name']}" - dibuat otomatis oleh panel.
const CACHE_NAME = {$cache};
const OFFLINE_URL = {$offline};

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll([OFFLINE_URL]))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// Navigasi selalu ke jaringan supaya target link terbaru langsung terpakai.
// Bila offline, tampilkan halaman fallback.
self.addEventListener('fetch', (event) => {
  if (event.request.mode !== 'navigate') return;
  event.respondWith(
    fetch(event.request).catch(() => caches.match(OFFLINE_URL))
  );
});
JS;
    exit;
}

function pwa_go(array $pwa)
{
    if (empty($pwa['active'])) {
        http_response_code(503);
        view('inactive', ['pwa' => $pwa]);
        exit;
    }
    if (!is_valid_target($pwa['target_url'] ?? '')) {
        http_response_code(503);
        view('error', ['code' => 503, 'msg' => 'Target link untuk aplikasi ini belum diisi.']);
        exit;
    }

    // s=pwa dikirim oleh start_url manifest, artinya dibuka dari ikon home screen
    stat_hit($pwa['slug'], query('s') === 'pwa' ? 'open' : 'click');

    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Referrer-Policy: no-referrer-when-downgrade');
    redirect($pwa['target_url'], 302);
}

function pwa_offline(array $pwa)
{
    http_response_code(200);
    header('Cache-Control: no-cache');
    view('offline', ['pwa' => $pwa]);
    exit;
}

function pwa_track(array $pwa)
{
    if (!is_post()) {
        json_out(['ok' => false], 405);
    }
    $raw = file_get_contents('php://input');
    $body = json_decode((string) $raw, true);
    $event = $body['event'] ?? post('event');

    // Hanya install yang dilaporkan dari sisi klien; open/click sudah dicatat di /go.
    if ($event === 'install') {
        stat_hit($pwa['slug'], $event);
        json_out(['ok' => true]);
    }
    json_out(['ok' => false, 'error' => 'event tidak dikenal'], 400);
}

/**
 * Pixel 1x1 untuk landing page yang dipasang di domain lain.
 * Memakai GET biasa supaya tidak kena CORS preflight.
 */
function pwa_track_pixel(array $pwa)
{
    $event = query('event');
    if ($event === 'install' || $event === 'view') {
        stat_hit($pwa['slug'], $event);
    }

    header('Content-Type: image/gif');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}
