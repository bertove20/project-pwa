<?php

/**
 * Path dasar aplikasi relatif terhadap document root.
 * Kosong bila dipasang di root vhost (http://dasboardpwa.test/),
 * berisi "/dasboardpwa" bila diakses lewat http://localhost/dasboardpwa/.
 */
function base_path()
{
    static $bp = null;
    if ($bp === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $bp = ($dir === '/' || $dir === '.' || $dir === '') ? '' : rtrim($dir, '/');
    }
    return $bp;
}

function url($path = '')
{
    return base_path() . '/' . ltrim($path, '/');
}

function url_origin()
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** URL absolut dari path aplikasi (base path ditambahkan). */
function abs_url($path = '')
{
    return url_origin() . url($path);
}

/**
 * URL absolut dari path yang SUDAH mengandung base path,
 * misalnya nilai balikan icon_url(). Memakai abs_url() di sini
 * akan menggandakan base path.
 */
function abs_from_root($path)
{
    return url_origin() . '/' . ltrim((string) $path, '/');
}

/** Path request tanpa base path dan tanpa slash di ujung. */
function request_path()
{
    static $p = null;
    if ($p === null) {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = rawurldecode($uri === null ? '/' : $uri);
        $bp = base_path();
        if ($bp !== '' && strpos($uri, $bp) === 0) {
            $uri = substr($uri, strlen($bp));
        }
        $p = trim($uri, '/');
    }
    return $p;
}

/** True bila URL request diakhiri slash (dipakai untuk normalisasi scope PWA). */
function request_has_trailing_slash()
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    return $uri !== null && substr($uri, -1) === '/';
}

function e($str)
{
    return htmlspecialchars((string) $str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect($to, $code = 302)
{
    header('Location: ' . $to, true, $code);
    exit;
}

function json_out($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function is_post()
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_ajax()
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function post($key, $default = '')
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function query($key, $default = '')
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}

function slugify($text)
{
    $text = strtolower(trim((string) $text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string) $text, '-');
}

function is_valid_slug($slug)
{
    return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{0,58}[a-z0-9])?$/', (string) $slug);
}

/** Hanya izinkan http/https supaya tidak bisa diisi javascript: atau data: */
function is_valid_target($url)
{
    $url = trim((string) $url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return $scheme === 'http' || $scheme === 'https';
}

function is_hex_color($c)
{
    return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', (string) $c);
}

function now_iso()
{
    return date('c');
}

function today()
{
    return date('Y-m-d');
}

function gen_id()
{
    return bin2hex(random_bytes(8));
}

function flash_set($type, $msg)
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get()
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function view($name, array $vars = [])
{
    extract($vars, EXTR_SKIP);
    require ROOT_DIR . '/views/' . $name . '.php';
}

function render($name, array $vars = [])
{
    ob_start();
    view($name, $vars);
    return ob_get_clean();
}

function not_found($msg = 'Halaman tidak ditemukan.')
{
    http_response_code(404);
    view('error', ['code' => 404, 'msg' => $msg]);
    exit;
}

function number_short($n)
{
    $n = (int) $n;
    if ($n >= 1000000) {
        return round($n / 1000000, 1) . 'jt';
    }
    if ($n >= 1000) {
        return round($n / 1000, 1) . 'rb';
    }
    return (string) $n;
}

/**
 * Muat sebuah berkas lib sekali saja.
 * Fungsi yang di-require di dalam fungsi tetap terdaftar secara global,
 * jadi pemuatan bertahap ini aman.
 */
function lib($name)
{
    static $dimuat = [];
    if (isset($dimuat[$name])) {
        return;
    }
    $dimuat[$name] = true;
    require ROOT_DIR . '/lib/' . $name . '.php';
}

/**
 * Kirim respons ke pengunjung sekarang juga, lalu biarkan skrip melanjutkan
 * pekerjaan latar seperti pencatatan statistik.
 *
 * Pada redirect /go, pengunjung hanya perlu menunggu satu SELECT; dua operasi
 * tulis yang menyusul tidak lagi menambah waktu tunggu yang ia rasakan.
 */
function response_finish()
{
    ignore_user_abort(true);

    // PHP-FPM punya cara resmi untuk ini
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }

    // mod_php: tutup koneksi dengan memberi tahu panjang isi yang pasti.
    // ob_get_length() bernilai false bila tidak ada buffer aktif, dan
    // menyambungnya langsung menghasilkan header Content-Length kosong.
    if (!headers_sent()) {
        $panjang = ob_get_length();
        header('Content-Length: ' . ($panjang === false ? 0 : $panjang));
        header('Connection: close');
    }
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}

function ensure_dirs()
{
    foreach ([DATA_DIR, ICON_DIR] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}
