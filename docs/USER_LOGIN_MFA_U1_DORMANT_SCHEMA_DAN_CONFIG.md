# User Login MFA U1 — Dormant Schema dan Configuration

**Tarikh:** 29 Julai 2026

**Status:** `PASS / SHARED SCHEMA APPLIED / MODE OFF`

**Live staging schema:** `APPLIED / 6 TABLES`

**Activation:** `OFF / NOT AUTHORIZED`

## Deliverable

U1 menyediakan:

- enam table additive bagi policy, history, factor, preference, pending login
  dan challenge;
- migration up/down;
- policy object dengan invariant server-side;
- repository interface dormant;
- committed runtime default `OFF`;
- schema apply gate `false`; dan
- isolated rehearsal tanpa mutation kepada schema staging.

Migration:

- `docs/migrations/20260729_user_login_mfa_u1_up.sql`
- `docs/migrations/20260729_user_login_mfa_u1_down.sql`

## Boundary

- `user_tbl` tidak diubah;
- tiada endpoint/login wiring;
- tiada e-mel dihantar;
- tiada factor pengguna live;
- tiada runtime bootstrap kepada class U1;
- OTP hanya mempunyai medan hash;
- TOTP secret wajib encrypted + nonce + key version;
- committed TOTP global default `false`; dan
- policy selain `OFF` tidak sah jika e-mel disabled.

## Sebelum staging apply

Masih diperlukan:

- formal authorization U1;
- change/backup reference sebenar;
- window 60 minit;
- retention confirmation;
- full backup integrity;
- target database verification;
- gated runner/review; dan
- rollback owner + verifier.

U1 local implementation bukan arahan untuk menjalankan SQL pada staging.

## Contract dan isolated rehearsal

```text
U1 static contract: 10/10 PASS
Isolated schema rehearsal: 6/6 PASS
Tables created: 6
Fail-closed singleton: PASS
Invalid enforced-without-email policy: BLOCKED
Dummy factor/transaction/challenge: PASS
Forbidden raw-material columns: 0
Down migration: PASS
Rehearsal database removed: yes
Live schema mutations: 0
Feature activation: 0
```

## Keputusan

U1 implementation ialah `PASS`. Shared schema migration kemudiannya diluluskan
dan dilaksanakan pada 30 Julai 2026:

```text
Change reference: ONEID-USER-MFA-U1-20260730
Backup reference: ONEID-DB-BACKUP-20260730-U1
Tables: 6/6
Default policy: OFF / PASSWORD_ONLY / email enabled / TOTP disabled
user_tbl rows changed: no
user_tbl definition changed: no
Partial schema: no
Runtime activation: 0
```

Schema kini tersedia tetapi service/route User MFA masih dormant. Sebarang
activation kekal memerlukan approval berasingan.
