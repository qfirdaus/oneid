# User Login MFA U7 — Security dan Regression

**Tarikh:** 30 Julai 2026

**Status:** `PASS / CLOSED / NOT ACTIVATED`

**Critical/High foundation defect:** `0`

## Keputusan foundation

Aggregate suite menjalankan:

- U0 baseline dan polisi;
- U1 isolated schema apply/down rollback;
- U2 OTP/TOTP, encryption, binding dan rate-limit primitives;
- U3 OFF/ENROLLMENT/PILOT_ENFORCED/ENFORCED, pending login dan zero-token
  sebelum MFA;
- U4 OTP e-mel success, wrong, expired, replay, resend, rate limit dan sender
  failure compensation;
- U5 TOTP success/replay, self-service, admin recovery, session-revocation
  contract dan kill-switch fallback;
- U6 BM/English, enumeration-safe output, accessibility dan sensitive HTML/URL;
- CSRF synchronizer-token gate;
- session fixation/rotation gate;
- atomic rate-limit persistence contract;
- Admin Email OTP/TOTP/Step-Up regression;
- MyDigital ID `PASSWORD_ONLY` parity; dan
- source scan bagi embedded credential, private key serta sensitive debug.

```text
Aggregate commands: 19/19 PASS
U7 foundation characterization: 9/9 PASS
PDO email integration: 4/4 PASS
PDO pending/TOTP integration: 5/5 PASS
Real MySQL rate-limit lock: 3/3 PASS
Dormant route contract: 9/9 PASS
Secret/PII source scan: PASS
Critical/High foundation defect: 0
Runtime activation: 0
```

## Security correction semasa U7

- request counter persistence dinamakan semula
  `emailRequestStatsForUpdate()` dan contract mewajibkan lock/reservation bagi
  user, session, IP dan destination sehingga transaksi commit;
- state-changing request mesti `POST` dengan synchronizer CSRF token tepat;
- authenticated session ID mesti berbeza daripada pre-authentication session;
  dan
- runtime kini mempunyai explicit
  `ONEID_USER_MFA_ACTIVATION_AUTHORIZED=false`.

## Live gate execution

Semua live gate U7 telah dijalankan. Gate terakhir menggunakan controlled
interactive rehearsal oleh pemilik verified pilot identity:

- primary password diterima tanpa password dipaparkan/disimpan;
- OTP dihantar ke e-mel berdaftar dan disahkan tanpa OTP dipaparkan;
- token SSO hanya dicipta selepas OTP sah;
- ACL single/group/blacklist digest kekal tepat;
- token ujian direvoke serta-merta;
- pending transaction dan challenge menjadi `CONSUMED`;
- terminal OTP hash telah dipadam; dan
- lima User MFA audit rows direkod tanpa credential material.

Gate lain yang ditutup:

- routed endpoint CSRF: invalid token `403`, valid token ketika mode `OFF`
  menghasilkan controlled `409 USER_MFA_NOT_ACTIVE`; dan
- concurrency: dua PDO connection pada MySQL sebenar membuktikan request kedua
  menunggu singleton counter lock sehingga transaksi pertama commit; dan
- SMTP: verified pilot destination menerima controlled User MFA message;
  proses berasingan dengan provider endpoint tertutup memulangkan failure tanpa
  raw OTP, database mutation atau persistent configuration change;
- real PHP session handler: authenticated session ID dan CSRF kedua-duanya
  berputar, kemudian test session dimusnahkan; dan
- multi-session: dua token sintetik dicipta dalam transaksi bagi verified pilot
  identity, `revokeAll()` menutup kedua-duanya dan rollback mengembalikan
  baseline `0` tanpa committed token mutation.

## Shared database preflight dan integration boundary

Read-only preflight selepas pengesahan bahawa WSL dan staging berkongsi
database menghasilkan:

```text
user_tbl: present / InnoDB
MySQL 8 compatible: yes
User MFA tables: 6/6 present
Partial schema: no
Migration required: no
Mutation statements: 0
```

HTTP/runtime boundary dormant turut disediakan dan lulus `7/7`:

- schema tidak tersedia gagal tertutup;
- mode bukan `OFF` memerlukan explicit activation authorization;
- request mutation hanya menerima `POST` dan CSRF tepat;
- tepat satu action canonical;
- target self-service diambil daripada authenticated session;
- public error enumeration-safe; dan
- OTP, e-mel, session serta internal reason tidak dipulangkan.

Action boundary telah dipautkan kepada centralized guard dalam `q_func.php`;
service dispatch umum masih dormant dan berhenti pada mode `OFF`.

Migration kemudiannya diluluskan dan dijalankan menggunakan change reference
`ONEID-USER-MFA-U1-20260730` serta backup reference
`ONEID-DB-BACKUP-20260730-U1`. Post-check menunjukkan `6/6` table, hanya satu
default policy row, `user_tbl` tidak berubah dan mode kekal `OFF`.

Integration turut menemukan dan membetulkan mismatch antara terminal OTP purge
dan schema CHECK constraint. Follow-up constraint diuji secara isolated sebelum
dipasang ketika challenge table kosong. Audit event IDs 55–65 juga dipasang
selepas collision check. Controlled pilot menghasilkan lima audit rows.

PDO adapter bagi pending login, OTP e-mel dan TOTP telah diuji end-to-end dalam
database sementara dan dibersihkan. Route canonical telah didaftarkan dalam
guard sebenar tetapi service dispatch sengaja belum dipautkan: mode `OFF`
berhenti sebelum sebarang User MFA mutation atau SMTP.

## Keputusan U7

Foundation, dormant integration dan semua live gates melepasi security
regression. **U7 ditutup `PASS / CLOSED` dengan zero unresolved Critical/High.**

Keputusan ini tidak memberi authorization untuk:

- perubahan schema tambahan;
- route/runtime activation;
- enrollment pengguna;
- pilot enforcement; atau
- global enforcement.

U7 closure tidak mengaktifkan User MFA. U8 enrollment/pilot masih memerlukan
approval fasa, peserta serta jadualnya sendiri. Mode kekal `OFF`.
