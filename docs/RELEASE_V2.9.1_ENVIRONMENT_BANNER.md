# OneID v2.9.1 — Environment Banner

**Tarikh:** 10 Ogos 2026
**Skop:** Penanda visual environment pada login dan permukaan authenticated utama.

## Ringkasan

- Local/development menggunakan gradient teal dan label `DEVELOPMENT ENVIRONMENT`.
- Staging menggunakan gradient MyDigital ID dan label `STAGING ENVIRONMENT`.
- Label dan mesej bertukar antara Bahasa Melayu dan English mengikut locale aktif.
- Production tidak menghasilkan markup banner.
- Runtime kosong atau tidak dikenali menghasilkan amaran konfigurasi.
- Tiada perubahan database, secret, autentikasi, Sync Apply atau integrasi luar.

## Verification

```bash
php tools/environment_banner_contract.php
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
git diff --check
```
