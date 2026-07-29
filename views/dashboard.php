<?php
/** @var array $items */
/** @var array $overview */
/** @var string $q */
/** @var int $total */
ob_start();
?>

<section class="cards">
  <div class="card">
    <span class="card-label">Total PWA</span>
    <strong class="card-value"><?= (int) $total ?></strong>
    <span class="card-foot"><?= count(array_filter(pwa_all(), function ($i) { return !empty($i['active']); })) ?> aktif</span>
  </div>
  <div class="card">
    <span class="card-label">Trafik hari ini</span>
    <strong class="card-value"><?= number_short($overview['today_traffic']) ?></strong>
    <span class="card-foot">buka aplikasi + klik link</span>
  </div>
  <div class="card">
    <span class="card-label">Total install</span>
    <strong class="card-value"><?= number_short($overview['install']) ?></strong>
    <span class="card-foot">sejak awal</span>
  </div>
  <div class="card">
    <span class="card-label">Total dibuka</span>
    <strong class="card-value"><?= number_short($overview['open'] + $overview['click']) ?></strong>
    <span class="card-foot"><?= number_short($overview['view']) ?> kunjungan landing</span>
  </div>
</section>

<section class="toolbar">
  <form method="get" action="<?= e(url('admin')) ?>" class="search">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama, slug, atau target link&hellip;">
    <?php if ($q !== ''): ?><a class="btn btn-ghost" href="<?= e(url('admin')) ?>">Reset</a><?php endif; ?>
  </form>
  <a class="btn btn-primary" href="<?= e(url('admin/new')) ?>">+ Tambah PWA</a>
</section>

<?php if (!$items): ?>
  <div class="empty">
    <p class="empty-title"><?= $q !== '' ? 'Tidak ada hasil untuk "' . e($q) . '".' : 'Belum ada PWA terdaftar.' ?></p>
    <p class="muted">Buat PWA pertama, lalu bagikan link install-nya. Target link bisa diganti kapan saja tanpa user perlu install ulang.</p>
    <a class="btn btn-primary" href="<?= e(url('admin/new')) ?>">Buat PWA pertama</a>
  </div>
<?php else: ?>
<div class="table-wrap">
<table class="table">
  <thead>
    <tr>
      <th class="col-app">Aplikasi</th>
      <th class="col-target">Target link (bisa diubah langsung)</th>
      <th class="col-stat">Statistik</th>
      <th class="col-status">Status</th>
      <th class="col-act">Aksi</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($items as $it):
      $ico = icon_url($it, 192);
      $st = stats_for($it['slug']);
      $installUrl = abs_url('p/' . $it['slug'] . '/');
  ?>
    <tr data-id="<?= e($it['id']) ?>">
      <td class="col-app">
        <div class="app-cell">
          <?php if ($ico): ?>
            <img class="app-icon" src="<?= e($ico) ?>" alt="" width="44" height="44">
          <?php else: ?>
            <span class="app-icon app-icon-fallback" style="background:<?= e($it['theme_color']) ?>"><?= e(mb_strtoupper(mb_substr($it['name'], 0, 1))) ?></span>
          <?php endif; ?>
          <div class="app-meta">
            <a class="app-name" href="<?= e(url('admin/edit/' . $it['id'])) ?>"><?= e($it['name']) ?></a>
            <span class="app-slug">/p/<?= e($it['slug']) ?>/</span>
          </div>
        </div>
      </td>

      <td class="col-target">
        <form class="quick-target" data-id="<?= e($it['id']) ?>">
          <input type="url" name="target_url" value="<?= e($it['target_url']) ?>" spellcheck="false">
          <button type="submit" class="btn btn-sm btn-primary" title="Simpan target baru">Simpan</button>
        </form>
        <span class="target-note">Diubah <?= e(date('d M Y H:i', strtotime($it['updated_at']))) ?></span>
      </td>

      <td class="col-stat">
        <div class="ministat">
          <span title="Install"><b><?= number_short($st['totals']['install']) ?></b> install</span>
          <span title="Dibuka dari ikon home screen"><b><?= number_short($st['totals']['open']) ?></b> buka</span>
          <span title="Klik dari landing page"><b><?= number_short($st['totals']['click']) ?></b> klik</span>
        </div>
        <a class="link-sm" href="<?= e(url('admin/stats/' . $it['slug'])) ?>">Lihat grafik &rarr;</a>
      </td>

      <td class="col-status">
        <button class="toggle <?= !empty($it['active']) ? 'is-on' : '' ?>" data-id="<?= e($it['id']) ?>"
                title="Klik untuk mengaktifkan / menonaktifkan">
          <span class="toggle-dot"></span>
          <span class="toggle-text"><?= !empty($it['active']) ? 'Aktif' : 'Nonaktif' ?></span>
        </button>
      </td>

      <td class="col-act">
        <div class="actions">
          <button class="btn btn-sm btn-ghost copy-btn" data-copy="<?= e($installUrl) ?>">Salin link</button>
          <a class="btn btn-sm btn-ghost" href="<?= e($installUrl) ?>" target="_blank" rel="noopener">Buka</a>
          <a class="btn btn-sm btn-ghost" href="<?= e(url('admin/embed/' . $it['slug'])) ?>" title="Kode untuk landing page di domain lain">Kode</a>
          <a class="btn btn-sm btn-ghost" href="<?= e(url('admin/edit/' . $it['id'])) ?>">Edit</a>
          <form method="post" action="<?= e(url('admin/delete')) ?>" class="inline-form"
                onsubmit="return confirm('Hapus PWA &quot;<?= e($it['name']) ?>&quot;? Ikon dan statistiknya ikut terhapus.');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($it['id']) ?>">
            <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
          </form>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = 'Daftar PWA';
include ROOT_DIR . '/views/layout.php';
