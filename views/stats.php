<?php
/** @var array $pwa */
/** @var array $report */
/** @var array $totals */
/** @var string $from */
/** @var string $to */
/** @var int $days */
/** @var bool $isCustom */
/** @var array $filters */
/** @var int $storage */

$evLabel = [
    'view' => 'Kunjungan',
    'install' => 'Install',
    'open' => 'Buka dari ikon',
    'click' => 'Klik ke target',
];
$devLabel = [
    'mobile' => 'Ponsel',
    'tablet' => 'Tablet',
    'desktop' => 'Desktop',
    'bot' => 'Bot / perayap',
    'lainnya' => 'Lainnya',
];
$srcLabel = [
    'pwa' => 'Ikon home screen',
    'web' => 'Tombol di landing',
    'panel' => 'Landing bawaan panel',
    'ext' => 'Landing domain lain',
    '(tidak diketahui)' => 'Tidak diketahui',
];
$hariLabel = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

/** Bangun URL halaman ini dengan sebagian parameter diganti. */
$qs = function (array $override = []) use ($pwa, $from, $to, $days, $isCustom, $filters) {
    $q = array_merge([
        'from' => $isCustom ? $from : null,
        'to' => $isCustom ? $to : null,
        'days' => $isCustom ? null : $days,
        'event' => $filters['event'] ?: null,
        'device' => $filters['device'] ?: null,
        'source' => $filters['source'] ?: null,
        'bots' => $filters['hide_bots'] ? null : '1',
    ], $override);
    $q = array_filter($q, function ($v) { return $v !== null && $v !== ''; });
    return url('admin/stats/' . $pwa['slug']) . ($q ? '?' . http_build_query($q) : '');
};

$totalRange = $report['count'];
$dibuka = $report['events']['open'] + $report['events']['click'];
$konversi = $report['events']['view'] > 0
    ? round($report['events']['install'] / $report['events']['view'] * 100, 1)
    : 0;

// Baris peringkat dengan batang proporsi
$rank = function (array $data, $labels = [], $limit = 8) use ($totalRange) {
    $out = '';
    $shown = 0;
    $sum = array_sum($data);
    foreach ($data as $key => $n) {
        if ($shown++ >= $limit) {
            break;
        }
        $pct = $sum > 0 ? round($n / $sum * 100, 1) : 0;
        $label = $labels[$key] ?? $key;
        $out .= '<div class="rank-row">'
            . '<span class="rank-label">' . e($label) . '</span>'
            . '<span class="rank-bar"><i style="width:' . max(1.5, $pct) . '%"></i></span>'
            . '<span class="rank-val">' . number_format($n, 0, ',', '.') . '</span>'
            . '<span class="rank-pct">' . $pct . '%</span>'
            . '</div>';
    }
    if (!$data) {
        $out = '<p class="muted empty-small">Belum ada data pada rentang ini.</p>';
    }
    return $out;
};

$maxDaily = 1;
foreach ($report['daily'] as $row) {
    $maxDaily = max($maxDaily, array_sum($row));
}
$maxHour = max(1, max($report['hourly']));
$maxWeekday = max(1, max($report['weekday']));

ob_start();
?>

<div class="page-head">
  <div>
    <h1>Analitik &mdash; <?= e($pwa['name']) ?></h1>
    <p class="muted">
      <?= e(date('d M Y', strtotime($from))) ?> &ndash; <?= e(date('d M Y', strtotime($to))) ?>
      &middot; <?= number_format($totalRange, 0, ',', '.') ?> peristiwa
      <?php if ($report['bots'] > 0 && $filters['hide_bots']): ?>
        &middot; <?= number_format($report['bots'], 0, ',', '.') ?> bot disembunyikan
      <?php endif; ?>
    </p>
  </div>
  <div class="head-actions">
    <a class="btn btn-ghost" href="<?= e($qs(['export' => 'csv'])) ?>">Ekspor CSV</a>
    <a class="btn btn-ghost" href="<?= e(url('admin')) ?>">&larr; Kembali</a>
  </div>
</div>

<div class="panel filter-panel">
  <div class="range-tabs">
    <?php foreach ([1 => 'Hari ini', 7 => '7 hari', 30 => '30 hari', 90 => '90 hari'] as $d => $lbl): ?>
      <a class="<?= (!$isCustom && $days === $d) ? 'is-active' : '' ?>"
         href="<?= e(url('admin/stats/' . $pwa['slug'] . '?days=' . $d)) ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </div>

  <form method="get" action="<?= e(url('admin/stats/' . $pwa['slug'])) ?>" class="filter-form">
    <label class="filter-field">
      <span>Dari</span>
      <input type="date" name="from" value="<?= e($from) ?>" max="<?= e(today()) ?>">
    </label>
    <label class="filter-field">
      <span>Sampai</span>
      <input type="date" name="to" value="<?= e($to) ?>" max="<?= e(today()) ?>">
    </label>
    <label class="filter-field">
      <span>Peristiwa</span>
      <select name="event">
        <option value="">Semua</option>
        <?php foreach ($evLabel as $k => $lbl): ?>
          <option value="<?= e($k) ?>" <?= $filters['event'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="filter-field">
      <span>Perangkat</span>
      <select name="device">
        <option value="">Semua</option>
        <?php foreach (['mobile', 'tablet', 'desktop'] as $k): ?>
          <option value="<?= e($k) ?>" <?= $filters['device'] === $k ? 'selected' : '' ?>><?= e($devLabel[$k]) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="filter-field">
      <span>Sumber</span>
      <select name="source">
        <option value="">Semua</option>
        <?php foreach (['pwa', 'web', 'panel', 'ext'] as $k): ?>
          <option value="<?= e($k) ?>" <?= $filters['source'] === $k ? 'selected' : '' ?>><?= e($srcLabel[$k]) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="filter-check">
      <input type="checkbox" name="bots" value="1" <?= $filters['hide_bots'] ? '' : 'checked' ?>>
      <span>Tampilkan bot</span>
    </label>
    <button type="submit" class="btn btn-primary btn-sm">Terapkan</button>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/stats/' . $pwa['slug'])) ?>">Reset</a>
  </form>
</div>

<section class="cards">
  <div class="card" title="Dihitung per hari: pengunjung yang datang di tiga hari berbeda terhitung tiga kali. Penanda tidak menyimpan alamat IP dan berganti setiap hari.">
    <span class="card-label">Pengunjung unik</span>
    <strong class="card-value"><?= number_short($report['unique']) ?></strong>
    <span class="card-foot">per hari, dari <?= number_short($totalRange) ?> peristiwa</span>
  </div>
  <div class="card">
    <span class="card-label">Kunjungan</span>
    <strong class="card-value"><?= number_short($report['events']['view']) ?></strong>
    <span class="card-foot">halaman install dibuka</span>
  </div>
  <div class="card">
    <span class="card-label">Install</span>
    <strong class="card-value"><?= number_short($report['events']['install']) ?></strong>
    <span class="card-foot"><?= $konversi ?>% dari kunjungan</span>
  </div>
  <div class="card">
    <span class="card-label">Target dibuka</span>
    <strong class="card-value"><?= number_short($dibuka) ?></strong>
    <span class="card-foot">
      <?= number_short($report['events']['open']) ?> dari ikon &middot;
      <?= number_short($report['events']['click']) ?> dari landing
    </span>
  </div>
</section>

<?php if ($report['webview'] > 0): ?>
  <div class="alert alert-warn">
    <?= number_format($report['webview'], 0, ',', '.') ?> peristiwa
    (<?= $totalRange > 0 ? round($report['webview'] / $totalRange * 100, 1) : 0 ?>%)
    berasal dari browser dalam aplikasi seperti Facebook, Instagram, atau TikTok.
    Webview semacam itu <strong>tidak pernah menampilkan tombol install PWA</strong> &mdash;
    pengunjung dari sana perlu membuka halaman di browser biasa lebih dulu.
  </div>
<?php endif; ?>

<div class="panel">
  <h2 class="panel-title">Peristiwa per hari</h2>
  <div class="legend">
    <span><i class="sw sw-view"></i> Kunjungan</span>
    <span><i class="sw sw-install"></i> Install</span>
    <span><i class="sw sw-traffic"></i> Buka dari ikon</span>
    <span><i class="sw sw-click"></i> Klik ke target</span>
  </div>
  <div class="chart" style="--cols: <?= max(1, count($report['daily'])) ?>">
    <?php foreach ($report['daily'] as $tgl => $row):
        $tot = array_sum($row);
        $tip = date('D, d M Y', strtotime($tgl)) . ' — '
             . $row['view'] . ' kunjungan, ' . $row['install'] . ' install, '
             . $row['open'] . ' buka, ' . $row['click'] . ' klik';
    ?>
      <div class="chart-col<?= $tot === 0 ? ' is-empty' : '' ?>" title="<?= e($tip) ?>">
        <div class="bars stacked">
          <?php foreach (['click', 'open', 'install', 'view'] as $ev):
              // Hari tanpa data tidak menggambar apa pun; batang setinggi nol
              // tetap terlihat sebagai garis dan menyesatkan.
              if ($row[$ev] <= 0) {
                  continue;
              }
              $cls = ['click' => 'bar-click', 'open' => 'bar-traffic', 'install' => 'bar-install', 'view' => 'bar-view'][$ev];
          ?>
            <span class="bar <?= $cls ?>" style="height:<?= round($row[$ev] / $maxDaily * 100, 2) ?>%"></span>
          <?php endforeach; ?>
        </div>
        <span class="chart-x"><?= e(date('j/n', strtotime($tgl))) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="muted chart-note">Batang tertinggi = <?= (int) $maxDaily ?> peristiwa dalam sehari.</p>
</div>

<div class="grid-2">
  <div class="panel">
    <h2 class="panel-title">Jam paling ramai</h2>
    <div class="chart chart-hour" style="--cols: 24">
      <?php foreach ($report['hourly'] as $jam => $n): ?>
        <div class="chart-col" title="<?= sprintf('%02d:00 – %02d:59', $jam, $jam) ?> — <?= (int) $n ?> peristiwa">
          <div class="bars">
            <?php if ($n > 0): ?>
              <span class="bar bar-traffic" style="height:<?= round($n / $maxHour * 100, 2) ?>%"></span>
            <?php endif; ?>
          </div>
          <span class="chart-x"><?= $jam % 3 === 0 ? str_pad($jam, 2, '0', STR_PAD_LEFT) : '' ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="muted chart-note">Waktu <?= e(TIMEZONE) ?>. Puncaknya <?= (int) $maxHour ?> peristiwa.</p>
  </div>

  <div class="panel">
    <h2 class="panel-title">Hari paling ramai</h2>
    <?php
    $wd = [];
    foreach ($report['weekday'] as $i => $n) {
        $wd[$hariLabel[$i]] = $n;
    }
    arsort($wd);
    echo $rank($wd, [], 7);
    ?>
  </div>
</div>

<div class="grid-2">
  <div class="panel">
    <h2 class="panel-title">Perangkat</h2>
    <?= $rank($report['device'], $devLabel) ?>
  </div>
  <div class="panel">
    <h2 class="panel-title">Sistem operasi</h2>
    <?= $rank($report['os']) ?>
  </div>
</div>

<div class="grid-2">
  <div class="panel">
    <h2 class="panel-title">Browser</h2>
    <?= $rank($report['browser']) ?>
  </div>
  <div class="panel">
    <h2 class="panel-title">Sumber trafik</h2>
    <?= $rank($report['source'], $srcLabel) ?>
  </div>
</div>

<?php if ($report['referrer']): ?>
<div class="panel">
  <h2 class="panel-title">Perujuk (referrer)</h2>
  <?= $rank($report['referrer'], [], 12) ?>
</div>
<?php endif; ?>

<div class="panel">
  <div class="panel-head">
    <h2 class="panel-title">Peristiwa terbaru</h2>
    <span class="muted" style="font-size:.82rem">
      <?= count($report['recent']) ?> terakhir dari <?= number_format($totalRange, 0, ',', '.') ?>
    </span>
  </div>

  <?php if (!$report['recent']): ?>
    <p class="muted">Belum ada peristiwa pada rentang dan filter ini.</p>
  <?php else: ?>
    <div class="table-wrap log-wrap">
      <table class="table log-table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Jam</th>
            <th>Peristiwa</th>
            <th>Perangkat</th>
            <th>Sistem operasi</th>
            <th>Browser</th>
            <th>Sumber</th>
            <th>Perujuk</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($report['recent'] as $r):
            $ts = strtotime($r['t']);
        ?>
          <tr>
            <td class="nowrap"><?= e(date('d M Y', $ts)) ?><span class="sub"><?= e($hariLabel[(int) date('w', $ts)]) ?></span></td>
            <td class="nowrap mono"><?= e(date('H:i:s', $ts)) ?></td>
            <td><span class="tag tag-<?= e($r['e']) ?>"><?= e($evLabel[$r['e']] ?? $r['e']) ?></span></td>
            <td class="nowrap"><?= e($devLabel[$r['d']] ?? $r['d']) ?></td>
            <td class="nowrap"><?= e($r['o']) ?></td>
            <td class="nowrap">
              <?= e($r['b']) ?>
              <?php if (!empty($r['w'])): ?><span class="tag tag-wv" title="Browser dalam aplikasi, tidak bisa install PWA">in-app</span><?php endif; ?>
            </td>
            <td class="nowrap"><?= e($srcLabel[$r['s']] ?? ($r['s'] !== '' ? $r['s'] : '&mdash;')) ?></td>
            <td class="ref"><?= $r['r'] !== '' ? e($r['r']) : '<span class="muted">langsung</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="muted chart-note">
      Butuh seluruh datanya? <a href="<?= e($qs(['export' => 'csv'])) ?>">Ekspor CSV</a>
      mengikuti rentang dan filter yang sedang aktif.
    </p>
  <?php endif; ?>
</div>

<div class="panel">
  <h2 class="panel-title">Kelola data</h2>
  <dl class="kv">
    <dt>Ukuran log</dt><dd><?= e(event_format_size($storage)) ?></dd>
    <dt>Masa simpan</dt><dd>Rincian <?= (int) EVENT_RETENTION_MONTHS ?> bulan, agregat harian <?= (int) STATS_RETENTION_DAYS ?> hari</dd>
    <dt>Total sepanjang masa</dt>
    <dd><?= number_short($totals['view']) ?> kunjungan &middot; <?= number_short($totals['install']) ?> install
        &middot; <?= number_short($totals['open']) ?> buka &middot; <?= number_short($totals['click']) ?> klik
        <small class="block muted">Dari penghitung agregat yang juga dipakai kartu di dashboard,
        terpisah dari log rincian di atas.</small></dd>
  </dl>
  <form method="post" action="<?= e(url('admin/stats-reset')) ?>"
        onsubmit="return confirm('Reset seluruh statistik dan log peristiwa untuk PWA ini? Tindakan ini tidak bisa dibatalkan.');">
    <?= csrf_field() ?>
    <input type="hidden" name="slug" value="<?= e($pwa['slug']) ?>">
    <button type="submit" class="btn btn-danger">Reset statistik PWA ini</button>
  </form>
</div>

<?php
$content = ob_get_clean();
$title = 'Analitik ' . $pwa['name'];
include ROOT_DIR . '/views/layout.php';
