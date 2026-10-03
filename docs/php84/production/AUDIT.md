# Audit persediaan production — read-only

Host: APPSSSOPRODv1 (172.16.2.109), /var/www/oneid. Semakan 3 Oktober 2026. Tiada fail production ditulis, tiada package/servis/routing/cron diubah. SSH BatchMode berjaya; git working tree production bersih ketika diperiksa.

## Runtime dan routing

CLI dan FPM ialah PHP 8.3.33. PHP 8.4 belum dipasang; apt-cache sedia ada menyenaraikan candidate 8.4.26 (bukan bukti pemasangan/simulasi dependency). Nginx OneID production menggunakan /run/php/php8.3-fpm-oneid.sock. Pool khusus oneid adalah dynamic dengan max_children=10; pool www berasingan max_children=20. Jangan menyalin pool UAT ondemand=8 secara membuta tuli. Master FPM 8.3 aktif dan NRestarts=0 ketika semakan.

Tiga cron pengguna iqs yang merujuk OneID menggunakan default /usr/bin/php: external sync, session housekeeping dan MFA lifecycle worker. Inventori ini bukan semua cron root/systemd timers. Kekalkan CLI/cron 8.3 dalam cutover web pertama melainkan diuji dan diarahkan secara khusus.

## Kod / dependency

Perbandingan 327 fail terpilih (root PHP, app/lib/bootstrap/config/page/admin/public PHP, composer manifests, fail OIDC vendor eksplisit): 315 sama dengan UAT, 12 berbeza. Ini bukan keseluruhan repository atau vendor tree. Senarai/hashes dalam inventory.json dan code-comparison.json.

325 fail PHP dibaca dari production dan dilint secara lokal menggunakan PHP 8.4.26 UAT: tiada syntax failure. Fail Jumbojett menghasilkan implicit-nullable deprecation. Kod tidak dieksekusi oleh lint; ini bukan pengesahan runtime production 8.4. Bukti php84-static-lint.json.

Production mempunyai Jumbojett v1.0.2 dan phpseclib 3.0.55; UAT menggunakan patch nullable Jumbojett dan phpseclib 3.0.57. composer.json production belum mempunyai hooks patch tersebut. Kekalkan perubahan dependency sebagai patch terhad beserta classmap Contracts untuk mobile jika skop mobile dipilih; jangan masukkan classmap/path yang tiada pada production. Constraint PHP ^8.3 tidak mengecualikan 8.4.

## Penemuan keselamatan sedia ada

Source production masih mempunyai refresh legacy sebelum status/ACL dan tiada method refresh_legacy_token bertransaksi. Ini sepadan dengan susunan kod yang gagal tiga senario sintetik pada UAT sebelum pembetulan. Tiada token production diuji. Cadangan: pindahkan hanya patch api.php dan method berkaitan Database, semak diff dan ulang fixture/database tests sebelum deployment berasingan. Jangan menimpa keseluruhan Database.php yang mempunyai perubahan lain.

## Perubahan yang tidak patut dibawa sebagai sebahagian upgrade PHP

Perbezaan MyDigitalIdConfig ialah forMobileStaging dengan callback hostname UAT yang dikodkan khusus. Jangan salin ke production. Perbezaan UI/index/dashboard/locale/MFA juga tidak diperlukan semata-mata untuk pertukaran PHP. Tiada route mobile production dilihat dalam vhost sites-enabled yang diperiksa; jangan infer production sudah menyediakan keseluruhan stack mobile.

## Pelan production yang dicadangkan (belum dilaksanakan)

1. Sediakan patch terpilih keselamatan refresh dan dependency, bukan salinan penuh UAT; review dan validasi pada checkout tempatan.
2. Inventori FPM ini/extensions efektif, cron root/timer, sumber ODBC dan baseline production sebelum apply. Lint/CLI extensions semasa tidak menggantikan probe pool sebenar.
3. Simulasi pemasangan pakej 8.4.26 berasingan, pin default CLI/phar pada 8.3; kekalkan cron. Sediakan pool production khusus berasaskan konfigurasi production dan kapasiti yang dinilai, bukan template UAT.
4. Snapshot dua hala konfigurasi/dependency/kod, pelan rollback routing dan rekod backup; runtime rollback tidak membatalkan perubahan data.
5. Ujian terpencil minimum dan jadual cutover production yang dipersetujui; belum ada kebenaran menukar production.

UAT masih dalam pemerhatian selepas max_children dinaikkan; tiada dakwaan kestabilan jangka panjang atau semua integrasi remote/native/ODBC telah disahkan. Audit read-only ini selesai dalam skop di atas, tetapi production belum sedia ditukar terus.


## Perluasan skop oleh pengguna

Pengguna mahu release penuh staging (termasuk UI/mobile) bersama PHP 8.4, bukan patch terhad sahaja. Rujuk `FULL-RELEASE-READINESS.md` untuk audit baru. Cadangan mengecualikan perubahan UI dalam audit terdahulu tidak lagi menggambarkan skop release; guard staging masih perlu diganti dengan sokongan production yang disahkan.
