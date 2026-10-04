# Fasa 1 — Audit kesiapsiagaan production

Tarikh: 4 Oktober 2026 (Asia/Kuala_Lumpur)
Server: `iqs@172.16.2.109` / `APPSSSOPRODv1`
Root: `/var/www/oneid`
Kaedah: SSH read-only. Tiada package install, service restart, config write, database write atau trafik ditukar.

## Dapatan

### Skop server

- Service OneID yang kelihatan aktif: `nginx.service` dan `php8.3-fpm.service`.
- Tiada service aplikasi PHP lain yang kelihatan dalam inventory ringkas. Production ini dianggap server OneID sahaja, seperti pengesahan pemilik.
- RAM tersedia kira-kira 14 GiB, root filesystem 118 GiB dengan 85 GiB tersedia.
- Git production bersih pada `main`, HEAD `06f364d5d2fbd936d8ed496d7e68095aa640cdcb` ketika audit.

### PHP semasa

- CLI/FPM aktif: PHP `8.3.33`.
- Default alternatives: `php`, `phar`, `phar.phar` semuanya menunjuk 8.3.
- FPM web OneID: `/run/php/php8.3-fpm-oneid.sock`.
- Pool `[oneid]`: user `iqs`, group `www-data`, `pm.max_children=10`, start 2, spare 1–3.
- Nginx production root `/var/www/oneid/public`; route OneID menggunakan socket pool khusus tersebut.
- Extension PHP 8.3 yang digunakan/tersedia termasuk PDO MySQL, ODBC, PDO ODBC, PDO DBLIB, mysqli, curl, mbstring, intl, XML, ZIP, GD, sodium dan OPcache.

### PHP 8.4 readiness

- PHP 8.4 **belum digunakan** oleh CLI atau FPM.
- Candidate apt `8.4.26-1+ubuntu24.04.1+deb.sury.org+1` tersedia untuk `php8.4`, `php8.4-cli`, `php8.4-fpm`, `php8.4-odbc`.
- Sebahagian package `php8.4-common`/extension versi `8.4.25` kelihatan telah terpasang, tetapi ini bukan runtime lengkap dan bukan target 8.4.26. Jangan campur versi atau terus tukar alternatives.
- Apt tidak menyediakan candidate `php8.4-pdo-odbc` atau `php8.4-pdo-dblib` sebagai package berasingan; `php8.4-odbc`/`php8.4-sybase` perlu disahkan selepas install kerana connector production menggunakan ODBC/DBLIB.
- Fasa 2 mesti memasang set lengkap 8.4.26 secara konsisten, termasuk extension yang dibuktikan digunakan oleh aplikasi, sebelum FPM/CLI cutover.

### Composer/dependency

- `composer.json` memerlukan PHP `^8.3`, `ext-curl`, `ext-json` dan `jumbojett/openid-connect-php ^1.0.2`.
- `composer.lock` wujud. `composer check-platform-reqs --no-dev` lulus di PHP 8.3 dengan ext-curl/json.
- Composer system version `2.7.1`. Build production perlu menggunakan lock, bukan update dependency; jalankan platform check selepas PHP 8.4 lengkap dan semak patch nullable OIDC.

### Cron dan timers

Tiga cron OneID menggunakan `/usr/bin/php` dan perlu ditukar/diuji secara eksplisit kepada PHP 8.4 selepas Fasa 3:

- `cron/run_conditional_external_sync.php` setiap jam pada minit 10
- `tools/as1_session_housekeeping.php --scheduled` setiap 10 minit
- `tools/user_mfa_lifecycle_worker.php --apply` setiap minit

`phpsessionclean.service` ialah housekeeping OS dan perlu disemak selepas PHP 8.4 package install. Tiada system timer OneID tambahan dijumpai dalam inventory ringkas; semakan penuh `systemctl list-timers --all` perlu dibuat sebelum cutover.

### Backup/readiness

- `/var/backups` mempunyai backup package/apt/alternatives berkala.
- Tiada bukti dalam semakan ini yang mengesahkan backup restore aplikasi/database OneID, vendor, `.private`, uploads dan konfigurasi Nginx/FPM boleh dipulihkan. Backup production khusus dan restore rehearsal masih **wajib** sebelum Fasa 4.
- Jangan anggap backup `dpkg`/`alternatives` sebagai backup database atau code runtime.

## Sasaran versi yang dimuktamadkan

Sasaran upgrade production ialah **PHP 8.4.26**, iaitu versi latest yang dipersetujui untuk release ini. Semua runtime OneID production akan disejajarkan kepada 8.4.26:

- CLI `/usr/bin/php`
- FPM web OneID
- FPM mobile yang masih dormant/OFF
- `phar` dan `phar.phar`
- tiga cron OneID dan timer/script berkaitan

Package PHP 8.4.25 yang telah kelihatan secara partial tidak boleh dijadikan runtime akhir; ia perlu dinaik taraf/disejajarkan ke 8.4.26 bersama extension yang sepadan. Tiada trafik atau package diubah dalam Fasa 1.

## Keputusan Fasa 1

**STATUS: PASS dengan blocker sebelum Fasa 2.**

Fasa 1 mengesahkan skop server dan runtime semasa. Fasa 2 belum boleh dianggap lengkap sehingga operator memilih dan memasang set package PHP 8.4.26 yang konsisten, mengesahkan ODBC/DBLIB connector, serta menyediakan backup/rollback production. Tiada perubahan dibuat pada production dalam audit ini.

## Tindakan Fasa 2 yang disediakan

1. Snapshot version/INI/socket/service dan backup konfigurasi `/etc/php`, `/etc/nginx`, cron serta alternatives.
2. Sahkan package names/dependencies dengan `apt-get --simulate`; jangan install 8.4.25 partial sebagai target.
3. Pasang PHP 8.4.26 CLI/FPM dan extension set yang sama atau serasi dengan 8.3, termasuk ODBC/DBLIB yang diperlukan.
4. Sediakan pool `oneid-web-prod84` dan pool mobile dormant pada socket berasingan; validate `php-fpm8.4 -t` dan direct FastCGI probe.
5. Pastikan web traffic masih 8.3 sehingga Fasa 4; jangan ubah `php`, `phar` atau cron sebelum code/dependency smoke Fasa 3 diluluskan.

## Sambungan Fasa 2 — 4 Oktober 2026

Dry-run pada production untuk sasaran tepat `8.4.26-1+ubuntu24.04.1+deb.sury.org+1` lulus:

- 12 package baharu: CLI, FPM, curl, GD, mbstring, MySQL, ODBC, OPcache, readline, sybase, XML dan ZIP.
- 2 package partial dinaik taraf: `php8.4-common` dan `php8.4-intl` daripada 8.4.25 ke 8.4.26.
- 0 package dibuang; routing Nginx/FPM 8.3 tidak disentuh.

Pemasangan sebenar belum berlaku kerana sesi SSH memerlukan kata laluan `sudo`. Skrip disimpan di `tools/release/prepare-production-php84.py` dan dry-run telah berjaya di production. Operator perlu menjalankannya dengan `sudo` pada server, selepas menyemak backup/approval. Skrip menetapkan PHP 8.3 sebagai default, memasang FPM 8.4 secara selari, menyediakan web/mobile dormant pool dan tidak menukar Nginx, cron atau trafik.
