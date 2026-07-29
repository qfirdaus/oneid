# User Login MFA — Shared Database Integration Readiness

**Tarikh:** 30 Julai 2026

**Status:** `MIGRATION APPLIED / VERIFIED / MODE OFF`

WSL dan staging menggunakan database yang sama. Oleh itu semua DDL/DML dari
WSL dianggap perubahan staging.

## Bukti semasa

`tools/user_login_mfa_shared_db_preflight.php` hanya menjalankan `SELECT`
terhadap `information_schema` dan runtime configuration.

```text
Shared database: yes
user_tbl: present / InnoDB
MySQL 8 compatible: yes
User MFA tables present: 6
User MFA tables missing: 0
Partial schema: no
Mode: OFF
Schema apply: disabled
Activation authorized: false
user_tbl rows/definition changed: no
```

## Keputusan

Migration dilaksanakan dalam window 30 minit menggunakan:

```text
Change reference: ONEID-USER-MFA-U1-20260730
Backup reference: ONEID-DB-BACKUP-20260730-U1
Expected execution: 15–25 minit
Rollback owner: pelaksana teknikal
Verifier: Administrator OneID
```

Post-migration reconciliation:

```text
user_login_mfa_policy: 1 default row
policy_history/factors/preferences/transactions/challenges: 0 rows
user_tbl row count: unchanged
user_tbl definition: unchanged
isolated apply/down rehearsal: 6/6 PASS
terminal OTP purge constraint: applied / verified
audit event catalog: 11/11, IDs 55–65, no collision
User MFA audit rows: 0
```

Migration tidak mengaktifkan User MFA. Service dispatch live belum dipautkan,
mode kekal `OFF` dan `ACTIVATION_AUTHORIZED=false`.

Route action kini berdaftar pada centralized CSRF/auth guard tetapi dispatch
service masih dormant. Ujian HTTP sebenar membuktikan invalid CSRF ditolak
`403`, manakala request sah ketika `OFF` ditolak secara controlled dengan
`409 USER_MFA_NOT_ACTIVE`.
