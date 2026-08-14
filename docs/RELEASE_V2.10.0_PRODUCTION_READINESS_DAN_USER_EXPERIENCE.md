# OneID v2.10.0 — Production Readiness dan User Experience

**Tarikh:** 14 Ogos 2026  
**Skop:** Kawalan aplikasi production, pemulihan kata laluan, ketahanan sesi dan penyeragaman pengalaman pengguna serta Administrator.

## Ringkasan

- Aplikasi yang disalin daripada staging hanya tersedia dalam production selepas
  `Ready for Production` diluluskan bersama Production URL HTTPS.
- Modal aplikasi menggunakan tab bagi memisahkan metadata, environment/release
  dan access/integration tanpa memenuhi satu halaman panjang.
- Pengguna MyDigital ID boleh menetapkan semula kata laluan manual melalui OTP
  e-mel; password history mengehadkan tiga kata laluan terdahulu.
- Penanda sesi tamat dibersihkan selepas login semula untuk mencegah popup lama
  berulang dalam sesi baharu.
- Side menu, FAQ, tetapan bahasa, profil Administrator dan modal Tukar Kata
  Laluan telah diseragamkan dengan paparan profesional serta responsif.
- Padanan kata laluan dipaparkan secara realtime sebagai requirement keenam dan
  masih dikuatkuasakan oleh server sebelum sebarang transaksi database.

## Migration

Pasang schema production-readiness dan selaraskan password history jika belum
dijalankan:

```bash
php tools/app_production_readiness_schema_migrate.php --apply
php tools/password_history_window_migrate.php --apply
```

## Verification

```bash
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
php tools/app_production_readiness_contract.php
php tools/mydigitalid_password_recovery_contract.php
php tools/password_history_window_migrate.php --check
php tools/user_password_modal_contract.php
php tools/user_faq_presentation_contract.php
git diff --check
```
