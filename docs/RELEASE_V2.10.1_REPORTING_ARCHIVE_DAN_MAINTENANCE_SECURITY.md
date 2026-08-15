# OneID v2.10.1 — Reporting, Archive dan Maintenance Security

**Tarikh:** 15 Ogos 2026  
**Skop:** Pusat laporan pentadbiran, pengurusan aplikasi arkib, penyeragaman UI dan pengukuhan akses pentadbir ketika maintenance.

## Ringkasan

- Pusat Laporan Administrator menyediakan enam kumpulan laporan dengan preview
  read-only, reference opaque, cetakan seragam dan susun atur column yang padat.
- Laporan pengguna, aplikasi, sesi, MFA, synchronisation, audit dan konfigurasi
  memaparkan actor serta status operasi yang bersesuaian tanpa mendedahkan ID
  dalaman pada URL.
- Archived Applications membolehkan restore atau purge terpilih selepas semakan
  dependency, pengesahan nama aplikasi dan sebab audit.
- Modal aplikasi dan kategori pengguna diseragamkan dengan bahasa visual
  Metadata Translation dan Sync User.
- Maintenance Mode memaparkan bantuan rasmi OneID@UPNM dan menyokong paparan
  dwibahasa yang compact serta responsif.
- Maintenance administrator login kini memerlukan kata laluan dan Admin
  Authenticator yang baharu sebelum token SSO atau grant lima minit dikeluarkan.
- Rate limit server, revocation grant lama, route allowlist dan compensation
  token memastikan kegagalan MFA tidak meninggalkan sesi authenticated.

## Migration

Release ini tidak menambah schema baharu. Pastikan schema maintenance, Admin
2FA, User MFA, session history dan application production readiness daripada
release terdahulu telah dipasang.

## Verification

```bash
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
php tools/maintenance_mode_contract.php
php tools/admin_reports_contract.php
php tools/user_login_mfa_u5_contract.php
php tools/f7_3_totp_service_contract.php
git diff --check
```
