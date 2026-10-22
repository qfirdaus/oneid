# Arkib migrasi PHP 8.3 ke PHP 8.4

Fail dalam direktori ini ialah skrip sekali guna yang digunakan semasa migrasi
OneID production daripada PHP 8.3 kepada PHP 8.4 pada Oktober 2026.

**Jangan jalankan skrip ini untuk deployment baharu.** Guard, versi, socket dan
andaian status awalnya sengaja mengekalkan keadaan ketika migrasi dan kini sudah
lapuk. Deployment aktif mesti menggunakan PHP 8.4, pool
`/run/php/oneid-web-prod84.sock` dan runbook semasa di `docs/release/RUNBOOK.md`.

Skrip dikekalkan hanya untuk audit, bukti perubahan dan rujukan rollback sejarah.
Ia tidak diimport atau dipanggil oleh aliran deployment aktif.
