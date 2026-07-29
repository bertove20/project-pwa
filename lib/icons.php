<?php

/**
 * Pemrosesan ikon PWA.
 * PNG/JPG/WEBP di-resize dengan GD menjadi 192, 512 dan versi maskable 512.
 * SVG disalin apa adanya (dipakai untuk semua ukuran, sizes="any").
 */

define('ICON_SIZES', [192, 512]);

function icon_allowed_mimes()
{
    return [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];
}

/**
 * @return array{ok:bool, basename?:string, is_svg?:bool, error?:string}
 */
function icon_process_upload(array $file, $slug, $bgColor = '#ffffff')
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'no_file'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload gagal (kode ' . $file['error'] . ').'];
    }
    if ($file['size'] > 4 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Ukuran file maksimal 4 MB.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    $allowed = icon_allowed_mimes();
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Format harus PNG, JPG, WEBP, atau SVG.'];
    }

    ensure_dirs();
    icon_delete($slug);

    if ($mime === 'image/svg+xml') {
        $dest = ICON_DIR . '/' . $slug . '.svg';
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['ok' => false, 'error' => 'Gagal menyimpan file ikon.'];
        }
        return ['ok' => true, 'basename' => $slug . '.svg', 'is_svg' => true];
    }

    if (!function_exists('imagecreatetruecolor')) {
        return ['ok' => false, 'error' => 'Ekstensi GD tidak aktif di PHP ini.'];
    }

    $src = icon_load_image($file['tmp_name'], $mime);
    if (!$src) {
        return ['ok' => false, 'error' => 'File gambar tidak bisa dibaca.'];
    }

    foreach (ICON_SIZES as $size) {
        $canvas = icon_fit($src, $size, $size, 1.0, null);
        imagepng($canvas, ICON_DIR . '/' . $slug . '-' . $size . '.png', 6);
        imagedestroy($canvas);
    }

    // Versi maskable: ikon diperkecil ke 80% di atas background solid (safe zone Android)
    $maskable = icon_fit($src, 512, 512, 0.8, $bgColor);
    imagepng($maskable, ICON_DIR . '/' . $slug . '-maskable.png', 6);
    imagedestroy($maskable);
    imagedestroy($src);

    return ['ok' => true, 'basename' => $slug . '-512.png', 'is_svg' => false];
}

function icon_load_image($path, $mime)
{
    switch ($mime) {
        case 'image/png':
            return @imagecreatefrompng($path);
        case 'image/jpeg':
            return @imagecreatefromjpeg($path);
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
    }
    return null;
}

/** Gambar $src di tengah kanvas $w x $h, mengisi $scale bagian dari kanvas. */
function icon_fit($src, $w, $h, $scale, $bgHex)
{
    $canvas = imagecreatetruecolor($w, $h);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);

    if ($bgHex !== null && is_hex_color($bgHex)) {
        $rgb = sscanf($bgHex, "#%02x%02x%02x");
        $bg = imagecolorallocatealpha($canvas, $rgb[0], $rgb[1], $rgb[2], 0);
    } else {
        $bg = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    }
    imagefilledrectangle($canvas, 0, 0, $w, $h, $bg);
    imagealphablending($canvas, true);

    $sw = imagesx($src);
    $sh = imagesy($src);
    $ratio = min(($w * $scale) / $sw, ($h * $scale) / $sh);
    $dw = max(1, (int) round($sw * $ratio));
    $dh = max(1, (int) round($sh * $ratio));
    $dx = (int) round(($w - $dw) / 2);
    $dy = (int) round(($h - $dh) / 2);

    imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
    return $canvas;
}

function icon_delete($slug)
{
    $patterns = [
        ICON_DIR . '/' . $slug . '.svg',
        ICON_DIR . '/' . $slug . '-maskable.png',
    ];
    foreach (ICON_SIZES as $size) {
        $patterns[] = ICON_DIR . '/' . $slug . '-' . $size . '.png';
    }
    foreach ($patterns as $p) {
        if (is_file($p)) {
            @unlink($p);
        }
    }
}

function icon_exists($slug, $isSvg = false)
{
    if ($isSvg) {
        return is_file(ICON_DIR . '/' . $slug . '.svg');
    }
    return is_file(ICON_DIR . '/' . $slug . '-512.png');
}

/** URL ikon untuk ditampilkan di panel / landing page. */
function icon_url($pwa, $size = 512)
{
    $slug = $pwa['slug'] ?? '';
    $isSvg = !empty($pwa['icon_svg']);
    $v = isset($pwa['icon_ver']) ? (int) $pwa['icon_ver'] : 0;

    if ($isSvg && icon_exists($slug, true)) {
        return url(ICON_URL_PATH . '/' . $slug . '.svg?v=' . $v);
    }
    if (icon_exists($slug)) {
        $size = in_array($size, ICON_SIZES, true) ? $size : 512;
        return url(ICON_URL_PATH . '/' . $slug . '-' . $size . '.png?v=' . $v);
    }
    return null;
}

/** URL ikon lengkap dengan skema dan host, siap dipakai dari domain lain. */
function icon_abs_url($pwa, $size = 512)
{
    $rel = icon_url($pwa, $size);
    return $rel === null ? '' : abs_from_root($rel);
}

/** Daftar entri "icons" untuk manifest. */
function icon_manifest_entries($pwa)
{
    $slug = $pwa['slug'] ?? '';
    $v = isset($pwa['icon_ver']) ? (int) $pwa['icon_ver'] : 0;
    $out = [];

    if (!empty($pwa['icon_svg']) && icon_exists($slug, true)) {
        $out[] = [
            'src' => abs_url(ICON_URL_PATH . '/' . $slug . '.svg?v=' . $v),
            'sizes' => 'any',
            'type' => 'image/svg+xml',
            'purpose' => 'any',
        ];
        return $out;
    }

    if (!icon_exists($slug)) {
        return $out;
    }

    foreach (ICON_SIZES as $size) {
        $out[] = [
            'src' => abs_url(ICON_URL_PATH . '/' . $slug . '-' . $size . '.png?v=' . $v),
            'sizes' => $size . 'x' . $size,
            'type' => 'image/png',
            'purpose' => 'any',
        ];
    }
    if (is_file(ICON_DIR . '/' . $slug . '-maskable.png')) {
        $out[] = [
            'src' => abs_url(ICON_URL_PATH . '/' . $slug . '-maskable.png?v=' . $v),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ];
    }
    return $out;
}

/** Ganti nama file ikon saat slug PWA diubah. */
function icon_rename($oldSlug, $newSlug)
{
    if ($oldSlug === $newSlug) {
        return;
    }
    $map = [$oldSlug . '.svg' => $newSlug . '.svg', $oldSlug . '-maskable.png' => $newSlug . '-maskable.png'];
    foreach (ICON_SIZES as $size) {
        $map[$oldSlug . '-' . $size . '.png'] = $newSlug . '-' . $size . '.png';
    }
    foreach ($map as $from => $to) {
        if (is_file(ICON_DIR . '/' . $from)) {
            @rename(ICON_DIR . '/' . $from, ICON_DIR . '/' . $to);
        }
    }
}
