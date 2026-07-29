# Deploy ke VPS Ubuntu + nginx

Panduan ini memakai Ubuntu 22.04/24.04, nginx, PHP-FPM 8.3, dan MySQL 8.
Konfigurasi nginx di [deploy/nginx.conf](deploy/nginx.conf) sudah diuji: routing,
blokir folder internal, penolakan eksekusi PHP di folder unggahan, login, unggah
ikon, sampai ekspor CSV.

> **nginx tidak membaca `.htaccess` sama sekali.** Seluruh proteksi yang di Apache
> tersebar di lima berkas `.htaccess` sudah dipindahkan ke `deploy/nginx.conf`.
> Memakai konfigurasi nginx buatan sendiri tanpa blok `deny` di sana membuat
> `data/` bisa diunduh siapa saja &mdash; di dalamnya ada hash password admin.
> Berkas `.htaccess` tetap disertakan agar panel ini masih bisa dipindah ke Apache.

## 1. Paket

```bash
sudo apt update
sudo apt install -y nginx mysql-server \
  php8.3-fpm php8.3-mysql php8.3-gd php8.3-mbstring php8.3-curl \
  php8.3-zip php8.3-opcache unzip git
```

`php8.3-gd` dipakai untuk mengubah ukuran ikon, `php8.3-zip` untuk mengunduh paket
landing eksternal sebagai `.zip`. Tanpa keduanya panel tetap jalan, hanya fitur itu
yang mati.

## 2. Database

Jangan memakai `root`. Buat pengguna khusus:

```bash
sudo mysql
```

```sql
CREATE DATABASE pwa_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pwa'@'localhost' IDENTIFIED BY 'GANTI_DENGAN_SANDI_PANJANG';
GRANT ALL PRIVILEGES ON pwa_manager.* TO 'pwa'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 3. Berkas aplikasi

```bash
sudo mkdir -p /var/www
sudo git clone https://github.com/bertove20/project-pwa.git /var/www/pwa-manager
cd /var/www/pwa-manager
```

Sunting `config.php`:

```php
define('DB_NAME', 'pwa_manager');
define('DB_USER', 'pwa');
define('DB_PASS', 'GANTI_DENGAN_SANDI_PANJANG');

// Database sudah dibuat manual di atas
define('DB_AUTO_CREATE', false);
```

`config.php` ikut terlacak git. Agar sandi tidak ikut ter-commit saat menarik
pembaruan:

```bash
git update-index --skip-worktree config.php
```

## 4. Hak akses

Aplikasi hanya perlu menulis ke dua tempat: `data/` (penanda skema dan berkas
cadangan) serta `uploads/icons/`. Sisanya sengaja tidak boleh ditulis proses web,
supaya celah pada aplikasi tidak bisa menimpa kodenya sendiri.

```bash
cd /var/www/pwa-manager
sudo chown -R root:www-data .
sudo find . -type d -exec chmod 750 {} \;
sudo find . -type f -exec chmod 640 {} \;

sudo mkdir -p data uploads/icons
sudo chown -R www-data:www-data data uploads
sudo chmod 770 data uploads uploads/icons
```

## 5. nginx

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/pwa-manager
sudo nano /etc/nginx/sites-available/pwa-manager      # ganti panel.contoh.com
sudo ln -s /etc/nginx/sites-available/pwa-manager /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Periksa versi soket PHP-FPM cocok dengan yang terpasang:

```bash
ls /run/php/          # mis. php8.3-fpm.sock
```

## 6. HTTPS

**Wajib.** PWA hanya bisa dipasang lewat HTTPS; tanpa sertifikat, tombol install
tidak akan pernah muncul.

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d panel.contoh.com
```

Certbot menyunting sendiri blok server tadi dan menambahkan pengalih dari HTTP.

## 7. OPcache

Pengukuran di proyek ini: tanpa OPcache, PHP mem-parse ulang berkas sebanyak
0,82 ms pada setiap permintaan. Ini satu perubahan konfigurasi dengan dampak
terbesar.

`/etc/php/8.3/fpm/conf.d/99-pwa.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
; Kode hanya berubah saat deploy, jadi tidak perlu dicek tiap permintaan
opcache.validate_timestamps=1
opcache.revalidate_freq=60

; Batas unggah ikon di aplikasi 4 MB
upload_max_filesize=8M
post_max_size=8M

expose_php=Off
```

```bash
sudo systemctl restart php8.3-fpm
```

## 8. Jumlah koneksi MySQL

Panel memakai koneksi persisten (`DB_PERSISTENT`), sehingga **setiap worker
PHP-FPM menahan satu koneksi MySQL**. Pastikan `max_connections` lebih besar dari
`pm.max_children`:

```bash
grep pm.max_children /etc/php/8.3/fpm/pool.d/www.conf
mysql -u pwa -p -e "SELECT @@max_connections;"
```

Bila `max_connections` (bawaan 151) terlalu dekat, naikkan di
`/etc/mysql/mysql.conf.d/mysqld.cnf`, atau setel `DB_PERSISTENT` ke `false` di
`config.php` dengan konsekuensi tiap permintaan membangun koneksi baru.

## 9. Periksa setelah deploy

```bash
D=https://panel.contoh.com

# Harus 404 semua. Bila ada yang 200, hentikan dan perbaiki nginx dulu.
for u in data/settings.json lib/db.php app/admin.php views/layout.php \
         config.php .gitignore deploy/nginx.conf; do
  echo -n "$u -> "; curl -s -o /dev/null -w "%{http_code}\n" "$D/$u"
done

# Harus hidup
curl -s -o /dev/null -w "login  %{http_code}\n" "$D/admin/login"
curl -s -o /dev/null -w "assets %{http_code}\n" "$D/assets/admin.css"
```

Lalu buka panel dan **ganti password bawaan `admin` / `admin123`** lewat menu
Pengaturan sebelum alamatnya disebarkan. Kode sumber panel ini publik, termasuk
nilai bawaan itu.

## 10. Memperbarui

```bash
cd /var/www/pwa-manager
sudo -u www-data git pull
sudo systemctl reload php8.3-fpm     # bersihkan OPcache
```

Perubahan struktur database dijalankan sendiri saat panel pertama kali dibuka
setelah pembaruan. Bila `data/.skema-vN` sudah ada tapi tabelnya hilang, panel
memasang ulang skema secara otomatis.

## Landing page eksternal di nginx

Paket landing untuk domain lain menyertakan `.htaccess` yang juga tidak berlaku
di nginx. Padanannya:

```nginx
location = /app/manifest.php { ... }   # sama seperti blok index.php di atas

location ^~ /app/ {
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    # Cache internal berisi salinan data panel
    location = /app/.pwa-sync.json { return 404; }
}

types { application/manifest+json webmanifest; }
```

Folder itu harus bisa ditulis proses web agar `.pwa-sync.json` terbentuk. Bila
tidak bisa, landing tetap jalan &mdash; hanya saja panel dihubungi pada setiap
kunjungan.
