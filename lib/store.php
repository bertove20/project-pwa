<?php

/**
 * Penyimpanan data PWA dan pengaturan panel.
 * Sejak v2 seluruhnya di database; berkas JSON lama otomatis dipindahkan
 * saat skema pertama kali dibuat (lihat lib/db.php).
 */

/**
 * Cache seumur-request. Dipakai lewat referensi supaya bisa dikosongkan
 * setelah penulisan; cache `static` di dalam fungsi tidak bisa dijangkau
 * dari luar sehingga perubahan tidak akan terlihat pada request yang sama.
 */
function &mem_cache()
{
    static $c = [];
    return $c;
}

function mem_get($key, callable $build)
{
    $c = &mem_cache();
    if (!array_key_exists($key, $c)) {
        $c[$key] = $build();
    }
    return $c[$key];
}

function mem_clear($prefix = null)
{
    $c = &mem_cache();
    if ($prefix === null) {
        $c = [];
        return;
    }
    foreach (array_keys($c) as $k) {
        if (strpos($k, $prefix) === 0) {
            unset($c[$k]);
        }
    }
}

/** Ubah baris database menjadi bentuk array yang dipakai view. */
function pwa_hydrate(array $r)
{
    return [
        'id' => $r['id'],
        'slug' => $r['slug'],
        'name' => $r['name'],
        'short_name' => $r['short_name'],
        'description' => $r['description'],
        'target_url' => $r['target_url'],
        'web_target_url' => (string) ($r['web_target_url'] ?? ''),
        'install_label' => (string) ($r['install_label'] ?? ''),
        'open_label' => (string) ($r['open_label'] ?? ''),
        'theme_color' => $r['theme_color'],
        'background_color' => $r['background_color'],
        'display' => $r['display'],
        'orientation' => $r['orientation'],
        'active' => (bool) $r['active'],
        'protect' => (bool) $r['protect'],
        'icon_svg' => (bool) $r['icon_svg'],
        'icon_ver' => (int) $r['icon_ver'],
        'created_at' => $r['created_at'],
        'updated_at' => $r['updated_at'],
    ];
}

function pwa_all($onlyActive = false)
{
    return mem_get('pwa.' . ($onlyActive ? 'aktif' : 'semua'), function () use ($onlyActive) {
        $sql = 'SELECT * FROM pwa' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY name ASC';
        return array_map('pwa_hydrate', db_all($sql));
    });
}

function pwa_find($slug)
{
    $r = db_row('SELECT * FROM pwa WHERE slug = ?', [$slug]);
    return $r ? pwa_hydrate($r) : null;
}

function pwa_find_by_id($id)
{
    $r = db_row('SELECT * FROM pwa WHERE id = ?', [$id]);
    return $r ? pwa_hydrate($r) : null;
}

function pwa_save(array $item)
{
    db_run(
        'INSERT INTO pwa (id, slug, name, short_name, description, target_url, web_target_url,
            install_label, open_label, theme_color,
            background_color, display, orientation, active, protect, icon_svg, icon_ver, created_at, updated_at)
         VALUES (:id, :slug, :name, :short_name, :description, :target_url, :web_target_url,
            :install_label, :open_label, :theme_color,
            :background_color, :display, :orientation, :active, :protect, :icon_svg, :icon_ver, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            slug = VALUES(slug), name = VALUES(name), short_name = VALUES(short_name),
            description = VALUES(description), target_url = VALUES(target_url),
            web_target_url = VALUES(web_target_url),
            install_label = VALUES(install_label), open_label = VALUES(open_label),
            theme_color = VALUES(theme_color), background_color = VALUES(background_color),
            display = VALUES(display), orientation = VALUES(orientation), active = VALUES(active),
            protect = VALUES(protect),
            icon_svg = VALUES(icon_svg), icon_ver = VALUES(icon_ver), updated_at = VALUES(updated_at)',
        [
            ':id' => $item['id'],
            ':slug' => $item['slug'],
            ':name' => $item['name'],
            ':short_name' => $item['short_name'],
            ':description' => $item['description'],
            ':target_url' => $item['target_url'],
            ':web_target_url' => $item['web_target_url'] !== '' ? $item['web_target_url'] : null,
            ':install_label' => $item['install_label'] ?? '',
            ':open_label' => $item['open_label'] ?? '',
            ':theme_color' => $item['theme_color'],
            ':background_color' => $item['background_color'],
            ':display' => $item['display'],
            ':orientation' => $item['orientation'],
            ':active' => !empty($item['active']) ? 1 : 0,
            ':protect' => !empty($item['protect']) ? 1 : 0,
            ':icon_svg' => !empty($item['icon_svg']) ? 1 : 0,
            ':icon_ver' => (int) $item['icon_ver'],
            ':created_at' => db_dt($item['created_at'] ?? null),
            ':updated_at' => db_dt($item['updated_at'] ?? null),
        ]
    );
    mem_clear('pwa.');
    return $item;
}

function pwa_delete($id)
{
    $item = pwa_find_by_id($id);
    if (!$item) {
        return null;
    }
    db_run('DELETE FROM pwa WHERE id = ?', [$id]);
    mem_clear('pwa.');
    return $item;
}

function pwa_slug_taken($slug, $exceptId = null)
{
    $r = db_val('SELECT COUNT(*) FROM pwa WHERE slug = ? AND id <> ?', [$slug, (string) $exceptId]);
    return (int) $r > 0;
}

/* ------------------------------------------------------------- Pengaturan */

function settings_all()
{
    return mem_get('settings', function () {
        $s = [];
        foreach (db_all('SELECT k, v FROM settings') as $r) {
            $s[$r['k']] = $r['v'];
        }

        if (empty($s['admin_user'])) {
            $s = array_merge($s, [
                'admin_user' => DEFAULT_ADMIN_USER,
                'admin_pass' => password_hash(DEFAULT_ADMIN_PASS, PASSWORD_DEFAULT),
                'panel_title' => APP_NAME,
                'created_at' => now_iso(),
                'must_change_password' => '1',
            ]);
            settings_write($s);
        }

        // Nilai boolean disimpan sebagai '1'/'0' di kolom TEXT
        $s['must_change_password'] = !empty($s['must_change_password']) && $s['must_change_password'] !== '0';

        return $s;
    });
}

function settings_write(array $pairs)
{
    $st = db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    foreach ($pairs as $k => $v) {
        if (is_bool($v)) {
            $v = $v ? '1' : '0';
        }
        $st->execute([$k, (string) $v]);
    }
}

function settings_save(array $patch)
{
    settings_write($patch);
    mem_clear('settings');
    return settings_all();
}
