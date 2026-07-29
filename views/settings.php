<?php
/** @var array $settings */
ob_start();
?>

<div class="page-head">
  <h1>Pengaturan</h1>
  <a class="btn btn-ghost" href="<?= e(url('admin')) ?>">&larr; Kembali</a>
</div>

<form method="post" action="<?= e(url('admin/settings')) ?>" class="form-grid" autocomplete="off">
  <?= csrf_field() ?>

  <div class="panel">
    <h2 class="panel-title">Panel</h2>
    <label class="field">
      <span>Judul panel</span>
      <input type="text" name="panel_title" value="<?= e($settings['panel_title'] ?? APP_NAME) ?>" maxlength="60">
      <small>Tampil di bilah atas dan halaman login.</small>
    </label>
  </div>

  <div class="panel">
    <h2 class="panel-title">Akun Admin</h2>
    <label class="field">
      <span>Username</span>
      <input type="text" name="admin_user" value="<?= e($settings['admin_user']) ?>" maxlength="32">
    </label>

    <div class="row-2">
      <label class="field">
        <span>Password baru</span>
        <input type="password" name="new_password" autocomplete="new-password" placeholder="Kosongkan bila tidak diubah">
        <small>Minimal 8 karakter.</small>
      </label>
      <label class="field">
        <span>Ulangi password baru</span>
        <input type="password" name="confirm_password" autocomplete="new-password">
      </label>
    </div>

    <label class="field">
      <span>Password saat ini</span>
      <input type="password" name="current_password" autocomplete="current-password" placeholder="Wajib diisi bila mengganti password">
    </label>
  </div>

  <div class="panel">
    <h2 class="panel-title">Informasi Sistem</h2>
    <dl class="kv">
      <dt>Base URL</dt><dd><code><?= e(rtrim(abs_url(''), '/')) ?></code></dd>
      <dt>Folder data</dt><dd><code>data/</code> (pwa.json, stats.json, settings.json)</dd>
      <dt>Folder ikon</dt><dd><code>uploads/icons/</code></dd>
      <dt>PHP</dt><dd><?= e(PHP_VERSION) ?> &middot; GD <?= function_exists('imagecreatetruecolor') ? 'aktif' : '<b>tidak aktif</b>' ?></dd>
      <dt>Pola link install</dt><dd><code><?= e(rtrim(abs_url('p/'), '/')) ?>/{slug}/</code></dd>
    </dl>
    <p class="muted">Untuk dipakai online, arahkan domain ke folder ini dan pastikan HTTPS aktif &mdash;
    PWA hanya bisa dipasang lewat HTTPS (kecuali di <code>localhost</code>).</p>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
  </div>
</form>

<?php
$content = ob_get_clean();
$title = 'Pengaturan';
include ROOT_DIR . '/views/layout.php';
