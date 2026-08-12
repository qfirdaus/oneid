# OneID v2.9.3 — Site API Code Rotation dan Admin Web Apps

**Tarikh:** 12 Ogos 2026  
**Skop:** Rotation credential per-app, secure retrieval dan pembaharuan UI Admin Web Apps.

## Ringkasan

- Site API Code boleh dirotate bagi aplikasi terpilih tanpa memadam app atau
  mengubah ACL, blacklist, favourite, icon dan metadata.
- App legacy kekal menggunakan kod lama sehingga ia dirotate; selepas rotation,
  hanya kod aktif terbaharu diterima.
- Kod baharu dijana secara kriptografi, disahkan melalui hash dan disimpan
  encrypted untuk paparan semula kepada pentadbir yang dibenarkan.
- Rotation memerlukan step-up authentication, CSRF, change reason, transaksi
  database dan audit correlation ID.
- Modal Application Details, pilihan Direct Link dan dua dialog rotation telah
  direka semula dengan UI lebar, responsif dan konsisten.

## Migration

```text
docs/migrations/20260812_site_api_code_rotation_up.sql
docs/migrations/20260812_site_api_code_retrieval_up.sql
```

## Verification

```bash
php tools/site_api_code_rotation_contract.php
php tools/w4_web_app_management_contract.php
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
git diff --check
```
