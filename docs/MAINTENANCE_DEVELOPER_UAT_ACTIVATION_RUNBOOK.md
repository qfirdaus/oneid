# Runbook Activation UAT — Maintenance Developer Access

Dokumen ini prosedur operasi, bukan kebenaran untuk menjalankannya.

## Pra-syarat

1. Fasa 8 disahkan owner dan security suite lulus.
2. Backup serta restore rehearsal disahkan; rekod change dan backup tersedia.
3. Change window maksimum dua jam ditetapkan dalam format ISO 8601 MYT.
4. User Login MFA ialah `ENFORCED`, e-mel MFA aktif, dan penghantaran e-mel diuji.
5. Sekurang-kurangnya seorang admin aktif boleh melengkapkan Admin Step-Up.

## Urutan terkawal

1. Kekalkan `ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED=false`.
2. Tetapkan approval schema, reference change/backup dan window dalam private runtime.
3. Dalam window sahaja, jalankan `php tools/maintenance_developer_schema_migrate.php --apply`.
4. Jalankan `php tools/maintenance_developer_uat_readiness.php`; pastikan
   `activation_ready=yes` dan `feature_enabled=false`.
5. Dengan approval activation berasingan, hidupkan feature dan reload runtime.
6. Admin Step-Up, beri satu grant pilot singkat kepada akaun `u_type=0` aktif.
7. Aktifkan maintenance, uji password + MFA + dashboard pengguna, kemudian revoke.
8. Sahkan sesi ditolak serta audit GRANTED/REVOKED wujud tanpa merekod credential.

## Stop dan rollback

Jika mana-mana reconciliation, MFA, token revoke atau audit gagal: matikan
feature dahulu, tamatkan maintenance jika selamat, revoke grant pilot dan
simpan bukti insiden. Down migration hanya dibenarkan selepas feature OFF,
semua sesi capability tamat, rekod audit dieksport, serta approval rollback
diberi. Jalankan fail down migration dalam change window dan sahkan kedua-dua
jadual tiada; jangan ubah `user_tbl`.

Jangan hidupkan feature sebelum schema lengkap. Runtime gate adalah fail-closed,
tetapi urutan yang salah akan menyebabkan semua login developer ditolak.
