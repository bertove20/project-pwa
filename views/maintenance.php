<?php
/** @var string $slug */
/** @var array $months */
/** @var array $items */
/** @var array $sizeEvents */
/** @var array $sizeDaily */

$totalRows = 0;
foreach ($months as $m) {
    $totalRows += (int) $m['n'];
}
$namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$labelBulan = function ($ym) use ($namaBulan) {
    [$y, $m] = explode('-', $ym);
    return $namaBulan[(int) $m] . ' ' . $y;
};
$cakupan = $slug !== '' ? '"' . $slug . '"' : 'semua PWA';

ob_start();
?>

<div class="page-head">
  <div>
    <h1>Pemeliharaan Data</h1>
    <p class="muted">Log peristiwa tidak pernah dihapus otomatis. Gunakan halaman ini bila ingin
    melepas data lama untuk menghemat ruang.</p>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('admin')) ?>">&larr; Kembali</a>
</div>

<section class="cards">
  <div class="card">
    <span class="card-label">Baris log</span>
    <strong class="card-value"><?= number_short($totalRows) ?></strong>
    <span class="card-foot"><?= count($months) ?> bulan pada <?= e($cakupan) ?></span>
  </div>
  <div class="card">
    <span class="card-label">Ukuran tabel events</span>
    <strong class="card-value"><?= e(db_format_size($sizeEvents['bytes'])) ?></strong>
    <span class="card-foot">termasuk indeks</span>
  </div>
  <div class="card">
    <span class="card-label">Ukuran agregat harian</span>
    <strong class="card-value"><?= e(db_format_size($sizeDaily['bytes'])) ?></strong>
    <span class="card-foot">ringkasan untuk dashboard</span>
  </div>
  <div class="card">
    <span class="card-label">Database</span>
    <strong class="card-value" style="font-size:1.15rem"><?= e(DB_NAME) ?></strong>
    <span class="card-foot"><?= e(DB_HOST) ?>:<?= e(DB_PORT) ?></span>
  </div>
</section>

<div class="panel">
  <div class="panel-head">
    <h2 class="panel-title">Cakupan</h2>
  </div>
  <div class="range-tabs">
    <a class="<?= $slug === '' ? 'is-active' : '' ?>" href="<?= e(url('admin/maintenance')) ?>">Semua PWA</a>
    <?php foreach ($items as $it): ?>
      <a class="<?= $slug === $it['slug'] ? 'is-active' : '' ?>"
         href="<?= e(url('admin/maintenance?slug=' . urlencode($it['slug']))) ?>"><?= e($it['name']) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <h2 class="panel-title">Data per bulan &mdash; <?= e($cakupan) ?></h2>

  <?php if (!$months): ?>
    <p class="muted">Belum ada log peristiwa pada cakupan ini.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Bulan</th><th>Jumlah baris</th><th>Peristiwa pertama</th><th>Peristiwa terakhir</th><th>Porsi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($months as $m):
            $pct = $totalRows > 0 ? round($m['n'] / $totalRows * 100, 1) : 0;
        ?>
          <tr>
            <td><strong><?= e($labelBulan($m['ym'])) ?></strong><span class="sub mono"><?= e($m['ym']) ?></span></td>
            <td class="mono"><?= number_format($m['n'], 0, ',', '.') ?></td>
            <td class="mono nowrap"><?= e(date('d M Y H:i', strtotime($m['awal']))) ?></td>
            <td class="mono nowrap"><?= e(date('d M Y H:i', strtotime($m['akhir']))) ?></td>
            <td style="min-width:140px">
              <span class="rank-bar" style="display:block"><i style="width:<?= max(1.5, $pct) ?>%"></i></span>
              <span class="sub"><?= $pct ?>%</span>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($months): ?>
<div class="panel panel-danger">
  <h2 class="panel-title">Hapus log berdasarkan rentang bulan</h2>
  <p class="explain">
    Pilih bulan awal dan bulan akhir. Seluruh baris log pada rentang itu akan dihapus permanen
    dari <?= e($cakupan) ?>. Penghapusan dijalankan bertahap per <?= number_format(EVENT_DELETE_CHUNK, 0, ',', '.') ?>
    baris agar tabel besar tidak terkunci lama.
  </p>

  <form method="post" action="<?= e(url('admin/maintenance')) ?>" id="form-hapus">
    <?= csrf_field() ?>
    <input type="hidden" name="slug" value="<?= e($slug) ?>">

    <div class="row-2">
      <label class="field">
        <span>Dari bulan</span>
        <select name="from_ym" id="from-ym">
          <?php foreach (array_reverse($months) as $m): ?>
            <option value="<?= e($m['ym']) ?>"><?= e($labelBulan($m['ym'])) ?> &mdash; <?= number_format($m['n'], 0, ',', '.') ?> baris</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">
        <span>Sampai bulan</span>
        <select name="to_ym" id="to-ym">
          <?php foreach (array_reverse($months) as $m): ?>
            <option value="<?= e($m['ym']) ?>"><?= e($labelBulan($m['ym'])) ?> &mdash; <?= number_format($m['n'], 0, ',', '.') ?> baris</option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <label class="check">
      <input type="checkbox" name="keep_daily" value="1" checked>
      <span><strong>Pertahankan ringkasan harian.</strong> Rincian per kejadian hilang, tapi grafik
      jumlah harian dan total sepanjang masa tetap utuh &mdash; ini yang biasanya Anda inginkan.
      Hilangkan centang bila ingin angkanya benar-benar bersih.</span>
    </label>

    <div class="danger-box">
      <p class="explain" id="ringkasan-hapus">&nbsp;</p>
      <label class="field">
        <span>Ketik konfirmasi untuk melanjutkan</span>
        <input type="text" name="confirm" id="konfirmasi" autocomplete="off" spellcheck="false"
               placeholder="ketik teks yang ditampilkan di atas">
        <small>Pengaman agar penghapusan tidak terjadi karena salah klik.</small>
      </label>
      <button type="submit" class="btn btn-danger">Hapus log pada rentang ini</button>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <h2 class="panel-title">Catatan</h2>
  <ul class="steps-list">
    <li>Ikon PWA tersimpan sebagai berkas di <code>uploads/icons/</code>, bukan di database,
        dan tidak terpengaruh penghapusan di halaman ini.</li>
    <li>Menghapus sebuah PWA dari daftar sudah otomatis membuang seluruh log dan statistiknya.</li>
    <li>Untuk cadangan sebelum menghapus, ekspor CSV dari
        <a href="<?= e(url('admin')) ?>">halaman analitik</a> masing-masing PWA, atau backup
        database <code><?= e(DB_NAME) ?></code> lewat mysqldump.</li>
    <li>Ruang disk yang dibebaskan InnoDB tidak langsung kembali ke sistem operasi. Jalankan
        <code>OPTIMIZE TABLE events;</code> bila ingin berkasnya benar-benar mengecil.</li>
  </ul>
</div>

<script>
(function () {
  var from = document.getElementById('from-ym');
  var to = document.getElementById('to-ym');
  var ringkas = document.getElementById('ringkasan-hapus');
  var konfirmasi = document.getElementById('konfirmasi');
  if (!from || !to) return;

  function perbarui() {
    var a = from.value, b = to.value;
    if (a > b) { var t = a; a = b; b = t; }
    var teks = a + ' ' + b;
    ringkas.innerHTML = 'Akan menghapus log dari <strong>' + a + '</strong> sampai <strong>' + b +
      '</strong>. Ketik <code>' + teks + '</code> pada kolom di bawah untuk mengonfirmasi.';
    konfirmasi.placeholder = teks;
  }

  from.addEventListener('change', perbarui);
  to.addEventListener('change', perbarui);
  perbarui();
})();
</script>

<?php
$content = ob_get_clean();
$title = 'Pemeliharaan Data';
include ROOT_DIR . '/views/layout.php';
