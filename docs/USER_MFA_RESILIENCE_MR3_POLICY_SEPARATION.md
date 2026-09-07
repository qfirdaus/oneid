# MR3 — Pemisahan Enjin Polisi MFA

**Tarikh:** 7 September 2026
**Fasa:** 3 — domain policy dan login integration
**Status:** IMPLEMENTED / AUTOMATED PASS / ADMIN MAINTENANCE UAT PASS

## 1. Hasil

Maintenance login tidak lagi mengambil keputusan enforcement daripada
`user_login_mfa_policy`. Polisi `maintenance_mfa_policy` dibaca melalui domain
reader tersendiri dan ditukar kepada polisi pending-login `ENFORCED` sebelum
sebarang token atau authenticated session diwujudkan.

Pemisahan digunakan pada dua boundary:

1. primary password login bagi Administrator/developer maintenance; dan
2. request, verify, resend, cancel serta finalize challenge maintenance.

Oleh itu database User MFA boleh berada pada `OFF` tanpa menjadikan Maintenance
MFA `OFF` atau menghentikan challenge maintenance yang telah dimulakan.

## 2. Runtime ceiling

Empat key khusus diperkenalkan:

- `ONEID_MAINTENANCE_MFA_ENABLED`;
- `ONEID_MAINTENANCE_MFA_ACTIVATION_AUTHORIZED`;
- `ONEID_MAINTENANCE_MFA_EMAIL_ENABLED`; dan
- `ONEID_MAINTENANCE_MFA_TOTP_ENABLED`.

Committed default kekal fail closed. Staging mengaktifkan ceiling tersebut
kerana schema MR2 telah diluluskan dan polisi database dormant ialah
`ENFORCED`. User MFA runtime key tidak digunakan untuk menentukan availability
Maintenance MFA.

## 3. Invariant keselamatan

- Maintenance MFA e-mel kekal wajib.
- Database/schema/runtime unavailable menyebabkan login maintenance ditolak.
- Polisi Maintenance MFA `DISABLED` ditolak sebelum pending transaction.
- Developer grant dan Maintenance Mode terus disemak sebelum/selepas finalize.
- Administrator masih memerlukan status Admin 2FA serta grant `ADMIN_ACCESS`.
- Login pengguna biasa kekal menggunakan `PdoUserMfaPolicyReader` dan
  `UserMfaPrimaryAuthDecision`.
- Faktor, ACL, sesi, polisi User MFA dan Admin Step-Up tidak dimutasi oleh Fasa 3.

## 4. Boundary

UI dan worker adalah di luar skop Fasa 3. Fasa ini tidak menyediakan mutation
polisi baharu, emergency bypass lifecycle, grace period, maker-checker atau
auto-restore. Semua jadual MR2 selain singleton Maintenance MFA kekal kosong.

## 5. Verification

```bash
php tools/user_mfa_resilience_mr3_policy_separation_contract.php
php tools/maintenance_developer_phase5_contract.php
php tools/maintenance_mode_contract.php
php tools/user_login_mfa_u3_contract.php
php tools/user_login_mfa_u4_contract.php
php tools/user_login_mfa_u5_contract.php
```

Manual UAT yang paling bernilai selepas owner review ialah controlled database
transaction/rehearsal yang menetapkan User MFA `OFF`, membuktikan login biasa
password-only dan kedua-dua maintenance login masih meminta faktor, kemudian
rollback tepat kepada polisi asal. UAT mutation tersebut memerlukan kelulusan
berasingan.

## 6. Gate Fasa 3

- [x] Domain policy, PDO reader dan runtime gate berasingan.
- [x] Primary maintenance login dipisahkan daripada User MFA mode.
- [x] Pending challenge maintenance dipisahkan daripada User MFA mode.
- [x] Admin Step-Up dan developer grant invariant dikekalkan.
- [x] Committed runtime kekal fail closed.
- [x] Automated regression lengkap.
- [x] Owner mengesahkan hasil Fasa 3 bagi login pengguna dan Administrator maintenance.
- [x] Controlled OFF/recovery UAT dibenarkan dan disahkan.
- [ ] Developer maintenance browser UAT — deferred kerana tiada active grant/akaun ujian.

## 7. Evidence controlled UAT

Controlled UAT pada 7 September 2026 menggunakan urutan berikut:

1. User MFA `ENFORCED` version 6 ditukar melalui UI kepada `OFF` version 7
   menggunakan `ONEID-MR3-UAT-OFF-20260907-01`;
2. 69 pending/verified transaction dan 7 challenge direvoke secara atomik,
   termasuk purge OTP terminal;
3. login password pengguna biasa berjaya tanpa faktor kedua;
4. Maintenance Mode `SCHEDULED` diaktifkan bagi window 22:00–22:15 MYT;
5. login khas Administrator berjaya melalui faktor Maintenance MFA ketika User
   MFA masih `OFF`, dengan audit `ADMIN_ACCESS` correlation
   `a3ed8faeab65e8d0`;
6. User MFA dipulihkan melalui UI kepada `ENFORCED` version 8 menggunakan
   `ONEID-MR3-UAT-RESTORE-20260907-01`; dan
7. 10 faktor TOTP berstatus `ACTIVE` kekal sebelum dan selepas restore.

Semasa UAT dua defect sedia ada pada global shutdown ditemui dan dibetulkan:

- identifier session Administrator berbentuk nombor staf kini dipetakan kepada
  internal `user_tbl.u_id` bagi foreign key, sementara audit kekal menggunakan
  public staff ID; dan
- revocation e-mel OTP kini memadam `otp_hash` serentak supaya mematuhi terminal
  challenge material constraint.

Browser UAT developer tidak dianggap lulus tanpa bukti. Automated developer
maintenance contract kekal `17/17 PASS`; browser UAT menunggu akaun dengan
credential yang boleh digunakan dan grant aktif yang singkat.
