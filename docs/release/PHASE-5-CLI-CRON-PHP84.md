# Fasa 5 — Production CLI, Phar, Cron dan Timer ke PHP 8.4.26

Status pada 2026-10-05: **PREPARED, NOT SWITCHED**.

## Gate

Fasa ini hanya boleh melalui cutover selepas sekurang-kurangnya 24 jam pemantauan web production tanpa isu kritikal. Tempoh 48–72 jam lebih baik. Cutover web berlaku pada 2026-10-05 sekitar 08:17 +08; semakan sekitar 15:33 +08 baru meliputi lebih kurang tujuh jam.

## Baseline

- Web production: PHP-FPM 8.4.26, validated.
- `/usr/bin/php`: PHP 8.3.33, manual alternative.
- `phar` dan `phar.phar`: PHP 8.3 alternative.
- Tiga cron pengguna `iqs` memanggil `/usr/bin/php`:
  - external conditional sync, setiap jam;
  - session housekeeping, setiap 10 minit;
  - user MFA lifecycle, setiap minit.
- `phpsessionclean.timer` aktif.
- Mobile production kekal disabled; database tidak disentuh oleh persediaan ini.

## Compatibility evidence

- Ketiga-tiga entrypoint cron lulus `php8.4 -l`.
- `as1_session_housekeeping.php --check` lulus menggunakan PHP 8.4 tanpa mutasi.
- `user_mfa_lifecycle_worker.php --check` lulus menggunakan PHP 8.4 tanpa mutasi.
- External sync tidak dijalankan secara manual kerana ia boleh membuat perubahan; hanya lint PHP 8.4 dibuat. Selepas cutover, hasil jadual pertama mesti dibandingkan dengan log baseline.

## Staged sequence after monitoring gate

1. Jalankan `prepare-production-cli-cron84.py --apply` untuk snapshot alternatives, crontab, timer, konfigurasi CLI dan hashes skrip. Skrip ini tidak membuat switch.
2. Semak backup dan ulang read-only PHP 8.4 checks.
3. Tukar `php`, `phar`, dan `phar.phar` alternatives ke 8.4 dalam maintenance window.
4. Jangan ubah kandungan tiga crontab; `/usr/bin/php` akan memilih 8.4 selepas switch.
5. Sahkan `php -v`, `phar`, loaded extensions, timezone, ODBC, dan konfigurasi runtime.
6. Pantau satu execution MFA, satu housekeeping, dan satu external sync. Semak exit/result log dan database reconciliation yang dikeluarkan oleh aplikasi.
7. Jika mana-mana job gagal, set alternatives kembali ke 8.3 menggunakan nilai dalam backup; jangan cuba memperbaiki data dengan rerun manual.

## Explicit exclusions

- Tiada mobile production enablement.
- Tiada database migration atau perubahan data manual.
- Tiada perubahan Nginx/FPM web routing.
- PHP 8.3 tidak dibuang dalam fasa ini; ia dikekalkan untuk rollback.
