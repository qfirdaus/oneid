# Fasa 0 — Baseline dan Rollback OneID-UAT

**Tarikh baseline:** 13 Julai 2026  
**Workspace:** `/var/www/app/oneid-uat`  
**Tujuan:** Merekod keadaan sebelum kawalan Sprint 1 dan menyenaraikan perkara yang hanya boleh disahkan pada host UAT sebenar.

## 1. Status Environment Audit

- PHP CLI workspace: PHP 8.3.32.
- Workspace bukan Git working tree.
- Binary `apache2ctl` tidak tersedia dalam workspace.
- Runtime lokal menggunakan Nginx dengan PHP-FPM 8.3, bukan Apache.
- Vhost lokal `oneid.local` menggunakan root `/var/www/app/oneid-uat`.
- Access log Nginx lokal tersedia tetapi hanya meliputi 11 hingga 13 Julai 2026.
- Crontab untuk user workspace adalah kosong.
- Systemd timer hanya menunjukkan timer OS biasa; tiada timer OneID.
- Source mengandungi skrip Windows Task Scheduler untuk job `UPNM_SSO_DailySync`.
- Scheduled task ditetapkan berjalan setiap hari pada 12:00 tengah malam sebagai `SYSTEM`.
- PHP Windows yang dijangka oleh skrip ialah `D:\www\php\php.exe`.
- `cron/logs/sync_cron.log` menunjukkan sync berjalan sehingga 13 Julai 2026.

Kesimpulan: workspace mempunyai runtime Nginx lokal untuk pembangunan, tetapi source deployment menunjukkan host UAT sebenar mungkin menggunakan Windows Task Scheduler dan konfigurasi web yang berbeza. Status Apache/IIS/reverse proxy, Windows Task Scheduler dan access log UAT sebenar masih perlu disahkan pada server tersebut.

## 2. Baseline Permission

Sebelum Sprint 1, item berikut mempunyai permission `777` dalam workspace:

- Root projek.
- `.htaccess`.
- `public_img/`.
- `cron/`.
- `cron/logs/`.

Permission belum diubah dalam Sprint 1 kerana ownership dan OS user host sebenar perlu dikenal pasti terlebih dahulu.

## 3. Baseline Checksum Fail Kritikal

| Fail | SHA-256 sebelum Sprint 1 |
|---|---|
| `.htaccess` | `79c702bffecc736640b88f1a47aa9f2a2bd695bde7cf16aac2b11d3cbdd0039f` |
| `index.php` | `9c93ee8e9af0421aaaabca7b14d83367e6621506385a9be1ce0f74c42ce4cfd1` |
| `page/dashboard.php` | `9a210983c9efad44df9540869059269631d13358a4b0763db44204ed512c26cb` |
| `admin/dashboard.php` | `102e368b69070185533bed5f479c5d73fe9e2b58592148b579c3db8afacc7ccc` |
| `api.php` | `b76d21200b7e1b75ee8bf5c15eca418c74300584979eb29bfa24d3a6267e5f9a` |
| `lib/q_func.php` | `6daa13a5416de5357c9991751124ff8c2f1269a55ecfb0487619b155bdd4c1b2` |
| `lib/SSO_IDP_INC.php` | `685c2c83007a70297eaeb68a2185db9735498fb39c7e1ff81b928e9baeaf452b` |
| `lib/config.php` | `4187c11bf19a91d3edf1b564005d5163ad7251a0d1d8f48d0aaa7e8adf60bddb` |
| `cron/run_sync.php` | `c0ea9531064a2b2c96c6c4d9e809dd11a5c36694a421a4d39da06edc1f4d2ef1` |
| `sso_db.sql` | `285ea548c9480e28f4fc2ddf8020a2e050f51e1c247f4e6e9c3c57118af3be38` |

## 4. Perubahan Sprint 1 Setakat Ini

### Root `.htaccess`

Ditambah deny rule untuk:

- `.sql`
- `.log`
- `.bat`
- `.ps1`
- `.DS_Store`
- `Thumbs.db`

Tujuan utamanya ialah menghalang dump database, operational log dan host setup script daripada dimuat turun melalui web.

### `public_img/.htaccess`

Ditambah deny rule untuk script/executable berikut:

- PHP, PHTML dan PHAR.
- CGI, Perl, Python dan shell script.
- Windows executable, DLL, BAT, CMD dan PowerShell.

Fail imej biasa tidak disekat.

Semakan MIME bagi 86 fail sedia ada dalam `public_img` mendapati semuanya dikenal pasti sebagai imej. Carian statik juga tidak menemui signature PHP/script yang mencurigakan dalam folder tersebut.

### Reference imej dashboard

Reference rosak `img/mock1.png` pada dashboard pengguna aktif ditukar kepada `img/mock1.jpg`, iaitu fail yang benar-benar tersedia. Fail dashboard lama tidak diubah.

### Kawalan runtime Nginx lokal

Nginx tidak membaca `.htaccess`. Akaun semasa tidak mempunyai sudo untuk mengubah vhost `/etc/nginx/sites-available/local-projects` yang dimiliki root.

Sebagai containment segera, permission item berikut ditukar daripada `777` kepada `600`, menyebabkan Nginx worker `www-data` tidak boleh membacanya:

- `sso_db.sql`
- `cron/logs/sync_cron.log`
- `cron/run_sync.bat`
- `cron/setup_scheduled_task.ps1`
- `.DS_Store` dan `Thumbs.db` yang ditemui

Semakan HTTP selepas perubahan:

| URL | Status |
|---|---:|
| `/sso_db.sql` | 403 |
| `/cron/logs/sync_cron.log` | 403 |
| `/cron/run_sync.bat` | 403 |
| `/cron/setup_scheduled_task.ps1` | 403 |
| `/public_img/app_icon_1752725496.png` | 200 |

Snippet Nginx yang perlu dipasang oleh administrator tersedia di `docs/nginx/oneid-sprint1-security.conf`. Permission `600` ialah containment lokal; deny rule pada web server masih kawalan deployment yang disyorkan.

### Semakan access log page lama

Log lokal mengandungi 8,583 request dari 11 hingga 13 Julai 2026. Tiada request ditemui kepada calon page lama, test, diagnostic atau integration endpoint yang disenaraikan dalam pelan. Dua request sensitif yang muncul selepas audit adalah request verifikasi audit sendiri.

Tempoh log kurang daripada tiga hari belum memenuhi syarat pemerhatian 30 hingga 90 hari. Oleh itu tiada page lama di-quarantine lagi.

## 5. Rollback Sprint 1

Jika perubahan menyebabkan HTTP 500 atau mengganggu asset:

1. Buang blok `Security baseline (Sprint 1)` daripada root `.htaccess`.
2. Buang fail `public_img/.htaccess`.
3. Jika permission containment perlu diundur, pulihkan permission berdasarkan ownership dan polisi host; jangan pulihkan kepada `777` secara automatik.
4. Reload/restart Apache atau Nginx jika konfigurasi server memerlukannya.
5. Uji semula login page dan image URL.

Root `.htaccess` asal ialah:

```apache
RewriteEngine On

# 1) Paksa HTTPS kalau masih HTTP
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://oneid.local%{REQUEST_URI} [L,R=301]

# 2) Auto-append .php kalau file tu wujud
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME}\.php -f
RewriteRule ^(.*)$ $1.php [NC,L]
```

## 6. Smoke-Test Checklist

### Public/login

- [ ] `GET /` memaparkan login page tanpa HTTP 500.
- [ ] CSS, JavaScript, font dan banner berjaya dimuatkan.
- [ ] HTTP redirect ke HTTPS menuju domain UAT yang betul.
- [ ] Login salah memaparkan mesej yang dijangka.
- [ ] Login staf berjaya.
- [ ] Login pelajar berjaya.

### Dashboard pengguna

- [ ] Dashboard memaparkan profil pengguna.
- [ ] Senarai aplikasi berjaya dimuatkan.
- [ ] Icon daripada `public_img` masih dipaparkan.
- [ ] Aplikasi SSO boleh dilancarkan.
- [ ] Aplikasi non-SSO boleh dibuka.
- [ ] Tukar password masih berfungsi.
- [ ] Logout masih berfungsi.

### Dashboard admin

- [ ] Admin dashboard boleh dibuka oleh admin.
- [ ] Senarai pengguna dan kategori berjaya dimuatkan.
- [ ] Tambah/edit aplikasi masih boleh membaca dan menulis icon.
- [ ] ACL, configuration, audit log dan sync log masih boleh dibaca.
- [ ] Manual sync hanya diuji dengan kelulusan kerana ia mengubah data.

### Forgot password

- [ ] Forgot-password menerima identifier yang sah.
- [ ] Email OTP diterima.
- [ ] OTP verification berfungsi.
- [ ] Password reset/login selepas reset berfungsi.

### Scheduled sync

- [ ] Windows task `UPNM_SSO_DailySync` wujud.
- [ ] Task menjalankan PHP dan path projek yang betul.
- [ ] Run terakhir berjaya.
- [ ] `sync_cron.log` terus menerima rekod baharu di lokasi yang dijangka.

### Exposure controls

- [ ] `GET /sso_db.sql` memberikan `403` atau `404`.
- [ ] `GET /cron/logs/sync_cron.log` memberikan `403` atau `404`.
- [ ] `GET /cron/run_sync.bat` memberikan `403` atau `404`.
- [ ] `GET /cron/setup_scheduled_task.ps1` memberikan `403` atau `404`.
- [ ] Fail imej biasa dalam `public_img` memberikan `200`.
- [ ] Fail percubaan berekstensi `.php` dalam `public_img` memberikan `403` tanpa dieksekusi. Jangan gunakan payload aktif; fail kosong memadai.

## 7. Bukti yang Masih Diperlukan daripada Host UAT

- [ ] Apache/IIS/reverse proxy vhost configuration.
- [ ] Apache version dan modul `mod_authz_core`.
- [ ] Nilai `AllowOverride` bagi document root dan `public_img`.
- [ ] Access log 30 hingga 90 hari.
- [ ] Error log selepas Sprint 1 deployment.
- [ ] Senarai Windows scheduled tasks.
- [ ] OS user bagi web server, deployment dan scheduled sync.
- [ ] Lokasi backup rasmi dan bukti restore procedure.
- [ ] Senarai consumer bagi `idms.php`, `skp_api.php`, `api_old.php` dan diagnostic agents.

## 8. Keputusan Quarantine

Tiada fail PHP lama, test atau monitoring di-quarantine pada langkah ini kerana access log host sebenar belum tersedia. Keputusan ini mengelakkan integration tersembunyi terputus tanpa bukti.
