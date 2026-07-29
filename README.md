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

Untuk memasang di VPS Ubuntu dengan nginx, lihat [DEPLOY.md](DEPLOY.md) dan konfigurasi
siap pakai di [deploy/nginx.conf](deploy/nginx.conf).

## Kebutuhan

PHP 7.4+ dengan ekstensi **pdo_mysql**, **gd**, **mbstring**, dan **fileinfo**, serta
MySQL 5.7+ / MariaDB 10.3+.

## Menjalankan

Atur kredensial database di [config.php](config.php). Dengan `DB_AUTO_CREATE` bernilai true,
panel membuat sendiri database `pwa_manager` beserta tabelnya saat pertama kali dibuka.
Di hosting yang penggunanya tidak berhak membuat database, buat dulu secara manual lalu
setel `DB_AUTO_CREATE` ke false.

Instalasi lama yang masih memakai penyimpanan JSON tidak perlu tindakan apa pun: isi
`data/*.json` dan `data/events/` otomatis dipindahkan ke database saat skema pertama kali
dibuat, lalu berkasnya ditandai `.migrated` (tidak dihapus) dan ringkasannya dicatat di
`data/migrasi-*.log`.

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

## Dua target yang terpisah

Halaman install bersifat publik dan tombol di sana bisa diklik siapa saja. Bila Anda tidak
ingin pengunjung biasa sampai ke alamat tujuan aplikasi, isi **URL tombol &ldquo;Buka Aplikasi&rdquo;**
pada formulir PWA:

| Dibuka dari | Diarahkan ke |
|---|---|
| Ikon di layar utama (`start_url`) | `target_url` |
| Tombol &ldquo;Buka Aplikasi&rdquo; di halaman install | `web_target_url`, atau `target_url` bila dikosongkan |

Berlaku juga untuk tombol pada landing page di domain lain, karena keduanya melewati
endpoint `/go` yang sama. PWA yang memakai target terpisah ditandai label
&ldquo;tombol dipisah&rdquo; di daftar PWA.

Perayap mesin pencari dan pengambil pratinjau tautan tidak pernah diberi alamat tujuan:
`/go` membalas `204 No Content` untuk User-Agent yang dikenali sebagai bot, dan selalu
mengirim header `X-Robots-Tag: noindex, nofollow`. Alamat tujuan juga tidak pernah muncul
di `config.json` maupun `manifest.webmanifest`.

**Batasannya perlu dipahami.** `start_url` pada manifest bersifat publik, sehingga siapa pun
yang membuka manifest bisa menemukan `/go?s=pwa` lalu mengikutinya dengan peramban biasa.
Pemisahan ini menutup alamat tujuan dari pengunjung awam, mesin pencari, dan pratinjau
tautan &mdash; bukan dari orang yang memang sengaja menelusurinya. Untuk pembatasan
sungguhan, alamat tujuan harus memeriksa sendiri siapa yang datang.

## Proteksi tampilan halaman install (opsional)

Aktifkan per PWA lewat centang **Proteksi Tampilan** di formulir. Dua bagian, aktif bersamaan:

1. **Konten tersamar.** Nama, deskripsi, dan teks tombol tidak dikirim sebagai HTML biasa;
   disandi (XOR + base64) dan diisi oleh JavaScript ke elemen kosong saat halaman dimuat.
   `view-source` menampilkan blob tersandi, bukan salinan siap-tempel dari halaman promosinya.
2. **Shield anti-DevTools.** Klik kanan dan pintasan F12/Ctrl+Shift+I/J/C/Ctrl+U dicegat.
   Saat DevTools terdeteksi terbuka (lewat selisih ukuran jendela dan pemicu getter objek
   yang dicetak konsol), seluruh halaman diganti tampilan mirip layar crash
   &ldquo;Aw, Snap!&rdquo; milik Chrome.

Implementasi di [lib/guard.php](lib/guard.php), diaktifkan lewat kolom `pwa.protect`.

**Ini penyamaran, bukan keamanan sungguhan** &mdash; baca komentar di kepala berkas
`lib/guard.php` sebelum mengaktifkan. Ringkasnya:

- `manifest.webmanifest` dan `config.json` milik PWA yang sama **tetap** mengembalikan nama
  dan deskripsi apa adanya dalam JSON biasa &mdash; keduanya dipakai fitur instalasi PWA dan
  sinkronisasi landing eksternal, sengaja tidak ikut disandi. Proteksi ini hanya menutup
  halaman promosinya sendiri, bukan seluruh data yang dipakai panel.
- Deteksi DevTools bisa dilewati (devtools "terpisah" ke jendela sendiri, sebagian versi
  Firefox, atau JavaScript yang dimatikan sepenuhnya).
- Mencegat Ctrl+U/F12 hanya best-effort; browser berhak mengabaikan `preventDefault` untuk
  pintasan miliknya sendiri.
- Konten yang dibangun lewat JavaScript umumnya terindeks lebih sedikit oleh mesin pencari.

Cocok untuk mempersulit peniru awam dan bot pengambil-konten sederhana. Bukan untuk data
yang benar-benar harus dirahasiakan.

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

| Lapis | Tabel | Isi |
|---|---|---|
| Agregat harian | `stats_daily` | Jumlah per peristiwa per hari, untuk kartu dashboard |
| Rincian | `events` | Satu baris per kejadian |

**Tidak ada penghapusan otomatis.** Data lama tetap tersimpan untuk keperluan analisa;
pelepasan ruang dilakukan manual per rentang bulan lewat menu **Pemeliharaan**.

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

### Pemeliharaan data

`/admin/maintenance` menampilkan jumlah baris per bulan, ukuran tabel, dan formulir penghapusan
per rentang bulan &mdash; bisa dibatasi ke satu PWA atau seluruhnya. Penghapusan meminta rentang
diketik ulang sebagai konfirmasi, dan dijalankan bertahap per 5.000 baris agar tabel besar tidak
terkunci lama.

Pilihan **pertahankan ringkasan harian** membuang rincian per kejadian tapi menyisakan grafik
jumlah harian dan total sepanjang masa. Ini biasanya yang diinginkan: ruang terbesar dipakai
tabel `events`, sementara `stats_daily` hanya beberapa ratus baris per tahun per PWA.

Ruang yang dibebaskan InnoDB tidak langsung kembali ke sistem operasi; jalankan
`OPTIMIZE TABLE events;` bila berkasnya perlu benar-benar mengecil.

### Kinerja jalur pengunjung

Halaman admin boleh lambat; jalur yang dilewati pengguna akhir tidak. Diukur pada
Apache + MySQL 8 di Windows, 50 permintaan bersamaan:

| Endpoint | TTFB (p50) | Throughput |
|---|---|---|
| `/p/{slug}/go` (buka dari ikon) | 2,6 ms | 343 rps |
| `/p/{slug}/` (landing) | 3,2 ms | 355 rps |
| `manifest.webmanifest` | 2,7 ms | 2.461 rps |
| `sw.js` | 2,7 ms | 2.537 rps |
| `config.json` | 2,8 ms | 2.122 rps |
| `track.gif` | 2,3 ms | 301 rps |

Yang membuatnya ringan:

- **Pemeriksaan skema hanya sekali seumur instalasi**, ditandai berkas
  `data/.skema-v2`. Sebelumnya `SHOW TABLES` dan `SHOW INDEX` dijalankan pada
  setiap permintaan, termasuk setiap redirect.
- **Prepare diemulasi** (`ATTR_EMULATE_PREPARES`), sehingga kueri sekali pakai
  butuh satu perjalanan ke MySQL, bukan dua. Satu redirect: 12 → 4 perjalanan.
- **Koneksi persisten.** Membangun koneksi baru memakan 9,47 ms per permintaan,
  memakai ulang hanya 0,14 ms. Setel `DB_PERSISTENT` ke false bila hosting
  membatasi jumlah koneksi. Perhatikan `max_connections` MySQL harus lebih besar
  dari jumlah worker web.
- **Respons dikirim sebelum statistik ditulis.** Pengunjung hanya menunggu satu
  SELECT; pencatatan menyusul setelah koneksi ditutup. Memangkas TTFB separuh.
- **Dua penulisan digabung satu transaksi.** InnoDB melakukan fsync pada tiap
  commit: 3,11 ms menjadi 1,61 ms.
- **Berkas dimuat sesuai rute.** `app/admin.php` dan `lib/embed.php` bersama-sama
  hampir separuh kode dan tidak pernah disentuh jalur publik.
- **Sesi tidak dijalankan** untuk endpoint publik, jadi tidak ada I/O berkas sesi
  maupun cookie yang dikirim ke pengunjung.

Throughput endpoint yang menulis dibatasi database, bukan oleh kode PHP. Dua
dugaan diuji, dan hasilnya tidak seperti perkiraan awal:

| Dugaan | Hasil pengukuran |
|---|---|
| fsync per commit (`innodb_flush_log_at_trx_commit`) | **Bukan penyebabnya.** Diubah ke `2`: 1,62 ms → 1,56 ms, praktis tidak berubah |
| Antre kunci pada satu baris `stats_daily` | **Ini penyebabnya.** 8 pekerja menumbuk baris sama 677 tulis/detik, baris berbeda 1.776 tulis/detik |

Setiap kunjungan pada PWA yang sama di hari yang sama memperbarui satu baris
`stats_daily` yang identik, sehingga saling menunggu. Kontensinya per-PWA:
sepuluh PWA berbeda menulis ke sepuluh baris berbeda tanpa saling mengganggu.

Batas 677 tulis/detik untuk satu PWA setara 58 juta kunjungan sehari, jadi ini
bukan masalah yang perlu ditangani sekarang. Bila suatu saat tercapai,
penyelesaiannya memecah penghitung menjadi beberapa baris (`bucket` acak per
kunjungan, dijumlahkan saat dibaca) &mdash; bukan menambah infrastruktur baru.

**OPcache belum aktif** di lingkungan ini. Tanpa OPcache, PHP mem-parse ulang
seluruh berkas pada setiap permintaan (0,82 ms untuk jalur publik). Mengaktifkannya
di `php.ini` menghilangkan biaya itu sepenuhnya dan merupakan satu perubahan
konfigurasi dengan dampak terbesar untuk aplikasi PHP mana pun:

```ini
zend_extension=opcache
opcache.enable=1
opcache.memory_consumption=128
opcache.validate_timestamps=1
```

### Apakah perlu Redis?

Belum, dan kemungkinan besar masih lama. Biaya tiap bagian satu permintaan
`/go` sudah diukur:

| Bagian | Biaya | Bisa digantikan Redis? |
|---|---|---|
| `SELECT` data PWA | 0,049 ms | ya, tapi Redis lebih lambat |
| `SELECT` salt pengunjung | 0,040 ms | ya, tapi Redis lebih lambat |
| Dua penulisan statistik | 2,142 ms | tidak, kecuali ditulis asinkron |
| Parsing berkas PHP | 0,855 ms | tidak &mdash; ini tugas OPcache |

Yang bisa diambil alih Redis totalnya **0,089 ms**, sementara satu panggilan
Redis lewat TCP saja sekitar 0,1&ndash;0,2 ms. Dengan koneksi persisten dan baris
yang sudah berada di buffer pool, MySQL di sini lebih cepat daripada perjalanan
ke Redis. Menambahkannya justru memperlambat sekaligus menambah satu layanan
yang harus dijaga.

Bandingkan: OPcache menghilangkan 0,855 ms &mdash; sepuluh kali lipat dari yang
mampu dihemat Redis, tanpa infrastruktur tambahan.

Redis baru masuk akal bila salah satu berikut terjadi:

- **Lebih dari satu server web.** Redis dipakai untuk keadaan bersama (sesi,
  pembatasan laju, cache lintas server) &mdash; soal kebenaran, bukan kecepatan.
- **Penulisan melampaui kemampuan MySQL** setelah penghitung dipecah. Pola yang
  dipakai: kunjungan ditumpuk di Redis, lalu dipindahkan ke MySQL secara berkala
  oleh proses latar. Konsekuensinya data bisa hilang bila Redis mati sebelum
  dipindahkan, dan perlu proses pemindah yang ikut dijaga.
- **Statistik dibaca sangat sering** oleh sistem lain lewat API.

### Kinerja landing page eksternal

`go.php` &mdash; titik masuk yang dipakai saat ikon diketuk &mdash; **tidak pernah
menghubungi panel**; isinya hanya membaca konstanta lalu mengirim redirect. Jadi
panel yang sedang bermasalah tidak membuat aplikasi pengguna gagal dibuka.

`index.php` dan `manifest.php` memakai cache lokal. Bila cache basi, isi lama
tetap disajikan lebih dulu dan penyegaran dilakukan setelah halaman terkirim,
dengan penanda waktu diperbarui di awal agar permintaan yang datang bersamaan
tidak ikut menghubungi panel. Hasilnya, **panel yang mati sekalipun tidak
memperlambat landing eksternal**: diuji dengan panel dimatikan dan cache basi,
setiap kunjungan tetap 1,5 ms.

### Catatan kinerja panel admin

Seluruh angka halaman analitik dihitung dari **satu kali baca** rentang tanggal, bukan satu
kueri agregat per bagian. Pada 63 ribu baris, dua belas kueri terpisah memakan 1.656 ms
sedangkan satu lintasan hanya 335 ms.

Indeks `events` sengaja hanya `(slug, occurred_at)` dan `(ym)`. Jangan menambahkan
`(slug, ym)`: kolom depannya sama dengan indeks pertama, dan optimizer sempat memilihnya
lalu memindai seluruh baris milik satu slug alih-alih range scan tanggal &mdash; halaman
analitik melambat sepuluh kali lipat.

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
