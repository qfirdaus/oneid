# User Login MFA — Pelan Implementasi Berfasa

**Tarikh:** 29 Julai 2026

**Rujukan:** `ONEID-USER-MFA-PLAN-20260729-01`

**Skop:** pengguna biasa (`u_type=0`), login kata laluan, OTP e-mel dan
Microsoft Authenticator (TOTP)

**Status:** `GO FOR U0 PLANNING / IMPLEMENTATION NOT YET AUTHORIZED`

**Activation:** `NOT AUTHORIZED`; committed default mesti kekal `OFF`

## 1. Keputusan owner

Owner meluluskan model berikut:

1. Administrator sedia ada sahaja mengawal feature; tiada role admin baharu.
2. Pengguna boleh enroll, memilih, menukar dan revoke Microsoft Authenticator
   sendiri melalui halaman Keselamatan Akaun.
3. Admin recovery hanya digunakan apabila pengguna kehilangan akses kepada
   Authenticator dan OTP e-mel.
4. User Login 2FA mempunyai master control global.
5. Jika master control `OFF`, login kata laluan kembali kepada flow biasa tanpa
   faktor kedua.
6. Jika master control bukan `OFF`, OTP e-mel wajib aktif.
7. Microsoft Authenticator ialah faktor tambahan yang boleh diaktifkan atau
   dihentikan secara global oleh Administrator.
8. Menutup Authenticator menghentikan enrollment dan verification TOTP tetapi
   tidak memadam faktor pengguna.
9. Menutup keseluruhan User Login 2FA membatalkan pending MFA transaction tetapi
   tidak memadam faktor atau audit history.
10. Polisi awal ialah `PASSWORD_ONLY`; MyDigital ID tidak menerima challenge
    MFA OneID tambahan.

## 2. Model konfigurasi dan invariant

Medan konfigurasi canonical yang dicadangkan:

```text
user_login_mfa_mode =
  OFF | ENROLLMENT | PILOT_ENFORCED | ENFORCED

user_login_mfa_scope = PASSWORD_ONLY
user_login_mfa_email_enabled = true
user_login_mfa_totp_enabled = true | false
user_login_mfa_pending_ttl_seconds = 300
user_login_mfa_otp_ttl_seconds = 300
user_login_mfa_max_attempts = 5
user_login_mfa_resend_cooldown_seconds = 60
user_login_mfa_hourly_send_limit = 10
```

Invariant server-side:

- mode selain `OFF` hanya sah apabila `email_enabled=true`;
- `email_enabled` tidak boleh dimatikan melalui UI apabila mode selain `OFF`;
- `totp_enabled=false` menolak enrollment, selection dan verification TOTP;
- UI tidak boleh menjadi satu-satunya enforcement;
- `PASSWORD_ONLY` disimpan secara eksplisit;
- committed default sentiasa `OFF`;
- activation memerlukan readiness reference dan change window;
- configuration update menggunakan version/optimistic concurrency; dan
- no-op tidak menghasilkan audit mutation.

Matriks tingkah laku:

| Mode | OTP e-mel | Authenticator | Login kata laluan |
|---|---|---|---|
| `OFF` | Tidak dicabar | Tidak dicabar | Terus finalisasi selepas password sah |
| `ENROLLMENT` | Tersedia untuk ujian/recovery | Pengguna boleh enroll jika global TOTP `ON` | Belum enforced |
| `PILOT_ENFORCED` | Wajib tersedia | Optional jika global TOTP `ON` | Faktor kedua wajib bagi allowlist |
| `ENFORCED` | Wajib tersedia | Optional jika global TOTP `ON` | Faktor kedua wajib bagi semua pengguna layak |

## 3. Kesan perubahan global

### 3.1 Master User Login 2FA ditutup

Apabila mode berubah kepada `OFF`:

- challenge baharu tidak boleh dicipta;
- pending login transaction/challenge direvoke;
- login selepas perubahan kembali kepada password biasa;
- faktor TOTP tersimpan kekal encrypted dan dormant;
- audit/factor history tidak dipadam;
- sesi yang sudah sah tidak direvoke secara pukal;
- MyDigital ID tidak berubah; dan
- perubahan memerlukan Admin Step-Up, reason, reference dan audit.

### 3.2 Microsoft Authenticator ditutup

Apabila `totp_enabled=false`:

- pilihan Authenticator hilang daripada login dan Account Security;
- enrollment/confirmation TOTP baharu ditolak;
- pending TOTP challenge dibatalkan;
- verification TOTP ditolak walaupun factor masih `ACTIVE`;
- pengguna menggunakan OTP e-mel;
- preference efektif fallback kepada `EMAIL_OTP`;
- encrypted factor tidak dipadam atau direvoke; dan
- re-enable boleh menggunakan factor sedia ada selepas validation polisi.

Jika management mengarahkan pemansuhan kekal dan mahu semua factor direvoke,
itu ialah operasi berasingan yang memerlukan impact Preview, typed confirmation,
session policy, backup dan audit count.

## 4. Aliran pengguna

### 4.1 Login password dengan MFA

```text
Password sah
  -> semak account active dan polisi User MFA
  -> OFF / out of scope: finalisasi login sedia ada
  -> enforced: cipta pending primary-auth transaction
  -> pilih EMAIL_OTP atau TOTP yang dibenarkan
  -> verify + consume transaction one-use
  -> regenerate session ID
  -> baru cipta token, cookie SSO dan authenticated session
```

Token/cookie/session tidak boleh diwujudkan sebelum faktor kedua berjaya.

### 4.2 Self-service Authenticator

```text
Pengguna login
  -> Keselamatan Akaun
  -> fresh password atau verified factor
  -> jana QR lokal/no-store
  -> scan Microsoft Authenticator
  -> sahkan kod pertama
  -> factor ACTIVE
```

Untuk menukar/revoke:

```text
verified TOTP semasa atau OTP e-mel
  -> revoke factor lama
  -> revoke semua sesi aktif pengguna
  -> wajib login semula
  -> enroll factor baharu sendiri jika diperlukan
```

Pengguna tidak boleh mengubah e-mel rasmi dari halaman MFA.

### 4.3 Admin recovery

Tiada role `USER_MFA_RECOVERY`. Kedua-dua Administrator sedia ada menggunakan
role yang sama. Recovery wajib:

- hanya apabila self-service tidak boleh digunakan;
- pengesahan identiti melalui SOP diluluskan;
- nombor tiket/reference dan reason;
- fresh Admin Step-Up;
- confirmation yang jelas;
- revoke factor/pending challenge;
- revoke semua sesi aktif pengguna;
- mandatory audit actor/target/outcome/correlation ID; dan
- pengguna enroll semula sendiri.

Admin tidak boleh melihat secret, QR atau kod pengguna dan tidak boleh enroll
Authenticator bagi pihak pengguna.

## 5. Polisi keselamatan yang diluluskan

| Polisi | Baseline |
|---|---|
| Pending login TTL | 5 minit |
| OTP TTL | 5 minit |
| Percubaan maksimum | 5 bagi setiap challenge |
| Resend cooldown | 60 saat |
| Hourly send limit | 10 per user/session/IP/destination policy |
| Resend | Membatalkan challenge lama |
| Enrollment window | 30 hari |
| Pilot enforced observation | Minimum 7 hari |
| Post-change observation | Minimum 72 jam |
| Polisi sesi semasa global activation | New-login-only; tiada mass revoke |
| Reset/revoke factor | Revoke semua sesi target |
| Challenge operational retention | 30 hari; hash dipadam selepas terminal state |
| Security/recovery audit retention | 365 hari, tertakluk kelulusan Security/DBA |
| MyDigital ID | `PASSWORD_ONLY`; dikecualikan daripada User MFA |

## 5.1 Keputusan operasi owner

Keputusan berikut diluluskan sebagai baseline perancangan:

| Perkara | Keputusan |
|---|---|
| Saiz pilot | 5–10 pengguna; sasaran 8 |
| Komposisi pilot | Staf, pensyarah, pelajar tempatan dan pelajar antarabangsa |
| Tarikh tepat | `TBD` selepas development selesai |
| Enrollment | Bermula selepas deployment staging lulus |
| Pilot observation | Minimum 7 hari |
| Post-change observation | Minimum 72 jam |
| Monitoring | Semakan manual dashboard/log dan e-mel operasi kepada kedua-dua Administrator |
| Critical SLA | Acknowledge/tindakan awal dalam 15 minit |
| High SLA | Acknowledge/tindakan awal dalam 30 minit |
| Warning SLA | Semak dalam 4 jam bekerja |
| Change window | 60 minit |
| Backup/change reference | Dijana pada tarikh execution |
| Rollback owner | Administrator OneID/System Analyst/DBA yang menjalankan perubahan |
| Verifier | Administrator kedua |
| Challenge metadata retention | 30 hari |
| OTP hash | Dipadam selepas terminal state |
| Security/recovery audit retention | 365 hari |
| Retention approval | Formal confirmation sebelum migration staging |
| Restore/purge rehearsal | Wajib sebelum U1 schema apply staging; bukan blocker U0 |
| Management activation reference | Belum diterbitkan |
| Global enforcement | `NOT AUTHORIZED` |

Nama/ID sebenar pilot, credential, alamat e-mel, nombor telefon atau data
sensitif tidak dimasukkan ke Git. Gunakan reference `PILOT-01` hingga
`PILOT-08` dalam evidence repository-safe.

## 6. Schema additive

Cadangan table:

- `user_mfa_factors`;
- `user_mfa_preferences`;
- `user_login_mfa_transactions`;
- `user_login_mfa_challenges`;
- `user_login_mfa_policy_history`; dan
- event audit khusus User Login MFA.

Keperluan:

- FK kepada `user_tbl.u_id`;
- TOTP secret encrypted, nonce dan key version;
- factor status `PENDING`, `ACTIVE`, `REVOKED`;
- satu active TOTP factor per pengguna bagi release pertama;
- session/browser binding, TTL, attempts, consumed/revoked timestamps;
- unique correlation ID;
- locked `last_used_time_step` untuk anti-replay;
- OTP hanya disimpan sebagai Argon2id hash;
- tiada raw OTP/TOTP/secret/session ID;
- migration up/down dan isolated rehearsal; dan
- tiada perubahan struktur/profile `user_tbl`.

## 7. Pelan fasa U0–U9

### U0 — Contract, baseline dan keputusan

Deliverable:

- lock configuration matrix dan invariant;
- refresh aggregate e-mel valid/invalid;
- betulkan drift contract Admin Email OTP;
- threat model pending-login;
- mapping audit event;
- acceptance matrix password/MyDigital ID/Admin Step-Up; dan
- isi operational owner/reference yang masih pending.

Exit:

- semua contract baseline hijau;
- no code/schema/runtime mutation;
- checklist U0 bertanda `PASS`;
- approval untuk dormant U1 sahaja.

### U1 — Dormant schema dan configuration

Deliverable:

- additive migration up/down;
- policy schema/history;
- default `OFF`;
- repository interfaces;
- isolated migration/recovery rehearsal; dan
- no public endpoint wiring.

Exit:

- `user_tbl` unchanged;
- zero activation;
- backup/change reference tersedia sebelum live staging apply;
- rollback rehearsal lulus.

### U2 — Generic security primitives

Deliverable:

- reuse/refactor TOTP, encryption, QR lokal dan anti-replay;
- generic OTP hashing, expiry, attempt dan rate-limit primitive;
- actor boundary `ADMIN_STEP_UP` berasingan daripada `USER_LOGIN_MFA`; dan
- non-regression Admin Step-Up.

Exit:

- Admin 2FA contract kekal lulus;
- user primitive tidak boleh mengakses endpoint/table admin;
- no secret/OTP output.

### U3 — Pending login foundation

Deliverable:

- password success menghasilkan pending transaction apabila enforced;
- one-use consume, session binding dan expiry;
- finalizer hanya selepas faktor lulus;
- cleanup partial/expired transaction;
- mode `OFF` parity; dan
- MyDigital ID `PASSWORD_ONLY` parity.

Exit:

- bukti zero token/cookie/authenticated session sebelum MFA;
- fixation/replay/cross-browser tests lulus;
- feature kekal dormant.

### U4 — OTP e-mel

Deliverable:

- request/resend/verify;
- masked destination;
- Argon2id hash-only storage;
- TTL/attempt/cooldown/hourly limits;
- SMTP failure compensation;
- enumeration-safe UX; dan
- audit request/sent/verified/rejected tanpa OTP.

Exit:

- success, wrong, expired, replay, resend dan rate-limit tests lulus;
- e-mel invalid masuk controlled recovery, bukan bypass.

### U5 — Microsoft Authenticator dan self-service

Deliverable:

- Account Security enrollment/confirmation;
- QR lokal/no-store;
- factor preference;
- anti-replay verification;
- self reset/revoke;
- global TOTP kill switch;
- existing-Admin recovery flow; dan
- session revocation selepas reset/revoke.

Exit:

- self-service happy/failure paths lulus;
- admin tidak boleh melihat/enroll secret pengguna;
- global TOTP `OFF` fallback kepada OTP e-mel terbukti.

### U6 — UI BM/English dan accessibility

Deliverable:

- Admin Configuration User MFA;
- challenge/factor selection;
- Account Security;
- recovery messages;
- loading/empty/success/error state;
- keyboard/screen-reader/mobile acceptance; dan
- canonical factor/mode code tidak diterjemah.

Exit:

- locale parity;
- server-derived state;
- no sensitive value pada URL/HTML/log.

### U7 — Security dan regression

Minimum suite:

- password OFF/enrollment/pilot/enforced;
- e-mel dan TOTP success/failure/replay;
- CSRF, fixation, session/browser isolation;
- SMTP/provider failure;
- reset/revoke/session invalidation;
- MyDigital ID parity;
- Admin Step-Up parity;
- SSO/token/ACL/multi-session;
- rate-limit concurrency;
- schema rollback;
- secret/PII scan; dan
- global disable/enable behavior.

Exit: zero Critical/High dan full suite hijau.

### U8 — Enrollment dan pilot

Urutan:

1. betulkan/reconcile akaun tanpa e-mel sah;
2. aktifkan `ENROLLMENT` selama 30 hari;
3. pilih 30–50 pilot mewakili staf, pensyarah, pelajar tempatan dan
   antarabangsa;
4. aktifkan `PILOT_ENFORCED`;
5. observe minimum 7 hari;
6. reconcile success/rejection/recovery/helpdesk; dan
7. rollback jika threshold dilanggar.

Exit: pilot owner sign-off dan tiada unresolved Critical/High.

### U9 — Controlled enforcement

Deliverable:

- readiness count dan management reference;
- Admin Step-Up configuration change;
- typed confirmation dan impact summary;
- `ENFORCED` untuk login baharu;
- monitoring/alert aktif;
- observation minimum 72 jam; dan
- closure/rollback evidence.

Exit: owner sign-off. Global activation tidak boleh dibuat hanya kerana U0–U7
telah siap.

## 8. Admin Configuration acceptance

Paparan minimum:

- master `2FA Login Pengguna`;
- mode rollout;
- OTP e-mel dipaparkan `Wajib apabila 2FA aktif`;
- toggle Microsoft Authenticator;
- jumlah pengguna aktif/e-mel invalid/pilot/factor aktif;
- impact dan warning;
- change reason/reference;
- configuration version;
- typed confirmation bagi `PILOT_ENFORCED`, `ENFORCED`, global TOTP shutdown
  dan master shutdown; serta
- audit history.

Semua mutation memerlukan:

- Administrator sedia ada;
- fresh Admin Step-Up `SECURITY_CONFIGURATION_CHANGE`;
- authorization + CSRF;
- optimistic concurrency;
- transaction dan mandatory audit;
- fail-closed jika audit gagal; dan
- server-side validation invariant.

## 9. Monitoring dan stop conditions

Pantau:

- OTP requested/sent/failed/verified;
- wrong/expired/rate-limited challenge;
- TOTP verification/replay;
- pending transaction expiry;
- login completion failure selepas MFA;
- recovery/reset/revoke;
- unexpected factor creation;
- SMTP failure;
- ACL/session regression; dan
- helpdesk/lockout volume.

Stop dan rollback ke `OFF` jika:

- token/session dicipta sebelum MFA;
- authorization bypass;
- raw OTP/secret/PII bocor;
- lockout meluas;
- ACL lebih luas;
- audit mandatory gagal; atau
- unresolved Critical/High.

## 10. Checklist readiness

### A. Keputusan reka bentuk — `COMPLETE`

- [x] Master global OFF/ON melalui mode.
- [x] OTP e-mel wajib apabila 2FA aktif.
- [x] Microsoft Authenticator optional dan boleh dihentikan global.
- [x] Disable tidak memadam factor/history.
- [x] Self-service enroll/change/revoke.
- [x] Existing Administrator role untuk controlled recovery.
- [x] Tiada role admin baharu.
- [x] `PASSWORD_ONLY`; MyDigital ID dikecualikan.
- [x] TTL/attempt/cooldown/hourly baseline.
- [x] Enrollment/pilot/observation baseline.
- [x] New-login-only activation session policy.
- [x] Retention baseline.

### B. U0 implementation readiness — `PASS / CLOSED`

- [x] MyDigital ID Gate A staging ditutup.
- [x] Tiada authentication Critical/High yang diketahui.
- [x] Admin TOTP dan multilingual baseline hijau.
- [x] Betulkan dua assertion drift Admin Email OTP rate-limit/cooldown.
- [x] Refresh aggregate e-mel semasa secara read-only; enam pelajar perlu
      direconcile sebelum pilot/enforcement.
- [x] Rekod owner role sistem sumber e-mel staf/pelajar.
- [x] Terbitkan SOP recovery/pengesahan identiti dalam dokumen U0.
- [x] Rekod threat model dan audit-event map.
- [x] Tetapkan format change/backup reference dan window 60 minit.
- [ ] Jana reference sebenar dan dapatkan authorization dormant U1 pada tarikh execution.

### C. Pilot/activation readiness — `PARTIAL / ACTIVATION NOT AUTHORIZED`

- [x] Saiz pilot 5–10; sasaran 8 dan komposisi kategori diputuskan.
- [ ] Pilih reference peserta sebenar dan consent/support contact.
- [x] Jadual relatif enrollment, pilot 7 hari dan observation 72 jam diputuskan.
- [ ] Isi tarikh sebenar selepas development.
- [x] Manual dashboard/log, e-mel operasi dan SLA severity diputuskan.
- [ ] Sahkan alamat distribution e-mel operasi pada execution.
- [x] Window 60 minit, executing Administrator sebagai rollback owner dan
      Administrator kedua sebagai verifier diputuskan.
- [ ] Jana backup/change reference sebenar pada execution.
- [x] Retention baseline 30/365 hari dan terminal OTP deletion diputuskan.
- [ ] Dapatkan formal confirmation retention sebelum migration staging.
- [x] Restore/purge rehearsal ditetapkan sebagai gate sebelum U1 staging apply.
- [ ] Jalankan dan rekod restore/purge rehearsal.
- [ ] Dapatkan management activation reference; belum diterbitkan.

## 11. Keputusan readiness

Checklist reka bentuk/polisi telah lengkap. Checklist untuk memulakan **U0
read-only contract/baseline** juga mencukupi.

U0 telah ditutup `PASS / CLOSED` melalui
`USER_LOGIN_MFA_U0_BASELINE_DAN_CONTRACT.md`. Ia tidak memberikan authorization
U1 migration atau sebarang activation.

U1 implementation dan shared-schema migration ditutup `PASS / MODE OFF` melalui
`USER_LOGIN_MFA_U1_DORMANT_SCHEMA_DAN_CONFIG.md`. Enam table hanya diuji dalam
isolated rehearsal sebelum dipasang pada shared staging database menggunakan
change/backup reference yang diluluskan. `user_tbl` kekal tidak berubah dan mode
kekal `OFF`.

U2 generic security primitives ditutup `PASS / CLOSED` melalui
`USER_LOGIN_MFA_U2_GENERIC_SECURITY_PRIMITIVES.md`. OTP/TOTP, encryption,
anti-replay, rate-limit dan request binding diuji tanpa endpoint, database
mutation atau runtime activation.

U3 pending-login foundation dilaksanakan secara dormant melalui
`USER_LOGIN_MFA_U3_PENDING_LOGIN_FOUNDATION.md`. Coordinator mengunci
password-only scope, session/browser binding, expiry, one-use consumption dan
compensating finalizer tanpa wiring kepada login semasa.

U4 OTP e-mel login ditutup `PASS / CLOSED` secara lokal melalui
`USER_LOGIN_MFA_U4_EMAIL_OTP_LOGIN.md`. Request/resend/verify, hash-only OTP,
masked destination, rate limit, delivery compensation dan atomic pending-login
verification diuji menggunakan fake sender tanpa SMTP, database live atau
runtime wiring.

U5 Microsoft Authenticator dan self-service ditutup `PASS / CLOSED` secara
lokal melalui `USER_LOGIN_MFA_U5_TOTP_DAN_SELF_SERVICE.md`. Enrollment,
confirmation, preference, atomic anti-replay, self revoke, existing-Admin
recovery, session revocation dan global kill-switch fallback diuji tanpa
endpoint, database live atau runtime wiring.

U6 UI BM/English dan accessibility ditutup `PASS / CLOSED` secara lokal melalui
`USER_LOGIN_MFA_U6_UI_BILINGUAL_ACCESSIBILITY.md`. Challenge, Account Security,
recovery dan Admin Configuration mempunyai locale parity, server-derived state,
accessible/mobile semantics dan sensitive-data boundary tanpa route atau
runtime wiring.

U7 security/regression foundation lulus melalui
`USER_LOGIN_MFA_U7_SECURITY_DAN_REGRESSION.md`: aggregate U0–U6, Admin MFA,
MyDigital ID, CSRF/fixation gate, rollback serta secret/PII scan semuanya hijau
dan zero Critical/High foundation defect. Dormant PDO integration, routed CSRF
dan real MySQL rate-limit locking serta SMTP success/failure turut lulus.
Controlled password + OTP rehearsal membuktikan token hanya dicipta selepas MFA,
ACL parity dan immediate token revocation. U7 ditutup `PASS / CLOSED`; keputusan
ini tidak membenarkan activation atau U8 enrollment secara automatik.

Keputusan operasi pilot, jadual relatif, monitoring manual, SLA, change window,
rollback/verifier, retention dan rehearsal telah mempunyai baseline owner.
Nilai execution-specific seperti nama pilot, tarikh, alamat distribution,
backup/change reference dan management reference kekal `TBD` sehingga gate
berkaitan.

Checklist belum lengkap untuk:

- migration staging U1;
- membina endpoint aktif;
- pilot enforcement; atau
- global enforcement.

Default keselamatan sehingga gate berkenaan diluluskan:

```text
user_login_mfa_mode=OFF
user_login_mfa_email_enabled=true
user_login_mfa_totp_enabled=false
activation_authorized=false
```

Dokumen ini tidak memberi authorization untuk migration, deployment atau
activation.
