# Audit dan Pelan Penambahbaikan Admin SSO Configuration

**Tarikh audit:** 16 Julai 2026  
**Skop:** Halaman Administrator — SSO Configuration  
**Status:** AUDITED — FASA 0 HINGGA FASA 6 DILAKSANAKAN; FASA 7 HINGGA FASA 8 DITANGGUHKAN OLEH OWNER
**Jenis kerja:** Audit, remediasi berfasa dan UAT terkawal

## 1. Tujuan

Dokumen ini merekodkan:

1. fungsi sebenar halaman `Administrator > SSO Configuration`;
2. aliran data daripada UI hingga ke enforcement;
3. perbezaan antara penerangan UI dan behavior backend;
4. kawalan keselamatan yang telah tersedia;
5. kelemahan dan risiko semasa; dan
6. cadangan penambahbaikan secara berfasa, termasuk UAT, rollout dan rollback.

Dokumen ini bukan kebenaran untuk mengubah protocol token, database, polisi
authentication atau consumer SSO. Perubahan tersebut tertakluk kepada keputusan
owner dan change control.

Keputusan penangguhan, task pending dan gate untuk menyambung SC7 hingga SC8
direkod dalam `docs/SC7_SC8_PENDING_CONFIGURATION_HANDOFF.md`.

## 2. Ringkasan Eksekutif

Walaupun dinamakan **SSO Configuration**, halaman ini bukan konfigurasi SSO
menyeluruh seperti client ID, callback URL, certificate, signing key atau
Identity Provider. Halaman ini hanya mengurus tiga polisi global dalam jadual
`sys_config`:

| Tetapan UI | Medan database | Fungsi sebenar |
|---|---|---|
| Session timeout | `token_timeout` | Menetapkan hayat token SSO dalam jam |
| Multiple sessions | `multi_session` | Menentukan sama ada login baharu membatalkan token lama pengguna |
| OTP email delivery | `email_OTP` | Menentukan sama ada OTP Forgot Password dihantar melalui e-mel |

Kawalan authorization dan CSRF untuk endpoint ini telah tersedia. Walau
bagaimanapun, halaman masih mempunyai kelemahan penting:

- istilah UI tidak menggambarkan behavior backend dengan tepat;
- input tidak divalidasi menggunakan polisi server-side yang ketat;
- perubahan konfigurasi tidak direkod sebagai audit event;
- query konfigurasi menganggap `sys_config` hanya mempunyai satu row;
- perubahan polisi tidak semestinya dikuatkuasakan serta-merta terhadap token
  sedia ada;
- OTP reset password diletakkan di bawah konfigurasi SSO; dan
- perubahan polisi keselamatan global belum memerlukan step-up authentication.

Nama yang lebih tepat bagi halaman ini ialah **Authentication & SSO Token
Policy**.

## 3. Komponen dan Aliran Semasa

### 3.1 Komponen utama

| Komponen | Lokasi | Peranan |
|---|---|---|
| UI dan JavaScript | `admin/dashboard.php` | Memaparkan setting, membaca nilai dan menghantar perubahan |
| Request guard | `lib/request_security.php` | Menguatkuasakan POST, CSRF, authentication dan role admin |
| Endpoint | `lib/q_func.php` | Membaca dan menghantar nilai kepada lapisan database |
| Persistence | `lib/Database.php` | Membaca dan mengemas kini `sys_config` |
| SSO token validation | `api.php` | Menggunakan `token_timeout` semasa token disahkan |
| PHP session policy | `lib/session_security.php` | Menguatkuasakan idle dan absolute timeout session OneID |

### 3.2 Aliran baca

```text
Admin membuka dashboard
    -> admin_get_settings()
    -> POST admin_get_sso_settings
    -> request guard mengesahkan CSRF dan role admin
    -> SELECT * FROM sys_config
    -> nilai dipaparkan dalam UI
```

### 3.3 Aliran simpan

```text
Admin memilih nilai dan menekan Save changes
    -> update_configuration()
    -> POST update_configuration
    -> request guard mengesahkan CSRF dan role admin
    -> UPDATE sys_config tanpa WHERE
    -> rowCount() dipulangkan
    -> UI memaparkan Updated atau No update
```

## 4. Behavior Sebenar Setiap Tetapan

### 4.1 SSO token lifetime

Pilihan UI membenarkan nilai berikut:

| Nilai | Paparan |
|---:|---|
| 0.5 | 30 minit |
| 1 | 1 jam |
| 2 | 2 jam |
| 12 | 12 jam |
| 24 | 1 hari |
| 48 | 2 hari |
| 72 | 3 hari |
| 168 | 1 minggu |

Nilai tersebut disimpan sebagai bilangan jam dan digunakan oleh API validation
token serta paparan sesi aktif.

Tetapan ini **bukan** timeout PHP session OneID. PHP session menggunakan nilai
berasingan yang kini ditetapkan dalam kod:

- idle timeout: 1,800 saat atau 30 minit;
- absolute timeout: 28,800 saat atau 8 jam; dan
- browser cookie session mempunyai lifetime `0`, iaitu sehingga browser session
  berakhir, tertakluk kepada enforcement server.

Oleh itu, memilih satu minggu pada UI tidak menjadikan PHP session OneID sah
selama satu minggu. Ia hanya mempengaruhi hayat token SSO.

API semasa turut mempunyai buffer auto-reissue selama kurang daripada satu jam
selepas token melepasi `token_timeout`. Consumer boleh menerima token baharu
tanpa pengguna login semula. Behavior ini menjadikan maksud label "timeout"
kurang tepat dan perlu dinilai bersama semua consumer.

### 4.2 Multiple active SSO tokens

Apabila `multi_session = 0`, login yang berjaya akan:

1. menetapkan semua token lama pengguna sebagai tidak aktif; dan
2. mencipta token baharu bagi login semasa.

Apabila `multi_session = 1`, token lama tidak dibatalkan semasa login baharu.
Pengguna boleh mempunyai beberapa token aktif daripada browser atau peranti
berlainan.

Menukar setting daripada ON kepada OFF tidak terus membatalkan semua sesi
berlebihan yang telah wujud. Polisi tersebut hanya digunakan pada login
berikutnya, melainkan token lama kemudiannya tamat atau dibatalkan melalui flow
lain.

### 4.3 Password-reset OTP email delivery

`email_OTP` hanya digunakan dalam flow Forgot Password. Ia bukan:

- OTP login;
- MFA pengguna;
- Admin 2FA;
- OTP untuk membuka halaman Administrator; atau
- OTP untuk melancarkan aplikasi SSO.

Flow Forgot Password masih boleh menjana dan menyimpan hash OTP apabila setting
ini OFF. Hanya penghantaran e-mel yang tidak dilakukan. Response awam kekal
generik bagi mengurangkan account enumeration, tetapi pengguna tidak mempunyai
saluran menerima kod jika tiada delivery channel lain.

## 5. Kawalan Keselamatan yang Telah Tersedia

Endpoint baca dan simpan konfigurasi telah dilindungi oleh kawalan berikut:

- request mesti menggunakan kaedah POST;
- tepat satu action yang dikenali mesti dihantar;
- CSRF token mesti sah;
- pengguna mesti mempunyai authenticated session;
- `login_user_type` mesti bernilai `1`; dan
- query update menggunakan prepared statement.

Kawalan ini mengurangkan risiko CSRF, akses pengguna biasa dan SQL injection.
Prepared statement bagaimanapun tidak menggantikan business validation.

## 6. Penemuan Audit dan Tahap Risiko

### A-SC-01 — Label session tidak tepat

**Tahap:** Tinggi  
**Penemuan:** UI menerangkan `token_timeout` sebagai tempoh sesi pengguna,
sedangkan PHP session mempunyai idle dan absolute timeout berasingan.  
**Impak:** Admin boleh menyangka polisi session telah ditetapkan sedangkan token
SSO dan PHP session menggunakan tempoh berlainan.  
**Cadangan:** Namakan semula kepada `SSO token lifetime` dan paparkan perbezaan
dengan PHP session secara jelas.

### A-SC-02 — Tiada validation polisi server-side

**Tahap:** Tinggi  
**Penemuan:** Endpoint menerima terus `token_timeout`, `multi_session` dan
`email_OTP` daripada POST tanpa whitelist domain.  
**Impak:** Session admin atau script boleh menghantar nilai negatif, terlalu
besar, kosong atau bukan boolean walaupun UI menyediakan pilihan sah.  
**Cadangan:** Gunakan service khusus, whitelist timeout dan normalisasi boolean;
tolak request tidak sah menggunakan response berstruktur.

### A-SC-03 — Tiada audit trail perubahan konfigurasi

**Tahap:** Tinggi  
**Penemuan:** Update tidak merekod admin, nilai lama, nilai baharu, masa, IP,
sebab atau correlation ID.  
**Impak:** Perubahan polisi authentication tidak boleh dikesan atau
direkonstruksi dengan baik semasa insiden.  
**Cadangan:** Rekod audit dalam transaction yang sama dengan perubahan.

### A-SC-04 — Query konfigurasi bergantung pada andaian single-row

**Tahap:** Sederhana  
**Penemuan:** `SELECT * FROM sys_config` mengambil row pertama, manakala `UPDATE
sys_config SET ...` tidak mempunyai `WHERE`.  
**Impak:** Jika duplicate row wujud, pembacaan menjadi tidak deterministik dan
semua row boleh dikemas kini.  
**Cadangan:** Gunakan primary key tetap, unique constraint dan `WHERE
config_id = 1`.

### A-SC-05 — Maklum balas update boleh mengelirukan

**Tahap:** Sederhana  
**Penemuan:** UI menganggap hanya `rowCount() === 1` sebagai kejayaan. Nilai `0`
boleh bermaksud tiada perubahan, manakala nilai lebih daripada `1` boleh
bermaksud beberapa row telah berubah.  
**Impak:** UI boleh melaporkan "No update" walaupun terdapat isu integriti data.  
**Cadangan:** Pulangkan status domain yang jelas dan semak tepat satu row sasaran.

### A-SC-06 — Polisi baharu tidak semestinya berkuat kuasa serta-merta

**Tahap:** Sederhana  
**Penemuan:** Mematikan multiple session tidak membatalkan token sedia ada.
Menurunkan timeout juga tidak menjalankan revocation global secara atomik.  
**Impak:** Keadaan token aktif mungkin tidak sepadan dengan polisi yang baru
dipaparkan.  
**Cadangan:** Tentukan strategi enforcement, sediakan preview impak dan gunakan
expiry/policy version per token.

### A-SC-07 — Password recovery bercampur dengan SSO policy

**Tahap:** Sederhana  
**Penemuan:** `email_OTP` ialah saluran Forgot Password tetapi ditempatkan sebagai
SSO configuration.  
**Impak:** Admin boleh menyangka toggle mengawal MFA atau OTP SSO. Mematikannya
boleh menjejaskan pemulihan akaun.  
**Cadangan:** Pindahkan ke bahagian Password Recovery dan namakan semula kepada
`password_reset_email_enabled`.

### A-SC-08 — Tiada step-up authentication

**Tahap:** Sederhana  
**Penemuan:** Session dan role admin sahaja mencukupi untuk menukar polisi
authentication global.  
**Impak:** Session admin yang dicuri boleh digunakan untuk mengubah polisi tanpa
faktor pengesahan tambahan.  
**Cadangan:** Wajibkan server-side Admin Step-Up 2FA untuk melihat atau mengubah
konfigurasi sensitif.

### A-SC-09 — Pengendalian ralat dan concurrency UI lemah

**Tahap:** Rendah  
**Penemuan:** AJAX error callback kosong dan butang Save tidak dikunci semasa
request.  
**Impak:** Admin tidak mengetahui sebab kegagalan dan double-submit boleh
berlaku.  
**Cadangan:** Paparkan ralat selamat, gunakan loading state dan cegah request
bertindih.

### A-SC-10 — Endpoint baca menggunakan `SELECT *`

**Tahap:** Rendah  
**Penemuan:** Keseluruhan row `sys_config` dihantar kepada browser admin.  
**Impak:** Medan baharu yang sensitif boleh terdedah pada masa hadapan tanpa
disedari.  
**Cadangan:** Gunakan explicit projection dan DTO response.

## 7. Prinsip Reka Bentuk Penambahbaikan

Pelaksanaan perlu mematuhi prinsip berikut:

1. Server menjadi sumber kebenaran bagi validation dan authorization.
2. UI menerangkan kesan sebenar setiap polisi.
3. Setiap perubahan boleh dijejak dan dipulihkan.
4. Polisi baharu mempunyai enforcement yang ditentukan secara eksplisit.
5. Perubahan protocol token dibuat hanya selepas consumer inventory dan UAT.
6. Tiada token, OTP, cookie, session ID atau credential direkod dalam audit.
7. Kegagalan konfigurasi mesti fail closed tanpa menyebabkan silent corruption.
8. Forward migration, rollback dan operational recovery mesti disediakan.

## 8. Pelan Pelaksanaan Berfasa

### Fasa 0 — Baseline dan keputusan owner

**Objektif:** Mewujudkan baseline sebelum sebarang perubahan behavior.

Aktiviti:

- sahkan schema, primary key dan jumlah row `sys_config`;
- rekod nilai konfigurasi semasa;
- petakan semua caller bagi tiga setting;
- inventori consumer SSO yang menggunakan validation dan auto-reissue;
- tambah characterization test bagi behavior semasa;
- backup konfigurasi dan sediakan langkah restore;
- sahkan owner OneID, database, SMTP dan consumer; dan
- rekod keputusan polisi yang masih terbuka.

**Exit criteria:** Baseline, backup, caller map, consumer inventory dan keputusan
owner minimum telah direkod.

### Fasa 1 — Ketepatan UI dan operational feedback

**Objektif:** Memastikan admin memahami kesan sebenar tanpa mengubah protocol.

Aktiviti:

- namakan halaman `Authentication & SSO Token Policy`;
- namakan `Session timeout` sebagai `SSO token lifetime`;
- namakan `Multiple sessions` sebagai `Allow multiple active SSO tokens`;
- namakan `OTP email delivery` sebagai `Send password-reset OTP by email`;
- paparkan perbezaan token SSO dan PHP session;
- tambah warning bagi satu minggu dan OTP email OFF;
- paparkan nilai lama dan baharu sebelum confirmation;
- tambah loading state, double-submit protection dan error message; dan
- bezakan status Saved, No Changes, Validation Failed dan System Error.

**Exit criteria:** Penerangan UI sepadan dengan behavior sebenar dan semua hasil
request mempunyai feedback yang jelas.

### Fasa 2 — Validation dan configuration service

**Objektif:** Menjamin hanya konfigurasi sah boleh disimpan.

Aktiviti:

- bina `SsoConfigurationService` atau service domain setara;
- whitelist `0.5, 1, 2, 12, 24, 48, 72, 168`;
- terima toggle hanya sebagai boolean sah;
- tolak field hilang, tambahan atau format tidak sah mengikut contract;
- gunakan explicit response DTO dan correlation ID;
- gunakan explicit column selection semasa membaca;
- bezakan unchanged daripada failure; dan
- tambah unit serta endpoint security test.

**Exit criteria:** Request tidak sah ditolak tanpa mutation dan response contract
konsisten.

### Fasa 3 — Integriti database dan audit trail

**Objektif:** Menjamin single-row configuration dan kebolehkesanan perubahan.

Aktiviti:

- tambah primary key atau unique singleton constraint;
- gunakan `WHERE config_id = 1` dan sahkan tepat satu row sasaran;
- rekod nilai lama dan baharu dalam audit;
- rekod admin, IP, masa, correlation ID, changed fields dan change reason;
- lakukan update dan audit dalam satu transaction;
- paparkan last changed by/at dalam UI; dan
- sediakan forward dan rollback migration.

Audit event dicadangkan:

- `SSO_CONFIG_UPDATED`;
- `SSO_CONFIG_UPDATE_REJECTED`;
- `SSO_TOKEN_LIFETIME_CHANGED`;
- `SSO_MULTI_SESSION_ENABLED`;
- `SSO_MULTI_SESSION_DISABLED`;
- `PASSWORD_RESET_EMAIL_ENABLED`; dan
- `PASSWORD_RESET_EMAIL_DISABLED`.

**Exit criteria:** Setiap mutation berjaya atau gagal dapat dikesan tanpa data
sensitif dan duplicate configuration dicegah.

### Fasa 4 — Definisi lifecycle token dan session

**Objektif:** Memisahkan dan mendokumentasikan setiap jenis timeout.

Model konfigurasi yang perlu dinilai:

- `php_session_idle_timeout`;
- `php_session_absolute_timeout`;
- `sso_token_absolute_lifetime`;
- `sso_token_refresh_window`; dan
- `sso_token_idle_timeout`, hanya jika diperlukan.

Tidak semua nilai mesti boleh diubah melalui UI. Nilai berisiko boleh kekal
sebagai deployment configuration.

Aktiviti:

- tentukan absolute versus sliding expiry;
- nilai semula buffer auto-reissue satu jam;
- reka refresh contract yang eksplisit;
- jalankan contract test terhadap semua consumer;
- sediakan compatibility flag dan monitoring; dan
- dokumentasikan behavior apabila clock skew berlaku.

**Exit criteria:** Polisi timeout tidak ambigu dan sekurang-kurangnya consumer
pilot lulus end-to-end tanpa login loop atau outage.

### Fasa 5 — Enforcement dan revocation polisi

**Status pelaksanaan (16 Julai 2026): IMPLEMENTED — CONTROLLED UAT PASS.**
Preview server-side, confirmation impak, grace period 15 minit, atomic
scheduling, lazy enforcement, batch runner dan cancellation berdasarkan
correlation ID telah dilaksanakan. Bukti dan runbook direkod dalam
`docs/SC5_ENFORCEMENT_DAN_REVOCATION_POLISI.md`.

**Objektif:** Menentukan kesan polisi terhadap token sedia ada.

Cadangan production ialah preview dan controlled revocation:

```text
Admin memilih polisi
    -> server memvalidasi
    -> preview menunjukkan token/pengguna terkesan
    -> admin mengesahkan dengan change reason
    -> polisi disimpan
    -> revocation dijalankan dengan correlation ID
    -> hasil direkod dan dipaparkan
```

Pilihan enforcement yang perlu diputuskan:

| Pilihan | Behavior | Trade-off |
|---|---|---|
| Next login | Token lama kekal hingga login/expiry | Gangguan rendah, enforcement lambat |
| Immediate | Semua token tidak patuh ditamatkan | Selamat segera, risiko gangguan tinggi |
| Grace period | Revocation selepas tempoh terkawal | Seimbang, memerlukan orchestration |

Pertimbangkan penyimpanan `expires_at` dan `policy_version` pada setiap token
supaya expiry tidak bergantung sepenuhnya pada nilai global yang boleh berubah.

**Exit criteria:** Kesan kepada token lama ditentukan, boleh dipreview, diaudit
dan diuji untuk rollback.

### Fasa 6 — Pisahkan Password Recovery

**Status pelaksanaan (dikemas kini 17 Julai 2026): COMPLETE — AUTOMATED
CONTRACT, MAILBOX DELIVERY DAN FORGOT-PASSWORD OTP E2E PASS.** Migration rename, panel dan service berasingan,
fail-closed delivery, SMTP readiness, test delivery dan audit telah disediakan.
Kegagalan awal bukan defect aplikasi; mailbox kehabisan storan dan telah
dibersihkan oleh DBA. Owner mengesahkan e-mel diterima selepas pembetulan.
Acceptance terbaru direkod melalui correlation `8feb00eba0828c18`. Rujuk
`docs/SC6_PEMISAHAN_PASSWORD_RECOVERY.md`.

**Objektif:** Mengeluarkan OTP reset password daripada skop SSO policy.

Aktiviti:

- pindahkan setting ke bahagian `Password Recovery`;
- rename kepada `password_reset_email_enabled` melalui migration selamat;
- tentukan behavior apabila semua delivery channel OFF;
- tambah SMTP health status dan test delivery yang diaudit;
- elakkan penciptaan challenge yang tidak boleh dihantar, kecuali flow manual
  rasmi wujud;
- sahkan recovery procedure bagi e-mel tiada/tidak sah; dan
- pastikan setting ini tidak digunakan sebagai Admin 2FA.

**Exit criteria:** Password recovery mempunyai owner, delivery policy, health
check dan fail-closed behavior yang jelas.

### Fasa 7 — Admin Step-Up 2FA dan authorization khusus

**Status:** DEFERRED BY OWNER pada 18 Julai 2026. Tiada feature flag, schema,
challenge atau enforcement Step-Up/TOTP diaktifkan. Rujuk handoff SC7-SC8.

**Objektif:** Melindungi konfigurasi keselamatan daripada session admin yang
dicuri.

Aktiviti:

- bina challenge khusus purpose `ADMIN_ACCESS` atau
  `SECURITY_CONFIGURATION_CHANGE`;
- sediakan dua kaedah pilihan: OTP e-mel dan Microsoft Authenticator melalui
  standard TOTP, bukan Microsoft Entra push notification;
- bina enrollment, confirmation, revocation dan recovery faktor TOTP dengan
  secret dienkripsi menggunakan key di luar database dan repository;
- ikat challenge kepada admin, session, purpose dan browser;
- wajibkan verification server-side untuk endpoint sensitif;
- rotasi session ID dan CSRF selepas verification;
- gunakan tempoh step-up pendek, dicadangkan 15 minit;
- batalkan step-up apabila logout, login semula, role atau session berubah;
- tambah rate limit, resend cooldown dan audit event; dan
- kekalkan satu role admin sedia ada tanpa memperkenalkan role security-admin.

**Exit criteria:** Direct URL dan direct endpoint tidak boleh memintas step-up
apabila kawalan diaktifkan; kedua-dua kaedah disahkan server-side dan kegagalan
satu faktor tidak menghasilkan bypass faktor yang lain.

### Fasa 8 — UAT, controlled rollout dan monitoring

**Status:** DEFERRED BY OWNER pada 18 Julai 2026. UAT khusus SC0-SC6 yang telah
lulus kekal sah, tetapi consolidated monitoring, Step-Up rollout dan keputusan
scheduler revocation belum ditutup. Rujuk handoff SC7-SC8.

**Objektif:** Melaksanakan perubahan tanpa outage consumer atau lockout admin.

UAT minimum:

- semua nilai timeout sah dan nilai tidak sah;
- authorization, CSRF dan session expiry;
- multiple token ON/OFF;
- token lama selepas polisi berubah;
- concurrent update oleh dua admin;
- audit dan correlation ID;
- Forgot Password ketika e-mel ON/OFF;
- kegagalan SMTP;
- semua consumer SSO yang diluluskan;
- forward/rollback migration; dan
- operational recovery admin.

Monitoring minimum:

- token validation dan refresh failure;
- lonjakan login semula;
- jumlah token direvoke;
- SMTP failure;
- configuration update dan rejected update;
- step-up failure/rate limit; dan
- consumer yang masih menggunakan contract legacy.

Urutan rollout:

```text
UAT
    -> pilot admin
    -> pilot consumer SSO
    -> maintenance window
    -> production dengan feature flag
    -> observation period
    -> penamatan compatibility lama selepas owner approval
```

**Exit criteria:** Semua gate UAT lulus, rollback diuji, monitoring aktif dan
owner memberi keputusan GO.

## 9. Keutamaan Remediasi

| Keutamaan | Remediasi |
|---|---|
| Kritikal | Validation polisi server-side |
| Kritikal | Audit setiap perubahan konfigurasi |
| Tinggi | Betulkan istilah dan penerangan UI |
| Tinggi | Primary key dan targeted update bagi `sys_config` |
| Tinggi | Bezakan token lifetime daripada PHP session |
| Tinggi | Tentukan enforcement terhadap token sedia ada |
| Tinggi | Pisahkan Password Recovery daripada SSO policy |
| Sederhana | Preview impak, confirmation dan change reason |
| Sederhana | Admin Step-Up 2FA |
| Sederhana | Monitoring dan configuration history |
| Strategik | Redesign token refresh dan migration consumer |

Urutan praktikal yang disyorkan:

```text
Baseline
    -> UI accuracy
    -> validation service
    -> database integrity dan audit
    -> enforcement preview
    -> Password Recovery separation
    -> Admin Step-Up 2FA
    -> token lifecycle redesign
    -> consumer migration
```

## 10. Acceptance Criteria Keseluruhan

- Hanya admin yang sah dan memenuhi step-up policy boleh mengubah konfigurasi.
- Nilai di luar domain dibenarkan sentiasa ditolak tanpa mutation.
- UI menerangkan token SSO, PHP session dan password recovery secara berasingan.
- Tepat satu configuration record boleh wujud dan dikemas kini.
- Setiap perubahan merekod actor, masa, IP, before/after, reason dan correlation.
- Audit tidak mengandungi token, OTP, cookie, session ID atau credential.
- Admin boleh melihat impak terhadap token aktif sebelum perubahan berisiko.
- Behavior token sedia ada selepas perubahan polisi telah ditentukan dan diuji.
- Multiple-session OFF mempunyai enforcement yang konsisten dengan keputusan
  owner.
- Password-reset email OFF tidak menghasilkan flow pemulihan yang tidak boleh
  diselesaikan tanpa prosedur rasmi.
- Semua consumer yang diluluskan lulus login, validation, refresh dan logout.
- Concurrent update tidak menyebabkan lost update tanpa amaran.
- Kegagalan database, SMTP dan consumer dipaparkan serta dimonitor dengan
  selamat.
- Forward migration, rollback dan operational recovery telah diuji.

## 11. Keputusan Owner yang Diperlukan

Sebelum fasa berimpak tinggi dilaksanakan, owner perlu mengesahkan:

1. nama dan skop rasmi halaman;
2. absolute atau sliding lifetime bagi token SSO;
3. keperluan dan tempoh refresh window;
4. sama ada buffer auto-reissue satu jam dikekalkan sementara;
5. behavior token lama apabila timeout dipendekkan;
6. immediate, grace-period atau next-login enforcement bagi multiple session;
7. timeout PHP session yang diluluskan;
8. sama ada password-reset email boleh dimatikan dalam production;
9. saluran recovery apabila SMTP atau e-mel pengguna gagal;
10. tempoh dan polisi Admin Step-Up 2FA;
11. role yang boleh melihat dan mengubah security configuration;
12. senarai serta owner setiap consumer SSO;
13. maintenance window, pilot consumer dan observation period; dan
14. owner/on-call untuk lockout admin, SMTP dan outage SSO.

## 12. Risiko Pelaksanaan dan Rollback

| Risiko | Mitigasi |
|---|---|
| Consumer gagal selepas expiry/refresh berubah | Inventory, contract test, pilot dan compatibility flag |
| Pengguna terkeluar serentak | Preview, grace period dan controlled revocation |
| Admin terkunci akibat step-up/SMTP | Pilot, e-mel disahkan dan DBA recovery procedure |
| Duplicate atau kehilangan config | Singleton constraint, transaction dan backup |
| Lost update oleh dua admin | Version field atau optimistic locking |
| Audit gagal selepas config berubah | Satu transaction bagi update dan audit |

Rollback minimum perlu merangkumi:

- restore nilai konfigurasi sebelumnya;
- rollback migration yang telah diuji;
- feature flag untuk behavior lifecycle baharu;
- penghentian job revocation yang belum selesai;
- pemulihan akses admin melalui prosedur terkawal; dan
- pengesahan semula sekurang-kurangnya satu consumer selepas rollback.

## 13. Keputusan Audit

Halaman semasa berfungsi sebagai editor ringkas tiga polisi global dan mempunyai
kawalan asas authorization serta CSRF yang baik. Walau bagaimanapun, ia belum
memenuhi tahap kawalan yang sesuai bagi perubahan authentication global kerana
ketidakjelasan istilah, kekurangan validation domain, audit trail, enforcement
yang konsisten dan step-up authentication.

Remediasi perlu bermula dengan baseline, pembetulan UI, validation server-side
dan audit trail. Redesign token lifecycle hendaklah dibuat kemudian melalui
consumer inventory, compatibility contract dan controlled rollout. Sehingga
owner memberikan kelulusan, semua cadangan dalam dokumen ini kekal berstatus
Fasa 0 hingga Fasa 6 telah dilaksanakan mengikut keputusan owner. SMTP,
mailbox delivery dan OTP reset end-to-end Password Recovery telah disahkan
berjaya. Penemuan berkaitan Admin
Step-Up 2FA dan controlled rollout penuh kekal sebagai skop Fasa 7 hingga Fasa 8.
