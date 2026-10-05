# OneID 2.14.2 — Upgrade PHP 8.4.26 Production

Tarikh release: 5 Oktober 2026

## Ringkasan

Release ini menutup pelaksanaan upgrade runtime OneID production daripada PHP
8.3.33 kepada PHP 8.4.26. Pelaksanaan dibuat secara staged, bermula dengan audit
kod dan dependency di UAT, pemasangan runtime selari, ujian FastCGI terpencil,
cutover web, migrasi CLI dan proses berjadual, kemudian pemantauan production.

## Skop yang telah diaktifkan

- Web OneID production menggunakan pool `oneid-web-prod84`.
- CLI, `phar` dan `phar.phar` menggunakan PHP 8.4.26.
- Cron sinkronisasi luaran, housekeeping sesi dan worker MFA telah disahkan pada
  jadual sebenar.
- Login, Administrator, sesi dan beberapa downstream SSO telah diuji berjaya.
- PHP 8.3 dikekalkan sebagai pilihan rollback.

## Kawalan release

- Mobile production kekal OFF dan tiada Android client production diaktifkan.
- Tiada migration atau perubahan database dibuat untuk pengaktifan mobile.
- Semua cutover mempunyai backup serta arahan rollback yang direkodkan dalam
  `docs/release/RESUME-HERE.md` dan `docs/release/ROLLBACK.md`.

## Keputusan pemantauan Fasa 6

- 52,406 respons HTTP 200 selepas titik pemulihan yang diluluskan.
- Sifar respons HTTP 5xx.
- Sifar ralat kritikal Nginx, PHP-FPM dan aplikasi.
- Tiada restart Nginx, PHP 8.4-FPM atau PHP 8.3-FPM.
- Proses cron selesai tanpa gangguan runtime.

## Dokumen laporan

Laporan penuh pengurusan dan teknikal disimpan di
`docs/release/reports/OneID_Laporan_Upgrade_PHP_8.4.26_Production_2026-10-05.docx`.

## English summary

This release completes the staged OneID production runtime upgrade from PHP
8.3.33 to PHP 8.4.26. Web, CLI and scheduled jobs have passed production
validation and Phase 6 monitoring. PHP 8.3 remains available for rollback,
production mobile access remains disabled, and no mobile database migration was
performed.
