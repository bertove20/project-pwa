<?php
/** @var array $pwa */
$theme = $pwa['theme_color'] ?: '#0f172a';
$bg = $pwa['background_color'] ?: '#ffffff';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tidak ada koneksi &middot; <?= e($pwa['name']) ?></title>
<meta name="theme-color" content="<?= e($theme) ?>">
<style>
body{margin:0;min-height:100svh;display:flex;align-items:center;justify-content:center;padding:28px;
 font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:<?= e($bg) ?>;color:#0f172a;text-align:center}
.box{max-width:340px}
.dot{width:64px;height:64px;border-radius:50%;margin:0 auto 20px;background:<?= e($theme) ?>;opacity:.15}
h1{font-size:1.25rem;margin:0 0 8px}
p{color:#64748b;line-height:1.6;margin:0 0 22px;font-size:.95rem}
button{background:<?= e($theme) ?>;color:#fff;border:0;border-radius:12px;padding:13px 26px;
 font:inherit;font-weight:600;cursor:pointer}
@media (prefers-color-scheme: dark){body{background:#0b1120;color:#e2e8f0}p{color:#94a3b8}}
</style>
</head>
<body>
<div class="box">
  <div class="dot"></div>
  <h1>Tidak ada koneksi internet</h1>
  <p><?= e($pwa['name']) ?> butuh koneksi untuk memuat halaman. Periksa jaringan Anda lalu coba lagi.</p>
  <button type="button" onclick="location.replace(<?= json_encode(url('p/' . $pwa['slug'] . '/go?s=pwa'), JSON_UNESCAPED_SLASHES) ?>)">Coba Lagi</button>
</div>
</body>
</html>
