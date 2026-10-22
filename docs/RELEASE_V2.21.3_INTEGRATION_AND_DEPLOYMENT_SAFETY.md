# OneID 2.21.3 — Integration and Deployment Safety

Tarikh: 22 Oktober 2026

Release ini mengukuhkan keserasian endpoint SSO legacy tanpa mengubah kontrak
downstream atau mengaktifkan mod `enforce`. Audit integrasi dipisahkan daripada
log ralat PHP supaya ralat aplikasi sebenar lebih mudah dikesan.

Aliran release kini hanya membina pakej daripada `main` yang bersih dan telah
dipush. Manifest candidate dipadankan dengan SHA penuh semasa deployment.
Direktori `vendors/`, runtime, upload dan `public/diagnostic/` dipelihara daripada
`rsync --delete`, serta dimasukkan dalam backup sebelum perubahan source.

Skrip migrasi PHP 8.3 ke PHP 8.4 yang telah selesai diarkibkan sebagai rekod
sejarah. Template aktif dan runbook kini merujuk runtime PHP 8.4 sahaja.

Sebelum deployment production, pasang
`deployment/cron/oneid-production-integration-audit.logrotate` sebagai
`/etc/logrotate.d/oneid-integration-audit` dan sahkan dengan `logrotate -d`.
Health check `2.21.3` akan gagal jika kawalan rotation ini belum tersedia.
