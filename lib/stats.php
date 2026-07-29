<?php

/**
 * Penghitung agregat harian.
 *
 * Dipakai kartu dashboard yang membaca angka lintas semua PWA sekaligus,
 * sehingga tidak layak dihitung ulang dari tabel events setiap kali.
 * Rincian per kejadian ada di lib/events.php.
 *
 * Tidak ada pemangkasan otomatis: data lama tetap tersimpan dan hanya
 * dihapus manual lewat menu Pemeliharaan.
 */

define('STAT_EVENTS', ['view', 'install', 'open', 'click']);

/**
 * @param string $src Asal trafik: pwa (ikon home screen), web (tombol landing),
 *                    panel (landing bawaan), ext (landing di domain lain)
 */
function stat_hit($slug, $event, $src = '')
{
    if (!in_array($event, STAT_EVENTS, true) || $slug === '') {
        return;
    }

    // Lapisan rincian: tanggal, jam, perangkat, browser, sumber
    event_log($slug, $event, $src);

    db_run(
        'INSERT INTO stats_daily (slug, day, event, hits) VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE hits = hits + 1',
        [$slug, today(), $event]
    );
}

/** Total sepanjang masa + rincian harian untuk satu PWA. */
function stats_for($slug)
{
    $totals = array_merge(
        array_fill_keys(STAT_EVENTS, 0),
        db_pairs('SELECT event, SUM(hits) FROM stats_daily WHERE slug = ? GROUP BY event', [$slug])
    );
    return ['totals' => $totals];
}

function stats_reset($slug)
{
    db_run('DELETE FROM stats_daily WHERE slug = ?', [$slug]);
    event_delete($slug);
}

function stats_rename($oldSlug, $newSlug)
{
    if ($oldSlug === $newSlug) {
        return;
    }
    db_run('UPDATE stats_daily SET slug = ? WHERE slug = ?', [$newSlug, $oldSlug]);
    event_rename($oldSlug, $newSlug);
}

/** Jumlah satu jenis peristiwa pada $days hari terakhir. */
function stats_sum($slug, $event, $days = 1)
{
    return (int) db_val(
        'SELECT COALESCE(SUM(hits), 0) FROM stats_daily
         WHERE slug = ? AND event = ? AND day >= ?',
        [$slug, $event, date('Y-m-d', strtotime('-' . (max(1, $days) - 1) . ' days'))],
        0
    );
}

/**
 * Ringkasan lintas seluruh PWA untuk kartu dashboard.
 * Dua kueri agregat, bukan satu per PWA.
 */
function stats_overview(array $items)
{
    $sum = array_merge(
        ['view' => 0, 'install' => 0, 'open' => 0, 'click' => 0],
        db_pairs('SELECT event, SUM(hits) FROM stats_daily GROUP BY event')
    );

    $today = db_pairs(
        'SELECT event, SUM(hits) FROM stats_daily WHERE day = ? GROUP BY event',
        [today()]
    );

    $sum['today_traffic'] = (int) ($today['open'] ?? 0) + (int) ($today['click'] ?? 0);
    return $sum;
}

/** Total per PWA untuk seluruh daftar sekaligus, dipakai tabel dashboard. */
function stats_totals_map()
{
    return mem_get('stats.map', function () {
        $map = [];
        foreach (db_all('SELECT slug, event, SUM(hits) AS n FROM stats_daily GROUP BY slug, event') as $r) {
            if (!isset($map[$r['slug']])) {
                $map[$r['slug']] = array_fill_keys(STAT_EVENTS, 0);
            }
            $map[$r['slug']][$r['event']] = (int) $r['n'];
        }
        return $map;
    });
}
