<?php

function admin_route(array $seg)
{
    $action = $seg[1] ?? '';
    $param = $seg[2] ?? '';

    // Rute publik
    if ($action === 'login') {
        return admin_login();
    }

    auth_require();

    switch ($action) {
        case '':
            return admin_dashboard();
        case 'logout':
            return admin_logout();
        case 'new':
            return admin_form(null);
        case 'edit':
            return admin_form($param);
        case 'save':
            return admin_save();
        case 'delete':
            return admin_delete();
        case 'toggle':
            return admin_toggle();
        case 'quick-target':
            return admin_quick_target();
        case 'embed':
            return admin_embed($param);
        case 'stats':
            return admin_stats($param);
        case 'stats-reset':
            return admin_stats_reset();
        case 'settings':
            return admin_settings();
        case 'maintenance':
            return admin_maintenance();
    }
    not_found();
}

/* ------------------------------------------------------------------ Login */

function admin_login()
{
    if (auth_check()) {
        redirect(url('admin'));
    }
    $error = '';
    if (is_post()) {
        csrf_verify();
        if (auth_attempt(post('username'), post('password'))) {
            redirect(url('admin'));
        }
        $error = 'Username atau password salah.';
        usleep(400000); // rem sederhana untuk percobaan beruntun
    }
    view('login', ['error' => $error]);
}

function admin_logout()
{
    auth_logout();
    redirect(url('admin/login'));
}

/* -------------------------------------------------------------- Dashboard */

function admin_dashboard()
{
    $items = pwa_all();
    $q = query('q');
    if ($q !== '') {
        $needle = mb_strtolower($q);
        $items = array_values(array_filter($items, function ($i) use ($needle) {
            return mb_strpos(mb_strtolower($i['name'] . ' ' . $i['slug'] . ' ' . $i['target_url']), $needle) !== false;
        }));
    }

    view('dashboard', [
        'items' => $items,
        'overview' => stats_overview(pwa_all()),
        'q' => $q,
        'total' => count(pwa_all()),
    ]);
}

/* ------------------------------------------------------------- Form & CRUD */

function admin_form($id)
{
    $pwa = null;
    if (!empty($id)) {
        $pwa = pwa_find_by_id($id);
        if (!$pwa) {
            flash_set('error', 'Data PWA tidak ditemukan.');
            redirect(url('admin'));
        }
    }
    view('form', ['pwa' => $pwa]);
}

function admin_save()
{
    if (!is_post()) {
        redirect(url('admin'));
    }
    csrf_verify();

    $id = post('id');
    $existing = $id !== '' ? pwa_find_by_id($id) : null;

    $name = post('name');
    $slug = slugify(post('slug') !== '' ? post('slug') : $name);
    $target = post('target_url');
    $errors = [];

    if ($name === '') {
        $errors[] = 'Nama aplikasi wajib diisi.';
    }
    if (!is_valid_slug($slug)) {
        $errors[] = 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (2-60 karakter).';
    } elseif (pwa_slug_taken($slug, $id)) {
        $errors[] = 'Slug "' . $slug . '" sudah dipakai PWA lain.';
    }
    if (!is_valid_target($target)) {
        $errors[] = 'Target link harus URL lengkap yang diawali http:// atau https://.';
    }

    // Opsional: kosong berarti tombol memakai target yang sama
    $webTarget = post('web_target_url');
    if ($webTarget !== '' && !is_valid_target($webTarget)) {
        $errors[] = 'URL tombol "Buka Aplikasi" harus diawali http:// atau https://.';
    }

    $theme = is_hex_color(post('theme_color')) ? post('theme_color') : '#0f172a';
    $bg = is_hex_color(post('background_color')) ? post('background_color') : '#ffffff';
    $display = in_array(post('display'), ['standalone', 'fullscreen', 'minimal-ui'], true)
        ? post('display') : 'standalone';
    $orientation = in_array(post('orientation'), ['any', 'portrait', 'landscape'], true)
        ? post('orientation') : 'any';

    if ($errors) {
        flash_set('error', implode(' ', $errors));
        $_SESSION['form_old'] = $_POST;
        redirect($existing ? url('admin/edit/' . $id) : url('admin/new'));
    }

    $oldSlug = $existing['slug'] ?? null;
    if ($oldSlug !== null && $oldSlug !== $slug) {
        icon_rename($oldSlug, $slug);
        stats_rename($oldSlug, $slug);
    }

    $item = [
        'id' => $existing['id'] ?? gen_id(),
        'slug' => $slug,
        'name' => $name,
        'short_name' => post('short_name') !== '' ? mb_substr(post('short_name'), 0, 12) : mb_substr($name, 0, 12),
        'description' => mb_substr(post('description'), 0, 300),
        'target_url' => $target,
        'web_target_url' => $webTarget,
        'install_label' => mb_substr(trim(post('install_label')), 0, 40),
        'open_label' => mb_substr(trim(post('open_label')), 0, 40),
        'theme_color' => $theme,
        'background_color' => $bg,
        'display' => $display,
        'orientation' => $orientation,
        'active' => post('active') === '1',
        'protect' => post('protect') === '1',
        'icon_svg' => $existing['icon_svg'] ?? false,
        'icon_ver' => $existing['icon_ver'] ?? 0,
        'created_at' => $existing['created_at'] ?? now_iso(),
        'updated_at' => now_iso(),
    ];

    // Hapus ikon bila diminta
    if (post('remove_icon') === '1') {
        icon_delete($slug);
        $item['icon_svg'] = false;
        $item['icon_ver'] = (int) $item['icon_ver'] + 1;
    }

    // Upload ikon baru
    if (!empty($_FILES['icon']['name'])) {
        $res = icon_process_upload($_FILES['icon'], $slug, $bg);
        if (!$res['ok'] && $res['error'] !== 'no_file') {
            flash_set('error', 'Ikon: ' . $res['error']);
            $_SESSION['form_old'] = $_POST;
            redirect($existing ? url('admin/edit/' . $item['id']) : url('admin/new'));
        }
        if ($res['ok']) {
            $item['icon_svg'] = !empty($res['is_svg']);
            $item['icon_ver'] = (int) $item['icon_ver'] + 1;
        }
    }

    pwa_save($item);
    flash_set('success', $existing ? 'Perubahan pada "' . $item['name'] . '" tersimpan.' : 'PWA "' . $item['name'] . '" berhasil dibuat.');
    redirect(url('admin'));
}

function admin_delete()
{
    if (!is_post()) {
        redirect(url('admin'));
    }
    csrf_verify();

    $removed = pwa_delete(post('id'));
    if ($removed) {
        icon_delete($removed['slug']);
        stats_reset($removed['slug']);
        flash_set('success', 'PWA "' . $removed['name'] . '" telah dihapus.');
    } else {
        flash_set('error', 'Data tidak ditemukan.');
    }
    redirect(url('admin'));
}

/* --------------------------------------------------------- Aksi cepat AJAX */

function admin_toggle()
{
    csrf_verify();
    $pwa = pwa_find_by_id(post('id'));
    if (!$pwa) {
        json_out(['ok' => false, 'error' => 'Data tidak ditemukan.'], 404);
    }
    $pwa['active'] = !$pwa['active'];
    $pwa['updated_at'] = now_iso();
    pwa_save($pwa);
    json_out(['ok' => true, 'active' => $pwa['active']]);
}

/** Inti panel: ganti target link tanpa membuka form penuh. */
function admin_quick_target()
{
    csrf_verify();
    $pwa = pwa_find_by_id(post('id'));
    if (!$pwa) {
        json_out(['ok' => false, 'error' => 'Data tidak ditemukan.'], 404);
    }
    $target = post('target_url');
    if (!is_valid_target($target)) {
        json_out(['ok' => false, 'error' => 'URL harus diawali http:// atau https://'], 422);
    }
    $pwa['target_url'] = $target;
    $pwa['updated_at'] = now_iso();
    pwa_save($pwa);
    json_out(['ok' => true, 'target_url' => $target, 'updated_at' => date('d M Y H:i')]);
}

/* ------------------------------- Kode landing page untuk domain lain */

function admin_embed($slug)
{
    $pwa = pwa_find($slug);
    if (!$pwa) {
        flash_set('error', 'PWA tidak ditemukan.');
        redirect(url('admin'));
    }

    $mode = query('mode') === 'static' ? 'static' : 'php';

    if (query('download') === 'zip') {
        $tmp = embed_zip($pwa, $mode);
        if (!$tmp) {
            flash_set('error', 'Ekstensi ZIP tidak aktif di PHP ini. Salin berkasnya satu per satu.');
            redirect(url('admin/embed/' . $slug . '?mode=' . $mode));
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="pwa-' . $slug . '-' . $mode . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    view('embed', [
        'pwa' => $pwa,
        'mode' => $mode,
        'files' => embed_files($pwa, $mode),
    ]);
}

/* ------------------------------------------------------------- Statistik */

function admin_stats($slug)
{
    $pwa = pwa_find($slug);
    if (!$pwa) {
        flash_set('error', 'PWA tidak ditemukan.');
        redirect(url('admin'));
    }

    // Rentang tanggal: preset lewat ?days, atau tanggal bebas lewat ?from & ?to
    $days = (int) query('days', 30);
    $days = in_array($days, [1, 7, 30, 90], true) ? $days : 30;

    $from = query('from');
    $to = query('to');
    $isCustom = admin_valid_date($from) && admin_valid_date($to);

    if (!$isCustom) {
        $to = today();
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    }
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }

    $filters = [
        'event' => in_array(query('event'), STAT_EVENTS, true) ? query('event') : '',
        'device' => in_array(query('device'), ['mobile', 'tablet', 'desktop', 'bot'], true) ? query('device') : '',
        'source' => in_array(query('source'), ['pwa', 'web', 'panel', 'ext'], true) ? query('source') : '',
        'hide_bots' => query('bots') !== '1',
    ];

    if (query('export') === 'csv') {
        event_export_csv($slug, $from, $to, $filters);
    }

    view('stats', [
        'pwa' => $pwa,
        'report' => event_report($slug, $from, $to, $filters),
        'totals' => stats_for($slug)['totals'],
        'from' => $from,
        'to' => $to,
        'days' => $days,
        'isCustom' => $isCustom,
        'filters' => $filters,
        'logRows' => event_count($slug),
    ]);
}

/* ------------------------------------------------- Pemeliharaan data */

function admin_maintenance()
{
    $slug = query('slug');
    if ($slug !== '' && !pwa_find($slug)) {
        $slug = '';
    }

    if (is_post()) {
        csrf_verify();
        $slug = post('slug');
        if ($slug !== '' && !pwa_find($slug)) {
            $slug = '';
        }

        $dari = post('from_ym');
        $sampai = post('to_ym');
        if (!admin_valid_ym($dari) || !admin_valid_ym($sampai)) {
            flash_set('error', 'Rentang bulan tidak valid.');
            redirect(url('admin/maintenance'));
        }
        if ($dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        // Ketik ulang rentangnya sebagai konfirmasi; penghapusan tidak bisa dibatalkan
        if (post('confirm') !== $dari . ' ' . $sampai) {
            flash_set('error', 'Konfirmasi tidak cocok. Ketik persis "' . $dari . ' ' . $sampai . '" untuk melanjutkan.');
            redirect(url('admin/maintenance?slug=' . urlencode($slug)));
        }

        $hasil = event_delete_range($dari, $sampai, $slug, post('keep_daily') !== '1');
        flash_set('success', sprintf(
            '%s baris log%s dihapus untuk %s (%s s/d %s).%s',
            number_format($hasil['events'], 0, ',', '.'),
            $hasil['daily'] > 0 ? ' dan ' . number_format($hasil['daily'], 0, ',', '.') . ' baris agregat harian' : '',
            $slug !== '' ? '"' . $slug . '"' : 'semua PWA',
            $dari,
            $sampai,
            post('keep_daily') === '1' ? ' Ringkasan harian dipertahankan.' : ''
        ));
        redirect(url('admin/maintenance' . ($slug !== '' ? '?slug=' . urlencode($slug) : '')));
    }

    // Tanpa ini information_schema melaporkan ukuran lama yang jauh meleset
    db_refresh_stats('events');
    db_refresh_stats('stats_daily');

    view('maintenance', [
        'slug' => $slug,
        'months' => event_months_available($slug),
        'items' => pwa_all(),
        'sizeEvents' => db_table_size('events'),
        'sizeDaily' => db_table_size('stats_daily'),
    ]);
}

function admin_valid_ym($v)
{
    return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $v);
}

function admin_valid_date($d)
{
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) && strtotime($d) !== false;
}

function admin_stats_reset()
{
    if (!is_post()) {
        redirect(url('admin'));
    }
    csrf_verify();
    $slug = post('slug');
    stats_reset($slug);
    flash_set('success', 'Statistik untuk "' . $slug . '" telah direset.');
    redirect(url('admin/stats/' . $slug));
}

/* ------------------------------------------------------------ Pengaturan */

function admin_settings()
{
    $s = settings_all();

    if (is_post()) {
        csrf_verify();
        $patch = [];
        $title = post('panel_title');
        if ($title !== '') {
            $patch['panel_title'] = mb_substr($title, 0, 60);
        }

        $newUser = post('admin_user');
        if ($newUser !== '' && $newUser !== $s['admin_user']) {
            if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $newUser)) {
                flash_set('error', 'Username hanya boleh huruf, angka, titik, garis bawah, dan strip (3-32 karakter).');
                redirect(url('admin/settings'));
            }
            $patch['admin_user'] = $newUser;
        }

        $newPass = post('new_password');
        if ($newPass !== '') {
            if (!password_verify(post('current_password'), $s['admin_pass'])) {
                flash_set('error', 'Password saat ini salah.');
                redirect(url('admin/settings'));
            }
            if (strlen($newPass) < 8) {
                flash_set('error', 'Password baru minimal 8 karakter.');
                redirect(url('admin/settings'));
            }
            if ($newPass !== post('confirm_password')) {
                flash_set('error', 'Konfirmasi password tidak cocok.');
                redirect(url('admin/settings'));
            }
            $patch['admin_pass'] = password_hash($newPass, PASSWORD_DEFAULT);
            $patch['must_change_password'] = false;
        }

        settings_save($patch);
        if (isset($patch['admin_user'])) {
            $_SESSION['uid'] = $patch['admin_user'];
        }
        flash_set('success', 'Pengaturan tersimpan.');
        redirect(url('admin/settings'));
    }

    view('settings', ['settings' => $s]);
}
