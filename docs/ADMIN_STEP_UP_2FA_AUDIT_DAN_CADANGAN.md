# Audit dan Cadangan Admin Step-Up 2FA

**Tarikh audit:** 16 Julai 2026  
**Tarikh semakan menyeluruh:** 19 Julai 2026
**Status:** F7.1-F7.6 IMPLEMENTED / VERIFIED / ACCEPTED — OPERATIONAL MONITORING
**Skop:** Akses Administrator, perubahan security configuration dan controlled
active-session revocation
**Keputusan semasa:** Dua kaedah diluluskan secara prinsip. Owner mengarahkan
F7.0 readiness dimulakan pada 20 Julai 2026; schema, runtime dan activation
kekal tidak berubah sehingga semua gate F7.0 ditutup.

## 1. Objektif

Dokumen ini merekod audit serta reka bentuk disyorkan untuk mewajibkan
authentication faktor kedua sebelum akaun berperanan admin boleh memasuki atau
menggunakan fungsi Administrator. Admin akan boleh memilih OTP melalui e-mel
atau kod TOTP melalui Microsoft Authenticator.

Dokumen ini ialah **sumber induk Fasa 7 Admin Step-Up 2FA**. Ia menggabungkan
keperluan berkaitan daripada:

- audit induk Administrator Configuration;
- handoff SC7-SC8;
- SC3 Configuration History, mandatory reason dan optimistic locking;
- SC6 Password Recovery dan SMTP readiness;
- AS0/AS1/AS2 Active Sessions dan revoked-token enforcement; dan
- SC4/SC5 token lifecycle, preview serta controlled revocation.

Dokumen evidence asal kekal sah, tetapi jika terdapat perbezaan istilah atau
skop Fasa 7, dokumen ini menjadi rujukan utama. `FASA_7_PELAN_IMPLEMENTASI_`
`CLEANUP_DAN_MODERNISASI.md` ialah program technical-debt yang berbeza dan tidak
termasuk dalam skop Admin Step-Up 2FA ini.

Tiada perubahan database, konfigurasi, session guard, endpoint, e-mel atau UI
dibuat sebagai sebahagian daripada audit ini.

## 2. Baseline Sistem Semasa

Baseline yang mesti dikekalkan sepanjang implementasi:

- authenticated PHP session, role admin, CSRF dan exactly-one-action guard;
- Active Sessions read-only, lifecycle housekeeping dan revoked-token
  enforcement yang telah lulus AS0 hingga AS2;
- Authentication Policy menggunakan fresh preview, mandatory change reason,
  `configuration_version`, optimistic locking dan transaction atomik;
- Configuration History merekod `SUCCESS` dan `REJECTED` tanpa material rahsia;
- Password Recovery menggunakan `password_reset_email_enabled`, SMTP readiness
  dan OTP purpose berasingan; dan
- SC5 menggunakan preview impak, grace period serta lazy revocation.

Admin Step-Up tidak boleh melemahkan atau menggantikan mana-mana kawalan ini.
OTP Forgot Password tidak boleh diterima sebagai challenge Step-Up.

Keputusan owner 20 Julai 2026 menetapkan bahawa tiada faktor default global.
Setiap admin boleh memilih sendiri `EMAIL_OTP` atau `TOTP` sebagai kaedah
pilihan selepas faktor berkenaan tersedia dan disahkan. Sistem tidak boleh
memaksa salah satu kaedah sebagai default, tetapi tetap menguatkuasakan
availability, confirmation, purpose binding, rate limit dan fail-closed.

Owner turut menetapkan bahawa pembangunan dan UAT Fasa 7 dikendalikan serta
diuji sepenuhnya oleh seorang pemilik, staff reference `0530-09`, tanpa admin
kedua. Peranan executing, rollback, DBA/backup, monitoring, security review,
pilot dan acceptance disatukan bagi environment ini. Risiko separation-of-duty
diterima dengan automated tests, checksum evidence, tested restore/rollback,
fail-closed flag, audit dan keputusan eksplisit owner. Model ini tidak terpakai
secara automatik kepada production.

### 2.1 Keadaan akses semasa

Pautan `Administrator` pada dashboard pengguna membawa terus ke
`/admin/dashboard`. Guard server semasa mengesahkan bahawa pengguna:

1. telah authenticated; dan
2. mempunyai `login_user_type = 1`.

Guard itu belum memerlukan step-up authentication. Oleh itu, menambah modal
OTP hanya pada event klik tidak mencukupi kerana URL atau endpoint admin masih
boleh dicapai secara terus.

Forgot Password OTP semasa telah mempunyai kawalan berikut:

- kod enam digit dijana menggunakan sumber rawak selamat;
- hanya hash OTP disimpan;
- luput selepas lima minit;
- maksimum lima cubaan;
- cooldown permintaan selama 60 saat;
- had lima permintaan sehari;
- challenge lama dibatalkan apabila OTP baharu dicipta;
- OTP dikonsum selepas verification berjaya.

Flow itu khusus untuk reset password dan belum diikat kepada purpose admin,
session login, challenge ID atau browser tertentu. Ia tidak patut digunakan
terus sebagai Admin 2FA.

Authentication Policy dan Password Recovery kini dipisahkan. Setting
`password_reset_email_enabled` hanya mengawal Forgot Password dan tidak boleh
digunakan sebagai toggle atau faktor Admin Step-Up.

### 2.2 Coverage task Fasa 7

| Task | Status semasa | Keperluan dokumen ini |
|---|---|---|
| SC7-01 Admin Step-Up 2FA | Pending | Diliputi sepenuhnya |
| SC7-02 Mandatory Change Reason | Complete dalam SC3 | Mesti dikekalkan selepas Step-Up |
| SC7-03 Optimistic Locking | Complete dalam SC3 | Step-Up tidak menggantikan revision check |
| SC7-04 Configuration History | Complete dalam SC3 | Tambah outcome authorization/Step-Up |
| SC7-05 Rejected-Update Audit | Partial | Lengkapkan authorization dan Step-Up rejection |
| SC7-06 Controlled Session Revocation | Pending | Dilindungi purpose dan confirmation khusus |

Fasa 8 monitoring, controlled rollout dan keputusan scheduler turut direkod
kerana ia menjadi exit gate operasi Fasa 7, walaupun scheduler SC5 bukan
komponen Step-Up.

### 2.3 Subfasa pelaksanaan rasmi

| Subfasa | Skop | Exit gate |
|---|---|---|
| F7.0 | Readiness, owner decision, endpoint inventory, pilot, backup, key custody, break-glass dan monitoring | Semua prerequisite direkod `PASS/APPROVED`; keputusan `GO F7.1` |
| F7.1 | Schema fail-closed, challenge/grant/factor store dan encryption foundation | Migration forward/rollback serta compatibility flag-OFF lulus |
| F7.2 | Enjin challenge dan OTP e-mel | Purpose/session binding, expiry, attempt, resend, rate limit dan delivery lulus |
| F7.3 | Microsoft Authenticator melalui TOTP | Enrollment, confirmation, encrypted secret, anti-replay, revoke dan recovery lulus |
| F7.4 | Enforcement server-side | Semua halaman/action admin dan direct bypass dilindungi mengikut purpose |
| F7.5 | UI, controlled bootstrap dan operational recovery | Flow pilot, toggle, current password, confirmation dan break-glass lulus |
| F7.6 | UAT, monitoring, controlled pilot dan rollout | Owner `ACCEPT` atau rollback lengkap selepas observation window |

Subfasa mesti bergerak mengikut urutan. F7.1 boleh memasang schema hanya selepas
F7.0 memberikan `GO`; feature flag kekal `OFF` sehingga F7.6. Rekod pelaksanaan
F7.0 berada dalam `docs/F7_0_READINESS_ADMIN_STEP_UP_2FA.md`.

## 3. Risiko Jika Hanya Dilaksanakan pada UI

- URL `/admin/dashboard` boleh dibuka terus.
- Endpoint POST admin boleh dipanggil tanpa melalui modal.
- Status verification dalam JavaScript, `localStorage` atau query string boleh
  diubah oleh pengguna.
- OTP reset password berpotensi diterima untuk purpose lain jika stor yang sama
  digunakan tanpa purpose binding.
- OTP daripada satu browser boleh digunakan pada session lain jika challenge
  tidak diikat kepada session.

Kesimpulan audit ialah enforcement mesti dibuat pada server untuk halaman dan
semua endpoint admin.

## 4. Reka Bentuk Disyorkan

### 4.1 Purpose dan authorization boundary

Challenge dan grant Step-Up mesti menggunakan purpose allowlist berasingan:

| Purpose | Digunakan untuk | Boleh meluluskan purpose lain? |
|---|---|---|
| `ADMIN_ACCESS` | Memasuki dan menggunakan fungsi admin biasa | Tidak |
| `SECURITY_CONFIGURATION_CHANGE` | Preview/Apply Authentication Policy, faktor MFA dan setting keselamatan | Tidak |
| `ACTIVE_SESSION_REVOCATION` | Revoke satu sesi atau semua sesi pengguna | Tidak |

Grant mesti diikat kepada admin, authenticated session, browser binding dan
purpose. Grant `ADMIN_ACCESS` tidak mencukupi untuk mutation Configuration atau
revocation sesi. Endpoint enrollment, request dan verify berada pada
authorization tier khas supaya flow tidak terkunci secara rekursif, tetapi tier
itu hanya boleh melakukan operasi challenge yang diallowlist.

Sistem mengekalkan satu role admin sedia ada. Fasa ini tidak memperkenalkan role
`security-admin`; least privilege dicapai melalui purpose dan fresh Step-Up.

### 4.2 Toggle khusus

Tambah setting database:

```text
admin_2fa_enabled
```

- `0`: role admin berfungsi seperti keadaan semasa.
- `1`: role admin mesti melengkapkan step-up OTP.

Toggle ini dipaparkan dalam tab Authentication Policy. Perubahannya sendiri
memerlukan `SECURITY_CONFIGURATION_CHANGE`, fresh preview, mandatory reason,
optimistic locking dan Configuration History. `password_reset_email_enabled`
tidak digunakan sebagai pengganti toggle ini.

Activation pertama ialah controlled bootstrap, bukan pengecualian senyap.
Sebelum toggle dihidupkan, admin pilot mesti mempunyai e-mel sah dan faktor TOTP
confirmed. Bootstrap memerlukan current password, salah satu faktor confirmed,
fresh Configuration preview, reason, typed confirmation, change approval dan
audit khusus `ADMIN_2FA_BOOTSTRAP_ENABLED`. Selepas activation pertama, semua
perubahan toggle/faktor tertakluk kepada `SECURITY_CONFIGURATION_CHANGE`.

### 4.3 Aliran pengguna

```text
Dashboard pengguna
    -> klik Administrator
    -> pilih kaedah Admin Step-Up
       -> OTP e-mel; atau
       -> Microsoft Authenticator (TOTP)
    -> kod sah
    -> session rotation dan CSRF rotation
    -> Admin Dashboard
```

Halaman challenge menggunakan enam input digit, Verify dan tindakan kembali ke
My Account. Pilihan e-mel memaparkan alamat masked, countdown berdasarkan expiry
server dan Resend. Pilihan Microsoft Authenticator hanya dipaparkan sebagai
boleh digunakan selepas faktor TOTP berjaya didaftarkan dan disahkan.

### 4.4 Enforcement server-side

Guard halaman admin biasa perlu mengesahkan:

```text
authenticated
AND role admin
AND (Admin 2FA disabled OR step-up session masih sah)
```

Guard yang sama mesti melindungi halaman admin dan action admin dalam
`/lib/q_func`. Endpoint request dan verify Admin 2FA perlu berada pada level
authorization khas yang memerlukan authenticated admin tetapi belum memerlukan
step-up, supaya flow tidak terkunci secara rekursif.

Endpoint sensitif mesti mengesahkan purpose yang tepat pada server selepas
role/CSRF guard dan sebelum preview atau mutation. Direct URL, AJAX manual,
replay request, JavaScript state, hidden field dan grant bagi purpose lain tidak
boleh memintas semakan ini.

### 4.5 Stor challenge dan grant berasingan

Cadangan table:

```text
admin_step_up_challenges
```

Medan minimum:

- challenge ID rawak;
- admin user ID;
- OTP hash;
- session-binding hash;
- purpose daripada allowlist yang tepat;
- created dan expiry timestamp;
- attempts dan resend count;
- consumed dan revoked timestamp;
- requesting IP;
- digest User-Agent.

OTP mentah, session ID mentah dan credential SMTP tidak boleh disimpan atau
direkod dalam log.

Step-Up grant mesti disimpan atau disahkan server-side dengan minimum admin ID,
session-binding hash, browser digest, purpose, verified factor, issued/expiry,
revoked timestamp dan correlation ID. Client hanya menerima status minimum;
grant mentah atau material yang membolehkan pemalsuan tidak boleh diletakkan
dalam `localStorage`, query string atau cookie tanpa perlindungan server.

Schema perlu mempunyai primary/unique constraint, index expiry/admin/session,
foreign-key atau reconciliation yang sesuai, serta retention cleanup yang
bounded. Forward/down migration mesti idempotent dan diuji pada salinan staging.

### 4.6 Faktor Microsoft Authenticator melalui TOTP

Integrasi yang dipilih ialah standard TOTP, bukan Microsoft Entra push
notification. OneID menjana secret dan QR provisioning URI, admin mengimbas QR
menggunakan Microsoft Authenticator, kemudian memasukkan kod enam digit untuk
mengesahkan enrollment. Telefon tidak memerlukan internet untuk menghasilkan
kod selepas enrollment.

Cadangan table faktor:

```text
admin_mfa_factors
```

Medan minimum:

- admin user ID dan jenis faktor `TOTP`;
- secret TOTP yang dienkripsi, bukan plaintext atau hash sehala;
- key-encryption version;
- status aktif atau revoked;
- tarikh enrollment, confirmation, penggunaan terakhir dan revocation;
- label peranti yang selamat dan pilihan; dan
- actor serta correlation ID bagi perubahan faktor.

Application encryption key mesti berada di luar database dan tidak boleh
disimpan dalam repository. QR provisioning URI, secret dan kod TOTP tidak boleh
direkod dalam log. Server dan telefon mesti mempunyai masa yang tepat; verifier
hanya menerima time-window kecil dan menghalang penggunaan semula kod bagi
challenge yang sama.

Enrollment, penggantian atau revocation Authenticator ialah operasi sensitif.
Ia mesti memerlukan password semasa bersama step-up/faktor sedia ada, merotasi
session dan CSRF apabila sesuai, menghasilkan audit event serta menghantar
notifikasi keselamatan melalui e-mel.

## 5. Polisi Keselamatan Dicadangkan

| Kawalan | Nilai cadangan |
|---|---:|
| Panjang OTP | 6 digit |
| Tempoh OTP | 5 minit |
| Maksimum verification | 5 cubaan |
| Resend cooldown | 60 saat |
| Had permintaan | 5 sejam, 10 sehari |
| Tempoh step-up session | 5, 10, 15 atau 30 minit; default 15 minit |
| Penggunaan OTP | Sekali sahaja |
| Tempoh kod TOTP | 30 saat |
| Toleransi masa TOTP | Window kecil yang didokumenkan dan diuji |

Step-up dibatalkan apabila logout, login semula, role berubah, setting 2FA
berubah, authenticated session bertukar atau tempoh grant yang dikonfigurasi
tamat. Perubahan tempoh hanya terpakai kepada grant baharu.

Selepas verification berjaya, session ID dan CSRF token perlu dirotasi.

## 6. Penghantaran E-mel

Gunakan e-mel khusus dengan subject seperti:

```text
OneID@UPNM - Administrator Access Verification
```

Kandungan menerangkan bahawa kod digunakan untuk akses Administrator, sah lima
minit dan perlu diabaikan/dilaporkan jika bukan diminta pengguna. UI hanya
memaparkan alamat e-mel masked.

Admin 2FA mesti fail closed:

- e-mel tiada atau tidak sah: akses admin ditolak;
- SMTP gagal: challenge tidak dianggap berjaya dihantar;
- OTP salah atau expired: akses admin kekal ditolak.

Kegagalan SMTP hanya menutup kaedah e-mel; admin yang telah mempunyai faktor
TOTP sah masih boleh memilih Microsoft Authenticator. Jika kedua-dua faktor
tidak tersedia atau tidak sah, akses Administrator kekal ditolak.

## 7. Audit Event Dicadangkan

- `ADMIN_2FA_REQUESTED`
- `ADMIN_2FA_SENT`
- `ADMIN_2FA_VERIFIED`
- `ADMIN_2FA_FAILED`
- `ADMIN_2FA_EXPIRED`
- `ADMIN_2FA_RATE_LIMITED`
- `ADMIN_2FA_ENABLED`
- `ADMIN_2FA_DISABLED`
- `ADMIN_2FA_BOOTSTRAP_ENABLED`
- `ADMIN_2FA_BREAK_GLASS_STARTED`
- `ADMIN_2FA_BREAK_GLASS_EXPIRED`
- `ADMIN_MFA_METHOD_SELECTED`
- `ADMIN_TOTP_ENROLLED`
- `ADMIN_TOTP_CONFIRMED`
- `ADMIN_TOTP_VERIFIED`
- `ADMIN_TOTP_FAILED`
- `ADMIN_TOTP_REVOKED`
- `ADMIN_TOTP_RECOVERY_USED`

Log tidak boleh mengandungi OTP, OTP hash, e-mel penuh, SMTP credential atau
session ID penuh.

### 7.1 Structured rejection audit

Authorization dan Step-Up rejection bagi Configuration mesti menggunakan
reason code allowlisted dan correlation ID yang boleh dipadankan dengan
Configuration History. Minimum outcome:

- `STEP_UP_REQUIRED`;
- `STEP_UP_PURPOSE_MISMATCH`;
- `STEP_UP_EXPIRED`;
- `STEP_UP_REPLAYED`;
- `STEP_UP_RATE_LIMITED`;
- `STEP_UP_FACTOR_UNAVAILABLE`; dan
- `STEP_UP_VERIFICATION_FAILED`.

Rejection tidak boleh menyimpan payload mentah, OTP, TOTP, secret, session ID,
cookie atau alamat e-mel penuh. Setiap rejection mesti kekal zero mutation
terhadap configuration dan target session. Kegagalan menulis audit tidak boleh
ditukar kepada laporan kejayaan atau membuka akses.

### 7.2 Controlled Active-Session Revocation

Selepas Step-Up tersedia, Active Sessions boleh ditambah tindakan revoke satu
sesi atau semua sesi pengguna dengan syarat:

1. purpose tepat `ACTIVE_SESSION_REVOCATION` masih fresh;
2. server menghasilkan target preview baharu tanpa token material;
3. admin memasukkan mandatory reason dan typed confirmation;
4. self-lockout protection memberi amaran atau menolak target session semasa
   mengikut polisi yang diluluskan;
5. mutation menggunakan targeted transaction dan audit atomik; dan
6. response serta result audit merekonsiliasi bilangan requested, matched,
   revoked, skipped dan failed.

Grant `ADMIN_ACCESS` atau `SECURITY_CONFIGURATION_CHANGE` tidak boleh digunakan
untuk revocation ini. Listing, carian, filter dan pagination Active Sessions
kekal read-only dan tidak memerlukan mutation.

## 8. Recovery dan Lockout

Sebelum feature diaktifkan:

- sahkan e-mel semua administrator;
- uji SMTP pada staging;
- ambil backup database;
- sediakan forward dan rollback migration;
- sediakan prosedur break-glass yang diluluskan untuk kegagalan semua faktor;
- jalankan pilot menggunakan satu akaun admin terkawal.

OTP e-mel dikekalkan sebagai kaedah alternatif dan recovery terkawal. Reset
Authenticator oleh service desk/DBA memerlukan pengesahan identiti, rekod actor,
sebab, correlation ID dan notifikasi kepada admin. Pendaftaran faktor baharu
tidak boleh dijadikan jalan pintas semasa challenge akses Administrator.

Emergency bypass melalui URL, hidden form field atau secret hardcoded tidak
disyorkan.

Break-glass bukan toggle kekal atau arahan DBA tanpa rekod. Ia mesti mempunyai
incident/change ID, dua-person approval jika tersedia, actor, reason, skop,
expiry automatik, audit, notification dan post-incident review. Selepas expiry,
enforcement mesti kembali fail-closed tanpa bergantung pada ingatan operator.
Kegagalan e-mel sahaja bukan alasan mematikan Step-Up jika TOTP masih tersedia.

## 9. Urutan Implementasi Dicadangkan

1. Refresh baseline, endpoint inventory, admin pilot, e-mel rasmi dan owner.
2. Tetapkan purpose matrix, authorization tier dan fail-closed feature flag.
3. Sediakan backup serta migration toggle, challenge, grant dan faktor MFA.
4. Sediakan pengurusan application encryption key, rotation dan recovery di
   luar database/repository.
5. Bina service request/verify/revoke challenge yang menyokong `EMAIL_OTP` dan
   `TOTP` bagi purpose allowlisted.
6. Tambah page dan API guard server-side bagi semua halaman/action admin.
7. Bina enrollment, confirmation, revocation dan recovery TOTP.
8. Bina halaman pemilihan kaedah dan challenge enam digit yang konsisten.
9. Ubah pautan Administrator pada dashboard pengguna kepada entry challenge.
10. Tambah toggle melalui service Configuration sedia ada tanpa memintas
    preview, reason, revision dan structured history.
11. Tambah audit event, structured rejection, anti-replay, rate limit dan
    resend cooldown.
12. Tambah controlled Active-Session Revocation di belakang purpose khusus.
13. Tambah automated security contracts dan negative/bypass tests.
14. Jalankan UAT dengan setting disabled untuk membuktikan compatibility.
15. Enrollment Microsoft Authenticator pada satu akaun admin pilot.
16. Uji kedua-dua kaedah, purpose isolation, recovery dan rollback.
17. Jalankan controlled pilot, observation window dan owner acceptance sebelum
    enable secara rasmi.

## 10. Acceptance Criteria

- 2FA disabled mengekalkan behavior semasa.
- 2FA enabled menghalang akses terus ke semua halaman dan endpoint admin.
- OTP hanya sah untuk user, purpose, challenge dan session yang mengeluarkannya.
- `ADMIN_ACCESS`, `SECURITY_CONFIGURATION_CHANGE` dan
  `ACTIVE_SESSION_REVOCATION` tidak boleh saling menggantikan.
- Admin boleh memilih e-mel atau TOTP jika kedua-duanya tersedia.
- TOTP hanya boleh dipilih selepas enrollment dan confirmation yang sah.
- Secret TOTP dienkripsi menggunakan key di luar database dan repository.
- Kod TOTP tidak boleh direplay untuk melengkapkan challenge yang sama.
- OTP expired, consumed atau melebihi cubaan sentiasa ditolak.
- Resend dan rate limit dikuatkuasakan pada server.
- Verification berjaya merotasi session dan CSRF.
- Logout dan expiry membatalkan step-up.
- Setting enable/disable serta hasil verification direkod tanpa data sensitif.
- Authorization/Step-Up rejection direkod dengan reason code dan kekal zero
  mutation.
- Kegagalan SMTP tidak memberikan akses admin.
- Kegagalan satu faktor tidak secara tersirat meluluskan atau bypass faktor lain.
- Enrollment, revocation dan recovery TOTP memerlukan kawalan serta audit khusus.
- Controlled session revocation memerlukan fresh target preview, reason, typed
  confirmation, self-lockout protection dan result reconciliation.
- Step-Up untuk Configuration masih tertakluk kepada fresh policy preview dan
  optimistic locking.
- Break-glass mempunyai approval, expiry, audit dan post-incident review.
- Rollback migration dan operational recovery telah diuji.

## 11. Perkara yang Perlu Disahkan Owner

- had 5 permintaan sejam dan 10 sehari;
- Authenticator kekal pilihan setara atau menjadi kaedah utama selepas
  enrollment, dengan e-mel sebagai fallback;
- pemilik dan lokasi pengurusan application encryption key;
- key rotation, backup dan recovery owner;
- senarai admin dan kesahan e-mel rasmi mereka;
- owner/on-call untuk kegagalan SMTP dan lockout;
- prosedur pengesahan identiti untuk reset faktor TOTP;
- polisi self-revoke session semasa;
- Change ID, executing admin, rollback owner dan maintenance window;
- monitoring owner, communication channel dan observation window; dan
- keputusan berasingan bagi scheduler SC5 yang tidak boleh diaktifkan secara
  tersirat oleh release Step-Up.

## 12. Monitoring, UAT dan Controlled Rollout

Sebelum enable, contract dan UAT minimum mesti meliputi:

- direct page dan endpoint bypass;
- CSRF, role, exactly-one-action dan purpose mismatch;
- OTP/TOTP wrong, expired, consumed, replay dan attempt exhaustion;
- resend/rate-limit concurrency;
- logout, login semula, session rotation, role change dan 15-minute expiry;
- dua browser/peranti dan dua admin serentak;
- SMTP failure dengan dan tanpa TOTP tersedia;
- TOTP clock skew, key version, revocation dan recovery;
- stale Configuration preview selepas Step-Up berjaya;
- revoke one/all, self-lockout dan result reconciliation; dan
- rollback schema/config tanpa orphan grant atau challenge aktif.

Monitoring dan alert minimum:

- challenge requested/sent/verified/failed/expired/rate-limited;
- lonjakan purpose mismatch, replay dan direct endpoint denial;
- SMTP delivery failure dan admin tanpa faktor tersedia;
- TOTP clock drift, decrypt/key-version failure dan recovery use;
- Configuration update/rejection serta session revocation result; dan
- PHP-FPM/application/database error selepas rollout.

Rollout menggunakan satu admin pilot, satu request Apply maksimum bagi setiap
ujian mutation, observation window yang direkod dan keputusan `ACCEPT` atau
`ROLLBACK`. Scheduler SC5 dan Cron External Sync kekal keputusan operasi
berasingan.

## 13. Gate Sebelum Implementasi

Kerja mutation tidak boleh bermula sehingga tersedia:

- Change ID, owner, executing admin, maintenance window dan rollback owner;
- backup serta restore rehearsal;
- encryption-key custody, rotation dan recovery procedure;
- mailbox dan admin pilot yang disahkan;
- break-glass berapproval, audit dan expiry;
- contract zero-mutation bagi semua rejection;
- endpoint inventory serta purpose/action matrix; dan
- monitoring owner serta communication channel.

Jangan aktifkan feature flag atau schema separa pada runtime live. Migration
boleh dipasang fail-closed, tetapi activation hanya selepas contract, UAT dan
owner GO lengkap.

## 14. Keputusan Semasa

Owner telah bersetuju dengan OTP e-mel, default step-up 15 minit, server-side
enforcement dan penambahan pilihan Microsoft Authenticator melalui TOTP. Selagi
step-up masih sah, kembali daripada My Account tidak memerlukan OTP baharu;
selepas expiry, logout, login semula atau pertukaran session, verification perlu
diulang.

Pada 20 Julai 2026, tempoh step-up dijadikan polisi berasingan daripada SSO
token lifetime dengan allowlist 5, 10, 15 dan 30 minit. Nilai UAT kekal 15
minit. Perubahan memerlukan `SECURITY_CONFIGURATION_CHANGE`, change reason,
optimistic locking dan audit atomik; grant sedia ada tidak dipanjangkan.

F7.0 readiness telah dimulakan pada 20 Julai 2026. Owner kemudian mengarahkan
pembangunan F7.1 secara dormant sebelum baki operational gate ditutup. Forward
dan rollback migration serta encryption foundation telah dibina, diuji dalam
database sementara dan diaplikasi kepada UAT selepas fresh backup/restore. Live
verification mengesahkan `admin_2fa_enabled=0`; feature kekal tidak aktif.
F7.2 email OTP challenge engine, persistence extension dan audit dictionary turut
dipasang serta diterima selepas feature-OFF, negative dan rollback-persistence
contracts lulus tanpa menghantar e-mel live.
F7.3 TOTP factor lifecycle turut dipasang dan disahkan melalui RFC 6238,
anti-replay, isolated migration serta rollback-persistence contract. Tiada faktor
sebenar didaftarkan dan feature kekal OFF. F7.4 server-side enforcement kini
melindungi 2 halaman dan 48 action admin dengan exact-purpose, session dan
browser-bound authorization; direct-bypass contracts lulus. F7.5 UI, local QR,
API, preference, session/CSRF rotation dan controlled-bootstrap gate telah dibina
secara dormant. Owner kemudian melengkapkan enrollment dan controlled bootstrap
pada 20 Julai 2026; pilot feature kini ON. Functional UAT F7.6 kemudian lulus
untuk owner tunggal, termasuk OTP e-mel, TOTP, reset/enrollment semula, QR
berjenama, preference, grant/session rotation dan purpose isolation. Observation
24 jam bermula pada activation `2026-07-20 19:11:29 MYT` dan final snapshot
selepas 27.90 jam lulus. Owner menerima F7.6 pada 21 Julai 2026 selepas semakan
UI/fungsi. Monitoring tujuh hari dikecualikan sebagai exit gate dan diganti
dengan continuous operational monitoring. Rekod F7.6 berada dalam
`docs/F7_6_UAT_CONTROLLED_ROLLOUT_DAN_OBSERVATION.md`. Dokumen ini ialah baseline
tunggal pelaksanaan Fasa 7.
Tiada Microsoft Entra push notification berada dalam skop semasa. Task SC7-02,
SC7-03 dan SC7-04 sudah complete dalam Fasa 3; SC7-01 Admin Step-Up 2FA kini
diterima. Controlled Active-Session Revocation dan scheduler kekal sebagai task
berasingan dalam `docs/AS3_CONTROLLED_ACTIVE_SESSION_REVOCATION_BACKLOG.md` dan
`docs/SC8_REVOCATION_SCHEDULER_DECISION_BACKLOG.md`.
