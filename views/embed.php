<?php
/** @var array $pwa */
/** @var string $mode */
/** @var array $files */

$slug = $pwa['slug'];
$hasIcon = icon_url($pwa) !== null;
$entry = embed_entry_file($mode);
$base = url('admin/embed/' . $slug);

$notes = [
    'config.php' => '<strong>Satu-satunya berkas yang perlu disunting</strong> bila panel pindah domain. Berisi alamat panel dan lama cache.',
    'sync.php' => 'Menarik data terbaru dari panel dan menyimpannya sebentar di cache lokal. Bila panel sedang tak terjangkau, cache lama yang dipakai; bila cache belum ada, nilai cadangan di <code>config.php</code>.',
    'manifest.php' => 'Manifest disusun saat diminta memakai data terbaru dari panel &mdash; jadi nama, warna, dan ikon ikut berubah tanpa upload ulang. <code>start_url</code> dan <code>scope</code> relatif supaya tetap satu origin.',
    'manifest.webmanifest' => 'Identitas aplikasi. Pada hosting statis nilainya <strong>beku</strong> &mdash; mengubah nama atau warna di panel menuntut berkas ini diupload ulang.',
    'go.php' => 'Titik masuk yang ikut terpasang di HP pengguna. Hanya meneruskan ke panel, jadi target link tetap dikendalikan dari sini.',
    'go.html' => 'Versi untuk hosting tanpa PHP. Fungsinya sama: meneruskan ke panel supaya target link tetap dikendalikan dari sini.',
    'sw.js' => 'Syarat wajib agar tombol install muncul di Chrome. Navigasi selalu lewat jaringan, jadi data terbaru langsung terpakai.',
    'index.php' => 'Halaman promosi install. Nama, deskripsi, warna, dan ikon ditarik dari panel. Desain bebas diganti &mdash; yang wajib dipertahankan hanya tag <code>&lt;link rel="manifest"&gt;</code> dan blok <code>&lt;script&gt;</code> di bawahnya.',
    'index.html' => 'Halaman promosi install. Tampilannya disegarkan dari panel lewat JavaScript saat halaman dibuka. Desain bebas diganti &mdash; yang wajib dipertahankan hanya tag <code>&lt;link rel="manifest"&gt;</code> dan blok <code>&lt;script&gt;</code> di bawahnya.',
    'offline.php' => 'Ditampilkan service worker saat pengguna kehilangan koneksi. Ikut memakai nama dan warna terbaru dari panel.',
    'offline.html' => 'Ditampilkan service worker saat pengguna kehilangan koneksi.',
    '.htaccess' => 'Opsional, khusus server Apache. Berguna bila manifest tidak terbaca karena MIME type.',
];

// Apa saja yang ikut berubah otomatis saat data diubah di panel.
$sync = $mode === 'static'
    ? [
        ['Target link', true, 'Dibaca panel saat redirect terjadi &mdash; berlaku seketika.'],
        ['Aktif / nonaktif', true, 'Berlaku seketika.'],
        ['Gambar ikon', true, 'File ikon ditarik dari panel.'],
        ['Nama & deskripsi di halaman', true, 'Disegarkan lewat JavaScript saat halaman dibuka.'],
        ['Warna tema & latar halaman', true, 'Disegarkan lewat JavaScript saat halaman dibuka.'],
        ['Nama & ikon aplikasi yang <em>sudah terpasang</em>', false, 'Terkunci di <code>manifest.webmanifest</code>. Hosting statis tidak bisa menyusun manifest saat diminta, jadi berkas ini harus diupload ulang.'],
        ['Mode tampilan & orientasi', false, 'Ikut terkunci di manifest. Perlu upload ulang.'],
    ]
    : [
        ['Target link', true, 'Dibaca panel saat redirect terjadi &mdash; berlaku seketika.'],
        ['Aktif / nonaktif', true, 'Berlaku seketika.'],
        ['Gambar ikon', true, 'Ikut manifest yang disusun ulang tiap kali diminta.'],
        ['Nama & deskripsi', true, 'Berlaku setelah cache habis (bawaan 5 menit).'],
        ['Warna tema & latar', true, 'Berlaku setelah cache habis (bawaan 5 menit).'],
        ['Mode tampilan & orientasi', true, 'Berlaku setelah cache habis (bawaan 5 menit).'],
        ['Slug PWA', false, 'Mengubah slug memutus semua PWA yang sudah terpasang. Jangan diubah setelah disebarkan.'],
    ];

ob_start();
?>

<div class="page-head">
  <div>
    <h1>Kode Landing Page Eksternal</h1>
    <p class="muted">Untuk memasang halaman install <strong><?= e($pwa['name']) ?></strong> di domain lain, di luar panel ini.</p>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('admin')) ?>">&larr; Kembali</a>
</div>

<div class="alert alert-warn">
  Kode di bawah menunjuk ke panel di <code><?= e(rtrim(abs_url(''), '/')) ?></code>, sesuai alamat yang
  Anda pakai membuka halaman ini. Bila panel nanti dipindah ke domain produksi, buka panel lewat domain
  itu dan salin ulang kodenya &mdash; kalau tidak, landing eksternal akan menunjuk alamat yang salah.
</div>

<?php if (!$hasIcon): ?>
  <div class="alert alert-warn">
    PWA ini belum punya ikon, sehingga Chrome tidak akan menawarkan tombol install.
    <a href="<?= e(url('admin/edit/' . $pwa['id'])) ?>">Unggah ikon dulu</a>, lalu kembali ke halaman ini.
  </div>
<?php endif; ?>

<div class="panel panel-accent">
  <h2 class="panel-title">Cara kerjanya</h2>
  <p class="explain">
    Manifest, service worker, dan <code>start_url</code> <strong>wajib satu origin</strong> dengan halaman yang
    memasang PWA. Jadi landing page di domain lain tidak bisa sekadar menunjuk manifest panel &mdash; ia butuh
    berkasnya sendiri, ditambah satu file penerus kecil.
  </p>
  <pre class="flow">HP ketuk ikon aplikasi
      |
      v
situs-anda.com/app/<?= e($entry) ?>          <- permanen, ikut terpasang di HP
      |
      v
<?= e(rtrim(abs_url(''), '/')) ?>/p/<?= e($slug) ?>/go?s=pwa   <- panel baca target terbaru
      |
      v
<?= e($pwa['target_url']) ?>   <- bisa diganti kapan saja dari panel</pre>
  <p class="explain muted">
    Statistik tetap tercatat di panel karena semua trafik lewat endpoint di atas.
  </p>
</div>

<div class="panel">
  <div class="panel-head">
    <h2 class="panel-title">Yang ikut berubah saat data diubah di panel</h2>
    <div class="range-tabs">
      <a class="<?= $mode === 'php' ? 'is-active' : '' ?>" href="<?= e($base . '?mode=php') ?>">Hosting dengan PHP</a>
      <a class="<?= $mode === 'static' ? 'is-active' : '' ?>" href="<?= e($base . '?mode=static') ?>">Hosting statis</a>
    </div>
  </div>

  <table class="sync-table">
    <tbody>
    <?php foreach ($sync as $row): ?>
      <tr>
        <td class="sync-mark"><span class="<?= $row[1] ? 'yes' : 'no' ?>"><?= $row[1] ? '&#10003;' : '&times;' ?></span></td>
        <td class="sync-what"><?= $row[0] ?></td>
        <td class="sync-how"><?= $row[2] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($mode === 'static'): ?>
    <p class="explain muted" style="margin-top:14px">
      Hosting statis tidak bisa menyusun manifest saat diminta, sehingga identitas aplikasi yang sudah
      terpasang di HP pengguna terkunci pada berkas yang diupload. Bila Anda ingin semuanya benar-benar
      tersinkron, pakai <a href="<?= e($base . '?mode=php') ?>">mode hosting dengan PHP</a>.
    </p>
  <?php else: ?>
    <p class="explain muted" style="margin-top:14px">
      Lama cache diatur lewat <code>SYNC_TTL</code> di <code>config.php</code> (bawaan 300 detik).
      Perkecil bila ingin perubahan lebih cepat terlihat, perbesar bila ingin panel lebih jarang dihubungi.
      Target link tidak melewati cache ini &mdash; selalu dibaca saat redirect.
    </p>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel-head">
    <h2 class="panel-title">Pilih jenis hosting</h2>
  </div>
  <div class="range-tabs">
    <a class="<?= $mode === 'php' ? 'is-active' : '' ?>" href="<?= e($base . '?mode=php') ?>">Hosting dengan PHP</a>
    <a class="<?= $mode === 'static' ? 'is-active' : '' ?>" href="<?= e($base . '?mode=static') ?>">Hosting statis (tanpa PHP)</a>
  </div>
  <p class="explain muted">
    <strong>Mode PHP sangat disarankan</strong> &mdash; hanya mode ini yang bisa menyusun manifest saat diminta,
    sehingga semua perubahan di panel benar-benar ikut terbawa. Mode statis untuk Netlify, GitHub Pages,
    atau Cloudflare Pages, dengan batasan pada tabel di atas.
  </p>
  <div class="form-actions">
    <a class="btn btn-primary" href="<?= e($base . '?mode=' . $mode . '&download=zip') ?>">Unduh semua sebagai .zip</a>
    <span class="muted" style="align-self:center;font-size:.85rem">
      Berisi <?= count($files) ?> berkas, tinggal upload ke satu folder di situs Anda.
    </span>
  </div>
</div>

<?php $n = 0; foreach ($files as $filename => $content): $n++; ?>
  <div class="panel file-panel">
    <div class="file-head">
      <div class="file-title">
        <span class="file-num"><?= $n ?></span>
        <code class="file-name"><?= e($filename) ?></code>
        <span class="file-lang"><?= e(embed_lang($filename)) ?></span>
      </div>
      <button type="button" class="btn btn-sm btn-ghost copy-code">Salin</button>
    </div>
    <?php if (isset($notes[$filename])): ?>
      <p class="file-note"><?= $notes[$filename] ?></p>
    <?php endif; ?>
    <pre class="code"><code><?= e($content) ?></code></pre>
  </div>
<?php endforeach; ?>

<div class="panel">
  <h2 class="panel-title">Langkah pemasangan</h2>
  <ol class="steps-list">
    <li>Buat satu folder di situs Anda, misalnya <code>/app/</code>. Satu folder untuk satu PWA &mdash;
        jangan campur dua PWA dalam folder yang sama karena <code>scope</code>-nya akan bentrok.</li>
    <li>Upload <?= count($files) ?> berkas di atas ke folder tersebut.</li>
    <li>Buka <code>https://situs-anda.com/app/</code> lewat HP.
        <strong>Wajib HTTPS</strong> &mdash; tanpa itu tombol install tidak akan pernah muncul.</li>
    <li>Pasang aplikasinya, lalu ketuk ikonnya di layar utama untuk memastikan sampai ke target yang benar.</li>
    <li>Cek <a href="<?= e(url('admin/stats/' . $slug)) ?>">halaman statistik</a> &mdash; kunjungan dan install
        dari domain eksternal ikut tercatat di sana.</li>
  </ol>

  <h2 class="panel-title" style="margin-top:24px">Yang perlu diperhatikan</h2>
  <ul class="steps-list">
    <li><strong>Jangan ganti nama <code><?= e($entry) ?></code></strong> setelah disebarkan. Alamat itu sudah
        ikut terpasang di HP pengguna; menggantinya membuat aplikasi mereka mati.</li>
    <li><strong>Jangan pindahkan foldernya.</strong> Alasannya sama &mdash; <code>scope</code> PWA terkunci pada path tersebut.</li>
    <li><code><?= e(embed_landing_file($mode)) ?></code> bebas Anda desain ulang. Yang wajib bertahan hanya tag manifest dan blok script install.</li>
    <li>Untuk mengganti target link, cukup ubah dari panel ini. Berkas di situs eksternal tidak perlu disentuh lagi.</li>
    <?php if ($mode !== 'static'): ?>
      <li>Pastikan folder tersebut <strong>bisa ditulis</strong> agar <code>.pwa-sync.json</code> terbentuk.
          Bila tidak bisa, semuanya tetap jalan &mdash; hanya saja panel dihubungi pada setiap kunjungan.</li>
    <?php endif; ?>
    <li>Bila slug PWA ini diubah di panel, <code><?= e($entry) ?></code> di situs eksternal harus diperbarui juga
        karena alamat tujuannya ikut berubah.</li>
  </ul>
</div>

<?php
$content = ob_get_clean();
$title = 'Kode Eksternal ' . $pwa['name'];
include ROOT_DIR . '/views/layout.php';
