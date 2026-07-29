<?php
/** @var int $code */
/** @var string $msg */
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= (int) $code ?> &middot; <?= e(APP_NAME) ?></title>
<style>
body{margin:0;min-height:100svh;display:flex;align-items:center;justify-content:center;padding:28px;
 font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f8fafc;color:#0f172a;text-align:center}
.box{max-width:400px}
.code{font-size:3.4rem;font-weight:800;letter-spacing:-.03em;color:#cbd5e1;line-height:1;margin-bottom:10px}
p{color:#64748b;line-height:1.6;margin:0 0 22px}
a{display:inline-block;background:#0f172a;color:#fff;text-decoration:none;border-radius:12px;
 padding:12px 24px;font-weight:600;font-size:.95rem}
@media (prefers-color-scheme: dark){body{background:#0b1120;color:#e2e8f0}.code{color:#334155}p{color:#94a3b8}a{background:#e2e8f0;color:#0f172a}}
</style>
</head>
<body>
<div class="box">
  <div class="code"><?= (int) $code ?></div>
  <p><?= $msg ?></p>
  <a href="<?= e(url('admin')) ?>">Ke Panel Admin</a>
</div>
</body>
</html>
