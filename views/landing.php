<?php
/** @var array $pwa */
$slug = $pwa['slug'];
$scope = url('p/' . $slug . '/');
$ico = icon_url($pwa, 512);
$theme = $pwa['theme_color'] ?: '#0f172a';
$bg = $pwa['background_color'] ?: '#ffffff';
$name = $pwa['name'];
$desc = $pwa['description'] !== '' ? $pwa['description'] : 'Pasang aplikasi ini di layar utama untuk akses sekali ketuk.';
$installLabel = $pwa['install_label'] !== '' ? $pwa['install_label'] : 'Pasang Aplikasi';
$openLabel = $pwa['open_label'] !== '' ? $pwa['open_label'] : 'Buka Aplikasi';

// Proteksi tampilan opsional (lib/guard.php): saat aktif, teks di bawah tidak
// dikirim sebagai HTML biasa - dikosongkan di sini dan diisi oleh JavaScript
// dari data tersandi, supaya view-source tidak menampilkan salinan siap-tempel.
// Lihat lib/guard.php untuk apa yang TIDAK dilindungi mode ini.
$protect = guard_active($pwa);
$gt = function ($text) use ($protect) {
    return $protect ? '' : e($text);
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($name) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="<?= e($theme) ?>">
<link rel="manifest" href="<?= e($scope . 'manifest.webmanifest') ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e($pwa['short_name']) ?>">
<?php if ($ico): ?>
<link rel="apple-touch-icon" href="<?= e($ico) ?>">
<link rel="icon" href="<?= e($ico) ?>">
<?php endif; ?>
<meta property="og:title" content="<?= e($name) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<?php if ($ico): ?><meta property="og:image" content="<?= e(abs_url(ltrim(parse_url($ico, PHP_URL_PATH), '/'))) ?>"><?php endif; ?>
<style>
:root{
  --theme: <?= e($theme) ?>;
  --bg: <?= e($bg) ?>;
}
*,*::before,*::after{box-sizing:border-box}
body{
  margin:0;min-height:100svh;
  font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
  background:var(--bg);
  color:#0f172a;
  display:flex;align-items:center;justify-content:center;
  padding:24px 20px calc(24px + env(safe-area-inset-bottom));
}
body::before{
  content:"";position:fixed;inset:0;z-index:0;
  background:radial-gradient(120% 80% at 50% 0%, color-mix(in srgb, var(--theme) 22%, transparent) 0%, transparent 70%);
  pointer-events:none;
}
.shell{position:relative;z-index:1;width:100%;max-width:420px;text-align:center}
.icon{
  width:108px;height:108px;border-radius:26px;margin:0 auto 22px;display:block;
  object-fit:cover;box-shadow:0 12px 34px rgba(15,23,42,.22);background:#fff;
}
.icon-fb{
  display:flex;align-items:center;justify-content:center;
  font-size:46px;font-weight:700;color:#fff;background:var(--theme);
}
h1{font-size:1.6rem;line-height:1.25;margin:0 0 10px;letter-spacing:-.02em}
p.desc{margin:0 0 28px;color:#475569;line-height:1.6;font-size:.98rem}
.btn{
  display:flex;align-items:center;justify-content:center;gap:8px;
  width:100%;padding:15px 20px;border:0;border-radius:14px;
  font:inherit;font-weight:650;font-size:1rem;cursor:pointer;text-decoration:none;
  transition:transform .12s ease, opacity .12s ease;
}
.btn:active{transform:scale(.98)}
.btn-install{background:var(--theme);color:#fff;box-shadow:0 8px 22px color-mix(in srgb, var(--theme) 35%, transparent)}
.btn-open{background:transparent;color:var(--theme);border:1.5px solid color-mix(in srgb, var(--theme) 35%, transparent);margin-top:12px}
.hidden{display:none !important}
.steps{
  margin-top:26px;text-align:left;background:rgba(15,23,42,.04);
  border-radius:14px;padding:16px 18px;font-size:.9rem;color:#334155;line-height:1.65;
}
.steps b{display:block;margin-bottom:6px;color:#0f172a}
.steps ol{margin:0;padding-left:20px}
.note{margin-top:20px;font-size:.8rem;color:#94a3b8}
.installed{
  margin-top:18px;padding:12px 14px;border-radius:12px;font-size:.9rem;
  background:color-mix(in srgb, var(--theme) 12%, transparent);color:var(--theme);font-weight:600;
}
@media (prefers-color-scheme: dark){
  body{background:#0b1120;color:#e2e8f0}
  p.desc{color:#94a3b8}
  .steps{background:rgba(255,255,255,.06);color:#cbd5e1}
  .steps b{color:#f1f5f9}
}
</style>
</head>
<body>
<main class="shell">
  <?php if ($ico): ?>
    <img class="icon" src="<?= e($ico) ?>" alt="<?= $gt($name) ?>" width="108" height="108">
  <?php else: ?>
    <div class="icon icon-fb"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></div>
  <?php endif; ?>

  <h1 id="app-name"><?= $gt($name) ?></h1>
  <p class="desc" id="app-desc"><?= $gt($desc) ?></p>

  <button type="button" class="btn btn-install hidden" id="btn-install"><?= $gt($installLabel) ?></button>
  <a class="btn btn-open" id="btn-open" href="<?= e($scope . 'go') ?>"><?= $gt($openLabel) ?></a>

  <div class="installed hidden" id="msg-installed"><?= $gt("Aplikasi terpasang. Membuka\u{2026}") ?></div>

  <div class="steps hidden" id="steps-ios">
    <b>Cara pasang di iPhone / iPad</b>
    <ol>
      <li>Ketuk tombol <strong>Bagikan</strong> di bilah bawah Safari.</li>
      <li>Pilih <strong>Tambahkan ke Layar Utama</strong>.</li>
      <li>Ketuk <strong>Tambah</strong> di pojok kanan atas.</li>
    </ol>
  </div>

  <div class="steps hidden" id="steps-generic">
    <b>Cara pasang</b>
    <ol>
      <li>Buka menu browser (ikon titik tiga).</li>
      <li>Pilih <strong>Instal aplikasi</strong> atau <strong>Tambahkan ke layar utama</strong>.</li>
      <li>Konfirmasi dengan menekan <strong>Instal</strong>.</li>
    </ol>
  </div>

  <p class="note">Setelah terpasang, aplikasi selalu membuka alamat terbaru &mdash; tidak perlu install ulang.</p>
</main>

<script>
(function () {
  var SCOPE = <?= json_encode($scope, JSON_UNESCAPED_SLASHES) ?>;
  var standalone = window.matchMedia('(display-mode: standalone)').matches
    || window.matchMedia('(display-mode: fullscreen)').matches
    || window.navigator.standalone === true;

  // Dibuka dari ikon home screen: langsung teruskan ke target.
  if (standalone) {
    location.replace(SCOPE + 'go?s=pwa');
    return;
  }

  var btnInstall = document.getElementById('btn-install');
  var btnOpen = document.getElementById('btn-open');
  var msgInstalled = document.getElementById('msg-installed');
  var stepsIos = document.getElementById('steps-ios');
  var stepsGeneric = document.getElementById('steps-generic');
  var deferred = null;
  var installTracked = false;
  var opening = false;

  function track(event) {
    var body = JSON.stringify({ event: event });
    if (navigator.sendBeacon) {
      navigator.sendBeacon(SCOPE + 'track', new Blob([body], { type: 'application/json' }));
    } else {
      fetch(SCOPE + 'track', { method: 'POST', body: body, headers: { 'Content-Type': 'application/json' }, keepalive: true });
    }
  }

  function markInstalled() {
    if (installTracked) return;
    installTracked = true;
    track('install');
  }

  // Begitu terpasang, tidak ada lagi yang perlu diketuk: langsung ke target.
  // Jeda singkat memberi waktu animasi install selesai dan beacon terkirim.
  function openTarget() {
    if (opening) return;
    opening = true;
    btnInstall.classList.add('hidden');
    btnOpen.classList.add('hidden');
    stepsIos.classList.add('hidden');
    stepsGeneric.classList.add('hidden');
    msgInstalled.classList.remove('hidden');
    setTimeout(function () { location.replace(SCOPE + 'go'); }, 700);
  }

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(SCOPE + 'sw.js', { scope: SCOPE }).catch(function () {});
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    btnInstall.classList.remove('hidden');
    stepsGeneric.classList.add('hidden');
  });

  btnInstall.addEventListener('click', function () {
    if (!deferred) return;
    deferred.prompt();
    deferred.userChoice.then(function (choice) {
      deferred = null;
      btnInstall.classList.add('hidden');
      if (choice.outcome === 'accepted') {
        markInstalled();
        openTarget();
      }
    });
  });

  window.addEventListener('appinstalled', function () {
    markInstalled();
    openTarget();
  });

  // Klik "Buka Aplikasi" tidak perlu dilacak di sini - endpoint /go sudah mencatatnya.

  var ua = navigator.userAgent;
  var isIos = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isSafari = /^((?!chrome|android|crios|fxios).)*safari/i.test(ua);

  if (isIos && isSafari) {
    stepsIos.classList.remove('hidden');
  } else {
    // Bila browser tidak memicu beforeinstallprompt dalam 1,5 detik, tampilkan panduan manual.
    setTimeout(function () {
      if (!deferred && btnInstall.classList.contains('hidden')) {
        stepsGeneric.classList.remove('hidden');
      }
    }, 1500);
  }
})();
</script>
<?php if ($protect): ?>
<?= guard_script($pwa) ?>
<?php endif; ?>
</body>
</html>
