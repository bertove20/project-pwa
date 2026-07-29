<?php

/**
 * Statistik sederhana per PWA, diagregasi harian.
 *
 * Event:
 *   view    - landing page promosi dibuka
 *   install - browser melaporkan aplikasi terpasang
 *   open    - PWA dibuka dari ikon home screen (start_url)
 *   click   - target dibuka dari tombol landing / link langsung
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

    // Lapisan detail: tanggal, jam, dan perangkat per kejadian
    event_log($slug, $event, $src);

    $day = today();
    store_update('stats', function ($data) use ($slug, $event, $day) {
        if (!isset($data[$slug])) {
            $data[$slug] = ['totals' => array_fill_keys(STAT_EVENTS, 0), 'daily' => []];
        }
        if (!isset($data[$slug]['totals'][$event])) {
            $data[$slug]['totals'][$event] = 0;
        }
        $data[$slug]['totals'][$event]++;

        if (!isset($data[$slug]['daily'][$day])) {
            $data[$slug]['daily'][$day] = array_fill_keys(STAT_EVENTS, 0);
        }
        $data[$slug]['daily'][$day][$event] = ($data[$slug]['daily'][$day][$event] ?? 0) + 1;
        $data[$slug]['last_hit'] = $day;

        // Buang data harian yang sudah lewat masa simpan
        $cutoff = date('Y-m-d', strtotime('-' . STATS_RETENTION_DAYS . ' days'));
        foreach (array_keys($data[$slug]['daily']) as $d) {
            if ($d < $cutoff) {
                unset($data[$slug]['daily'][$d]);
            }
        }
        return $data;
    });
}

function stats_all()
{
    return store_read('stats', []);
}

function stats_for($slug)
{
    $all = stats_all();
    if (!isset($all[$slug])) {
        return ['totals' => array_fill_keys(STAT_EVENTS, 0), 'daily' => []];
    }
    $s = $all[$slug];
    $s['totals'] = array_merge(array_fill_keys(STAT_EVENTS, 0), $s['totals'] ?? []);
    $s['daily'] = $s['daily'] ?? [];
    return $s;
}

function stats_reset($slug)
{
    store_update('stats', function ($data) use ($slug) {
        unset($data[$slug]);
        return $data;
    });
    event_delete($slug);
}

/** Pindahkan riwayat statistik saat slug PWA diubah. */
function stats_rename($oldSlug, $newSlug)
{
    if ($oldSlug === $newSlug) {
        return;
    }
    store_update('stats', function ($data) use ($oldSlug, $newSlug) {
        if (isset($data[$oldSlug])) {
            $data[$newSlug] = $data[$oldSlug];
            unset($data[$oldSlug]);
        }
        return $data;
    });
    event_rename($oldSlug, $newSlug);
}

/** Deret harian $days terakhir, selalu lengkap termasuk hari bernilai nol. */
function stats_series($slug, $days = 30)
{
    $s = stats_for($slug);
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $row = $s['daily'][$d] ?? [];
        $out[] = [
            'date' => $d,
            'view' => (int) ($row['view'] ?? 0),
            'install' => (int) ($row['install'] ?? 0),
            'open' => (int) ($row['open'] ?? 0),
            'click' => (int) ($row['click'] ?? 0),
        ];
    }
    return $out;
}

/** Jumlah event pada rentang $days terakhir (0 = hari ini saja). */
function stats_sum($slug, $event, $days = 1)
{
    $s = stats_for($slug);
    $sum = 0;
    for ($i = 0; $i < max(1, $days); $i++) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $sum += (int) ($s['daily'][$d][$event] ?? 0);
    }
    return $sum;
}

/** Ringkasan global untuk kartu di dashboard. */
function stats_overview(array $items)
{
    $sum = ['view' => 0, 'install' => 0, 'open' => 0, 'click' => 0];
    $todayOpen = 0;
    foreach ($items as $it) {
        $s = stats_for($it['slug']);
        foreach ($sum as $k => $_) {
            $sum[$k] += (int) ($s['totals'][$k] ?? 0);
        }
        $todayOpen += (int) ($s['daily'][today()]['open'] ?? 0)
            + (int) ($s['daily'][today()]['click'] ?? 0);
    }
    $sum['today_traffic'] = $todayOpen;
    return $sum;
}
