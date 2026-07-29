<?php
/** @var string $error */
$settings = settings_all();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Masuk &middot; <?= e($settings['panel_title'] ?? APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/admin.css?v=' . APP_VERSION)) ?>">
</head>
<body class="login-body">
<div class="login-card">
  <div class="login-head">
    <span class="brand-mark lg">PWA</span>
    <h1><?= e($settings['panel_title'] ?? APP_NAME) ?></h1>
    <p class="muted">Masuk untuk mengelola aplikasi dan target link.</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('admin/login')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <label class="field">
      <span>Username</span>
      <input type="text" name="username" required autofocus>
    </label>
    <label class="field">
      <span>Password</span>
      <input type="password" name="password" required>
    </label>
    <button type="submit" class="btn btn-primary btn-block">Masuk</button>
  </form>

  <?php if (!empty($settings['must_change_password'])): ?>
    <p class="login-hint">Login bawaan: <code><?= e(DEFAULT_ADMIN_USER) ?></code> / <code><?= e(DEFAULT_ADMIN_PASS) ?></code></p>
  <?php endif; ?>
</div>
</body>
</html>
