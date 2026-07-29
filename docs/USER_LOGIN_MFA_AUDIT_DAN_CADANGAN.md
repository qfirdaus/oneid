# Audit dan Cadangan MFA Login Pengguna OneID

**Tarikh audit:** 26 Julai 2026
**Skop:** pengguna biasa (`u_type=0`), login kata laluan dan MyDigital ID
**Kaedah:** semakan read-only kod, schema dan agregat data development/staging
**Status:** audit dibuka semula untuk U0 planning; implementation dan activation
belum dibenarkan
**Rujukan audit:** `ONEID-USER-MFA-AUDIT-20260726-01`

> **Canonical planning note — 29 Julai 2026:** Keputusan owner terkini,
> master ON/OFF, OTP e-mel wajib, Microsoft Authenticator optional, self-service,
> existing-Admin recovery, pelan U0–U9 dan checklist readiness direkod dalam
> `USER_LOGIN_MFA_PELAN_IMPLEMENTASI_BERFASA.md`.
> Baseline operasi pilot 5–10 pengguna, monitoring manual, SLA severity,
> window 60 minit, retention 30/365 hari dan rehearsal gate turut diluluskan;
> management activation reference masih belum diterbitkan.

## 1. Objektif

Audit ini menilai kemungkinan menyediakan OTP e-mel dan TOTP melalui Microsoft
Authenticator kepada staf serta pelajar. Polisi yang dibayangkan ialah:

- apabila MFA pengguna disabled, login berfungsi seperti sekarang;
- apabila MFA pengguna enabled, login yang berada dalam skop mesti melalui
  faktor kedua sebelum sesi OneID diwujudkan;
- pengguna boleh memilih OTP e-mel atau Microsoft Authenticator; dan
- pengguna boleh mendaftar, mengesahkan, mengganti dan revoke Authenticator
  sendiri melalui halaman keselamatan akaun.

Audit ini tidak mengubah aplikasi, schema, konfigurasi atau data.

Owner telah memilih untuk menyimpan audit ini sebagai rujukan implementasi
akan datang. Tiada feature flag, endpoint, migration atau activation User Login
MFA dibenarkan hanya berdasarkan audit ini.

## 2. Keputusan ringkas

Implementasi adalah **feasible dan disyorkan secara berfasa**. Sebahagian besar
komponen Admin Step-Up boleh diguna semula pada tahap primitive dan pola
keselamatan, tetapi flow admin tidak boleh disalin terus.

Perbezaan utama:

- Admin Step-Up berlaku selepas sesi admin diwujudkan dan memberi grant untuk
  purpose tertentu.
- User Login MFA mesti berlaku selepas primary authentication tetapi sebelum
  token SSO, cookie dan authenticated session diwujudkan.

Oleh itu, ia ialah feature baharu yang berkongsi komponen keselamatan, bukan
sekadar membuka endpoint admin kepada `u_type=0`.

## 3. Komponen sedia ada yang boleh diguna semula

| Komponen | Keputusan |
|---|---|
| `Totp` | Guna semula: secret, provisioning URI, verification window dan anti-replay |
| `TotpKeyring` | Guna semula: key material kekal di luar DB/repository |
| `TotpSecretCipher` | Guna semula: encryption/decryption secret TOTP |
| QR renderer lokal | Guna semula; tiada penghantaran secret kepada servis QR luar |
| OTP enam digit + Argon2id | Guna semula sebagai pola, bukan challenge admin yang sama |
| Session/browser binding | Guna semula sebagai pola |
| Cooldown dan rate limit | Guna semula dengan limit khusus pengguna |
| Audit correlation ID | Guna semula |
| BM/English UX | Guna semula struktur locale, tambah namespace user MFA |
| Enrollment/confirm/revoke | Refactor kepada service generik atau bina adapter pengguna |

Komponen berikut kekal khusus admin:

- `AdminStepUpEmailOtpService`;
- `AdminStepUpTotpService`;
- `AdminTotpFactorService`;
- `AdminMfaPreferenceService`;
- purpose `ADMIN_ACCESS`, `SECURITY_CONFIGURATION_CHANGE` dan
  `ACTIVE_SESSION_REVOCATION`;
- jadual `admin_mfa_*` dan `admin_step_up_*`; dan
- guard yang mensyaratkan `u_type=1`.

Memasukkan pengguna biasa terus ke jadual admin akan mencampurkan dua boundary
authorization dan menyukarkan audit, recovery serta rollback.

## 4. Keadaan login semasa

### 4.1 Login kata laluan

Selepas password disahkan, `lib/q_func.php` terus:

1. melaksanakan polisi multi-session;
2. mencipta rekod `token_tbl`;
3. menetapkan cookie SSO;
4. memanggil `oneid_establish_authenticated_session()`; dan
5. mengembalikan redirect dashboard/aplikasi.

Untuk MFA pengguna, urutan ini mesti dipisahkan. Kejayaan password hanya
mewujudkan pending login transaction. Token, cookie dan authenticated session
tidak boleh diwujudkan sehingga faktor kedua lulus.

### 4.2 Login MyDigital ID

Callback MyDigital ID sekarang memadankan akaun aktif dan
`MyDigitalIdLocalLoginFinalizer` terus mencipta token/cookie/session OneID.
Jika polisi MFA merangkumi MyDigital ID, finalizer juga perlu ditangguhkan
sehingga faktor OneID lulus.

Raw password, raw OTP, raw TOTP, TOTP secret, authorization code dan ID token
tidak boleh disimpan dalam pending transaction atau audit.

## 5. Readiness data development/staging

Snapshot agregat read-only pada 26 Julai 2026:

| Populasi aktif | Jumlah | E-mel berformat sah | Tiada/tidak sah |
|---|---:|---:|---:|
| Pengguna biasa (`u_type=0`) | 6,550 | 6,543 | 7 |
| Administrator (`u_type=1`) | 4 | 4 | 0 |
| Pelajar | 5,492 | 5,485 | 7 |
| Staf Pentadbiran | 655 | 655 | 0 |
| Pensyarah | 403 | 403 | 0 |

Implikasi:

- blanket enable hari ini boleh mengunci sekurang-kurangnya tujuh pelajar;
- valid syntax tidak membuktikan mailbox aktif atau dimiliki pengguna;
- readiness perlu menguji deliverability melalui pilot, bukan menghantar OTP
  pukal; dan
- OneID bukan sistem induk e-mel rasmi. E-mel staf dan pelajar datang daripada
  sistem sumber masing-masing melalui proses sync dan tidak boleh ditukar
  melalui halaman MFA atau terus dalam OneID.

Admin 2FA pada snapshot ini aktif, dengan lifecycle challenge/grant/factor
terbukti digunakan. Ia menyokong feasibility tetapi bukan bukti user-login
flow sudah selamat.

### 5.1 Finding regression sedia ada

Kontrak langsung `tools/f7_2_email_otp_service_contract.php` mempunyai dua
assertion lama yang menganggap hourly threshold `5`, sedangkan runtime/default
semasa menetapkan admin hourly limit `10`. Akibatnya semakan hourly-limit dan
ujian cooldown berantai dilaporkan gagal apabila kontrak dijalankan terus.

Ini bukan kegagalan runtime MyDigital ID dan suite MyDigital ID 24/24 masih
lulus. Namun, drift fixture/konfigurasi kontrak Admin OTP ini perlu dibetulkan
atau dibuat explicit sebelum service rate-limit dijadikan asas User Login MFA.
Kontrak keselamatan tidak patut bergantung secara senyap pada runtime host.

## 6. Cadangan polisi konfigurasi

Satu boolean `enabled/disabled` boleh menjadi kawalan UI akhir, tetapi rollout
selamat memerlukan keadaan lebih terperinci:

| Mode | Tingkah laku |
|---|---|
| `OFF` | Semua login pengguna kekal seperti sekarang |
| `ENROLLMENT` | Login biasa kekal; pengguna boleh daftar dan uji Authenticator |
| `PILOT_ENFORCED` | MFA diwajibkan hanya kepada allowlist pilot |
| `ENFORCED` | MFA diwajibkan kepada semua pengguna yang layak |

Cadangan medan polisi:

- `user_login_mfa_mode`;
- `user_login_mfa_scope`;
- `user_login_mfa_challenge_ttl_seconds`;
- configuration version, change reason, updated by/at; dan
- readiness/activation reference.

Perubahan konfigurasi mesti memerlukan Admin Step-Up
`SECURITY_CONFIGURATION_CHANGE`, optimistic concurrency, typed confirmation
untuk activation, audit dan rollback plan. Disable mesti menghentikan challenge
baharu dan revoke pending transactions, tetapi tidak memadam faktor pengguna.

## 7. Keputusan skop MyDigital ID

**Keputusan diluluskan: `PASSWORD_ONLY`.**

- Login password memerlukan OTP e-mel/TOTP apabila MFA enforced.
- Login MyDigital ID dianggap external high-assurance authentication dan tidak
  dicabar lagi oleh MFA OneID.
- MyDigital ID hanya membenarkan akaun OneID aktif yang berjaya dipadankan dan
  tidak boleh mendaftarkan pengguna baharu.

Scope masih perlu disimpan secara eksplisit sebagai `PASSWORD_ONLY`, bukan
disimpulkan secara tersirat daripada boolean. Ini membolehkan polisi disemak
semula pada masa hadapan tanpa mengaburkan audit.

## 8. Reka bentuk flow yang disyorkan

### 8.1 Password + MFA

```text
Password sah
  -> semak status pengguna dan polisi MFA
  -> OFF / tidak dalam skop: finalisasi login seperti sekarang
  -> enforced: cipta pending primary-auth transaction (TTL 5 minit)
  -> pilih faktor tersedia
  -> request/verify OTP e-mel atau verify TOTP
  -> consume transaction secara one-use
  -> rotate session ID
  -> baru cipta token_tbl, cookie SSO dan authenticated session
```

### 8.2 MyDigital ID + MFA jika scope `ALL_LOGIN_METHODS`

```text
Provider callback sah
  -> account match/link/audit
  -> cipta pending primary-auth transaction
  -> faktor OneID
  -> consume transaction
  -> finalisasi token/cookie/session OneID
```

Kegagalan atau expiry mesti membersihkan pending state. Tiada orphan active
token dibenarkan.

## 9. Schema additive yang dicadangkan

Jangan ubah atau rename jadual admin dalam fasa awal. Tambah boundary pengguna:

- `user_mfa_factors`;
- `user_mfa_preferences`;
- `user_login_mfa_transactions`;
- `user_login_mfa_challenges`; dan
- event audit khusus user login MFA.

Keperluan minimum:

- FK kepada `user_tbl.u_id`;
- TOTP secret encrypted, nonce dan key version;
- status `PENDING`, `ACTIVE`, `REVOKED`;
- satu active TOTP factor per user bagi release pertama;
- session binding, browser digest, TTL, attempts, consumed/revoked timestamps;
- unique correlation IDs;
- last-used time-step untuk anti-replay;
- tiada raw OTP/TOTP/secret/session ID; dan
- migration up/down serta isolated rehearsal.

Jangka panjang, primitive bersama boleh dipindahkan ke service generik dengan
actor type, tetapi migrasi jadual admin sedia ada tidak diperlukan untuk
release pertama.

## 10. Self-service pengguna

Halaman `Keselamatan Akaun` pada User Dashboard dicadangkan menyediakan:

- status enforcement dan faktor tersedia;
- masked official e-mail;
- pilihan default `EMAIL_OTP` atau `TOTP`;
- jana QR dan secret sekali sahaja untuk enrollment;
- confirmation kod pertama sebelum faktor menjadi active;
- tukar/reset Authenticator;
- revoke faktor;
- senarai masa penggunaan terakhir tanpa memaparkan secret; dan
- BM/English serta accessibility lengkap.

Kawalan sensitif:

- enrollment baharu memerlukan current password atau fresh verified factor;
- revoke/reset memerlukan current TOTP atau OTP e-mel recovery;
- session semata-mata tidak mencukupi;
- semua sesi aktif pengguna mesti direvoke selepas reset atau revoke faktor;
- QR/secret menggunakan `Cache-Control: no-store` dan tidak masuk log;
- e-mel tidak boleh diedit pada halaman ini; dan
- service-desk recovery memerlukan prosedur identiti serta audit berasingan.

Mode `ENROLLMENT` perlu dibuka sebelum enforcement supaya pengguna sempat
mendaftarkan Authenticator. Tempoh enrollment yang diluluskan ialah **30 hari**.

### 10.1 Pembetulan e-mel rasmi

Polisi yang sama terpakai kepada staf dan pelajar:

1. pengguna melaporkan e-mel tiada/tidak tepat melalui saluran sokongan;
2. identiti dan sistem sumber yang berkenaan dikenal pasti;
3. e-mel rasmi dibetulkan dalam sistem sumber staf atau sistem sumber pelajar;
4. proses sync membawa perubahan itu ke OneID;
5. OneID mengesahkan nilai baharu sebelum OTP e-mel dibenarkan; dan
6. Helpdesk/pentadbir tidak menaip e-mel gantian terus ke OneID atau halaman
   MFA.

Jika sync belum selesai, pengguna boleh menggunakan TOTP yang sudah aktif.
Jika pengguna tiada e-mel sah dan belum mempunyai TOTP, akaun masuk proses
recovery terkawal dan tidak menerima bypass MFA.

## 11. Failure dan recovery policy

- Jika e-mel gagal tetapi TOTP aktif, TOTP kekal boleh digunakan.
- Jika TOTP tiada tetapi e-mel sah, OTP e-mel menjadi bootstrap/recovery.
- Jika kedua-duanya tiada, jangan bypass automatik; akaun masuk queue pemulihan.
- Rate limit perlu meliputi user, session, IP dan destinasi e-mel.
- Resend membatalkan challenge lama.
- Maksimum percubaan dan TTL mesti fail closed.
- Disable global tidak memadam faktor atau sejarah audit.
- Emergency disable mesti direkod dan memerlukan fresh Admin Step-Up.

Recovery codes boleh menjadi fasa kemudian. Ia tidak patut ditambah secara
senyap dalam skop OTP e-mel/TOTP ini.

## 12. Risiko utama

| Risiko | Kawalan |
|---|---|
| Global lockout | Mode enrollment/pilot, readiness gate dan tujuh akaun diperbetulkan dahulu |
| Token diwujudkan sebelum MFA | Pending transaction; finalisasi hanya selepas verify |
| Endpoint admin dibuka kepada user | Boundary service, action dan jadual pengguna berasingan |
| OTP brute force | Argon2id, max attempts, TTL, cooldown dan rate limit |
| TOTP replay | Simpan dan kunci `last_used_time_step` |
| Secret bocor | Encryption keyring luar DB/Git, QR lokal, no-store |
| Pengambilalihan melalui reset | Fresh factor/current password, revoke sessions, audit |
| Enumeration e-mel | Mask destinasi dan mesej generik |
| MyDigital ID policy kabur | `user_login_mfa_scope` eksplisit |
| Shared dev/staging DB | Backup, gated migration dan feature default OFF |

## 13. Fasa pembangunan yang disyorkan

1. **U0 — contract dan baseline:** ikat keputusan polisi, tetapkan TTL/attempt,
   recovery role, pilot dan acceptance.
2. **U1 — dormant schema/config:** migration up/down, mode `OFF`, audit events,
   isolated rehearsal.
3. **U2 — generic primitives:** extract reusable crypto/rate-limit components
   tanpa mengubah Admin Step-Up.
4. **U3 — pending login foundation:** password orchestration dormant; buktikan
   tiada token sebelum MFA dan MyDigital ID kekal serasi tanpa challenge MFA.
5. **U4 — OTP e-mel login:** request, resend, verify, expiry, rate limit dan
   audit.
6. **U5 — TOTP self-service:** enroll QR, confirm, select, reset/revoke dan
   recovery.
7. **U6 — UI BM/English:** challenge page dan Account Security dashboard.
8. **U7 — security/regression:** password, MyDigital ID, admin 2FA, SSO, ACL,
   replay, CSRF, fixation dan failure tests.
9. **U8 — enrollment pilot:** pembetulan tujuh e-mel, pilot staf/pelajar,
   observation.
10. **U9 — controlled enforcement:** staged rollout, monitoring dan rollback.

## 14. Rekod keputusan polisi

Keputusan berikut telah diluluskan oleh owner:

| Perkara | Keputusan |
|---|---|
| Skop login | `PASSWORD_ONLY`; MyDigital ID dikecualikan pada fasa awal |
| Faktor | OTP e-mel rasmi sebagai alternatif/recovery; TOTP digalakkan tetapi bukan satu-satunya faktor |
| Enrollment | 30 hari sebelum enforcement |
| Rollout | `OFF` → `ENROLLMENT` → `PILOT_ENFORCED` → `ENFORCED` |
| E-mel rasmi | Staf dan pelajar dibetulkan dalam sistem sumber masing-masing, kemudian sync ke OneID |
| Recovery | Helpdesk/PTMK menyemak permohonan; pemilik data mengesahkan; pentadbir OneID reset faktor jika diperlukan |
| Perubahan e-mel dalam OneID | Tidak dibenarkan melalui halaman MFA atau kemas kini terus |
| Reset/revoke Authenticator | Revoke semua sesi aktif dan wajib login semula |
| Audit recovery | Actor, sebab dan correlation ID wajib direkod |
| Kawalan global | Administrator boleh menutup keseluruhan User Login 2FA |
| OTP e-mel | Wajib tersedia apabila mode selain `OFF` |
| Microsoft Authenticator | Optional; boleh dihentikan global tanpa memadam factor |
| Self-service | Pengguna enroll, tukar dan revoke Authenticator sendiri |
| Admin recovery | Guna role Administrator sedia ada; tiada role baharu |
| Disable keseluruhan | Login password biasa; revoke pending MFA, kekalkan factor/history |
| Polisi sesi activation | Terpakai kepada login baharu; tiada mass revoke |
| Pilot | 5–10 pengguna; sasaran 8 merangkumi staf, pensyarah serta pelajar tempatan/antarabangsa |
| Jadual | Tarikh tepat selepas development; pilot 7 hari dan post-change 72 jam |
| Monitoring | Manual dashboard/log dan e-mel operasi kepada kedua-dua Administrator |
| Response SLA | Critical 15 minit; High 30 minit; Warning 4 jam bekerja |
| Change window | 60 minit; executing Administrator rollback owner, Administrator kedua verifier |
| Retention | Challenge metadata 30 hari; OTP hash terminal dipadam; audit 365 hari |
| Rehearsal | Wajib sebelum U1 staging schema apply; bukan blocker U0 |
| Management activation | Belum diterbitkan; global enforcement `NOT AUTHORIZED` |

## 15. Register keputusan yang ditangguhkan

Perkara berikut sengaja tidak diputuskan sekarang. Nilai contoh dalam audit
tidak boleh dianggap sebagai approval.

| ID | Keputusan tertangguh | Owner yang dicadangkan | Bila perlu diputuskan | Bukti minimum |
|---|---|---|---|---|
| UMFA-D01 | TTL pending login dan maksimum percubaan OTP/TOTP | OneID owner + Security | Sebelum reka bentuk U1/U3 diluluskan | Threat model, UX timeout dan rate-limit test |
| UMFA-D02 | SLA pembetulan dan sync e-mel staf/pelajar | Pemilik sistem sumber + PTMK | Sebelum pilot enrollment | Data owner, jadual sync, escalation dan sample reconciliation |
| UMFA-D03 | Bukti identiti minimum untuk recovery | Security + Helpdesk/PTMK | Sebelum endpoint reset dibina | SOP recovery, authorized roles dan audit fields |
| UMFA-D04 | Role yang boleh melakukan service-desk reset | Security + OneID owner | Sebelum U5 | Least-privilege role, Admin Step-Up dan approval flow |
| UMFA-D05 | Kategori/saiz kumpulan pilot | OneID owner + pemilik data | Sebelum `PILOT_ENFORCED` | Staf/pelajar representation dan akaun ujian |
| UMFA-D06 | Tempoh observation enrollment dan pilot | Operations + OneID owner | Sebelum pilot bermula | Success/rejection baseline dan support capacity |
| UMFA-D07 | Monitoring threshold dan alert channel | Operations + Security | Sebelum enforcement | Event mapping, dashboard/query, owner dan response SLA |
| UMFA-D08 | Rollback owner dan activation window | Change owner + DBA/Operations | Sebelum migration/activation | Backup, restore rehearsal dan disable procedure |
| UMFA-D09 | Polisi sesi semasa apabila global MFA diaktifkan | Security + OneID owner | Sebelum `ENFORCED` | Risiko revoke serentak berbanding new-login-only |
| UMFA-D10 | Tempoh retention/purge data challenge dan audit MFA | Security + DBA/data owner | Sebelum migration | Retention approval dan purge/archival rehearsal |

Default keselamatan sementara bagi perkara yang belum diputuskan ialah:

- mode kekal `OFF`;
- tiada migration diaplikasikan;
- tiada pengguna dimasukkan ke pilot;
- behavior password dan MyDigital ID kekal seperti release semasa; dan
- Admin Step-Up 2FA tidak diubah.

## 16. Syarat membuka semula audit

Audit boleh dibuka semula untuk perancangan implementasi apabila owner
mengesahkan OneID telah cukup stabil. Minimum precondition:

1. acceptance MyDigital ID staging ditutup atau baki risikonya diterima;
2. tiada insiden authentication kritikal yang masih terbuka;
3. regression password, session, SSO, ACL dan Admin Step-Up berada pada
   baseline hijau;
4. e-mel staf/pelajar dan proses sync mempunyai owner serta reconciliation;
5. sekurang-kurangnya UMFA-D01 hingga UMFA-D05 mempunyai keputusan; dan
6. change window untuk dormant implementation diluluskan.

Apabila dibuka semula, mulakan pada **U0 — contract dan baseline**. Jangan terus
melompat kepada schema, UI atau activation kerana kod dan data mungkin telah
berubah selepas tarikh audit.

## 17. Kesimpulan audit

Cadangan ini sesuai untuk menambah keselamatan OneID dan banyak primitive Admin
2FA boleh diguna semula. Namun, risiko terbesar ialah menganggap Admin Step-Up
sama dengan User Login MFA. Implementasi selamat mesti:

- menangguhkan token/cookie/session sehingga faktor kedua lulus;
- mempunyai rollout mode, bukan global boolean secara tiba-tiba;
- membetulkan readiness e-mel;
- memisahkan boundary data dan endpoint pengguna daripada admin; dan
- menetapkan polisi MyDigital ID secara eksplisit.

Dengan syarat tersebut, keputusan audit kini ialah **GO untuk U0 contract,
baseline dan perancangan** berasaskan keputusan polisi di Seksyen 14 serta
pelan canonical. Ia masih **bukan authorization untuk migration, endpoint,
pilot atau activation**.
