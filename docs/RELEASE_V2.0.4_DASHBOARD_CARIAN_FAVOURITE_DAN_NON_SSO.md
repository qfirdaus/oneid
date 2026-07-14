# Release OneID v2.0.4 — Dashboard Carian, Favourite dan NON SSO

**Tarikh:** 14 Julai 2026  
**Versi:** 2.0.4  
**Jenis:** UI/UX dashboard pengguna, preference aplikasi dan ACL-safe patch

## Ringkasan

v2.0.4 memperkemas direktori aplikasi pengguna supaya aplikasi lebih mudah
ditemui dan aplikasi kerap digunakan boleh dikumpulkan tanpa mengubah struktur
ACL. Release ini turut membetulkan istilah tindakan antara aplikasi SSO dan
NON SSO serta merekodkan status penangguhan live Apply-path M1.

Release ini tidak mengaktifkan External Sync Apply S4E dan tidak mengubah
contract integrasi SSO consumer.

## Perubahan utama

- Carian global menggunakan nama dan penerangan aplikasi merentas kategori.
- Kategori yang mempunyai hasil carian dipilih secara automatik.
- Tab Favourite berikon bintang berada pada kedudukan pertama.
- Pengguna boleh menambah atau membuang Favourite terus daripada kad aplikasi.
- Preference disimpan mengikut akaun dan kekal selepas login semula.
- Aplikasi Favourite masih dipaparkan dalam kategori asal.
- Tab NON SSO menggunakan identiti warna berbeza.
- Aplikasi SSO memaparkan `Login`; NON SSO memaparkan `Akses`.
- Kad aplikasi dan tindakan Favourite/Login/Akses disusun secara responsive.
- Version Releases memaparkan 10 versi terkini dahulu dan membuka 10 release
  terdahulu pada setiap tindakan `Lihat release terdahulu`.
- Tab kategori Web Apps admin hanya memaparkan kategori dengan aplikasi aktif.
- Manage Categories memaparkan inventori aktif/inactive dan hanya membenarkan
  kategori benar-benar kosong dipadam.
- Remove App mengarkib aplikasi, membersihkan semua ACL/Favourite dan merekod
  correlation reference dalam satu transaction.
- Effective ACL menolak aplikasi inactive dan schema menguatkuasakan nama
  kategori unik serta foreign key kategori berpolisi `ON DELETE RESTRICT`.

## Sempadan keselamatan

- Owner Favourite diperoleh daripada session, bukan input `u_id` browser.
- Enable Favourite memerlukan effective ACL yang masih sah.
- Jadual `user_app_favourite` ialah preference paparan sahaja.
- Ia tidak menulis `acl_group`, `acl_single` atau `acl_blacklist`.
- `go_to_service_provider` tetap mengesahkan ACL semasa setiap tindakan akses.

## Schema dan rollback

Migration U1 yang telah digunakan:

```bash
php tools/u1_user_app_favourites_migrate.php --apply
```

Rollback schema tersedia di:

`docs/migrations/U1_USER_APP_FAVOURITES_DOWN.sql`

Jadual hanya mengandungi preference Favourite. Backup preference sebelum
menjalankan DOWN migration jika data pilihan pengguna mahu dikekalkan.

## Verification

```bash
php tools/u1_user_dashboard_contract.php
php tools/u1_user_app_favourites_migrate.php --check
php tools/release_metadata_contract.php
php tools/w4_web_app_management_contract.php
php tools/m2_user_security_actions_contract.php
php tools/m3_profile_acl_contract.php
php tools/restructure_smoke.php https://oneid.local --insecure
```

Automated contract, migration check, transaction rollback proof dan smoke HTTP
telah lulus. Owner UAT W0–W4 turut lulus bagi category create, duplicate
rejection, empty-only removal, app soft archive dan ACL/Favourite cleanup.
Bukti correlation dan keadaan database akhir direkod dalam
`docs/W0_W4_AUDIT_DAN_HARDENING_WEB_APPS.md`.

## Rekod M1

No-change UAT M1 telah lulus. Live Apply-path UAT ditangguhkan oleh owner
sehingga akaun external ujian yang sesuai tersedia. Penangguhan dan evidence
yang diperlukan direkod dalam `docs/M1_SAFE_USER_RESYNC_DAN_ROLLBACK.md`.
