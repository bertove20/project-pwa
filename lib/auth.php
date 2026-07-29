<?php

function auth_start()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => base_path() === '' ? '/' : base_path() . '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function auth_check()
{
    return !empty($_SESSION['uid']);
}

function auth_user()
{
    return $_SESSION['uid'] ?? null;
}

function auth_attempt($user, $pass)
{
    $s = settings_all();
    $okUser = hash_equals((string) $s['admin_user'], (string) $user);
    $okPass = password_verify((string) $pass, (string) $s['admin_pass']);
    if ($okUser && $okPass) {
        session_regenerate_id(true);
        $_SESSION['uid'] = $s['admin_user'];
        $_SESSION['login_at'] = time();
        return true;
    }
    return false;
}

function auth_logout()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function auth_require()
{
    if (!auth_check()) {
        if (is_ajax()) {
            json_out(['ok' => false, 'error' => 'Sesi habis, silakan login ulang.'], 401);
        }
        redirect(url('admin/login'));
    }
}

/* ------------------------------------------------------------------- CSRF */

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify()
{
    $sent = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string) $sent)) {
        // Pakai 400, bukan 419: Apache membalas 500 untuk kode status non-standar.
        if (is_ajax()) {
            json_out(['ok' => false, 'error' => 'Token tidak valid. Muat ulang halaman.'], 400);
        }
        http_response_code(400);
        view('error', ['code' => 400, 'msg' => 'Token kedaluwarsa. Silakan muat ulang halaman dan coba lagi.']);
        exit;
    }
}
