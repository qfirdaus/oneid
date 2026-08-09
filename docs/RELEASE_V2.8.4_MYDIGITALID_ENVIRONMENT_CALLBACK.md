# OneID v2.8.4 — MyDigital ID Environment Callback

**Versi:** 2.8.4  
**Tarikh:** 9 Ogos 2026  
**Skop:** MyDigital ID configuration validation

## Ringkasan

Release ini membuang kebergantungan kepada host UAT yang sebelum ini ditulis
tetap dalam validasi MyDigital ID. Host callback kini dipilih secara eksplisit
daripada `ONEID_ENVIRONMENT`:

- `staging` menggunakan `oneid-uat.upnm.edu.my`;
- `production` menggunakan `oneid.upnm.edu.my`.

Environment lain serta callback silang ditolak secara fail-closed. Semua syarat
HTTPS, path callback, post-logout root, larangan port, query, fragment dan user
information dikekalkan.

## Batas release

- Tidak mengaktifkan feature flag MyDigital ID.
- Tidak mengubah atau merekod secret.
- Tidak menjalankan migration atau perubahan database.
- Tidak mengubah kontrak login manual dan SSO sedia ada.

## Validasi

```bash
php tests/characterization/mydigitalid_f1_foundation.php
php tests/characterization/mydigitalid_f3_callback_foundation.php
php tests/characterization/mydigitalid_f5_ui_logout.php
php tools/mydigitalid_f6_security_contract.php
php tools/release_metadata_contract.php
```

## English summary

OneID v2.8.4 derives the registered MyDigital ID callback host from the explicit
deployment environment. Staging and production accept only their own HTTPS
callback and logout hosts, while unknown environments and cross-environment
redirects fail closed.
