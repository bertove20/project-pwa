<?php
/** @var array $pwa */
/** @var array $series */
/** @var array $totals */
/** @var int $days */

$max = 1;
foreach ($series as $row) {
    $max = max($max, $row['view'], $row['open'] + $row['click'], $row['install']);
}
$sumRange = ['view' => 0, 'install' => 0, 'open' => 0, 'click' => 0];
foreach ($series as $row) {
    foreach ($sumRange as $k => $_) {
        $sumRange[$k] += $row[$k];
    }
}
ob_start();
?>

<div class="page-head">
  <div>
    <h1>Statistik &mdash; <?= e($pwa['name']) ?></h1>
    <p class="muted"><code><?= e(abs_url('p/' . $pwa['slug'] . '/')) ?></code></p>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('admin')) ?>">&larr; Kembali</a>
</div>

<section class="cards">
  <div class="card">
    <span class="card-label">Kunjungan landing</span>
    <strong class="card-value"><?= number_short($totals['view']) ?></strong>
    <span class="card-foot"><?= number_short($sumRange['view']) ?> dalam <?= (int) $days ?> hari</span>
  </div>
  <div class="card">
    <span class="card-label">Install</span>
    <strong class="card-value"><?= number_short($totals['install']) ?></strong>
    <span class="card-foot"><?= number_short($sumRange['install']) ?> dalam <?= (int) $days ?> hari</span>
  </div>
  <div class="card">
    <span class="card-label">Dibuka dari ikon</span>
    <strong class="card-value"><?= number_short($totals['open']) ?></strong>
    <span class="card-foot"><?= number_short($sumRange['open']) ?> dalam <?= (int) $days ?> hari</span>
  </div>
  <div class="card">
    <span class="card-label">Klik dari landing</span>
    <strong class="card-value"><?= number_short($totals['click']) ?></strong>
    <span class="card-foot"><?= number_short($sumRange['click']) ?> dalam <?= (int) $days ?> hari</span>
  </div>
</section>

<div class="panel">
  <div class="panel-head">
    <h2 class="panel-title">Grafik harian</h2>
    <div class="range-tabs">
      <?php foreach ([7 => '7 hari', 30 => '30 hari', 90 => '90 hari'] as $d => $lbl): ?>
        <a class="<?= $days === $d ? 'is-active' : '' ?>" href="<?= e(url('admin/stats/' . $pwa['slug'] . '?days=' . $d)) ?>"><?= e($lbl) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="legend">
    <span><i class="sw sw-view"></i> Kunjungan</span>
    <span><i class="sw sw-traffic"></i> Dibuka (ikon + klik)</span>
    <span><i class="sw sw-install"></i> Install</span>
  </div>

  <div class="chart" style="--cols: <?= count($series) ?>">
    <?php foreach ($series as $row):
        $traffic = $row['open'] + $row['click'];
        $tip = date('d M', strtotime($row['date'])) . ' — ' . $row['view'] . ' kunjungan, '
             . $traffic . ' dibuka, ' . $row['install'] . ' install';
    ?>
      <div class="chart-col" title="<?= e($tip) ?>">
        <div class="bars">
          <span class="bar bar-view" style="height:<?= round($row['view'] / $max * 100, 2) ?>%"></span>
          <span class="bar bar-traffic" style="height:<?= round($traffic / $max * 100, 2) ?>%"></span>
          <span class="bar bar-install" style="height:<?= round($row['install'] / $max * 100, 2) ?>%"></span>
        </div>
        <span class="chart-x"><?= e(date('j/n', strtotime($row['date']))) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="muted chart-note">Nilai tertinggi pada rentang ini: <?= (int) $max ?>. Arahkan kursor ke batang untuk detail.</p>
</div>

<div class="panel">
  <h2 class="panel-title">Kelola data</h2>
  <form method="post" action="<?= e(url('admin/stats-reset')) ?>"
        onsubmit="return confirm('Reset semua statistik untuk PWA ini? Tindakan ini tidak bisa dibatalkan.');">
    <?= csrf_field() ?>
    <input type="hidden" name="slug" value="<?= e($pwa['slug']) ?>">
    <button type="submit" class="btn btn-danger">Reset statistik PWA ini</button>
    <small class="muted block">Data harian disimpan <?= (int) STATS_RETENTION_DAYS ?> hari terakhir.</small>
  </form>
</div>

<?php
$content = ob_get_clean();
$title = 'Statistik ' . $pwa['name'];
include ROOT_DIR . '/views/layout.php';
