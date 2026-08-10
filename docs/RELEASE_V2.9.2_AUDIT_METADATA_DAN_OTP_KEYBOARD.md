# OneID v2.9.2 — Audit, Metadata dan OTP Keyboard

**Tarikh:** 11 Ogos 2026  
**Skop:** Penambahbaikan operasi dan kebolehgunaan sejak OneID v2.9.1.

## Ringkasan

- Label environment local/development dan staging kini multilingual dengan
  gaya visual yang konsisten sebelum dan selepas login.
- Status dan layout Sync Sessions diperjelas supaya perubahan direkodkan dan
  semua label boleh dibaca tanpa terpotong.
- Editor metadata menggunakan Select2, pilihan Change reason, coverage aktif
  mengikut locale dan terjemahan lengkap bagi aplikasi serta kategori aktif.
- No. IC tidak lagi digunakan sebagai identiti paparan Audit Log dan sejarah
  konfigurasi; No. Staf atau No. Matrik digunakan sebagai identiti awam.
- Semua input OTP e-mel dan Microsoft Authenticator aktif menyokong kekunci
  Enter melalui aliran pengesahan sedia ada.
- Integriti foreign key MFA, CSRF, rate limit, replay protection dan dialog
  pengesahan keselamatan kekal tidak berubah.

## Verification

```bash
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
php tools/audit_public_identity_contract.php
php tools/otp_enter_submission_contract.php
php tools/production_trial_health_check.php
git diff --check
```
