<?php
/** @var array|null $pwa */
$old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_old']);

$val = function ($key, $default = '') use ($pwa, $old) {
    if (isset($old[$key])) {
        return $old[$key];
    }
    if ($pwa && isset($pwa[$key])) {
        return $pwa[$key];
    }
    return $default;
};
$isEdit = (bool) $pwa;
$ico = $pwa ? icon_url($pwa, 192) : null;
ob_start();
?>

<div class="page-head">
  <h1><?= $isEdit ? 'Edit PWA' : 'Tambah PWA Baru' ?></h1>
  <a class="btn btn-ghost" href="<?= e(url('admin')) ?>">&larr; Kembali</a>
</div>

<form method="post" action="<?= e(url('admin/save')) ?>" enctype="multipart/form-data" class="form-grid">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= e($val('id')) ?>">

  <div class="panel">
    <h2 class="panel-title">Identitas Aplikasi</h2>

    <label class="field">
      <span>Nama aplikasi <em>*</em></span>
      <input type="text" name="name" id="f-name" value="<?= e($val('name')) ?>" required maxlength="80"
             placeholder="Contoh: Toko Berkah">
      <small>Muncul sebagai judul saat proses install.</small>
    </label>

    <label class="field">
      <span>Nama pendek</span>
      <input type="text" name="short_name" value="<?= e($val('short_name')) ?>" maxlength="12"
             placeholder="Maks. 12 karakter">
      <small>Label di bawah ikon home screen. Kosongkan untuk memakai nama aplikasi.</small>
    </label>

    <label class="field">
      <span>Slug (alamat PWA) <em>*</em></span>
      <div class="input-prefix">
        <span class="prefix"><?= e(rtrim(abs_url('p/'), '/')) ?>/</span>
        <input type="text" name="slug" id="f-slug" value="<?= e($val('slug')) ?>" maxlength="60"
               pattern="[a-z0-9][a-z0-9\-]*[a-z0-9]" placeholder="toko-berkah">
        <span class="suffix">/</span>
      </div>
      <small<?= $isEdit ? ' class="warn-text"' : '' ?>>
        <?= $isEdit
            ? 'Hati-hati: mengubah slug memutus PWA yang sudah terpasang di HP pengguna. Ikon dan statistik ikut dipindahkan.'
            : 'Otomatis dibuat dari nama aplikasi. Hanya huruf kecil, angka, dan tanda hubung.' ?>
      </small>
    </label>

    <label class="field">
      <span>Deskripsi</span>
      <textarea name="description" rows="2" maxlength="300" placeholder="Keterangan singkat aplikasi"><?= e($val('description')) ?></textarea>
    </label>
  </div>

  <div class="panel">
    <h2 class="panel-title">Teks Tombol</h2>
    <div class="row-2">
      <label class="field">
        <span>Teks tombol &ldquo;Pasang Aplikasi&rdquo;</span>
        <input type="text" name="install_label" id="f-install-label" value="<?= e($val('install_label')) ?>"
               maxlength="40" placeholder="Pasang Aplikasi">
        <small>Tampil saat browser menawarkan instalasi. Kosongkan untuk teks bawaan.</small>
      </label>
      <label class="field">
        <span>Teks tombol &ldquo;Buka Aplikasi&rdquo;</span>
        <input type="text" name="open_label" id="f-open-label" value="<?= e($val('open_label')) ?>"
               maxlength="40" placeholder="Buka Aplikasi">
        <small>Tombol yang mengarah ke target link (atur di panel Target Link).</small>
      </label>
    </div>
    <div class="btn-preview">
      <span class="btn-preview-demo btn-preview-install" id="preview-install">Pasang Aplikasi</span>
      <span class="btn-preview-demo btn-preview-open" id="preview-open">Buka Aplikasi</span>
    </div>
  </div>

  <div class="panel panel-accent">
    <h2 class="panel-title">Target Link</h2>
    <label class="field">
      <span>URL tujuan aplikasi <em>*</em></span>
      <input type="url" name="target_url" value="<?= e($val('target_url')) ?>" required
             placeholder="https://situs-tujuan.com" spellcheck="false">
      <small>Dipakai saat aplikasi dibuka dari <strong>ikon di layar utama</strong>.
      Ganti kapan saja &mdash; semua HP yang sudah install langsung mengikuti tanpa perlu install ulang.</small>
    </label>

    <label class="field">
      <span>URL tombol &ldquo;Buka Aplikasi&rdquo;</span>
      <input type="url" name="web_target_url" value="<?= e($val('web_target_url')) ?>"
             placeholder="Kosongkan untuk memakai URL tujuan aplikasi" spellcheck="false">
      <small>Halaman install bersifat publik, dan tombol di sana bisa diklik siapa saja.
      Isi kolom ini bila Anda tidak ingin pengunjung biasa sampai ke URL tujuan aplikasi
      &mdash; tombol akan mengarah ke alamat ini, sedangkan ikon di layar utama tetap
      membuka URL di atas.</small>
    </label>

    <label class="check">
      <input type="checkbox" name="active" value="1" <?= $val('active', true) ? 'checked' : '' ?>>
      <span>Aktif &mdash; matikan untuk menghentikan sementara redirect tanpa menghapus PWA.</span>
    </label>
  </div>

  <div class="panel">
    <h2 class="panel-title">Proteksi Tampilan Halaman Install</h2>
    <label class="check">
      <input type="checkbox" name="protect" value="1" <?= $val('protect') ? 'checked' : '' ?>>
      <span>Aktifkan proteksi &mdash; persulit peniru menyalin halaman ini.</span>
    </label>
    <small class="block" style="margin-top:10px">
      Saat aktif: teks di halaman (nama, deskripsi, teks tombol) tidak dikirim sebagai HTML biasa,
      klik kanan dan pintasan F12/Ctrl+Shift+I/Ctrl+U dicegat, dan bila DevTools terdeteksi terbuka
      halaman diganti tampilan mirip layar &ldquo;Aw, Snap!&rdquo; milik Chrome.
    </small>
    <small class="block warn-text" style="margin-top:8px">
      Ini penyamaran, bukan proteksi mutlak. <code>manifest.webmanifest</code> dan
      <code>config.json</code> milik PWA ini tetap mengembalikan nama dan deskripsi apa adanya
      (dipakai fitur instalasi &amp; sinkronisasi landing eksternal). Deteksi DevTools bisa dilewati,
      dan halaman akan mengindeks lebih sedikit di mesin pencari. Cocok untuk mempersulit peniru
      awam, bukan untuk data yang benar-benar rahasia.
    </small>
  </div>

  <div class="panel">
    <h2 class="panel-title">Ikon</h2>
    <div class="icon-row">
      <div class="icon-preview" id="icon-preview">
        <?php if ($ico): ?>
          <img src="<?= e($ico) ?>" alt="Ikon saat ini" width="96" height="96">
        <?php else: ?>
          <span class="icon-empty">Belum ada<br>ikon</span>
        <?php endif; ?>
      </div>
      <div class="icon-fields">
        <label class="field">
          <span>Unggah ikon</span>
          <input type="file" name="icon" id="f-icon" accept="image/png,image/jpeg,image/webp,image/svg+xml">
          <small>PNG / JPG / WEBP / SVG, maksimal 4 MB. Disarankan bujur sangkar minimal 512&times;512 px.
          Panel otomatis membuat ukuran 192, 512, dan versi maskable.</small>
        </label>
        <?php if ($ico): ?>
          <label class="check">
            <input type="checkbox" name="remove_icon" value="1">
            <span>Hapus ikon saat ini</span>
          </label>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2 class="panel-title">Tampilan</h2>
    <div class="row-2">
      <label class="field">
        <span>Warna tema</span>
        <div class="color-input">
          <input type="color" value="<?= e($val('theme_color', '#0f172a')) ?>" data-sync="#f-theme">
          <input type="text" name="theme_color" id="f-theme" value="<?= e($val('theme_color', '#0f172a')) ?>" pattern="#[0-9a-fA-F]{6}">
        </div>
        <small>Warna status bar aplikasi.</small>
      </label>
      <label class="field">
        <span>Warna latar</span>
        <div class="color-input">
          <input type="color" value="<?= e($val('background_color', '#ffffff')) ?>" data-sync="#f-bg">
          <input type="text" name="background_color" id="f-bg" value="<?= e($val('background_color', '#ffffff')) ?>" pattern="#[0-9a-fA-F]{6}">
        </div>
        <small>Latar splash screen saat aplikasi dibuka.</small>
      </label>
    </div>
    <div class="row-2">
      <label class="field">
        <span>Mode tampilan</span>
        <select name="display">
          <?php foreach (['standalone' => 'Standalone (seperti aplikasi)', 'fullscreen' => 'Fullscreen (tanpa status bar)', 'minimal-ui' => 'Minimal UI (ada tombol navigasi)'] as $k => $lbl): ?>
            <option value="<?= e($k) ?>" <?= $val('display', 'standalone') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">
        <span>Orientasi</span>
        <select name="orientation">
          <?php foreach (['any' => 'Bebas', 'portrait' => 'Potrait', 'landscape' => 'Landscape'] as $k => $lbl): ?>
            <option value="<?= e($k) ?>" <?= $val('orientation', 'any') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Buat PWA' ?></button>
    <a class="btn btn-ghost" href="<?= e(url('admin')) ?>">Batal</a>
    <?php if ($isEdit): ?>
      <a class="btn btn-ghost" href="<?= e(abs_url('p/' . $pwa['slug'] . '/')) ?>" target="_blank" rel="noopener">Lihat halaman install</a>
      <a class="btn btn-ghost" href="<?= e(url('admin/embed/' . $pwa['slug'])) ?>">Kode landing eksternal</a>
    <?php endif; ?>
  </div>
</form>

<?php
$content = ob_get_clean();
$title = $isEdit ? 'Edit ' . $pwa['name'] : 'Tambah PWA';
include ROOT_DIR . '/views/layout.php';
