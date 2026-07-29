<?php
/** @var array $pwa */
$theme = $pwa['theme_color'] ?: '#0f172a';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Sementara tidak tersedia</title>
<style>
body{margin:0;min-height:100svh;display:flex;align-items:center;justify-content:center;padding:28px;
 font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f8fafc;color:#0f172a;text-align:center}
.box{max-width:340px}
.dot{width:64px;height:64px;border-radius:50%;margin:0 auto 20px;background:<?= e($theme) ?>;opacity:.15}
h1{font-size:1.25rem;margin:0 0 8px}
p{color:#64748b;line-height:1.6;margin:0;font-size:.95rem}
@media (prefers-color-scheme: dark){body{background:#0b1120;color:#e2e8f0}p{color:#94a3b8}}
</style>
</head>
<body>
<div class="box">
  <div class="dot"></div>
  <h1>Sedang dalam pemeliharaan</h1>
  <p><?= e($pwa['name']) ?> untuk sementara tidak tersedia. Silakan coba beberapa saat lagi.</p>
</div>
</body>
</html>
