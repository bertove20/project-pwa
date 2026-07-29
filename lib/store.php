<?php

/**
 * Penyimpanan flat-file JSON dengan file locking.
 * Semua tulis memakai LOCK_EX + rename atomik supaya file tidak pernah setengah jadi.
 */

function store_path($name)
{
    return DATA_DIR . '/' . $name . '.json';
}

/**
 * Cache per-request. Satu halaman dashboard membaca pwa.json dan stats.json
 * puluhan kali, jadi hasilnya disimpan sampai ada penulisan.
 */
function &store_cache()
{
    static $cache = [];
    return $cache;
}

function store_cache_clear($name = null)
{
    $cache = &store_cache();
    if ($name === null) {
        $cache = [];
    } else {
        unset($cache[$name]);
    }
}

function store_read($name, $default = [])
{
    $cache = &store_cache();
    if (array_key_exists($name, $cache)) {
        return $cache[$name];
    }

    $file = store_path($name);
    if (!is_file($file)) {
        return $default;
    }
    $fh = @fopen($file, 'rb');
    if (!$fh) {
        return $default;
    }
    flock($fh, LOCK_SH);
    $raw = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    $data = json_decode((string) $raw, true);
    $cache[$name] = is_array($data) ? $data : $default;
    return $cache[$name];
}

function store_write($name, $data)
{
    ensure_dirs();
    store_cache_clear($name);
    $file = store_path($name);
    $tmp = $file . '.' . getmypid() . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }
    // rename() pada Windows gagal bila tujuan ada, jadi pakai copy+unlink sebagai fallback
    if (!@rename($tmp, $file)) {
        $ok = @copy($tmp, $file);
        @unlink($tmp);
        return $ok;
    }
    return true;
}

/**
 * Baca-ubah-tulis dalam satu lock eksklusif.
 * $fn menerima data saat ini dan mengembalikan data baru.
 */
function store_update($name, callable $fn)
{
    ensure_dirs();
    store_cache_clear($name);
    $file = store_path($name);
    $fh = fopen($file, 'c+b');
    if (!$fh) {
        return false;
    }
    flock($fh, LOCK_EX);

    $raw = stream_get_contents($fh);
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        $data = [];
    }

    $new = $fn($data);
    $json = json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, (string) $json);
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    return $new;
}

/* ---------------------------------------------------------------- PWA CRUD */

function pwa_all($onlyActive = false)
{
    $data = store_read('pwa', ['items' => []]);
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
    if ($onlyActive) {
        $items = array_values(array_filter($items, function ($i) {
            return !empty($i['active']);
        }));
    }
    usort($items, function ($a, $b) {
        return strcmp($a['name'] ?? '', $b['name'] ?? '');
    });
    return $items;
}

function pwa_find($slug)
{
    foreach (pwa_all() as $item) {
        if (($item['slug'] ?? '') === $slug) {
            return $item;
        }
    }
    return null;
}

function pwa_find_by_id($id)
{
    foreach (pwa_all() as $item) {
        if (($item['id'] ?? '') === $id) {
            return $item;
        }
    }
    return null;
}

function pwa_save(array $item)
{
    store_update('pwa', function ($data) use ($item) {
        if (!isset($data['items']) || !is_array($data['items'])) {
            $data['items'] = [];
        }
        $found = false;
        foreach ($data['items'] as $i => $row) {
            if (($row['id'] ?? '') === $item['id']) {
                $data['items'][$i] = $item;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $data['items'][] = $item;
        }
        return $data;
    });
    return $item;
}

function pwa_delete($id)
{
    $removed = null;
    store_update('pwa', function ($data) use ($id, &$removed) {
        $items = isset($data['items']) ? $data['items'] : [];
        $keep = [];
        foreach ($items as $row) {
            if (($row['id'] ?? '') === $id) {
                $removed = $row;
            } else {
                $keep[] = $row;
            }
        }
        $data['items'] = $keep;
        return $data;
    });
    return $removed;
}

function pwa_slug_taken($slug, $exceptId = null)
{
    foreach (pwa_all() as $item) {
        if (($item['slug'] ?? '') === $slug && ($item['id'] ?? '') !== $exceptId) {
            return true;
        }
    }
    return false;
}

/* ------------------------------------------------------------- Pengaturan */

function settings_all()
{
    $s = store_read('settings', []);
    if (empty($s['admin_user'])) {
        $s = [
            'admin_user' => DEFAULT_ADMIN_USER,
            'admin_pass' => password_hash(DEFAULT_ADMIN_PASS, PASSWORD_DEFAULT),
            'panel_title' => APP_NAME,
            'created_at' => now_iso(),
            'must_change_password' => true,
        ];
        store_write('settings', $s);
    }
    return $s;
}

function settings_save(array $patch)
{
    return store_update('settings', function ($s) use ($patch) {
        return array_merge($s, $patch);
    });
}
