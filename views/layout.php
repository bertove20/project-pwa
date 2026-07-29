<?php
/** @var string $title */
/** @var string $content */
$settings = settings_all();
$panelTitle = $settings['panel_title'] ?? APP_NAME;
$flash = flash_get();
$path = request_path();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> &middot; <?= e($panelTitle) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/admin.css?v=' . APP_VERSION)) ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='26' font-size='26'>&#128241;</text></svg>">
</head>
<body>
<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="brand" href="<?= e(url('admin')) ?>">
      <span class="brand-mark">PWA</span>
      <span class="brand-text"><?= e($panelTitle) ?></span>
    </a>
    <nav class="topnav">
      <a href="<?= e(url('admin')) ?>" class="<?= $path === 'admin' ? 'is-active' : '' ?>">Daftar PWA</a>
      <a href="<?= e(url('admin/new')) ?>" class="<?= $path === 'admin/new' ? 'is-active' : '' ?>">Tambah</a>
      <a href="<?= e(url('admin/settings')) ?>" class="<?= $path === 'admin/settings' ? 'is-active' : '' ?>">Pengaturan</a>
      <a href="<?= e(url('admin/logout')) ?>" class="muted-link">Keluar</a>
    </nav>
  </div>
</header>

<?php if (!empty($settings['must_change_password'])): ?>
  <div class="wrap">
    <div class="alert alert-warn">
      Anda masih memakai password bawaan. <a href="<?= e(url('admin/settings')) ?>">Ganti sekarang</a> sebelum panel dipakai online.
    </div>
  </div>
<?php endif; ?>

<?php if ($flash): ?>
  <div class="wrap">
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
  </div>
<?php endif; ?>

<main class="wrap">
<?= $content ?>
</main>

<footer class="wrap foot">
  <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> &middot; data tersimpan di <code>data/pwa.json</code>
</footer>

<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>; window.BASE = <?= json_encode(url('')) ?>;</script>
<script src="<?= e(url('assets/admin.js?v=' . APP_VERSION)) ?>"></script>
</body>
</html>
