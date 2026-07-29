<?php

/**
 * Generator berkas PWA untuk dipasang di situs LAIN (origin berbeda).
 *
 * Manifest, service worker, dan start_url wajib satu origin dengan halaman
 * yang memasang PWA. Jadi situs eksternal butuh berkasnya sendiri, ditambah
 * satu file penerus (go.php / go.html) yang mengarah ke endpoint panel:
 *
 *   HP  ->  situs-anda.com/app/go.php  ->  panel/p/{slug}/go?s=pwa  ->  target
 *           (permanen, ikut ter-install)   (baca target terbaru)
 *
 * Supaya perubahan di panel ikut terbawa, berkas yang dihasilkan tidak
 * menanam data secara mati melainkan menarik /p/{slug}/config.json:
 *
 *   Mode PHP    - manifest.php dan index.php membaca config.json dari sisi
 *                 server (dengan cache lokal), jadi nama, deskripsi, warna,
 *                 dan ikon ikut berubah tanpa upload ulang.
 *   Mode statis - hosting tanpa PHP tidak bisa menyusun manifest saat diminta,
 *                 sehingga identitas aplikasi beku di manifest.webmanifest.
 *                 Tampilan landing tetap disegarkan lewat JavaScript.
 */

function embed_config_url(array $pwa)
{
    return abs_url('p/' . $pwa['slug'] . '/config.json');
}

/** Endpoint panel yang menjadi tujuan akhir file penerus. */
function embed_go_url(array $pwa, $src = 'pwa')
{
    return abs_url('p/' . $pwa['slug'] . '/go') . '?s=' . $src;
}

/** Pixel GET untuk mencatat statistik dari origin lain (tanpa CORS preflight). */
function embed_pixel_url(array $pwa)
{
    return abs_url('p/' . $pwa['slug'] . '/track.gif');
}

function embed_entry_file($mode)
{
    return $mode === 'static' ? 'go.html' : 'go.php';
}

/** Halaman landing yang dibuka pengunjung. */
function embed_landing_file($mode)
{
    return $mode === 'static' ? 'index.html' : 'index.php';
}

/**
 * @param string $mode 'php' untuk hosting dengan PHP, 'static' untuk hosting statis
 * @return array<string,string> nama berkas => isinya
 */
function embed_files(array $pwa, $mode = 'php')
{
    if ($mode === 'static') {
        return [
            'manifest.webmanifest' => embed_manifest_static($pwa),
            'go.html' => embed_go_html($pwa),
            'index.html' => embed_index_static($pwa),
            'offline.html' => embed_offline_static($pwa),
            'sw.js' => embed_sw($pwa, 'static'),
            '.htaccess' => embed_htaccess($mode),
        ];
    }

    return [
        'config.php' => embed_config_php($pwa),
        'sync.php' => embed_sync_php(),
        'manifest.php' => embed_manifest_php(),
        'go.php' => embed_go_php($pwa),
        'index.php' => embed_index_php($pwa),
        'offline.php' => embed_offline_php(),
        'sw.js' => embed_sw($pwa, 'php'),
        '.htaccess' => embed_htaccess($mode),
    ];
}

/** Nilai cadangan bila panel tak bisa dihubungi dan cache lokal belum ada. */
function embed_fallback_array(array $pwa)
{
    return [
        'slug' => $pwa['slug'],
        'name' => $pwa['name'],
        'short_name' => $pwa['short_name'] !== '' ? $pwa['short_name'] : $pwa['name'],
        'description' => $pwa['description'] ?? '',
        'display' => $pwa['display'] ?? 'standalone',
        'orientation' => $pwa['orientation'] ?? 'any',
        'theme_color' => $pwa['theme_color'] ?? '#0f172a',
        'background_color' => $pwa['background_color'] ?? '#ffffff',
        'active' => true,
        'icons' => icon_manifest_entries($pwa),
        'icon' => icon_abs_url($pwa, 512),
    ];
}

/** Ubah array PHP menjadi kode sumber yang rapi dibaca manusia. */
function embed_var_export($value, $indent = 0)
{
    $pad = str_repeat(' ', $indent);
    if (is_array($value)) {
        $isList = array_keys($value) === range(0, count($value) - 1);
        $out = "[\n";
        foreach ($value as $k => $v) {
            $out .= $pad . '    ';
            if (!$isList) {
                $out .= "'" . addcslashes((string) $k, "'\\") . "' => ";
            }
            $out .= embed_var_export($v, $indent + 4) . ",\n";
        }
        return $out . $pad . ']';
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    return "'" . addcslashes((string) $value, "'\\") . "'";
}

/* ------------------------------------------------------- Berkas mode PHP */

function embed_config_php(array $pwa)
{
    $tpl = <<<'PHP'
<?php
/**
 * Pengaturan sambungan ke panel PWA Manager.
 * Hanya berkas ini yang perlu disunting bila panel pindah domain.
 */

// Sumber data: nama, deskripsi, warna, dan ikon ditarik dari sini.
define('PANEL_CONFIG_URL', '{{CONFIG_URL}}');

// Endpoint redirect. Target link dibaca panel saat diminta, jadi selalu terkini.
define('PANEL_GO_URL', '{{GO_URL}}');

// Pixel statistik.
define('PANEL_PIXEL_URL', '{{PIXEL_URL}}');

// Berapa detik data panel disimpan sebelum diambil ulang.
// Perubahan nama/warna/ikon di panel terlihat paling lama setelah rentang ini.
// Target link TIDAK terpengaruh - itu selalu dibaca langsung saat redirect.
define('SYNC_TTL', 300);

/**
 * Dipakai hanya bila panel tidak bisa dihubungi DAN cache lokal belum pernah
 * terbentuk. Nilainya diambil saat berkas ini dibuat.
 */
$FALLBACK = {{FALLBACK}};

PHP;

    return strtr($tpl, [
        '{{CONFIG_URL}}' => addslashes(embed_config_url($pwa)),
        '{{GO_URL}}' => addslashes(abs_url('p/' . $pwa['slug'] . '/go')),
        '{{PIXEL_URL}}' => addslashes(embed_pixel_url($pwa)),
        '{{FALLBACK}}' => embed_var_export(embed_fallback_array($pwa)),
    ]);
}

function embed_sync_php()
{
    return <<<'PHP'
<?php
/**
 * Penarik data dari panel, dengan cache lokal supaya tidak memanggil panel
 * pada setiap kunjungan. Bila panel sedang tidak bisa dihubungi, cache lama
 * yang dipakai; bila cache belum ada, nilai cadangan di config.php.
 */

require_once __DIR__ . '/config.php';

define('SYNC_CACHE_FILE', __DIR__ . '/.pwa-sync.json');

function pwa_fetch_remote($url)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'PWA-Landing/1.0',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code >= 200 && $code < 300) ? $body : null;
    }

    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : $body;
    }

    return null;
}

function pwa_cache_read()
{
    if (!is_file(SYNC_CACHE_FILE)) {
        return null;
    }
    $c = json_decode((string) @file_get_contents(SYNC_CACHE_FILE), true);
    return (is_array($c) && !empty($c['name'])) ? $c : null;
}

function pwa_cache_write($raw)
{
    $fresh = json_decode((string) $raw, true);
    if (is_array($fresh) && !empty($fresh['name'])) {
        @file_put_contents(SYNC_CACHE_FILE, $raw, LOCK_EX);
        return true;
    }
    return false;
}

/**
 * Kirim halaman ke pengunjung, tutup koneksinya, baru segarkan cache.
 *
 * Wajib dipanggil di akhir halaman, dan halaman wajib dibuka dengan ob_start()
 * supaya Content-Length masih bisa dipasang. Tanpa panjang isi yang pasti,
 * peramban menunggu koneksi tertutup - artinya tetap menunggu panel menjawab.
 */
function pwa_selesaikan_respons()
{
    ignore_user_abort(true);

    if (!headers_sent()) {
        $n = ob_get_length();
        header('Content-Length: ' . ($n === false ? 0 : $n));
        header('Connection: close');
    }
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();

    if (!empty($GLOBALS['__pwa_perlu_segarkan'])) {
        $GLOBALS['__pwa_perlu_segarkan'] = false;
        pwa_cache_write(pwa_fetch_remote(PANEL_CONFIG_URL));
    }
}

/**
 * Data PWA terkini. Dibaca sekali per request.
 *
 * Cache basi tetap dipakai lebih dulu dan penyegaran dilakukan setelah respons
 * terkirim. Tanpa itu, setiap kali TTL habis ada satu pengunjung yang menunggu
 * panel menjawab - dan bila panel sedang mati, setiap pengunjung menunggu
 * sampai timeout.
 */
function pwa_data()
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }

    global $FALLBACK;
    $ada = is_file(SYNC_CACHE_FILE);
    $masihSegar = $ada && (time() - filemtime(SYNC_CACHE_FILE) < SYNC_TTL);

    if ($ada && $masihSegar) {
        return $data = pwa_cache_read() ?: $FALLBACK;
    }

    if ($ada) {
        // Tandai lebih dulu agar permintaan lain yang datang bersamaan tidak
        // ikut-ikutan menghubungi panel, dan agar panel yang mati tidak
        // dicoba ulang pada setiap kunjungan.
        @touch(SYNC_CACHE_FILE);
        $GLOBALS['__pwa_perlu_segarkan'] = true;
        return $data = pwa_cache_read() ?: $FALLBACK;
    }

    // Belum pernah ada cache: hanya kali ini pengunjung menunggu.
    if (pwa_cache_write($raw = pwa_fetch_remote(PANEL_CONFIG_URL))) {
        return $data = json_decode($raw, true);
    }

    return $data = $FALLBACK;
}

/** Ambil satu nilai dari data PWA. */
function pv($key, $default = '')
{
    $d = pwa_data();
    return isset($d[$key]) && $d[$key] !== '' ? $d[$key] : $default;
}

/** Versi siap cetak ke HTML. */
function pe($key, $default = '')
{
    return htmlspecialchars((string) pv($key, $default), ENT_QUOTES, 'UTF-8');
}

PHP;
}

function embed_manifest_php()
{
    return <<<'PHP'
<?php
/**
 * Manifest disusun saat diminta, memakai data terbaru dari panel.
 * start_url, scope, dan id sengaja relatif supaya tetap satu origin dengan
 * halaman ini - syarat wajib agar PWA bisa dipasang.
 */

require_once __DIR__ . '/sync.php';

ob_start();
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

echo json_encode([
    'id' => './',
    'name' => pv('name'),
    'short_name' => pv('short_name', pv('name')),
    'description' => pv('description'),
    'start_url' => 'go.php',
    'scope' => './',
    'display' => pv('display', 'standalone'),
    'orientation' => pv('orientation', 'any'),
    'background_color' => pv('background_color', '#ffffff'),
    'theme_color' => pv('theme_color', '#0f172a'),
    'lang' => 'id',
    'dir' => 'ltr',
    'icons' => pv('icons', []),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

pwa_selesaikan_respons();

PHP;
}

function embed_go_php(array $pwa)
{
    $tpl = <<<'PHP'
<?php
/**
 * Titik masuk PWA "{{NAME}}".
 * JANGAN ubah nama file ini - alamatnya sudah ikut terpasang di HP pengguna.
 *
 * Target sebenarnya dibaca panel saat redirect terjadi, jadi mengganti target
 * di panel langsung berlaku di sini tanpa menyentuh berkas apa pun.
 */

require_once __DIR__ . '/config.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Location: ' . PANEL_GO_URL . '?s=pwa', true, 302);
exit;

PHP;

    return strtr($tpl, ['{{NAME}}' => str_replace(['*/', "\n"], '', $pwa['name'])]);
}

function embed_index_php(array $pwa)
{
    $tpl = <<<'PHP'
<?php
/**
 * Halaman promosi install.
 * Nama, deskripsi, warna, dan ikon ditarik dari panel - ubah di panel, halaman
 * ini ikut berubah sendiri. Desain bebas Anda ganti; yang wajib dipertahankan
 * hanya <link rel="manifest"> dan blok <script> di bagian bawah.
 */

require_once __DIR__ . '/sync.php';

// Buffer sejak awal supaya Content-Length bisa dipasang dan koneksi ditutup
// sebelum cache disegarkan di latar.
ob_start();

$theme = pe('theme_color', '#0f172a');
$bg = pe('background_color', '#ffffff');
$name = pe('name');
$desc = pv('description') !== ''
    ? pe('description')
    : 'Pasang aplikasi ini di layar utama untuk akses sekali ketuk.';
$icon = pe('icon');
$initial = htmlspecialchars(mb_strtoupper(mb_substr(pv('name'), 0, 1)), ENT_QUOTES, 'UTF-8');
$aktif = pv('active', true);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $name ?></title>
<meta name="description" content="<?= $desc ?>">
<meta name="theme-color" content="<?= $theme ?>">
<link rel="manifest" href="manifest.php">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= pe('short_name', pv('name')) ?>">
<?php if ($icon !== ''): ?>
<link rel="apple-touch-icon" href="<?= $icon ?>">
<link rel="icon" href="<?= $icon ?>">
<?php endif; ?>
<style>
:root{--theme:<?= $theme ?>;--bg:<?= $bg ?>}
*,*::before,*::after{box-sizing:border-box}
body{margin:0;min-height:100svh;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
 background:var(--bg);color:#0f172a;display:flex;align-items:center;justify-content:center;
 padding:24px 20px calc(24px + env(safe-area-inset-bottom))}
.shell{width:100%;max-width:420px;text-align:center}
.icon{width:108px;height:108px;border-radius:26px;margin:0 auto 22px;display:block;object-fit:cover;
 box-shadow:0 12px 34px rgba(15,23,42,.22);background:#fff}
.icon-fb{display:flex;align-items:center;justify-content:center;font-size:46px;font-weight:700;
 color:#fff;background:var(--theme)}
h1{font-size:1.6rem;line-height:1.25;margin:0 0 10px;letter-spacing:-.02em}
p.desc{margin:0 0 28px;color:#475569;line-height:1.6;font-size:.98rem}
.btn{display:flex;align-items:center;justify-content:center;width:100%;padding:15px 20px;border:0;
 border-radius:14px;font:inherit;font-weight:650;font-size:1rem;cursor:pointer;text-decoration:none}
.btn:active{transform:scale(.98)}
.btn-install{background:var(--theme);color:#fff}
.btn-open{background:transparent;color:var(--theme);border:1.5px solid var(--theme);margin-top:12px}
.hidden{display:none !important}
.installed{margin-top:18px;padding:12px 14px;border-radius:12px;font-size:.9rem;
 background:rgba(15,23,42,.06);color:var(--theme);font-weight:600}
.steps{margin-top:26px;text-align:left;background:rgba(15,23,42,.04);border-radius:14px;
 padding:16px 18px;font-size:.9rem;color:#334155;line-height:1.65}
.steps b{display:block;margin-bottom:6px;color:#0f172a}
.steps ol{margin:0;padding-left:20px}
.note{margin-top:20px;font-size:.8rem;color:#94a3b8}
@media (prefers-color-scheme:dark){body{background:#0b1120;color:#e2e8f0}p.desc{color:#94a3b8}
 .steps{background:rgba(255,255,255,.06);color:#cbd5e1}.steps b{color:#f1f5f9}}
</style>
</head>
<body>
<main class="shell">
  <?php if ($icon !== ''): ?>
    <img class="icon" id="app-icon" src="<?= $icon ?>" alt="<?= $name ?>" width="108" height="108">
  <?php else: ?>
    <div class="icon icon-fb" id="app-icon-fb"><?= $initial ?></div>
  <?php endif; ?>

  <h1><?= $name ?></h1>
  <p class="desc"><?= $desc ?></p>

<?php if (!$aktif): ?>
  <div class="installed">Aplikasi sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.</div>
<?php else: ?>
  <button type="button" class="btn btn-install hidden" id="btn-install">Pasang Aplikasi</button>
  <a class="btn btn-open" id="btn-open" href="<?= htmlspecialchars(PANEL_GO_URL, ENT_QUOTES) ?>?s=web">Buka Aplikasi</a>

  <div class="installed hidden" id="msg-installed">Aplikasi terpasang. Membuka&hellip;</div>

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
<?php endif; ?>
</main>

<?php if ($aktif): ?>
<script>
(function () {
  var PIXEL = <?= json_encode(PANEL_PIXEL_URL, JSON_UNESCAPED_SLASHES) ?>;
  var OPEN_URL = <?= json_encode(PANEL_GO_URL . '?s=web', JSON_UNESCAPED_SLASHES) ?>;
  var ENTRY = 'go.php';

  var standalone = window.matchMedia('(display-mode: standalone)').matches
    || window.matchMedia('(display-mode: fullscreen)').matches
    || window.navigator.standalone === true;

  // Dibuka dari ikon home screen: teruskan ke titik masuk.
  if (standalone) { location.replace(ENTRY); return; }

  var btnInstall = document.getElementById('btn-install');
  var btnOpen = document.getElementById('btn-open');
  var msgInstalled = document.getElementById('msg-installed');
  var stepsIos = document.getElementById('steps-ios');
  var stepsGeneric = document.getElementById('steps-generic');
  var deferred = null;
  var installTracked = false;
  var opening = false;

  // Pixel GET dipakai karena panel berada di domain lain (tanpa CORS preflight).
  function track(event) {
    new Image().src = PIXEL + '?event=' + event + '&r=' + Math.random().toString(36).slice(2);
  }

  function markInstalled() {
    if (installTracked) return;
    installTracked = true;
    track('install');
  }

  // Begitu terpasang, tidak ada lagi yang perlu diketuk: langsung ke target.
  // Jeda singkat memberi waktu animasi install selesai dan pixel terkirim.
  function openTarget() {
    if (opening) return;
    opening = true;
    btnInstall.classList.add('hidden');
    btnOpen.classList.add('hidden');
    stepsIos.classList.add('hidden');
    stepsGeneric.classList.add('hidden');
    msgInstalled.classList.remove('hidden');
    setTimeout(function () { location.replace(OPEN_URL); }, 700);
  }

  track('view');

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js', { scope: './' }).catch(function () {});
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

  var ua = navigator.userAgent;
  var isIos = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isSafari = /^((?!chrome|android|crios|fxios).)*safari/i.test(ua);

  if (isIos && isSafari) {
    stepsIos.classList.remove('hidden');
  } else {
    setTimeout(function () {
      if (!deferred && btnInstall.classList.contains('hidden')) {
        stepsGeneric.classList.remove('hidden');
      }
    }, 1500);
  }
})();
</script>
<?php endif; ?>
</body>
</html>
<?php pwa_selesaikan_respons(); ?>

PHP;

    return $tpl;
}

function embed_offline_php()
{
    return <<<'PHP'
<?php
/** Ditampilkan service worker saat pengguna kehilangan koneksi. */
require_once __DIR__ . '/sync.php';

$theme = pe('theme_color', '#0f172a');
$bg = pe('background_color', '#ffffff');
$name = pe('name');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tidak ada koneksi &middot; <?= $name ?></title>
<meta name="theme-color" content="<?= $theme ?>">
<style>
body{margin:0;min-height:100svh;display:flex;align-items:center;justify-content:center;padding:28px;
 font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:<?= $bg ?>;color:#0f172a;text-align:center}
.box{max-width:340px}
.dot{width:64px;height:64px;border-radius:50%;margin:0 auto 20px;background:<?= $theme ?>;opacity:.15}
h1{font-size:1.25rem;margin:0 0 8px}
p{color:#64748b;line-height:1.6;margin:0 0 22px;font-size:.95rem}
button{background:<?= $theme ?>;color:#fff;border:0;border-radius:12px;padding:13px 26px;font:inherit;
 font-weight:600;cursor:pointer}
@media (prefers-color-scheme:dark){body{background:#0b1120;color:#e2e8f0}p{color:#94a3b8}}
</style>
</head>
<body>
<div class="box">
  <div class="dot"></div>
  <h1>Tidak ada koneksi internet</h1>
  <p><?= $name ?> butuh koneksi untuk memuat halaman. Periksa jaringan Anda lalu coba lagi.</p>
  <button type="button" onclick="location.reload()">Coba Lagi</button>
</div>
</body>
</html>

PHP;
}

/* ---------------------------------------------------- Berkas mode statis */

function embed_manifest_static(array $pwa)
{
    $manifest = [
        'id' => './',
        'name' => $pwa['name'],
        'short_name' => $pwa['short_name'] !== '' ? $pwa['short_name'] : $pwa['name'],
        'description' => $pwa['description'] ?? '',
        'start_url' => 'go.html',
        'scope' => './',
        'display' => $pwa['display'] ?? 'standalone',
        'orientation' => $pwa['orientation'] ?? 'any',
        'background_color' => $pwa['background_color'] ?? '#ffffff',
        'theme_color' => $pwa['theme_color'] ?? '#0f172a',
        'lang' => 'id',
        'dir' => 'ltr',
        'icons' => icon_manifest_entries($pwa),
    ];

    return json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

function embed_go_html(array $pwa)
{
    $url = embed_go_url($pwa);
    $urlAttr = e($url);
    $urlJs = json_encode($url, JSON_UNESCAPED_SLASHES);
    $name = e($pwa['name']);

    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Membuka&hellip;</title>
<!--
  Titik masuk PWA "{$name}".
  JANGAN ubah nama file ini - alamatnya sudah ikut terpasang di HP pengguna.
  Target sebenarnya dibaca panel saat redirect terjadi, jadi mengganti target
  di panel langsung berlaku di sini tanpa menyentuh berkas apa pun.
-->
<meta name="robots" content="noindex">
<meta http-equiv="refresh" content="0; url={$urlAttr}">
<script>location.replace({$urlJs});</script>
</head>
<body></body>
</html>

HTML;
}

function embed_index_static(array $pwa)
{
    $name = e($pwa['name']);
    $shortName = e($pwa['short_name'] !== '' ? $pwa['short_name'] : $pwa['name']);
    $desc = e($pwa['description'] !== ''
        ? $pwa['description']
        : 'Pasang aplikasi ini di layar utama untuk akses sekali ketuk.');
    $theme = e($pwa['theme_color']);
    $bg = e($pwa['background_color']);
    $initial = e(mb_strtoupper(mb_substr($pwa['name'], 0, 1)));

    $iconAbs = icon_abs_url($pwa, 512);

    $iconTags = $iconAbs !== ''
        ? "<link rel=\"apple-touch-icon\" id=\"tap-icon\" href=\"" . e($iconAbs) . "\">\n<link rel=\"icon\" href=\"" . e($iconAbs) . "\">"
        : '';
    $iconMarkup = $iconAbs !== ''
        ? '<img class="icon" id="app-icon" src="' . e($iconAbs) . '" alt="' . $name . '" width="108" height="108">'
        : '<div class="icon icon-fb" id="app-icon-fb">' . $initial . '</div>';

    $openUrl = e(embed_go_url($pwa, 'web'));
    $openUrlJs = json_encode(embed_go_url($pwa, 'web'), JSON_UNESCAPED_SLASHES);
    $pixel = json_encode(embed_pixel_url($pwa), JSON_UNESCAPED_SLASHES);
    $configJs = json_encode(embed_config_url($pwa), JSON_UNESCAPED_SLASHES);

    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{$name}</title>
<meta name="description" content="{$desc}">
<meta name="theme-color" id="meta-theme" content="{$theme}">
<link rel="manifest" href="manifest.webmanifest">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{$shortName}">
{$iconTags}
<style>
:root{--theme:{$theme};--bg:{$bg}}
*,*::before,*::after{box-sizing:border-box}
body{margin:0;min-height:100svh;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
 background:var(--bg);color:#0f172a;display:flex;align-items:center;justify-content:center;
 padding:24px 20px calc(24px + env(safe-area-inset-bottom))}
.shell{width:100%;max-width:420px;text-align:center}
.icon{width:108px;height:108px;border-radius:26px;margin:0 auto 22px;display:block;object-fit:cover;
 box-shadow:0 12px 34px rgba(15,23,42,.22);background:#fff}
.icon-fb{display:flex;align-items:center;justify-content:center;font-size:46px;font-weight:700;
 color:#fff;background:var(--theme)}
h1{font-size:1.6rem;line-height:1.25;margin:0 0 10px;letter-spacing:-.02em}
p.desc{margin:0 0 28px;color:#475569;line-height:1.6;font-size:.98rem}
.btn{display:flex;align-items:center;justify-content:center;width:100%;padding:15px 20px;border:0;
 border-radius:14px;font:inherit;font-weight:650;font-size:1rem;cursor:pointer;text-decoration:none}
.btn:active{transform:scale(.98)}
.btn-install{background:var(--theme);color:#fff}
.btn-open{background:transparent;color:var(--theme);border:1.5px solid var(--theme);margin-top:12px}
.hidden{display:none !important}
.installed{margin-top:18px;padding:12px 14px;border-radius:12px;font-size:.9rem;
 background:rgba(15,23,42,.06);color:var(--theme);font-weight:600}
.steps{margin-top:26px;text-align:left;background:rgba(15,23,42,.04);border-radius:14px;
 padding:16px 18px;font-size:.9rem;color:#334155;line-height:1.65}
.steps b{display:block;margin-bottom:6px;color:#0f172a}
.steps ol{margin:0;padding-left:20px}
.note{margin-top:20px;font-size:.8rem;color:#94a3b8}
@media (prefers-color-scheme:dark){body{background:#0b1120;color:#e2e8f0}p.desc{color:#94a3b8}
 .steps{background:rgba(255,255,255,.06);color:#cbd5e1}.steps b{color:#f1f5f9}}
</style>
</head>
<body>
<main class="shell">
  {$iconMarkup}
  <h1 id="app-name">{$name}</h1>
  <p class="desc" id="app-desc">{$desc}</p>

  <button type="button" class="btn btn-install hidden" id="btn-install">Pasang Aplikasi</button>
  <a class="btn btn-open" id="btn-open" href="{$openUrl}">Buka Aplikasi</a>

  <div class="installed hidden" id="msg-installed">Aplikasi terpasang. Membuka&hellip;</div>

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
  var CONFIG_URL = {$configJs};
  var PIXEL = {$pixel};
  var OPEN_URL = {$openUrlJs};
  var ENTRY = 'go.html';

  var standalone = window.matchMedia('(display-mode: standalone)').matches
    || window.matchMedia('(display-mode: fullscreen)').matches
    || window.navigator.standalone === true;

  // Dibuka dari ikon home screen: teruskan ke titik masuk.
  if (standalone) { location.replace(ENTRY); return; }

  var btnInstall = document.getElementById('btn-install');
  var btnOpen = document.getElementById('btn-open');
  var msgInstalled = document.getElementById('msg-installed');
  var stepsIos = document.getElementById('steps-ios');
  var stepsGeneric = document.getElementById('steps-generic');
  var deferred = null;
  var installTracked = false;
  var opening = false;

  // Pixel GET dipakai karena panel berada di domain lain (tanpa CORS preflight).
  function track(event) {
    new Image().src = PIXEL + '?event=' + event + '&r=' + Math.random().toString(36).slice(2);
  }

  function markInstalled() {
    if (installTracked) return;
    installTracked = true;
    track('install');
  }

  // Begitu terpasang, tidak ada lagi yang perlu diketuk: langsung ke target.
  // Jeda singkat memberi waktu animasi install selesai dan pixel terkirim.
  function openTarget() {
    if (opening) return;
    opening = true;
    btnInstall.classList.add('hidden');
    btnOpen.classList.add('hidden');
    stepsIos.classList.add('hidden');
    stepsGeneric.classList.add('hidden');
    msgInstalled.classList.remove('hidden');
    setTimeout(function () { location.replace(OPEN_URL); }, 700);
  }

  // Segarkan tampilan dari panel. Hosting statis tidak bisa menyusun manifest
  // saat diminta, jadi identitas aplikasi yang SUDAH terpasang tetap mengikuti
  // manifest.webmanifest - hanya halaman ini yang ikut berubah.
  function syncFromPanel() {
    fetch(CONFIG_URL, { cache: 'no-store', mode: 'cors' })
      .then(function (r) { return r.json(); })
      .then(function (c) {
        if (!c || !c.name) return;

        document.title = c.name;
        var elName = document.getElementById('app-name');
        var elDesc = document.getElementById('app-desc');
        if (elName) elName.textContent = c.name;
        if (elDesc && c.description) elDesc.textContent = c.description;

        if (c.theme_color) {
          document.documentElement.style.setProperty('--theme', c.theme_color);
          var meta = document.getElementById('meta-theme');
          if (meta) meta.setAttribute('content', c.theme_color);
        }
        if (c.background_color) {
          document.documentElement.style.setProperty('--bg', c.background_color);
        }

        var img = document.getElementById('app-icon');
        if (img && c.icon) img.src = c.icon;
        var tap = document.getElementById('tap-icon');
        if (tap && c.icon) tap.setAttribute('href', c.icon);

        if (c.go_url) {
          OPEN_URL = c.go_url + '?s=web';
          if (btnOpen) btnOpen.setAttribute('href', OPEN_URL);
        }
      })
      .catch(function () { /* panel tidak terjangkau - pakai nilai bawaan halaman */ });
  }

  syncFromPanel();
  track('view');

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js', { scope: './' }).catch(function () {});
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

  var ua = navigator.userAgent;
  var isIos = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isSafari = /^((?!chrome|android|crios|fxios).)*safari/i.test(ua);

  if (isIos && isSafari) {
    stepsIos.classList.remove('hidden');
  } else {
    setTimeout(function () {
      if (!deferred && btnInstall.classList.contains('hidden')) {
        stepsGeneric.classList.remove('hidden');
      }
    }, 1500);
  }
})();
</script>
</body>
</html>

HTML;
}

function embed_offline_static(array $pwa)
{
    $name = e($pwa['name']);
    $theme = e($pwa['theme_color']);
    $bg = e($pwa['background_color']);

    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tidak ada koneksi &middot; {$name}</title>
<meta name="theme-color" content="{$theme}">
<style>
body{margin:0;min-height:100svh;display:flex;align-items:center;justify-content:center;padding:28px;
 font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:{$bg};color:#0f172a;text-align:center}
.box{max-width:340px}
.dot{width:64px;height:64px;border-radius:50%;margin:0 auto 20px;background:{$theme};opacity:.15}
h1{font-size:1.25rem;margin:0 0 8px}
p{color:#64748b;line-height:1.6;margin:0 0 22px;font-size:.95rem}
button{background:{$theme};color:#fff;border:0;border-radius:12px;padding:13px 26px;font:inherit;
 font-weight:600;cursor:pointer}
@media (prefers-color-scheme:dark){body{background:#0b1120;color:#e2e8f0}p{color:#94a3b8}}
</style>
</head>
<body>
<div class="box">
  <div class="dot"></div>
  <h1>Tidak ada koneksi internet</h1>
  <p>{$name} butuh koneksi untuk memuat halaman. Periksa jaringan Anda lalu coba lagi.</p>
  <button type="button" onclick="location.reload()">Coba Lagi</button>
</div>
</body>
</html>

HTML;
}

/* -------------------------------------------------------- Berkas bersama */

/** Service worker sama untuk kedua mode, hanya nama halaman offline berbeda. */
function embed_sw(array $pwa, $mode = 'php')
{
    return embed_sw_body($pwa, $mode === 'static' ? 'offline.html' : 'offline.php');
}

function embed_sw_body(array $pwa, $offline)
{
    $ver = substr(md5($pwa['slug'] . ($pwa['updated_at'] ?? '')), 0, 8);
    $cache = json_encode('pwa-' . $pwa['slug'] . '-' . $ver);
    $offlineJs = json_encode($offline);

    return <<<JS
// Service worker untuk "{$pwa['name']}".
// Naikkan angka versi di CACHE_NAME bila ingin memaksa pembaruan di HP pengguna.
const CACHE_NAME = {$cache};
const OFFLINE_URL = {$offlineJs};

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll([OFFLINE_URL]))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// Navigasi selalu lewat jaringan supaya data terbaru dari panel langsung terpakai.
// Bila offline, tampilkan halaman cadangan.
self.addEventListener('fetch', (event) => {
  if (event.request.mode !== 'navigate') return;
  event.respondWith(
    fetch(event.request).catch(() => caches.match(OFFLINE_URL))
  );
});

JS;
}

function embed_htaccess($mode = 'php')
{
    $out = "# Sebagian server belum mengenali ekstensi .webmanifest\n"
        . "AddType application/manifest+json .webmanifest\n"
        . "\n"
        . "# Service worker & manifest jangan di-cache lama supaya perubahan cepat terbaca\n"
        . "<IfModule mod_headers.c>\n"
        . "    <FilesMatch \"(sw\\.js|manifest\\.webmanifest|manifest\\.php)$\">\n"
        . "        Header set Cache-Control \"no-cache, must-revalidate\"\n"
        . "    </FilesMatch>\n"
        . "</IfModule>\n";

    if ($mode !== 'static') {
        $out .= "\n"
            . "# Cache data panel bersifat internal\n"
            . "<Files \".pwa-sync.json\">\n"
            . "    Require all denied\n"
            . "</Files>\n";
    }

    return $out;
}

/** Bahasa untuk pewarnaan/label blok kode di panel. */
function embed_lang($filename)
{
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    $map = [
        'php' => 'php',
        'js' => 'javascript',
        'html' => 'html',
        'webmanifest' => 'json',
        'json' => 'json',
    ];
    if ($filename === '.htaccess') {
        return 'apache';
    }
    return $map[$ext] ?? 'text';
}

function embed_zip(array $pwa, $mode)
{
    if (!class_exists('ZipArchive')) {
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'pwa');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        return null;
    }
    foreach (embed_files($pwa, $mode) as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();
    return $tmp;
}
