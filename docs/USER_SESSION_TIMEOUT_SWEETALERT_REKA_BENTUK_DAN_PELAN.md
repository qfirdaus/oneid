# User Session Timeout SweetAlert — Reka Bentuk dan Pelan Pelaksanaan

**Tarikh keputusan:** 7 Ogos 2026

**Environment sasaran awal:** Staging/UAT

**Status:** REKA BENTUK DIPERSETUJUI / FASA 0 SELESAI DAN STAGING VALIDATED

**Pendekatan:** OneID-only, berisiko rendah dan serasi dengan service provider sedia ada

## 1. Tujuan

Dokumen ini menetapkan reka bentuk amaran tamat sesi pengguna OneID menggunakan
SweetAlert. Perubahan mesti menggunakan setting timeout Administrator sebagai
sumber tempoh, melindungi kerja pengguna pada halaman OneID dan tidak memerlukan
sebarang perubahan kod pada aplikasi atau service provider lain.

Dokumen ini bukan rekod bahawa fungsi tersebut telah diaktifkan. Semua perubahan
runtime masih perlu melalui fasa pelaksanaan, ujian dan kelulusan UAT.

## 2. Keputusan Pemilik Sistem

Keputusan berikut telah dipersetujui:

1. Tempoh PHP session pengguna OneID mesti mengikuti setting `token_timeout` yang
   ditetapkan Administrator; nilai 30 minit tidak boleh kekal hard-coded.
2. Implementasi tidak boleh memerlukan perubahan pada aplikasi lain.
3. Idle expiry melalui popup hanya menutup sesi portal OneID dan tidak membatalkan
   token yang mungkin sedang digunakan oleh aplikasi lain.
4. Aktiviti atau heartbeat daripada aplikasi lain tidak digunakan untuk
   memperbaharui PHP session OneID kerana OneID tidak boleh membezakan aktiviti
   manusia daripada polling automatik tanpa perubahan integrasi.
5. Logout manual mengekalkan behavior sedia ada: token semasa dibatalkan, cookie
   dibersihkan dan PHP session dimusnahkan.
6. Format token, kontrak `api.php`, URL SSO dan behavior validation service
   provider tidak boleh diubah oleh projek ini.

## 3. Keadaan Semasa

### 3.1 Setting Administrator

Setting `sys_config.token_timeout` disimpan dalam unit jam. Pilihan UI semasa:

| Nilai | Paparan |
|---:|---|
| `0.5` | 30 minit |
| `1` | 1 jam |
| `2` | 2 jam |
| `12` | 12 jam |
| `24` | 1 hari |
| `48` | 2 hari |
| `72` | 3 hari |
| `168` | 1 minggu |

Token SSO dinilai menggunakan `token_issued_at`. PHP session pula menggunakan
idle timeout 1,800 saat dan absolute timeout 28,800 saat yang masih hard-coded.
Kedua-dua lifecycle belum menggunakan satu sumber konfigurasi yang konsisten.

### 3.2 Dashboard Pengguna

Dashboard menghantar `update_specific_token_datetime` setiap lima minit. Request
ini diklasifikasikan sebagai technical heartbeat dan tidak memperbaharui
`oneid_session_last_activity`. Apabila request gagal, dashboard kini melakukan
reload tanpa penerangan kepada pengguna.

### 3.3 Aplikasi Lain

Aplikasi lain membaca atau mengesahkan token melalui kontrak SSO sedia ada.
OneID tidak menerima signal yang boleh dipercayai bahawa pengguna sedang
berinteraksi di aplikasi tersebut. Idle PHP session OneID juga tidak serta-merta
menamatkan local session aplikasi lain.

## 4. Sempadan Skop

### 4.1 Dalam Skop

- PHP session untuk halaman pengguna OneID yang authenticated;
- setting timeout Administrator sebagai sumber tempoh;
- endpoint status dan pembaharuan idle session OneID;
- SweetAlert amaran dua minit sebelum deadline efektif;
- pengendalian expiry, network failure, token revoked dan account inactive;
- penyelarasan tab OneID dalam browser yang sama;
- dashboard, Account Security dan halaman authenticated berkaitan;
- locale Bahasa Melayu dan English;
- audit log, characterization test, UAT dan rollback.

### 4.2 Di Luar Skop

- perubahan kod pada service provider atau aplikasi luar;
- format token atau parameter API integrasi;
- server-to-server activity reporting daripada aplikasi lain;
- push logout atau front-channel logout merentas aplikasi;
- perubahan local session aplikasi lain;
- menganggap polling, websocket ping atau heartbeat sebagai aktiviti manusia;
- refactor besar keseluruhan modul SSO.

## 5. Kontrak Behavior

### 5.1 Penentuan Deadline

Backend ialah sumber kebenaran. Ia perlu memulangkan sekurang-kurangnya:

- `authenticated`;
- `idle_timeout_seconds`;
- `idle_remaining_seconds`;
- `absolute_remaining_seconds`, jika had absolute dikekalkan;
- `effective_remaining_seconds`;
- `server_epoch`;
- `code` dan `reason` yang selamat.

Frontend tidak boleh mengira timeout hanya daripada waktu page render. Deadline
efektif ialah baki terpendek antara idle dan absolute deadline.

### 5.2 Masa Popup

Warning window lalai ialah 120 saat:

| Setting admin | Popup dijangka |
|---|---:|
| 30 minit | sekitar minit ke-28 |
| 1 jam | sekitar minit ke-58 |
| 2 jam | sekitar 1 jam 58 minit |

Jika baki efektif sudah kurang daripada dua minit ketika halaman kembali visible,
popup dipaparkan serta-merta dengan baki sebenar daripada server.

### 5.3 Stay Connected

Apabila pengguna menekan **Stay Connected**:

1. OneID mengesahkan PHP session, token semasa dan status akaun;
2. hanya idle session OneID diperbaharui mengikut setting Administrator;
3. token tidak di-rotate dan tidak dibatalkan;
4. popup kejayaan memerlukan pengesahan `OK`;
5. deadline diselaraskan kepada tab OneID lain dalam browser yang sama.

Jika token telah revoked, akaun tidak aktif, CSRF tidak sah atau deadline telah
tamat, pembaharuan mesti ditolak. Kegagalan rangkaian tidak boleh dianggap
sebagai expiry.

### 5.4 Tiada Tindakan

Apabila countdown mencapai `00:00` tanpa tindakan pengguna:

1. input sensitif OneID dibersihkan apabila sesuai;
2. PHP session authenticated OneID dikosongkan;
3. cookie OneID pada domain semasa dibersihkan;
4. token database tidak dibatalkan oleh idle-expiry flow;
5. pengguna dibawa ke landing/login OneID dengan mesej sesi portal tamat;
6. aplikasi lain tidak menerima kontrak atau perubahan kod baharu.

Wording tidak boleh mendakwa bahawa semua aplikasi telah dilog keluar.

### 5.5 Logout Manual

Logout manual kekal berasingan daripada idle expiry. Behavior sedia ada dikekalkan:

- batalkan token semasa;
- bersihkan cookie;
- musnahkan PHP session;
- jalankan federated logout apabila berkenaan.

### 5.6 Aktiviti Pengguna

Request OneID yang berpunca daripada tindakan bermakna boleh mengemas kini idle
activity melalui lifecycle session sedia ada. Perkara berikut tidak boleh
memanjangkan idle session:

- `update_specific_token_datetime`;
- status polling session;
- timer JavaScript;
- pergerakan mouse tanpa request;
- tab sekadar terbuka;
- heartbeat aplikasi lain.

## 6. Keadaan Khas Halaman User

Controller perlu serasi dengan:

- modal wajib tukar kata laluan yang static;
- perubahan password yang boleh rotate CSRF atau meminta reauthentication;
- carian aplikasi lokal yang tidak menghasilkan request;
- favourite, pelancaran SSO dan active-session sign-off;
- FAQ dan modal Bootstrap;
- Account Security, enrollment dan revoke MFA/TOTP;
- QR enrollment yang sedang dipaparkan;
- Administrator entry loader;
- beberapa tab, background throttling dan laptop sleep;
- browser Back/Forward cache;
- offline, timeout rangkaian dan HTTP 5xx.

`user_mfa_challenge` ialah flow pra-authentication dan tidak menerima controller
popup authenticated. Endpoint imej seperti profile photo juga tidak memaparkan UI.

## 6A. Hierarchy Session User dan Administrator

Administrator juga ialah pengguna authenticated. Akses Administrator bukan
session asas yang berasingan sepenuhnya; ia ialah grant tambahan di atas PHP
session pengguna:

```text
PHP authenticated user session
└── ADMIN_ACCESS step-up grant
```

Kontrak berikut mesti dipelihara:

1. Grant `ADMIN_ACCESS` tidak boleh digunakan apabila PHP authenticated session,
   akaun atau token asas sudah tidak sah.
2. Halaman Administrator hanya memuatkan controller/popup admin. Controller popup
   user tidak boleh dimuatkan serentak pada halaman admin.
3. Klik **Stay Connected** pada popup admin ialah aktiviti manusia yang eksplisit.
   Request tersebut mesti:

   - memperbaharui idle timestamp PHP session asas;
   - memperbaharui grant `ADMIN_ACCESS` mengikut lifetime Administrator;
   - tidak rotate atau revoke token SSO;
   - tidak melepasi absolute deadline PHP session.

4. Status polling admin kekal technical heartbeat dan tidak memperbaharui idle
   timestamp PHP session.
5. Deadline efektif pada halaman admin ialah baki terpendek antara:

   - PHP idle session;
   - PHP absolute session;
   - grant `ADMIN_ACCESS`.

6. Respons renewal admin perlu memberikan baki authoritative yang mencukupi untuk
   controller menyusun deadline sebenar. UI tidak boleh menjanjikan 15 minit jika
   base/absolute session hanya berbaki kurang daripada 15 minit.
7. Jika PHP authenticated session tamat, renewal admin mesti ditolak dan pengguna
   keluar daripada halaman Administrator melalui flow yang terkawal.

Contoh apabila lifetime Administrator ialah 15 minit dan timeout user ialah 30
minit:

```text
10:00  PHP user session dan ADMIN_ACCESS aktif
10:13  Popup admin dipaparkan
10:14  Administrator klik Stay Connected
10:14  PHP idle session reset kepada 30 minit
10:14  ADMIN_ACCESS diperbaharui kepada 15 minit
10:27  Popup admin seterusnya dipaparkan
```

Jika absolute session hanya berbaki lima minit, tempoh efektif selepas renewal
ialah maksimum lima minit walaupun setting grant Administrator ialah 15 minit.

## 7. Kod Respons Minimum

Respons JSON authenticated perlu membezakan sekurang-kurangnya:

| Kod | Maksud frontend |
|---|---|
| `USER_SESSION_ACTIVE` | Susun deadline daripada respons server |
| `USER_SESSION_RENEWED` | Paparkan pengesahan dan susun deadline baharu |
| `USER_SESSION_EXPIRED` | Tutup sesi portal OneID dan redirect |
| `SSO_TOKEN_REVOKED` | Akses telah ditamatkan; jangan tawarkan renewal |
| `ACCOUNT_INACTIVE` | Maklumkan akaun tidak aktif |
| `CSRF_INVALID` | Jangan anggap renewal berjaya; revalidate/reauthenticate |
| `SESSION_STATUS_UNAVAILABLE` | Network/server failure; beri pilihan cuba lagi |

Mesej pengguna mesti localized dan tidak mendedahkan token atau butiran dalaman.

## 8. Keserasian yang Tidak Boleh Pecah

Pelaksanaan mesti membuktikan perkara berikut sebelum rollout:

- payload dan respons `api.php` untuk service provider tidak berubah;
- `new_sso_cre`, `sso_cre`, site validation dan ACL kekal serasi;
- aplikasi yang sedang terbuka tidak menerima revocation akibat idle popup OneID;
- manual logout masih membatalkan token seperti baseline;
- setting multi-session tidak berubah;
- admin step-up session controller tidak terjejas;
- MyDigital ID local-first/federated logout tidak terjejas;
- perubahan setting Administrator masih melalui preview, version check, audit dan
  grace-period policy sedia ada.

## 9. Fasa Pelaksanaan yang Dicadangkan

### Fasa 0 — Baseline dan Contract Lock

**Rekod pelaksanaan:** Lihat
`docs/USER_SESSION_TIMEOUT_F0_BASELINE_DAN_CONTRACT.md`. Characterization source
baseline lulus 17/17 tanpa mutation runtime. FPM staging retention dinaikkan
secara terkawal kepada lapan jam, rollback satu-baris disahkan dan smoke-test
authenticated OneID, Administrator, SSO serta sistem UAT lain semuanya lulus.

- rekod behavior semasa PHP session, cookie, token dan logout;
- tambah characterization test yang mengunci kontrak aplikasi luar;
- rekod setting semasa dan konfigurasi PHP session UAT;
- tentukan rollback dan smoke-test.

**Gate:** Tiada mutation production dan semua kontrak integrasi baseline lulus.

### Fasa 1 — Polisi Timeout Berpusat dalam OneID

- perkenalkan pembaca polisi timeout daripada `sys_config.token_timeout`;
- tukar unit jam kepada saat secara terkawal;
- gunakan polisi sama untuk PHP idle session;
- kekalkan had absolute sebagai kawalan berasingan dan dokumentasikan interaksinya;
- wujudkan helper baki authoritative bagi PHP idle dan absolute session untuk
  digunakan oleh flow user serta admin;
- jangan ubah API service provider.

**Gate:** 30 minit, 1 jam dan boundary tepat disahkan melalui ujian tanpa UI.

### Fasa 2 — Endpoint Status, Renew dan Portal Expiry

- tambah action authenticated untuk status teknikal;
- tambah action eksplisit untuk Stay Connected;
- tambah expiry handler portal-only yang tidak revoke token;
- tambah kod respons stabil dan audit event;
- pastikan status polling tidak memperbaharui idle activity;
- kemas kini kontrak renewal admin supaya klik Stay Connected turut menyegarkan
  PHP idle session asas tanpa rotate/revoke token SSO;
- pulangkan deadline efektif admin berdasarkan baki terpendek antara PHP idle,
  PHP absolute dan grant `ADMIN_ACCESS`;
- tolak renewal admin apabila base authenticated session tidak lagi sah.

**Gate:** Renew, expiry, revoked token, inactive account, CSRF dan network failure
memberikan hasil yang berbeza dan fail-closed.

### Fasa 3 — SweetAlert dan Integrasi Halaman User

- bina aset JS/CSS berasingan daripada controller admin;
- load config localized daripada server;
- tambah countdown, Stay Connected dan logout/exit action;
- selaraskan beberapa tab;
- revalidate selepas tab visible atau browser bangun daripada sleep;
- selesaikan z-index dan konflik modal/MFA;
- pastikan controller user tidak dimuatkan pada halaman Administrator dan hanya
  satu popup session boleh menjadi pemilik countdown pada sesuatu halaman.

**Gate:** Dashboard dan Account Security lulus desktop/mobile, BM/English dan
keyboard navigation.

### Fasa 4 — Heartbeat dan Error Handling Dashboard

- gantikan `location.reload(true)` apabila heartbeat gagal;
- route `401` berdasarkan kod sebenar;
- jangan samakan offline/5xx dengan expiry;
- pastikan request biasa menyusun semula deadline daripada keadaan server tanpa
  mencipta polling yang menghidupkan session.

**Gate:** Tiada reload loop dan tiada silent success apabila backend menolak request.

### Fasa 5 — Regression, UAT dan Controlled Rollout

- jalankan automated contracts dan smoke-test authenticated;
- uji 30 minit dan 1 jam menggunakan jam terkawal/test override yang tidak aktif
  di production;
- uji aplikasi lain kekal berjalan selepas portal idle expiry;
- uji manual logout masih menyebabkan token ditolak pada validation berikutnya;
- uji perubahan polisi ketika session dan popup sedang aktif;
- aktifkan secara feature flag di UAT, pantau log dan sediakan rollback aset/config.

**Gate:** Pemilik sistem menerima bukti UAT dan tiada perubahan diperlukan pada
mana-mana aplikasi lain.

## 10. Acceptance Criteria

Pelaksanaan dianggap lengkap hanya apabila:

1. Setting 30 minit menghasilkan warning sekitar minit ke-28.
2. Setting 1 jam menghasilkan warning sekitar minit ke-58.
3. Stay Connected memperbaharui idle session OneID tanpa rotate/revoke token.
4. Tiada tindakan menamatkan portal OneID tanpa mengganggu aplikasi lain.
5. Logout manual masih membatalkan token.
6. Technical heartbeat tidak memanjangkan idle session.
7. Tab background mendapatkan baki authoritative apabila kembali visible.
8. Network failure tidak menyebabkan logout atau reload loop secara automatik.
9. Popup tidak merosakkan password/MFA modal dan input sedia ada.
10. BM/English, mobile dan keyboard navigation lulus.
11. Contract test membuktikan API/token service provider tidak berubah.
12. Rollback boleh mematikan controller user tanpa menjejaskan login/SSO semasa.
13. Stay Connected admin memperbaharui PHP idle session dan `ADMIN_ACCESS` tanpa
    rotate/revoke token SSO.
14. Status polling admin tidak memperbaharui PHP idle session.
15. Halaman admin tidak memaparkan popup user dan popup admin secara serentak.
16. Renewal admin tidak melepasi PHP absolute deadline dan UI memaparkan baki
    efektif sebenar.
17. Grant `ADMIN_ACCESS` tidak boleh meneruskan akses apabila base authenticated
    session telah tamat.

## 11. Risiko Baki yang Diterima

Tanpa perubahan aplikasi lain:

- OneID tidak mengetahui aktiviti manusia dalam aplikasi lain;
- idle expiry portal tidak menjamin global logout;
- aplikasi lain menentukan sendiri bila token disemak semula;
- token lifecycle dan local session aplikasi lain boleh tamat pada masa berlainan.

Risiko ini diterima secara sedar bagi mengelakkan regression dan koordinasi besar
merentas pemilik aplikasi. Popup mesti menerangkan bahawa hanya sesi portal OneID
yang akan ditutup.

## 12. Cadangan Teks SweetAlert

**Bahasa Melayu**

- Tajuk: `Sesi OneID hampir tamat`
- Mesej: `Sesi portal OneID anda akan tamat kerana tiada aktiviti.`
- Butang utama: `Kekalkan Sesi`
- Butang kedua: `Tutup Sesi OneID`
- Kejayaan: `Sesi OneID berjaya diperbaharui.`

**English**

- Title: `Your OneID session is about to expire`
- Message: `Your OneID portal session will end due to inactivity.`
- Primary button: `Stay Connected`
- Secondary button: `End OneID Session`
- Success: `Your OneID session has been renewed.`

Nota sokongan boleh menyatakan bahawa aplikasi lain yang sedang digunakan tidak
ditutup oleh idle-expiry portal ini.
