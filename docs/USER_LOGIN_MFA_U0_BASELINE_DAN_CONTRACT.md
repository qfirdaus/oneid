# User Login MFA U0 — Baseline, Threat Model dan Contract

**Tarikh:** 29 Julai 2026

**Rujukan:** `ONEID-USER-MFA-U0-20260729-01`

**Status:** `PASS / CLOSED FOR U0`

**Mutation:** tiada schema, configuration, endpoint atau activation

## 1. Boundary U0

U0 mengunci baseline dan contract sebelum dormant implementation:

- polisi global OFF/ENROLLMENT/PILOT/ENFORCED;
- OTP e-mel wajib apabila 2FA aktif;
- Microsoft Authenticator optional/global kill switch;
- `PASSWORD_ONLY`;
- pending-login sebelum token/cookie/session;
- self-service factor dan existing-Admin recovery;
- readiness e-mel;
- threat model;
- audit-event map;
- SOP recovery; dan
- regression Admin OTP/TOTP.

U0 tidak membenarkan U1 migration atau runtime activation.

## 2. Baseline contract

| Perkara | Keputusan |
|---|---|
| Committed mode | `OFF` |
| Login scope | `PASSWORD_ONLY` |
| Pending login TTL | 300 saat |
| OTP TTL | 300 saat |
| Maksimum percubaan | 5 |
| Resend cooldown | 60 saat |
| Hourly send limit | 10 |
| E-mel | Wajib bagi mode selain `OFF` |
| TOTP | Optional dan boleh dihentikan global |
| Token/cookie/session | Hanya selepas faktor kedua lulus |
| MyDigital ID | Tidak dicabar User MFA |
| Admin recovery | Role Administrator sedia ada + Admin Step-Up |

## 3. Readiness e-mel read-only

Tool:

```bash
php tools/user_login_mfa_u0_email_readiness.php
```

Snapshot development/staging pada 29 Julai 2026:

| Populasi aktif | Jumlah | E-mel sah | Tiada/tidak sah |
|---|---:|---:|---:|
| Semua pengguna biasa | 6,551 | 6,545 | 6 |
| Pensyarah | 403 | 403 | 0 |
| Staf Pentadbiran | 656 | 656 | 0 |
| Pelajar | 5,492 | 5,486 | 6 |

Tool hanya mengeluarkan agregat, `raw_email_output=0` dan
`mutation_statements=0`. Enam pelajar perlu dibetulkan melalui sistem sumber
pelajar dan direconcile sebelum `PILOT_ENFORCED` atau `ENFORCED`; ia bukan
blocker untuk U1 dormant implementation.

Owner data:

- e-mel staf/pensyarah: pemilik sistem sumber staf;
- e-mel pelajar: pemilik sistem sumber pelajar/ODL berkaitan;
- reconciliation OneID: OneID Administrator/System Analyst/DBA; dan
- Helpdesk tidak mengubah e-mel terus dalam OneID.

## 4. Threat model

| Ancaman | Kawalan wajib | Contract U7 |
|---|---|---|
| Token/session sebelum MFA | Pending transaction; finalizer selepas one-use verify | Zero token/cookie/session sebelum faktor |
| OTP brute force | Argon2id, TTL, 5 attempts, cooldown dan multi-axis rate limit | Wrong/expired/rate-limit/concurrency |
| OTP replay/resend lama | Consume one-use; resend revoke challenge lama | Replay dan old-code rejection |
| TOTP replay | Atomic lock `last_used_time_step` | Same-step replay rejection |
| Cross-browser/session theft | Session binding + browser digest + rotate session ID | Cross-browser/fixation test |
| Enumeration | Masked destination dan mesej generik | Same response envelope |
| Secret/QR leakage | Encryption, local QR, no-store, no URL/log | PII/secret scan |
| Admin endpoint exposed to user | Separate User MFA boundary dan ownership check | Authorization/IDOR test |
| User changes another factor | Target derived from authenticated user | IDOR negative test |
| Recovery account takeover | Official identity SOP, ticket, Admin Step-Up, verifier | Recovery authorization test |
| Global lockout | OFF/enrollment/pilot, e-mel readiness dan kill switch | Mode-transition tests |
| TOTP shutdown traps user | OTP e-mel mandatory dan fallback | Global TOTP OFF test |
| Audit failure hides mutation | Mandatory audit in transaction | Audit-failure rollback |
| SMTP partial failure | Revoke unsent challenge; no login finalization | Delivery compensation |
| MyDigital ID double challenge | Explicit `PASSWORD_ONLY` | MyDigital ID parity |
| ACL expansion | OneID category/ACL remains authoritative | Password-vs-MFA ACL parity |

Critical stop conditions ialah authorization bypass, token/session sebelum MFA,
raw secret/OTP/PII exposure, session fixation atau ACL expansion.

## 5. Audit-event map

Canonical event names sebelum numeric event ID diperuntukkan:

| Event | Outcome minimum |
|---|---|
| `USER_MFA_PRIMARY_AUTH_PENDING` | created, rejected, expired, revoked |
| `USER_MFA_EMAIL_CHALLENGE` | requested, sent, delivery_failed, rate_limited |
| `USER_MFA_EMAIL_VERIFY` | verified, rejected, expired, replayed |
| `USER_MFA_TOTP_VERIFY` | verified, rejected, replayed, unavailable |
| `USER_MFA_LOGIN_COMPLETE` | success, finalization_failed |
| `USER_MFA_FACTOR_ENROLL` | started, confirmed, expired, cancelled |
| `USER_MFA_FACTOR_REVOKE` | self_service, admin_recovery, global_policy |
| `USER_MFA_PREFERENCE_CHANGE` | email_otp, totp |
| `USER_MFA_ADMIN_RECOVERY` | approved, rejected, completed, failed |
| `USER_MFA_POLICY_CHANGE` | off, enrollment, pilot_enforced, enforced |
| `USER_MFA_RETENTION_PURGE` | previewed, completed, failed |

Medan minimum:

- actor type dan safe actor ID;
- safe target user ID;
- outcome/reason code;
- factor/mode canonical;
- correlation ID;
- timestamp dan IP audit yang tervalidasi;
- ticket/change/reference jika berkaitan; dan
- planned/executed/audited count untuk bulk/purge.

Dilarang: raw OTP/TOTP, secret/QR, password, session/cookie/token, NRIC,
authorization code dan e-mel penuh.

Numeric event ID hanya diperuntukkan dalam U1 selepas collision check terhadap
event catalog live.

## 6. SOP recovery pengguna

### 6.1 Self-service sebagai laluan utama

Pengguna menggunakan TOTP semasa atau OTP e-mel rasmi untuk menukar/revoke
factor. Selepas revoke/reset, semua sesi aktif pengguna direvoke dan pengguna
login/enroll semula.

### 6.2 Admin recovery apabila semua faktor hilang

1. Pengguna membuka tiket melalui saluran rasmi.
2. Administrator merekod ticket/reference dan tidak meminta OTP/TOTP/secret.
3. Identiti dibandingkan dengan ID pengguna serta rekod authoritative melalui
   pemilik sistem sumber; e-mel/telefon baharu yang diberi pemohon sahaja tidak
   memadai.
4. Administrator kedua bertindak sebagai verifier bagi pasukan dua orang.
5. Pelaksana melakukan fresh Admin Step-Up
   `SECURITY_CONFIGURATION_CHANGE`.
6. UI memaparkan target, reason, ticket dan impact session; typed confirmation
   diperlukan.
7. Sistem revoke factor/pending challenge dan semua sesi aktif target dalam
   transaction dengan mandatory audit.
8. Admin tidak enroll factor bagi pihak pengguna.
9. Pengguna login semula dan enroll sendiri.
10. Pelaksana serta verifier reconcile audit outcome.

Jika verifier tidak tersedia, recovery menunggu kecuali prosedur incident
break-glass organisasi diluluskan dan direkod berasingan. Break-glass tidak
menjadi bypass kekal.

## 7. Regression baseline

U0 membetulkan fixture contract Admin Email OTP supaya threshold hourly selari
dengan runtime/default `10`, bukan nilai historical `5`.

```text
Admin Email OTP service: 15/15 PASS
Admin rate-limit config: 6/6 PASS
Admin TOTP primitive: 10/10 PASS
Admin TOTP lifecycle/service: PASS
```

Pembetulan hanya mengubah fixture test; runtime Admin Step-Up tidak berubah.

## 8. U0 exit checklist

- [x] Keputusan konfigurasi/invariant dikunci.
- [x] Scope `PASSWORD_ONLY` dikunci.
- [x] Admin OTP drift dibetulkan dan baseline hijau.
- [x] Aggregate e-mel direfresh secara read-only.
- [x] Owner role sistem sumber dan reconciliation direkod.
- [x] Threat model serta stop conditions direkod.
- [x] Audit-event map direkod.
- [x] SOP self-service/admin recovery direkod.
- [x] Pelan U0–U9 dan operational baseline direkod.
- [x] Zero schema/runtime mutation.

## 9. Keputusan U0

U0 ialah `PASS / CLOSED`. U1 boleh disediakan secara lokal/dormant hanya selepas
authorization khusus U1. Sebelum schema apply staging, masih wajib:

- change dan backup reference sebenar;
- window 60 minit;
- formal retention confirmation;
- isolated restore/purge rehearsal;
- migration review; dan
- committed/default mode `OFF`.

Global activation kekal `NOT AUTHORIZED`.
