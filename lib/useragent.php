<?php

/**
 * Pengenalan perangkat dari User-Agent.
 *
 * Sengaja ringkas dan tanpa pustaka luar: yang dibutuhkan panel ini hanya
 * jenis perangkat, sistem operasi, browser, dan apakah halaman dibuka di
 * dalam webview aplikasi lain. Yang terakhir penting karena webview
 * (Facebook, Instagram, TikTok) tidak pernah menawarkan tombol install PWA.
 */

function ua_parse($ua)
{
    $ua = trim((string) $ua);

    if ($ua === '') {
        return [
            'device' => 'lainnya',
            'os' => 'Tidak diketahui',
            'browser' => 'Tidak diketahui',
            'webview' => false,
            'bot' => false,
        ];
    }

    $bot = (bool) preg_match(
        '/bot\b|crawl|spider|slurp|mediapartners|facebookexternalhit|headless|preview|monitor|'
        . 'curl\/|wget|python-requests|go-http|okhttp|postman|lighthouse|pingdom|uptime/i',
        $ua
    );

    return [
        'device' => $bot ? 'bot' : ua_device($ua),
        'os' => ua_os($ua),
        'browser' => ua_browser($ua),
        'webview' => ua_is_webview($ua),
        'bot' => $bot,
    ];
}

function ua_device($ua)
{
    // Android tanpa kata "Mobile" berarti tablet
    if (preg_match('/ipad|tablet|playbook|silk|kindle/i', $ua)
        || (stripos($ua, 'android') !== false && stripos($ua, 'mobile') === false)) {
        return 'tablet';
    }
    if (preg_match('/mobi|iphone|ipod|android|blackberry|iemobile|opera mini|windows phone/i', $ua)) {
        return 'mobile';
    }
    return 'desktop';
}

function ua_os($ua)
{
    if (preg_match('/windows nt ([0-9.]+)/i', $ua, $m)) {
        $map = [
            '10.0' => 'Windows 10/11',
            '6.3' => 'Windows 8.1',
            '6.2' => 'Windows 8',
            '6.1' => 'Windows 7',
        ];
        return $map[$m[1]] ?? 'Windows';
    }
    if (preg_match('/windows phone/i', $ua)) {
        return 'Windows Phone';
    }
    if (preg_match('/android[\s\/]?([0-9]+)/i', $ua, $m)) {
        return 'Android ' . $m[1];
    }
    if (stripos($ua, 'android') !== false) {
        return 'Android';
    }
    if (preg_match('/ipad;.*?os ([0-9]+)/i', $ua, $m)) {
        return 'iPadOS ' . $m[1];
    }
    if (preg_match('/(?:iphone|ipod).*?os ([0-9]+)/i', $ua, $m)) {
        return 'iOS ' . $m[1];
    }
    if (preg_match('/ipad|iphone|ipod/i', $ua)) {
        return 'iOS';
    }
    if (stripos($ua, 'cros') !== false) {
        return 'ChromeOS';
    }
    if (preg_match('/mac os x/i', $ua)) {
        return 'macOS';
    }
    if (stripos($ua, 'linux') !== false) {
        return 'Linux';
    }
    return 'Lainnya';
}

function ua_browser($ua)
{
    // Webview aplikasi diperiksa lebih dulu: UA-nya juga mengandung "Chrome"
    if (preg_match('/FBAN|FBAV|FB_IAB|FBIOS/i', $ua)) {
        return 'Facebook App';
    }
    if (stripos($ua, 'Instagram') !== false) {
        return 'Instagram App';
    }
    if (preg_match('/\bLine\//i', $ua)) {
        return 'LINE App';
    }
    if (preg_match('/TikTok|BytedanceWebview|musical_ly/i', $ua)) {
        return 'TikTok App';
    }
    if (preg_match('/\bWhatsApp/i', $ua)) {
        return 'WhatsApp';
    }
    if (preg_match('/Telegram/i', $ua)) {
        return 'Telegram';
    }
    if (preg_match('/SamsungBrowser/i', $ua)) {
        return 'Samsung Internet';
    }
    if (preg_match('/UCBrowser|UCWEB/i', $ua)) {
        return 'UC Browser';
    }
    if (preg_match('/OPR\/|Opera/i', $ua)) {
        return 'Opera';
    }
    if (preg_match('/Edg(?:e|A|iOS)?\//i', $ua)) {
        return 'Edge';
    }
    if (stripos($ua, 'CriOS') !== false) {
        return 'Chrome (iOS)';
    }
    if (stripos($ua, 'FxiOS') !== false) {
        return 'Firefox (iOS)';
    }
    if (preg_match('/Firefox\//i', $ua)) {
        return 'Firefox';
    }
    if (preg_match('/Chrome\//i', $ua)) {
        return 'Chrome';
    }
    if (preg_match('/Safari\//i', $ua)) {
        return 'Safari';
    }
    return 'Lainnya';
}

/** Halaman dibuka di dalam aplikasi lain, bukan browser penuh. */
function ua_is_webview($ua)
{
    return (bool) preg_match(
        '/FBAN|FBAV|FB_IAB|FBIOS|Instagram|BytedanceWebview|TikTok|musical_ly|\bLine\/|'
        . '\bWhatsApp|Telegram|; wv\)|WebView/i',
        $ua
    );
}
