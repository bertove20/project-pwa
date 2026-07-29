<?php

/**
 * Proteksi tampilan opsional per PWA untuk halaman landing bawaan panel.
 *
 * Dua bagian independen, aktif bersamaan lewat kolom `pwa.protect`:
 *
 *   1. Konten (nama, deskripsi, teks tombol) tidak dikirim sebagai teks biasa.
 *      Disandi XOR+base64 lalu ditaruh ke dalam elemen kosong oleh JavaScript
 *      saat halaman dimuat, sehingga view-source tidak menampilkan salinan
 *      siap-tempel dari halaman promosinya.
 *   2. "Shield": mencegat F12/Ctrl+Shift+I/J/C/Ctrl+U dan klik kanan, lalu bila
 *      DevTools terdeteksi terbuka, seluruh halaman diganti tampilan mirip
 *      layar crash "Aw, Snap!" milik Chrome.
 *
 * INI PENYAMARAN, BUKAN KEAMANAN SUNGGUHAN - baca sampai habis sebelum memakai:
 *
 *   - view-source selalu menampilkan persis byte yang dikirim server. Yang
 *     bisa diubah hanya ISI-nya (disandi, bukan teks biasa) - bukan membuatnya
 *     benar-benar tidak bisa dibaca. Siapa pun yang mau meluangkan waktu bisa
 *     menyalin skrip dekripsinya dan menjalankannya sendiri.
 *   - manifest.webmanifest dan config.json milik PWA ini TETAP mengembalikan
 *     nama, deskripsi, dan ikon dalam JSON biasa - keduanya dipakai fitur lain
 *     (instalasi PWA, sinkronisasi landing eksternal) dan sengaja tidak ikut
 *     disandi. Proteksi ini hanya menutup halaman promosi itu sendiri, bukan
 *     seluruh data yang dipakai panel.
 *   - Deteksi DevTools bisa dilewati: devtools "terpisah" (undocked ke jendela
 *     sendiri), sebagian versi Firefox, atau JavaScript yang dimatikan sama
 *     sekali membuat seluruh halaman ini (termasuk tombol install) tidak
 *     tampil - hanya fallback minim di <noscript> yang terlihat.
 *   - Mencegat Ctrl+U/F12 lewat preventDefault hanya best-effort; sebagian
 *     browser tetap mengizinkannya karena itu pintasan bawaan browser, bukan
 *     sesuatu yang wajib dituruti oleh JavaScript halaman.
 *   - Konten yang dibangun lewat JavaScript umumnya terindeks lebih sedikit
 *     oleh mesin pencari dibanding teks biasa.
 *
 * Cocok untuk mempersulit peniru awam dan bot pengambil-konten sederhana.
 * Bukan untuk data yang benar-benar harus dirahasiakan.
 */

function guard_active(array $pwa)
{
    return !empty($pwa['protect']);
}

function guard_xor($data, $key)
{
    $out = '';
    $kl = strlen($key);
    for ($i = 0, $n = strlen($data); $i < $n; $i++) {
        $out .= $data[$i] ^ $key[$i % $kl];
    }
    return $out;
}

/** @return array{blob:string,key:string} keduanya base64, siap ditaruh langsung di JS */
function guard_encode(array $payload)
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $key = random_bytes(12);
    return [
        'blob' => base64_encode(guard_xor($json, $key)),
        'key' => base64_encode($key),
    ];
}

/**
 * Skrip pengisi konten (dekode payload -> textContent) digabung dengan
 * shield anti-devtools. Dipasang sekali di akhir body saat proteksi aktif.
 */
function guard_script(array $pwa)
{
    $payload = [
        'title' => $pwa['name'],
        'name' => $pwa['name'],
        'desc' => $pwa['description'] !== ''
            ? $pwa['description']
            : 'Pasang aplikasi ini di layar utama untuk akses sekali ketuk.',
        'install' => $pwa['install_label'] !== '' ? $pwa['install_label'] : 'Pasang Aplikasi',
        'open' => $pwa['open_label'] !== '' ? $pwa['open_label'] : 'Buka Aplikasi',
        'installed' => "Aplikasi terpasang. Membuka\u{2026}",
    ];
    $enc = guard_encode($payload);

    $tpl = <<<'JS'
<script>
(function(){
var B="%%BLOB%%",K="%%KEY%%";
function xd(b,k){b=atob(b);k=atob(k);var o="";for(var i=0;i<b.length;i++){o+=String.fromCharCode(b.charCodeAt(i)^k.charCodeAt(i%k.length));}return o;}
function u8(s){try{return decodeURIComponent(escape(s));}catch(e){return s;}}
var d;
try{d=JSON.parse(u8(xd(B,K)));}catch(e){d={};}
if(d.title)document.title=d.title;
["app-name","app-desc","btn-install","btn-open","msg-installed"].forEach(function(id){
  var m={"app-name":"name","app-desc":"desc","btn-install":"install","btn-open":"open","msg-installed":"installed"};
  var el=document.getElementById(id);
  if(el&&d[m[id]])el.textContent=d[m[id]];
});

function crash(){
  try{window.stop();}catch(e){}
  document.documentElement.innerHTML=
'<head><meta charset="utf-8"><title>Aw, Snap!</title><style>'+
'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'+
'font-family:Arial,Helvetica,sans-serif;background:#fff;color:#374151}'+
'.c{max-width:520px;padding:32px;text-align:center}'+
'.f{width:84px;height:84px;margin:0 auto 20px}'+
'h1{font-size:1.5rem;font-weight:400;margin:0 0 14px;color:#1f2937}'+
'p{font-size:.95rem;line-height:1.6;color:#4b5563;margin:0 0 22px}'+
'button{font:inherit;font-size:.9rem;padding:9px 22px;border-radius:3px;border:1px solid #dadce0;'+
'background:#f8f9fa;color:#1a73e8;cursor:pointer}'+
'button:hover{background:#f1f3f4}'+
'</style></head><body><div class="c">'+
'<svg class="f" viewBox="0 0 84 84"><circle cx="42" cy="42" r="40" fill="none" stroke="#dadce0" stroke-width="3"/>'+
'<circle cx="28" cy="34" r="4" fill="#5f6368"/><circle cx="56" cy="34" r="4" fill="#5f6368"/>'+
'<path d="M25 56 Q42 42 59 56" stroke="#5f6368" stroke-width="3" fill="none" stroke-linecap="round"/></svg>'+
'<h1>Aw, Snap!</h1>'+
'<p>Something went wrong while displaying this webpage.<br>To continue, reload or go to another page.</p>'+
'<button onclick="location.reload()">Reload</button>'+
'</div></body>';
}

document.addEventListener("contextmenu",function(e){e.preventDefault();});
document.addEventListener("keydown",function(e){
  var k=(e.key||"").toLowerCase();
  if(e.keyCode===123||(e.ctrlKey&&e.shiftKey&&["i","j","c"].indexOf(k)>-1)||(e.ctrlKey&&k==="u")){
    e.preventDefault();e.stopPropagation();return false;
  }
});

var seen=false;
var probe=new Image();
Object.defineProperty(probe,"id",{get:function(){seen=true;}});
setInterval(function(){
  seen=false;
  console.log(probe);
  console.clear();
  var wDiff=window.outerWidth-window.innerWidth>160;
  var hDiff=window.outerHeight-window.innerHeight>160;
  if(seen||wDiff||hDiff){crash();}
},1000);
})();
</script>
JS;

    return strtr($tpl, ['%%BLOB%%' => $enc['blob'], '%%KEY%%' => $enc['key']]);
}
