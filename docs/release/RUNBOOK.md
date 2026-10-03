# Deployment bersepadu production — belum dijalankan

Satu release boleh merangkumi web, Android dan PHP 8.4. Pelaksanaan tetap berurutan supaya titik pemulihan jelas. Jangan jalankan tool `*staging*`, `php84_uat_cutover.py` atau installer UAT pada production.

## 1. Bekukan candidate dan tutup input

Lengkapkan `INPUTS.md`. Verify SHA256/tar sebelum extract ke direktori candidate **di luar `/var/www/oneid`**; bukan terus overwrite live. Semak Git production masih bersih pada base manifest. Backup penuh source production termasuk vendor dan private config secara sulit. Simpan salinan `/etc/nginx`, `/etc/php/8.3`, senarai unit/timers/crontab dan alternative PHP/phar semasa. Backup uploads/runtime yang perlu dikekalkan. Backup MySQL dengan schema/triggers dan uji restore secara terasing; sebarang database provider sedia ada juga perlu backup.

Deployment root mobile dipin `/var/www/oneid`; jangan menukar kepada symlink release yang realpath berbeza tanpa menyesuaikan guard dan menjalankan ujian semula. Gunakan maintenance window untuk pertukaran source in-place daripada inventory fail, dengan code backup tersedia.

## 2. Sediakan dependency candidate, bukan live

Jalankan Composer menggunakan binary PHP eksplisit; jangan update lock:

```sh
php8.3 /usr/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php8.4 /usr/bin/composer check-platform-reqs --no-dev
php8.4 tools/patch_openid_php84.php --check
```

Composer sistem lama mengeluarkan deprecation pada PHP 8.4; sebab itu build boleh menggunakan CLI 8.3 yang dikekalkan, manakala platform dan runtime tetap disemak dengan 8.4 (atau gunakan Composer baharu yang telah diverifikasi). Ini warning tool Composer, bukan warning aplikasi.

`post-install-cmd` menjalankan patch dengan hash/struktur disemak. Jangan guna `--no-scripts` tanpa menjalankan patch berasingan. Semak vendor provenance dan Composer audit pada masa release; keadaan advisory boleh berubah. Archive candidate tidak mengandungi vendor. Simpan vendor yang dibina sebagai sebahagian deployment/rollback snapshot, jangan overwrite vendor live separuh siap.

## 3. Runtime selari dan provider dormant

- Semak package version/repository/ext yang tersedia ketika pemasangan. Pin PHP 8.4.26 dan semua extension diperlukan berdasarkan perbandingan **FPM production**, termasuk PDO MySQL, mysqli, mbstring, curl, openssl, sodium, XML, intl dan connector ODBC/SQL Server jika digunakan. Jangan memasang `php` meta-package atau menaik taraf pool lain.
- Kekalkan alternatif CLI `php`, `phar`, `phar.phar` pada 8.3; pakej apt boleh menukarnya, jadi verify/restore selepas pemasangan. Tiga cron diketahui memakai `/usr/bin/php`; inventori root/system timers masih perlu disemak. Jangan ubah cron ke 8.4 secara tersirat.
- Review `php84-web.conf.example` terhadap production 8.3 INI/pool sebenar (session path, timezone, upload, timeout, disabled functions, extensions, OPcache). Template menggunakan capacity production 10 workers. Mobile pool 4 workers, body 64K, session path berasingan. FPM 8.4 default www socket, jika diwujudkan installer, tidak diberi trafik sistem lain.
- Sediakan log files dengan owner/mode betul serta logrotate. Validate `php-fpm8.4 -t` dan direct FastCGI runtime probes kedua-dua pool. Belum tukar Nginx web.
- Sediakan user/directory `oneid-mobile-prod`, private PostgreSQL dan Hydra daripada pins `deployment/production/provider-lock.json`. Review dependency/patch ownership. Template Postgres mengandaikan binary di `/usr/lib/postgresql/16/bin/postgres`; package installation mungkin auto-create/start cluster default—elakkan kesan itu atau sediakan binari secara terasing selepas review. Port 25432 untuk DB, 24144/24145 untuk provider mesti kosong dan hanya loopback.
- `initdb` private cluster dengan auth host SCRAM dan password production baharu. Cipta database/user Hydra berasingan; jangan guna superuser pada service Hydra. Store DSN/system/cookie secrets di `/etc/oneid-mobile-prod/hydra.env` mode 0600 root, bukan template placeholder. Jalankan migration schema Hydra versi pinned **sebelum** serve, menggunakan environment private dan arahan migration yang disahkan dengan binary `hydra migrate sql --help`. Migrasi provider berasingan daripada MySQL OneID.
- Gunakan `hydra.yaml.example`, tanpa `--dev`. Render `token_hook.auth.config.value` daripada hook key private yang sama dengan hosted config; rendered YAML mode 0640 root:oneid-mobile-prod, jangan commit. Service-scoped `/etc/oneid-mobile-prod/hosts` memetakan `oneid.upnm.edu.my` ke 127.0.0.1 untuk token hook dengan TLS/SNI sah; kekalkan localhost dan entry penting dari hosts asal. `BindReadOnlyPaths` hanya mengubah pandangan service Hydra, bukan `/etc/hosts` sistem. Jangan disable certificate verification.
- Sahkan `/health/ready` admin loopback dan callback hook hanya boleh loopback. Jangan expose port admin/DB.

## 4. Install source/config dalam maintenance window

Freeze perubahan admin/identity dan pause writer/sync jobs OneID yang boleh berlumba dengan DDL; rekod jobs yang dihentikan untuk dipulihkan. Pakej mengandungi sejarah tools/migrations; **jangan execute semuanya**.

Pasang source yang disenaraikan manifest dan vendor candidate yang telah dibina. Lindungi fail private/uploads/runtime: jangan `rsync --delete` seluruh root atau extract ke root tanpa review. Simpan ownership/mode dan writable-directory policy asal. Reload/reset FPM OPcache selepas pertukaran kod sebelum beri trafik.

Render `.private/mobile-oidc-hosted.php` daripada production template dengan `enabled=false`, `production_ready=false`. Permission 0640 root:www-data atau lebih ketat. Keys binding/hook sekurang-kurangnya 32 bytes raw entropy (misalnya 64 hex chars), berlainan dan baharu; simpan `.private/mobile-oidc-secrets.php` secara sulit. Pautkan DSN/MFA/SMTP/TOTP ke konfigurasi **production sedia ada**, bukan snapshot staging. Jangan replace `.private/runtime.php` dengan fail UAT.

Isi `.private/production-release-target.php` daripada template, dengan physical database dan bukti backup sebenar. Operator mesti verify backup restore sebelum `backup_verified=true`. Review environment variables supaya override DSN tidak melarikan scope.

## 5. MySQL migration tambahan

```sh
php8.4 tools/release/mobile-migration.php --plan
php8.4 tools/release/mobile-migration.php --check
php8.4 tools/release/mobile-migration.php --apply
```

`--plan` hanya print SQL. `--check` memerlukan root production, private config production/OFF, DB target tepat, MySQL 8.0, sumber columns/InnoDB dan tiada mobile schema; ia tidak menjamin privilege DDL. `--apply` juga memerlukan backup marker dan provider ready. Ia mengambil advisory lock sebelum preflight, merekod DDL/progress secara private dan **tidak enable observer**.

4 jadual baharu: mutex, records, control, identity_epoch. 28 trigger baharu menjaga perubahan identity/MFA. Tiada historical web migration dijalankan, tiada data identity sumber diubah. Semak trigger definers/privilege, existing trigger coexistence dan visibility menggunakan account aplikasi. DDL MySQL autocommit; kegagalan separuh jalan perlu review progress/schema, bukan rerun buta atau DROP. `docs/migrations/mobile-oidc-state.sql` ialah schema asas fixture sahaja; **jangan apply bersama full migration**.

## 6. Konfigurasi route dan client sebelum buka trafik

Pasang safe-log format di `http{}` dan dormant mobile snippet di HTTPS server **sedia ada**. Review konflik exact routes dan regex PHP sebelum include. Semua route mula loopback-only; token-hook kekal private selama-lamanya. `/mobile-test/` ditutup. Tujuh handler mobile menuju `/run/php/oneid-mobile-prod84.sock`; OIDC public endpoints menuju provider loopback. Jangan proxy admin API.

Daftarkan Android public client daripada JSON yang dilengkapkan, menggunakan admin API loopback. GET dahulu; jika ID wujud dan berbeza, STOP (jangan overwrite). POST hanya client baharu yang disahkan. Padankan exact ID/redirect di hosted `clients`; tiada wildcard, staging URI atau client secret native. Verify metadata/grants/scopes dan PKCE S256 dengan provider. Template kosong/REPLACE tidak sah.

Daftar callback MyDigital ID production dengan provider. Hanya set `mydigitalid_callback_registered=true` dan `mydigitalid_enabled=true` selepas disahkan. Pastikan callback web asal tetap sah. `production_ready=true` ialah pengakuan operator selepas semua syarat, bukan auto-detector.

## 7. Aktivasi bersepadu dan semakan ringkas

Semasa window yang diluluskan, masih kawal akses awam mobile:

1. Verify 28 trigger, seed dan identity source. Enable observer dalam DB target: `UPDATE mobile_oidc_control SET observer_enabled=1 WHERE singleton_id=1;` (setelah semua source writers dihentikan semasa migrasi). Set hosted `database_scope_confirmed=true`, `production_ready=true`, `enabled=true` dengan atomic private-file replacement; reload FPM jika diperlukan.
2. Tukar **hanya** FastCGI web OneID daripada `/run/php/php8.3-fpm-oneid.sock` ke `/run/php/oneid-web-prod84.sock`. Jangan replace socket secara global. `nginx -t` sebelum reload; jika gagal pulihkan config tanpa reload.
3. Buka hanya endpoint mobile public daripada loopback allow/deny. `/token-hook` kekal loopback-only. Validate dan reload Nginx. Sahkan `php` default masih 8.3 dan vhost/pool sistem lain tidak berubah.
4. Jalankan smoke terhad dalam `INPUTS.md`, termasuk Android sebenar. Semak log sanitized, FPM exceptions/queue, HTTP 5xx dan akses tidak sah. Nilai 400/401 dijangka untuk request tanpa parameter/token, bukan bukti masalah sendirinya.
5. Sambung jobs OneID yang dipause setelah observer sedia, pantau impaknya. Tutup maintenance window apabila semakan lulus. Jika gagal, guna `ROLLBACK.md` mengikut lapisan yang terkesan.

Tidak perlu mengulangi semua ujian UAT yang telah lulus. Namun callback/Android production dan root/environment baharu belum dilaksanakan di server production; semakan pendek ini tidak boleh digantikan oleh laporan UAT.
