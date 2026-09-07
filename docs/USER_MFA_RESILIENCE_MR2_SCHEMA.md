# MR2 — Dormant Schema, Data Dictionary dan Rollback

**Tarikh:** 7 September 2026
**Fasa:** 2 — schema sahaja
**Status:** STAGING MIGRATION APPLIED / DORMANT / POST-CHECK PASS
**Dependency:** MR1 disahkan owner pada 7 September 2026

**Migration staging:** 7 September 2026
**Change reference:** `ONEID-MR2-STAGING-20260907-01`
**Backup reference:** `ONEID-MD9-BACKUP-20260907-210103`

## 1. Boundary

Fasa ini menyediakan migration dan contract. Ia tidak mengubah service, login,
UI, worker, runtime atau polisi operasi staging. Migration juga belum dibenarkan
sehingga owner memberi approval khusus selepas semakan.

## 2. ERD ringkas

```text
user_tbl
  |-- maintenance_mfa_policy.updated_by
  |-- maintenance_mfa_policy_history.changed_by
  |-- user_mfa_policy_change_requests.requested_by / approved_by
  `-- user_mfa_policy_change_approvals.decided_by

user_mfa_policy_change_requests
  |-- user_mfa_policy_change_approvals
  `-- user_mfa_policy_transition_runs
```

Jadual User MFA/faktor/challenge sedia ada tidak dipindah atau ditulis oleh
migration. Constraint `user_login_mfa_policy.policy_mode` sahaja diperluas
untuk menerima `EMERGENCY_BYPASS`.

## 3. Data dictionary

### `maintenance_mfa_policy`

Singleton polisi yang bebas daripada User MFA. Initial row ialah `ENFORCED`,
e-mel wajib dan TOTP tersedia. Row ini masih dormant sehingga wiring Fasa 3.

### `maintenance_mfa_policy_history`

Audit immutable bagi setiap perubahan polisi Maintenance MFA. Menyimpan JSON
before/after, actor, reason, reference, correlation dan source IP.

### `user_mfa_policy_change_requests`

Lifecycle permintaan mode, jadual, grace/immediate, restore state dan approval.
Generated unique slot menghadkan satu request terbuka bagi setiap environment.
Constraint memastikan bypass mempunyai restore mode dan tamat dalam maksimum
8 jam. Production self-approval ditolak pada lapisan database dan mesti turut
ditolak oleh service.

Maker-checker production melebihi 2 jam akan dikuatkuasakan oleh domain service
dalam Fasa 3/4 kerana constraint temporal merentas role dan duration lebih jelas
serta boleh diaudit pada service boundary. Database tetap melarang requester
menjadi approver bagi mana-mana approval production.

### `user_mfa_policy_change_approvals`

Rekod keputusan immutable dengan digest payload dan expected version. Sebarang
perubahan payload/window menghasilkan digest baharu dan approval lama tidak sah.

### `user_mfa_policy_transition_runs`

Rekod setiap cubaan worker untuk activate, grace revoke atau restore. Unique
request/type/attempt menyokong retry bounded dan audit idempotency. Planned dan
executed counts tidak mengandungi identifier atau secret pengguna.

## 4. Dormant dan compatibility guarantees

- Tiada trigger, event atau scheduled job dicipta.
- Tiada row sedia ada diubah kecuali metadata CHECK constraint.
- Initial Maintenance MFA policy tidak dibaca oleh code release semasa.
- User MFA kekal pada mode dan version semasa selepas migration.
- Faktor, preference, transaction, challenge, category, pilot dan exemption
  tidak disentuh.
- Down migration memerlukan tiada request terbuka dan tiada mode
  `EMERGENCY_BYPASS`.

## 5. Preflight sebelum migration

1. Backup aplikasi dan database.
2. Sahkan target database/environment secara eksplisit.
3. Sahkan jadual User MFA asas serta `user_tbl` wujud.
4. Sahkan constraint `chk_user_mfa_policy_mode` wujud tepat sekali.
5. Sahkan tiada jadual MR2 separa wujud.
6. Rekod row dan checksum polisi User MFA semasa.
7. Jalankan schema contract.
8. Jalankan migration dalam controlled window.
9. Sahkan initial Maintenance MFA `ENFORCED` dan User MFA tidak berubah.

## 6. Rollback checklist

1. Hentikan worker MR2 jika sudah diperkenalkan oleh fasa kemudian.
2. Sahkan tiada request `PENDING_APPROVAL`, `APPROVED` atau `ACTIVE`.
3. Sahkan User MFA bukan `EMERGENCY_BYPASS`; restore dahulu jika perlu.
4. Eksport history, approvals dan transition runs sebagai evidence.
5. Ambil backup database baharu.
6. Jalankan down migration child-to-parent.
7. Sahkan constraint lama menerima empat mode asal sahaja.
8. Sahkan User MFA mode/version/faktor dan login regression tidak berubah.

## 7. Verification yang perlu dijalankan selepas migration

```bash
php tools/user_mfa_resilience_mr2_schema_contract.php
```

Database verification tool/migrator hanya akan disediakan atau dijalankan pada
controlled migration selepas approval owner. Contract semasa ialah static,
zero-mutation dan boleh dijalankan sebelum migration.

## 8. Gate Fasa 2

- [x] Up/down migration disediakan.
- [x] Data dictionary dan ownership direkodkan.
- [x] Maintenance MFA initial policy fail-closed dan dormant.
- [x] Emergency bypass mempunyai expiry/restore constraint.
- [x] Production self-approval ditolak.
- [x] Worker retry/audit schema disediakan.
- [x] Rollback precondition dan checklist disediakan.
- [x] Owner mengesahkan schema.
- [x] Backup staging disahkan melalui restore reconciliation 54 jadual.
- [x] Owner membenarkan migration staging secara khusus.
- [x] Migration dan post-migration verification selesai.

Fasa 3 tidak boleh bermula hanya kerana fail schema telah disediakan.

## 9. Evidence migration staging

- Target disahkan: environment `staging`, database `oneiddb`, MySQL `8.0.41`.
- Backup penuh: 87,664,930 bytes; SHA-256
  `4f7ac7293f70a2dd8eceb5fe5993ece78d6d7d66ea2d5f1ac02db56d769bc4f7`.
- Restore rehearsal: 54 jadual, exact row-count reconciliation `PASS`, restore
  target sementara telah dibuang dan source mutation `0`.
- Jadual MR2 selepas migration: `5/5`.
- User MFA kekal `ENFORCED`, e-mel `1`, TOTP `1`, version `6`.
- Maintenance MFA initial row: `ENFORCED`, e-mel `1`, TOTP `1`, version `1`.
- Change/approval/transition history kosong; tiada operasi atau worker aktif.
- MR2 static schema contract: `13/13 PASS`.
- U1 schema contract, U3 isolated characterization, U4 e-mel OTP, U5 TOTP,
  Admin OTP dan Admin TOTP: `PASS`.

Suite agregat legacy masih mengandungi assertion dormant yang tidak sepadan
dengan staging aktif (`ENFORCED`/MyDigital ID aktif). Kegagalan assertion itu
telah diasingkan; ujian domain berkaitan lulus dan migration tidak mengubah
runtime, policy version, token, challenge atau faktor pengguna.
