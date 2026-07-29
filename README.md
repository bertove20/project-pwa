# PWA Manager

Panel admin untuk mengelola banyak PWA sekaligus. Setiap PWA yang dibuat di sini punya
manifest, service worker, dan halaman install sendiri &mdash; sementara **target link-nya
bisa diganti kapan saja** tanpa pengguna perlu install ulang.

## Cara kerja

```
User tap ikon PWA di HP
        |
        v
start_url:  /p/{slug}/go?s=pwa      <- alamat ini permanen, ikut ter-install
        |
        v
Panel baca target terbaru dari data/pwa.json
        |
        v
302 redirect -> https://target-saat-ini.com
```

Karena `start_url` tidak pernah berubah, mengganti target link di panel langsung
berlaku untuk semua HP yang sudah memasang aplikasi tersebut.

## Menjalankan

Folder ini sudah siap dipakai di Laragon (Apache + mod_rewrite). Akses lewat:

- `http://localhost/dasboardpwa/` &mdash; subfolder, atau
- `http://dasboardpwa.test/` &mdash; bila memakai auto-vhost Laragon

Login bawaan: **admin / admin123**. Ganti lewat menu *Pengaturan* sebelum dipakai online
&mdash; panel akan terus memperingatkan selama password bawaan masih dipakai.

Folder `data/` dan `uploads/icons/` terbentuk sendiri saat panel pertama kali dibuka, dan sengaja
tidak ikut ke repo: isinya milik tiap instalasi (hash password, daftar PWA, statistik, ikon).

## Alamat tiap PWA

| Alamat | Fungsi |
|---|---|
| `/p/{slug}/` | Halaman promosi install (bagikan link ini ke pengguna) |
| `/p/{slug}/go` | Redirect ke target link, sekaligus `start_url` manifest |
| `/p/{slug}/manifest.webmanifest` | Manifest dinamis |
| `/p/{slug}/sw.js` | Service worker (versinya ikut berubah saat data PWA diubah) |
| `/p/{slug}/offline` | Halaman fallback saat tidak ada koneksi |
| `/p/{slug}/track.gif` | Pixel statistik untuk landing page di domain lain |
| `/p/{slug}/config.json` | Sumber data live untuk landing page di domain lain |

## Landing page di domain lain

Manifest, service worker, dan `start_url` **wajib satu origin** dengan halaman yang memasang PWA.
Jadi landing page di domain lain tidak bisa sekadar menunjuk manifest panel &mdash; ia butuh
berkasnya sendiri plus satu file penerus.

Panel menyediakan generatornya: buka **Daftar PWA &rarr; Kode**, atau langsung ke
`/admin/embed/{slug}`. Berkas bisa disalin satu per satu atau diunduh sebagai `.zip`.

```
HP ketuk ikon aplikasi
      |
      v
situs-anda.com/app/go.php            <- permanen, ikut terpasang di HP
      |
      v
panel/p/{slug}/go?s=pwa              <- panel baca target terbaru
      |
      v
https://target-saat-ini.com
```

Berkas yang dihasilkan tidak menanam data secara mati, melainkan menarik `config.json` dari panel
supaya perubahan di panel ikut terbawa tanpa upload ulang.

### Mode PHP (disarankan)

`config.php`, `sync.php`, `manifest.php`, `go.php`, `index.php`, `offline.php`, `sw.js`, `.htaccess`

`manifest.php` disusun saat diminta memakai data terbaru, sehingga **semua** ikut tersinkron:
nama, deskripsi, warna, mode tampilan, orientasi, dan ikon. `sync.php` menyimpan data panel di
`.pwa-sync.json` selama `SYNC_TTL` detik (bawaan 300). Bila panel tidak terjangkau, cache lama
yang dipakai; bila cache belum ada, nilai cadangan di `config.php`.

Target link **tidak** melewati cache itu &mdash; selalu dibaca panel saat redirect, jadi berlaku seketika.

### Mode statis (Netlify, GitHub Pages, Cloudflare Pages)

`manifest.webmanifest`, `go.html`, `index.html`, `offline.html`, `sw.js`, `.htaccess`

Hosting statis tidak bisa menyusun manifest saat diminta, sehingga **identitas aplikasi yang sudah
terpasang** (nama, ikon, mode tampilan) terkunci di `manifest.webmanifest` dan menuntut upload ulang.
Tampilan landing page tetap disegarkan dari panel lewat JavaScript, dan target link tetap berlaku seketika.

### Aturan

- Satu folder untuk satu PWA &mdash; dua PWA dalam folder yang sama akan bentrok `scope`-nya.
- Jangan ganti nama `go.php`/`go.html` atau pindahkan foldernya setelah disebarkan.
- Kode yang dihasilkan menunjuk panel sesuai alamat yang dipakai membuka halaman generator.
  Setelah panel pindah ke domain produksi, buka panel lewat domain itu dan salin ulang
  (atau cukup sunting `PANEL_*` di `config.php` pada mode PHP).

## Struktur

```
index.php          Router utama (semua request non-file lewat sini)
config.php         Konstanta dasar & kredensial awal
.htaccess          Rewrite ke index.php
app/
  admin.php        Controller panel: login, CRUD, statistik, pengaturan
  pwa.php          Controller publik: landing, manifest, sw.js, redirect
lib/
  helpers.php      URL, escaping, validasi
  store.php        Baca/tulis JSON dengan file lock + cache per-request
  auth.php         Session, login, CSRF
  icons.php        Resize ikon dengan GD (192, 512, maskable)
  stats.php        Agregasi statistik harian
views/             Template panel & halaman publik
assets/            CSS & JS panel
data/              pwa.json, stats.json, settings.json  (ditolak dari web)
uploads/icons/     Ikon hasil upload
```

## Statistik & analitik

Empat peristiwa dicatat per PWA:

- **view** &mdash; halaman promosi dibuka
- **install** &mdash; browser melaporkan aplikasi berhasil dipasang
- **open** &mdash; aplikasi dibuka dari ikon home screen
- **click** &mdash; target dibuka dari tombol di halaman promosi

Disimpan dalam dua lapis:

| Lapis | Berkas | Isi | Masa simpan |
|---|---|---|---|
| Agregat harian | `data/stats.json` | Jumlah per peristiwa per hari, untuk kartu dashboard | 120 hari |
| Rincian | `data/events/{slug}/{YYYY-MM}.jsonl` | Satu baris per kejadian | 12 bulan |

Tiap baris rincian memuat tanggal dan jam persis, jenis peristiwa, sumber trafik
(ikon home screen / landing panel / landing domain lain / tombol landing), jenis perangkat,
sistem operasi, browser, penanda webview, perujuk, dan penanda pengunjung.

Penanda pengunjung adalah hash ber-salt dari IP + User-Agent + tanggal. **Alamat IP tidak
pernah disimpan**, dan penandanya berganti setiap hari sehingga hanya berguna untuk menghitung
pengunjung unik harian.

Halaman **Analitik** (`/admin/stats/{slug}`) menyajikan rentang tanggal bebas, filter per
peristiwa / perangkat / sumber, grafik harian dan per jam, peringkat perangkat, OS, browser,
dan perujuk, tabel peristiwa terbaru, serta ekspor CSV yang mengikuti filter aktif.

Bot dan perayap dikenali dan disembunyikan secara bawaan. Trafik dari webview aplikasi
(Facebook, Instagram, TikTok) ditandai khusus karena webview tidak pernah menampilkan tombol
install PWA &mdash; berguna untuk menjelaskan angka install yang rendah dari sumber tersebut.

## Catatan penting

- **HTTPS wajib** agar PWA bisa dipasang. Pengecualian hanya `localhost`. Saat naik ke
  server produksi, pastikan sertifikat SSL sudah aktif.
- **Jangan ganti slug** setelah PWA disebarkan. Slug adalah identitas aplikasi; mengubahnya
  membuat aplikasi yang sudah terpasang di HP pengguna kehilangan koneksi ke panel.
  (Ikon dan statistik tetap ikut dipindahkan, tapi PWA lama di HP pengguna tidak.)
- **Nginx**: konfigurasi rewrite di `.htaccess` hanya berlaku untuk Apache. Untuk nginx,
  tambahkan `try_files $uri $uri/ /index.php?$query_string;` dan blokir akses ke
  `data/`, `lib/`, `app/`, dan `views/`.
- **Backup** cukup dengan menyalin folder `data/` dan `uploads/`.
