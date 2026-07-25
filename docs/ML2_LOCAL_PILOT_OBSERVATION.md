# ML2 Local Pilot Observation

**Environment:** Local WSL
**URL:** `https://oneid.local`
**Authorization:** `ONEID-ML2-LOCAL-20260725-01`
**Status:** PASS / CLOSED

## Revised observation authority

Keperluan asal lima peserta dan minimum tiga hari bekerja telah digantikan
melalui `ONEID-ML2-LOCAL-20260725-02`.

- Penguji tunggal: Firdaus, System Analyst/DBA.
- Penguji yang sama melaksanakan peranan pengguna BM, pengguna English dan
  Administrator.
- Tempoh minimum tiga hari bekerja diketepikan untuk local-only testing.
- Risiko observation singkat diterima oleh approver.
- Closure dibenarkan hanya selepas semua senario wajib mempunyai evidence PASS.

## Pilot matrix tanpa PII

| Label | Locale | Peranan yang diuji oleh Firdaus | Login | Recovery | OTP UI | E-mel | Preference | Keputusan |
|---|---|---|---|---|---|---|---|---|
| SOLO-BM | `ms` | Pengguna | PASS | PASS | PASS | PASS | PASS | PASS |
| SOLO-EN | `en` | User | PASS | PASS | PASS | PASS | PASS | PASS |
| SOLO-ADMIN | `ms/en` | Administrator | PASS | PASS | PASS | PASS | PASS | PASS |

Nama, ID pengguna, IC, e-mel dan OTP tidak boleh dimasukkan ke dalam dokumen,
screenshot, log observation atau Git.

## Pemeriksaan wajib

1. BM ialah default bagi browser baharu dan invalid locale.
2. Pemilih bahasa mengubah Login tanpa menukar authentication behavior.
3. Refresh dan session baharu mengekalkan preference yang diluluskan.
4. Pengguna authenticated mempunyai satu row preference `ms` atau `en`.
5. Forgot Password mengekalkan generic account-enumeration-safe response.
6. OTP enam digit, expiry, retry dan invalid flow berfungsi dalam kedua bahasa.
7. HTML dan plain-text e-mel menggunakan bahasa penerima yang sama.
8. OTP, correlation ID, response code dan audit event kekal canonical.
9. Legacy `msg` masih wujud bersama stable `translation_key`.
10. Keyboard, focus, label dan screen reader language metadata berfungsi.
11. Dashboard User/Admin, External Sync dan Admin Step-Up UI kekal di luar Pilot.
12. Disable infrastructure mengembalikan paparan dengan selamat kepada BM.

## Evidence commands

```bash
php tools/ml2_local_observation_snapshot.php
php tools/ml2_pilot_ui_contract.php
php tools/ml2_local_preference_rehearsal.php
php tools/sc6_password_recovery_contract.php
php tools/oneid_email_template_contract.php
php tools/r52_dashboard_characterization.php
```

Snapshot hanya mengeluarkan aggregate locale count dan tidak mengeluarkan
identity pengguna.

## Closure gate

ML2 hanya boleh ditutup apabila Firdaus selesai menguji ketiga-tiga peranan,
semua pemeriksaan wajib lulus, tiada defect security/critical terbuka, dan owner
memberi keputusan `ML2 PASS / CLOSED`. Staging, Git push dan Production kekal
tidak dibenarkan oleh observation ini.

## Manual evidence response

Selepas ujian browser dan mailbox selesai, gunakan format tanpa PII berikut:

```text
ML2 SOLO OBSERVATION RESULT

SOLO-BM Login/Recovery/OTP UI/E-mail/Preference: PASS
SOLO-EN Login/Recovery/OTP UI/E-mail/Preference: PASS
SOLO-ADMIN Login/OTP security E-mail/Preference: PASS
Invalid locale fallback: PASS
Keyboard/focus/screen-reader language metadata: PASS
Authentication/authorization/ACL regression: PASS
Critical or security defects: 0

Decision: PASS / CLOSED
Tester and approver: Firdaus, System Analyst/DBA
Evidence reference: ONEID-ML2-LOCAL-20260725-02
```

## Closure evidence

Keputusan manual diterima pada 25 Julai 2026:

- SOLO-BM Login/Recovery/OTP UI/E-mail/Preference: PASS.
- SOLO-EN Login/Recovery/OTP UI/E-mail/Preference: PASS.
- SOLO-ADMIN Login/OTP security E-mail/Preference: PASS.
- Invalid locale fallback: PASS.
- Keyboard/focus/screen-reader language metadata: PASS.
- Authentication/authorization/ACL regression: PASS.
- Critical or security defects: `0`.

**Decision:** PASS / CLOSED
**Tester and approver:** Firdaus, System Analyst/DBA
**Evidence reference:** `ONEID-ML2-LOCAL-20260725-02`
