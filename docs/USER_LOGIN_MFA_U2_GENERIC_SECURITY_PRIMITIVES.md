# User Login MFA U2 — Generic Security Primitives

**Tarikh:** 30 Julai 2026

**Status:** `PASS / CLOSED LOCALLY / DORMANT`

**Runtime activation:** `0`

## Skop

U2 menyediakan primitive pengguna yang berasingan daripada service Admin:

- OTP enam digit;
- Argon2id hashing dan verification;
- rate limit user/session/IP/destination;
- resend cooldown;
- session/browser request binding;
- TOTP enrollment material;
- encryption melalui `TotpSecretCipher`;
- provisioning URI lokal melalui `Totp`; dan
- verification/anti-replay melalui last-used time-step.

Primitive generic `Totp`, `TotpKeyring` dan `TotpSecretCipher` diguna semula.
Service, table, purpose dan authorization Admin Step-Up tidak digunakan oleh
class User MFA.

## Boundary

- tiada endpoint;
- tiada login wiring;
- tiada database repository implementation;
- tiada e-mel sebenar;
- tiada QR remote;
- tiada audit event live;
- tiada schema staging mutation;
- tiada runtime activation; dan
- tiada output OTP, TOTP secret atau key material dalam contract.

Raw enrollment secret hanya dikembalikan kepada orchestration U5 yang akan
memaparkannya sekali melalui response `no-store`; encrypted material sahaja
disediakan untuk persistence.

## Exit gate

- OTP format/hash/verification lulus;
- rate-limit dan cooldown lulus;
- request binding hash-only lulus;
- encryption/provisioning URI lulus;
- TOTP verification dan replay rejection lulus;
- Admin 2FA regression kekal hijau;
- MyDigital ID regression kekal hijau; dan
- bootstrap/runtime tidak merujuk primitive U2.

## Evidence dan keputusan

```text
U2 characterization: 8/8 PASS
U2 static/lint contract: 11/11 PASS
Admin Email OTP: 15/15 PASS
Admin TOTP lifecycle/service: PASS
Admin multilingual contract: PASS
MyDigital ID security/regression: 24/24 PASS
Network calls: 0
Database mutations: 0
Runtime activation: 0
Raw secret output: 0
```

U2 ditutup `PASS / CLOSED` secara lokal. U3 boleh membina pending-login
foundation secara dormant. Tiada primitive U2 dirujuk oleh bootstrap atau flow
login semasa.
