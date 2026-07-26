# Audit dan Pelan Pelaksanaan MyDigital ID SSO untuk OneID UAT

**Tarikh audit:** 26 Julai 2026
**Environment sasaran:** UAT/Staging
**Domain:** `https://oneid-uat.upnm.edu.my/`
**Status:** STAGING IMPLEMENTED/ACTIVE — CONTROLLED ACCEPTANCE MASIH BERJALAN
**Runtime/schema mutation:** F2 additive schema applied; `user_tbl` unchanged
**Rujukan akaun ujian positif:** `0530-09` (diberikan oleh owner; padanan
canonical, NRIC dan live MyDigital ID login telah disahkan)

> **Canonical status note — 26 Julai 2026:** Bahagian awal dokumen ini
> mengekalkan audit/design historical. Status pelaksanaan sebenar, evidence
> staging, commit, migration, Nginx hardening dan baki kerja diringkaskan dalam
> Seksyen 28 serta `MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md`.

## 1. Objektif

Task ini akan menambah MyDigital ID sebagai kaedah authentication kedua kepada
OneID tanpa menggantikan login sedia ada.

OneID selepas pelaksanaan mempunyai dua pintu masuk:

1. login form OneID menggunakan ID pengguna/nombor matrik dan kata laluan; dan
2. login melalui MyDigital ID menggunakan OpenID Connect (OIDC).

MyDigital ID hanya mengesahkan identiti. OneID kekal sebagai sumber kebenaran
bagi kewujudan akaun, status akaun, session, token, kategori pengguna, ACL dan
akses aplikasi.

## 2. Sempadan Audit

Audit ini merangkumi:

- guideline MyDigital ID SSO v6.0A;
- endpoint khusus UPNM;
- sample PHP OIDC dan HTML/JavaScript;
- struktur runtime, session, token dan authentication OneID;
- model data identiti OneID yang berkaitan;
- keputusan requirement daripada owner;
- reka bentuk sasaran;
- risiko keselamatan dan privasi;
- pelan pembangunan berfasa;
- strategi ujian, deployment dan rollback.

Audit ini tidak:

- mengaktifkan MyDigital ID;
- mengubah schema;
- memasang dependency;
- mengubah login page;
- menguji credential sebenar;
- membuat panggilan kepada MyDigital ID;
- membaca atau merekod NRIC sebenar pengguna; atau
- membuktikan callback telah didaftarkan pada Keycloak.

## 3. Bahan Rujukan dan Integrity Snapshot

| Artefak | SHA-256 |
|---|---|
| `PP24176-MyDigitalID_2.0_SSO_Integration_Guideline_Document_v6.0A.pdf` | `9e6fcd6dd38b67ce53f9b0a386b49d03fbfe780c191e9b6b699321859c5003fc` |
| `Endpoint_UPNM.pdf` | `3b8e3a2a9a1fb9f69c19c9569540a3a1385c3f2e3dce690460f71037f52488fb` |
| sample `phpoidc/composer.json` | `ab7274bad2bdf6fd9d0558323d1e12d8e6c705643a4a7d86cb527d8a3248639b` |
| sample `phpoidc/composer.lock` | `8539143738d0a10e13514b402c72a7623ed2ecd2eccbd4f3a00e7e6b01207466` |
| sample `phpoidc/config.php` | `756348ea6c0009684993256feb84fdc5c0afc33c5abab95b68204a2504a70746` |
| sample `phpoidc/login.php` | `b67d698db782811355e4a716ee3c55cdd6518c698fdf3437bc58b4f5b8f8ef5` |
| sample `phpoidc/callback.php` | `1cf6a2ff6a82994f8a6ee79d97e8f741ff90ec78ca56382212dab277f82d477f` |
| sample `phpoidc/logout.php` | `50a9a94c127c367c94ddb22daf518901e7c38d087f23e24cc2021278ee768205` |
| sample `phpoidc/index.php` | `97a16f35c381f4f549e61bfdc549b561d66fb69a274447f4359d1534159f4451` |

Hash ialah evidence bahan yang diaudit, bukan kelulusan untuk memasukkan
credential atau dokumen rujukan ke Git.

Guideline menyatakan dokumen mengandungi maklumat sensitif dan tidak boleh
diedarkan semula tanpa kebenaran. Folder rujukan juga mengandungi credential
dalam bentuk plain text dan pada masa audit masih untracked oleh Git.

## 4. Ringkasan Guideline MyDigital ID

MyDigital ID menggunakan Keycloak sebagai OpenID Provider dan menyokong OAuth
2.0/OpenID Connect. Flow yang relevan kepada OneID ialah Authorization Code
Flow:

1. OneID mengarahkan browser ke authorization endpoint;
2. Keycloak/MyDigital ID memaparkan proses QR;
3. pengguna mengesahkan melalui aplikasi MyDigital ID;
4. browser menerima redirect ke callback OneID bersama authorization code;
5. backend OneID menukar code kepada token;
6. backend mendapatkan atau mengesahkan maklumat identiti;
7. OneID memulakan session hanya jika polisi akses tempatan lulus.

Guideline menerangkan contoh Laravel dan CodeIgniter, tetapi OneID ialah aplikasi
PHP custom. Contoh tersebut tidak boleh diterapkan secara salin-terus.

## 5. Konfigurasi Provider yang Diketahui

| Item | Nilai |
|---|---|
| Base URL | `https://sso.digital-id.my` |
| Realm | `upnm` |
| Client ID | `upnm-generic` |
| Issuer dijangka | `https://sso.digital-id.my/realms/upnm` |
| Authorization endpoint | `https://sso.digital-id.my/realms/upnm/protocol/openid-connect/auth` |
| Token endpoint | `https://sso.digital-id.my/realms/upnm/protocol/openid-connect/token` |
| UserInfo endpoint | `https://sso.digital-id.my/realms/upnm/protocol/openid-connect/userinfo` |
| JWKS endpoint terbitan standard | `https://sso.digital-id.my/realms/upnm/protocol/openid-connect/certs` |
| Logout endpoint | `https://sso.digital-id.my/realms/upnm/protocol/openid-connect/logout` |
| Scope minimum | `openid` |
| Format NRIC disahkan owner | 12 digit tanpa sengkang, contoh `900101011234` |

Client secret telah diterima tetapi sengaja tidak disalin ke dokumen ini.

`Endpoint_UPNM.pdf` mempunyai medan `REDIRECT URI`, tetapi nilai medan itu kosong
dalam layout text, raw text dan struktur XML PDF. Redirect URI masih perlu
ditetapkan dan disahkan sebagai registered/allowed pada client MyDigital ID.

Cadangan URI UAT:

```text
Redirect URI:
https://oneid-uat.upnm.edu.my/auth/mydigitalid/callback.php

Post-logout redirect URI:
https://oneid-uat.upnm.edu.my/
```

## 6. Keputusan Requirement yang Telah Disahkan

| Keputusan | Status |
|---|---|
| Login form OneID dikekalkan | CONFIRMED |
| MyDigital ID ialah kaedah login kedua | CONFIRMED |
| MyDigital ID authentication tidak memberikan akses secara automatik | CONFIRMED |
| Hanya pengguna yang sudah wujud dalam OneID boleh login | CONFIRMED |
| Akaun mesti aktif | CONFIRMED |
| Tiada auto-registration | CONFIRMED |
| Tiada auto-link berdasarkan nama/input pengguna | CONFIRMED |
| Data MyDigital ID tidak menggantikan data `user_tbl` | CONFIRMED |
| Nama MyDigital ID bukan identity matching key | CONFIRMED |
| NRIC digunakan untuk initial exact-one matching | CONFIRMED |
| NRIC staf berada pada `user_tbl.data4` | CONFIRMED BY SOURCE MAPPING |
| NRIC pelajar berada pada `user_tbl.data2` | CONFIRMED BY SOURCE MAPPING |
| Pelajar antarabangsa tanpa NRIC menggunakan login form/nombor matrik | CONFIRMED |
| ACL OneID kekal menentukan aplikasi yang boleh dicapai | CONFIRMED |
| Rekod perlu membezakan `password` dan `mydigitalid` | CONFIRMED |
| Data MyDigital ID boleh disimpan sebagai rujukan tanpa overwrite profil | CONFIRMED |
| Domain UAT boleh dicapai melalui VPN | CONFIRMED BY OWNER |
| Akaun `0530-09` digunakan sebagai pilot positif | CONFIRMED BY OWNER |

## 7. Model Authorization

Authentication dan authorization mesti kekal berasingan:

```text
MyDigital ID authentication berjaya
                |
                v
Token dan claims sah?
                |
                v
Identiti dipadankan kepada tepat satu akaun OneID?
                |
                v
Akaun OneID aktif dan dibenarkan login?
                |
                v
Session/token OneID diwujudkan
                |
                v
Kategori dan ACL OneID menentukan aplikasi yang boleh dicapai
```

Keputusan MyDigital ID tidak boleh:

- mencipta akaun;
- mengaktifkan akaun suspended;
- menukar nama atau NRIC OneID;
- memberi role admin;
- menambah ACL;
- memindahkan identity link kepada akaun lain; atau
- memintas lifecycle token/session OneID.

## 8. Polisi Pemetaan Identiti

### 8.1 Normalisasi NRIC

Walaupun format provider telah disahkan sebagai 12 digit tanpa sengkang,
callback mesti tetap fail-closed:

1. trim whitespace;
2. buang ruang dan sengkang yang dibenarkan untuk defensive compatibility;
3. pastikan hasil hanya digit;
4. pastikan panjang tepat 12;
5. jangan log nilai penuh.

### 8.2 Initial matching

Initial matching hanya berlaku apabila `issuer + sub` belum mempunyai link:

```text
staf:    normalized nric == normalized user_tbl.data4
pelajar: normalized nric == normalized user_tbl.data2
```

Query mesti menghasilkan tepat satu akaun aktif:

| Keputusan | Tindakan |
|---|---|
| 0 padanan | Tolak `MYDID_USER_NOT_FOUND` |
| 1 padanan aktif | Boleh cipta identity link dan login |
| 1 padanan tidak aktif | Tolak `MYDID_USER_INACTIVE` |
| Lebih daripada 1 padanan | Tolak `MYDID_IDENTITY_AMBIGUOUS` |

Jangan padan menggunakan nama, e-mel, nombor matrik yang tidak dikeluarkan oleh
provider atau inference kategori.

### 8.3 Subsequent login

Login berikutnya menggunakan identifier OIDC stabil:

```text
issuer + sub
```

OneID tetap perlu:

- memastikan link aktif;
- memastikan akaun OneID masih wujud dan aktif;
- memeriksa NRIC provider masih konsisten dengan akaun yang dipautkan;
- menolak jika berlaku mismatch;
- tidak memindahkan link secara automatik.

## 9. Pengguna Malaysia dan Antarabangsa

| Populasi | Login form | MyDigital ID |
|---|---:|---:|
| Staf Malaysia dengan akaun aktif | Ya | Ya, jika NRIC sepadan |
| Pelajar Malaysia dengan akaun aktif | Ya | Ya, jika NRIC sepadan |
| Pelajar antarabangsa tanpa NRIC/MyDigital ID | Ya, nombor matrik | Tidak |
| Pengguna MyDigital ID tanpa akaun OneID | Tidak melalui MyDigital ID | Ditolak |
| Akaun OneID suspended/tidak aktif | Mengikut polisi OneID | Ditolak |

Mesej kepada pengguna hendaklah generik dan tidak mendedahkan sama ada NRIC
tertentu wujud:

```text
Akaun ini tidak boleh menggunakan MyDigital ID. Sila log masuk menggunakan
ID pengguna dan kata laluan OneID atau hubungi pentadbir.
```

## 10. Audit Sample Code

### 10.1 Perkara yang boleh dijadikan rujukan

- penggunaan OIDC Authorization Code Flow;
- issuer berasaskan realm;
- scope `openid`;
- callback untuk code/token exchange;
- tuntutan `nama` dan `nric`;
- RP-initiated logout dengan `id_token_hint`.

### 10.2 Perkara yang tidak boleh dibawa ke production

| Finding | Risiko / keputusan |
|---|---|
| Credential hardcoded dalam `config.php` | Secret mesti berada dalam runtime secret store |
| HTML sample meminta client secret pada browser | Dilarang; confidential-client secret tidak boleh sampai ke browser/cookie |
| HTML sample menyimpan secret dalam cookie | Critical exposure |
| Sample menyimpan ID token dan memaparkannya | Token disclosure; jangan paparkan/log |
| Sample callback terus membina PHP session ringkas | Memintas session/token/ACL OneID |
| Sample tidak menunjukkan explicit application-level exact-one account gate | OneID mesti melaksanakannya |
| Sample logout menggunakan beberapa fetch/iframe dan memadam semua cookie/storage | Tidak scoped, rapuh dan bercanggah dengan CSP OneID |
| Sample mempunyai dependency `vendor` yang disalin | Dependency perlu reproducible melalui Composer |
| Sample library terkunci pada `jumbojett/openid-connect-php v0.9.10` | Compatibility/security perlu disahkan sebelum adoption |
| Comment login menyebut redirect Google | Bukti sample generik; bukan specification |

Sample ialah reference implementation sahaja, bukan security baseline.

## 11. Baseline OneID

Audit source mendapati:

- runtime menggunakan PHP 8.3;
- aplikasi bukan Laravel/CodeIgniter;
- document root sepatutnya `public/`;
- private implementation berada dalam `app/`, `lib/`, `config/` dan folder
  bukan-public lain;
- login page telah mempunyai visual MyDigital ID dalam keadaan disabled;
- belum ada endpoint atau action authentication MyDigital ID;
- `tools/login_mydigitalid_logo_contract.php` secara sengaja memastikan tiada
  authentication action diperkenalkan pada baseline;
- session dimulakan melalui `oneid_start_secure_session()`;
- authenticated session dibina melalui
  `oneid_establish_authenticated_session()`;
- session ID diregenerate ketika principal berubah;
- token aktif, timeout, multiple-session policy dan ACL sudah mempunyai
  lifecycle tempatan;
- logout semasa membatalkan token OneID dan memusnahkan session;
- runtime config menyokong environment/private file/default;
- secret dibaca melalui `oneid_secret()` daripada environment atau
  `.private/runtime.php`;
- tiada root Composer manifest semasa audit.

Implikasi: MyDigital ID perlu menjadi adapter authentication kepada OneID,
bukan session system yang selari dan berasingan.

## 12. Reka Bentuk Komponen Sasaran

Struktur tepat boleh diselaraskan semasa implementasi, tetapi ownership yang
dicadangkan ialah:

```text
public/auth/mydigitalid/login.php       thin public endpoint
public/auth/mydigitalid/callback.php    thin public endpoint
public/auth/mydigitalid/logout.php      thin public endpoint

app/Auth/MyDigitalId/
  MyDigitalIdConfig.php
  MyDigitalIdClient.php
  MyDigitalIdLoginService.php
  MyDigitalIdCallbackService.php
  MyDigitalIdIdentityMatcher.php
  MyDigitalIdAuditService.php
  MyDigitalIdLogoutService.php
  MyDigitalIdException.php

config/runtime.php                      non-secret fail-closed defaults
.private/runtime.php                    environment values dan secret
```

Public endpoint mesti kekal nipis. Protocol, validation, matching dan audit
berada dalam service yang boleh diuji.

## 13. Konfigurasi Sasaran

Cadangan key:

```text
ONEID_MYDID_ENABLED=false
ONEID_MYDID_ISSUER=https://sso.digital-id.my/realms/upnm
ONEID_MYDID_CLIENT_ID=upnm-generic
ONEID_MYDID_CLIENT_SECRET=<private only>
ONEID_MYDID_REDIRECT_URI=https://oneid-uat.upnm.edu.my/auth/mydigitalid/callback.php
ONEID_MYDID_POST_LOGOUT_REDIRECT_URI=https://oneid-uat.upnm.edu.my/
ONEID_MYDID_SCOPE=openid
ONEID_MYDID_HTTP_TIMEOUT_SECONDS=12
```

Kawalan:

- committed default `ONEID_MYDID_ENABLED` mesti `false`;
- secret tidak mempunyai committed default;
- enable memerlukan semua konfigurasi sah;
- issuer dan endpoint mesti HTTPS;
- production tidak boleh menerima TLS bypass;
- redirect selepas login hanya kepada path dalaman allowlisted;
- konfigurasi environment tidak boleh diterima daripada query string/cookie.

## 14. Cadangan Model Data

Data MyDigital ID tidak dimasukkan ke medan profil `user_tbl`. Dua concern
disimpan berasingan.

### 14.1 Identity link

Cadangan jadual `user_federated_identity`:

```text
id
u_id
provider
issuer
provider_subject
nric_hmac
verified_name_snapshot
first_login_at
last_login_at
last_verified_at
login_count
status
created_at
updated_at
```

Constraint minimum:

```text
UNIQUE(provider, issuer, provider_subject)
UNIQUE(provider, u_id)
FOREIGN KEY (u_id) REFERENCES user_tbl(u_id)
```

`provider_subject` datang daripada claim `sub`. Nama bukan key. NRIC tidak
dicadangkan disalin sebagai plain text ke jadual ini; gunakan keyed HMAC bagi
consistency evidence. `verified_name_snapshot` hanya disimpan jika owner
mengesahkan keperluan operasi dan retention.

### 14.2 Authentication event

Cadangan jadual `federated_auth_event`:

```text
id
provider
u_id nullable
outcome
reason_code
provider_subject_hash
nric_hmac
ip_address
user_agent_hash
session_id_hash
correlation_id
authenticated_at
```

Reason code minimum:

```text
MYDID_LOGIN_SUCCESS
MYDID_USER_NOT_FOUND
MYDID_USER_INACTIVE
MYDID_IDENTITY_AMBIGUOUS
MYDID_IDENTITY_MISMATCH
MYDID_STATE_INVALID
MYDID_NONCE_INVALID
MYDID_TOKEN_INVALID
MYDID_PROVIDER_ERROR
MYDID_CONFIGURATION_DISABLED
```

### 14.3 HMAC dan data minimization

Gunakan:

```text
HMAC-SHA256(normalized_nric, environment-private pepper)
```

Jangan gunakan SHA-256 NRIC tanpa key kerana ruang NRIC boleh dienumerate.
HMAC pepper mesti:

- berada di secret store;
- berbeza mengikut environment;
- tidak sama dengan client secret;
- mempunyai version/key ID jika rotation diperlukan.

## 15. Token dan Session Policy

OneID tidak boleh menggunakan token MyDigital ID sebagai pengganti token/session
tempatan.

Selepas identity dan authorization gate lulus:

1. dapatkan canonical user packet OneID;
2. hormati `avail_status`, password-change policy yang relevan dan account
   controls;
3. apply multiple-session/token policy yang sama seperti login biasa;
4. hasilkan token OneID melalui service yang sama atau seam yang diextract;
5. panggil `oneid_establish_authenticated_session()`;
6. set `auth_method=mydigitalid` dalam server-side session/audit;
7. redirect ke dashboard atau safe internal intended route.

Token MyDigital ID:

- authorization code hanya one-use;
- access token tidak disimpan secara kekal;
- refresh token tidak diperlukan bagi login/session OneID biasa kecuali
  requirement baharu diluluskan;
- ID token boleh disimpan sementara server-side jika diperlukan sebagai
  `id_token_hint` untuk logout;
- tiada token dimasukkan ke URL selepas callback, HTML, JavaScript, cookie
  readable oleh JavaScript atau log.

## 16. Security Control OIDC

Callback mesti mengesahkan sekurang-kurangnya:

- feature flag aktif;
- request method dan parameter allowlist;
- authorization response error;
- `state` rawak, one-use, session-bound dan time-bound;
- `nonce` rawak, one-use dan session-bound;
- token endpoint TLS;
- JWT signature menggunakan key JWKS yang sah;
- algorithm allowlist; jangan terima `none`;
- issuer sama tepat;
- audience/client ID betul;
- `azp` apabila berkenaan;
- expiry dan not-before dengan clock skew kecil;
- nonce pada ID token;
- subject tidak kosong dan dalam had panjang;
- UserInfo `sub` sama dengan ID token `sub`;
- claim NRIC valid;
- exact-one local account matching.

Selepas callback berjaya atau gagal secara terminal, state/nonce/code-related
session data mesti dibuang untuk mencegah replay.

## 17. CSP dan Network

Login biasa OneID mempunyai CSP `connect-src 'self'` dan `frame-ancestors
'self'`. Flow backend redirect tidak memerlukan client secret atau token
request melalui JavaScript dan lebih serasi dengan CSP ini.

Keperluan infra UAT:

- browser penguji boleh mencapai `oneid-uat.upnm.edu.my` melalui VPN;
- DNS resolve melalui laluan ujian;
- sijil TLS sah dan trusted;
- server OneID boleh membuat outbound TCP/TLS ke `sso.digital-id.my:443`;
- CA bundle server sah;
- system clock/NTP tepat;
- reverse proxy meneruskan HTTPS/host dengan betul;
- callback path sampai ke `public/` document root;
- log proxy tidak menyimpan query/token sensitif melebihi keperluan.

Provider tidak semestinya membuat server-to-server call ke callback. Browser
pengguna melakukan redirect; oleh itu browser yang menerima callback mesti
boleh mencapai domain melalui VPN.

## 18. Logout Sasaran

Logout MyDigital ID perlu:

1. membatalkan token OneID aktif;
2. membersihkan local authenticated session;
3. mengambil `id_token_hint` server-side jika tersedia;
4. redirect ke Keycloak logout endpoint dengan parameter yang di-encode;
5. menggunakan post-logout URI yang telah didaftarkan;
6. tidak memadam cookie/storage yang bukan milik integration;
7. fail selamat jika provider logout tidak tersedia.

Keputusan tambahan semasa implementation perlu menentukan sama ada logout biasa
bagi session `auth_method=password` kekal local sahaja dan logout
`auth_method=mydigitalid` melakukan federated logout. Cadangan: ya.

## 19. Pelan Pembangunan Berfasa

Setiap fasa mempunyai rollback sendiri. Feature flag kekal `false` sehingga
fasa pilot.

> Pelan dan keputusan F0–F6 di bawah ialah chronological record. Foundation
> tersebut kemudiannya dideploy, migration diaplikasi dan feature diaktifkan di
> staging. Committed default masih `false`; private staging runtime ialah
> `true`.

### Fasa 0 — Baseline, decisions dan readiness

**Tujuan:** mengunci requirement dan prerequisite tanpa mutation.

Deliverable:

- dokumen ini;
- endpoint/claim/config register tanpa secret;
- pengesahan callback dan post-logout whitelist;
- network/TLS/NTP preflight;
- read-only verification akaun pilot `0530-09`;
- semakan duplicate NRIC dan exact-one feasibility secara aggregate/masked;
- keputusan retention nama snapshot dan auth event;
- backup/restore owner serta change reference bagi migration.

Exit gate:

- tiada blocker identiti/schema;
- callback registered;
- outbound provider berjaya;
- owner memberi `GO FASA 1`.

### Fasa 1 — Dependency, config dan protocol client dormant

**Tujuan:** membina asas OIDC tanpa login aktif.

Deliverable:

- keputusan library OIDC berdasarkan PHP 8.3, maintenance dan security review;
- reproducible Composer manifest/lock atau adapter yang diluluskan;
- `MyDigitalIdConfig`;
- issuer discovery atau explicit endpoint validation;
- HTTP client dengan TLS verification dan bounded timeout;
- fail-closed configuration;
- feature flag default `false`;
- unit/contract test config dan protocol validation.

Rollback:

- buang dependency/config/service baharu;
- tiada schema atau UI aktif.

**Keputusan 26 Julai 2026:** COMPLETE. `jumbojett/openid-connect-php v1.0.2`
dikunci melalui Composer, konfigurasi fail-closed ditambah, protocol client
memaksa Authorization Code + PKCE S256 + TLS verification, 27 contract/test
lulus, feature flag kekal `false`, secret tidak dimuatkan dan tiada endpoint
public/schema/session/token diperkenalkan. Evidence penuh berada dalam
`docs/MYDIGITALID_F1_DORMANT_OIDC_FOUNDATION.md`.

### Fasa 2 — Schema identity link dan audit event dormant

**Tujuan:** menyediakan persistence berasingan tanpa mengubah `user_tbl`.

Deliverable:

- additive migration up/down;
- `user_federated_identity`;
- `federated_auth_event`;
- unique/FK/index/length constraints;
- repository dengan prepared statements;
- HMAC key/pepper readiness;
- retention dan privacy controls;
- isolated migration rehearsal;
- exact rollback verification.

Acceptance:

- migration tidak mengubah row profil OneID;
- duplicate subject/link ditolak;
- raw NRIC/token tidak wujud dalam table/log;
- rollback mengembalikan baseline.

**Keputusan awal 26 Julai 2026:** COMPLETE secara dormant. Dua migration
additive up/down, keyed-HMAC protector dan transactional PDO repository dibina.
Isolated rehearsal mengesahkan 2 table, 3 FK, 3 CHECK, zero forbidden raw
identity/token column, uniqueness/correlation/mismatch rejection, `user_tbl`
tidak berubah dan exact rollback.

**Supersession staging:** Selepas backup dan readiness `10/10`, migration telah
diaplikasi kepada shared development/staging `oneiddb`. Reconciliation
mengesahkan 2 table, 3 FK, 3 CHECK, `user_tbl` 9,793 row/structure unchanged.
Schema apply flag kemudian ditutup semula. Evidence penuh berada dalam
`docs/MYDIGITALID_F2_DORMANT_IDENTITY_AUDIT_SCHEMA.md`.

### Fasa 3 — Login initiation dan callback validation dormant

**Tujuan:** melaksanakan protocol boundary secara fail-closed.

Deliverable:

- thin login endpoint;
- state/nonce generation dan one-use session binding;
- callback endpoint;
- code exchange backend;
- signature/issuer/audience/expiry/nonce validation;
- UserInfo subject consistency;
- claim extraction/normalization;
- error mapping dan correlation ID;
- mock OIDC test bagi success, tamper, replay, expiry dan provider failure.

Feature flag masih `false` pada UAT shared.

**Keputusan awal 26 Julai 2026:** COMPLETE secara dormant. Authorization
transaction sekali guna, TTL, state/nonce/PKCE, callback input boundary dan
protocol adapter telah dibina.

**Supersession staging:** Callback kini memanggil protocol/repository apabila
private feature flag aktif; endpoint login menghasilkan `303`. Apabila flag
dimatikan, endpoint kekal fail-closed `404`. Evidence berada dalam
`docs/MYDIGITALID_F3_DORMANT_CALLBACK_FOUNDATION.md`.

### Fasa 4 — Identity matching dan OneID session/token integration

**Tujuan:** membenarkan identiti sah melalui authorization gate OneID.

Deliverable:

- exact-one staff/student NRIC matcher;
- active-account gate;
- initial `issuer + sub` link;
- subsequent-link verification;
- mismatch/duplicate/inactive rejection;
- reuse/extraction login finalization seam OneID;
- local token issuance dan multi-session enforcement;
- `auth_method` session/audit;
- no-registration/no-overwrite contract.

Acceptance:

- MyDigital ID user tanpa OneID tidak boleh login;
- inactive user tidak boleh login;
- nama/NRIC `user_tbl` tidak berubah;
- ACL tidak berubah;
- password login regression lulus.

**Keputusan awal 26 Julai 2026:** ACCOUNT MATCHING, LINKING DAN AUDIT COMPLETE
secara dormant. Exact-one active matcher, initial/subsequent link verification,
inactive/ambiguous/mismatch rejection dan transactional audit telah dibina.
Isolated rehearsal 11/11 lulus dengan `user_tbl` tidak berubah.

**Supersession staging:** Callback, authenticated session dan local token telah
disambungkan selepas gated migration. Positive pilot dan unmatched rejection
telah direkod secara live. Evidence berada dalam
`docs/MYDIGITALID_F4_DORMANT_ACCOUNT_LINKING_AUDIT.md`.

**Keputusan Fasa 4B, 26 Julai 2026:** CALLBACK, LOCAL TOKEN DAN SESSION SEAM
COMPLETE, DORMANT. Callback kini mempunyai urutan state → protocol → account
authorization → local token → authenticated session. Reject tidak membentuk
sesi; kegagalan selepas token insert melakukan compensating revocation. Session
berjaya ditanda `auth_method=mydigitalid`. Feature flag dan UI kekal disabled.
Evidence berada dalam `docs/MYDIGITALID_F4B_DORMANT_CALLBACK_SESSION.md`.

### Fasa 5 — UI, bilingual response dan logout

**Tujuan:** memperkenalkan pilihan login kedua secara terkawal.

Deliverable:

- tukar preview logo kepada action apabila flag aktif;
- kekalkan form login;
- BM/English text;
- mesej generik untuk unsupported/unmatched user;
- loading/error accessibility;
- federated logout;
- local-only logout untuk password session;
- CSP dan cache-control verification;
- pelajar antarabangsa terus diarahkan menggunakan form login.

Acceptance:

- flag `false` mengekalkan UI/behavior baseline;
- flag `true` memaparkan action;
- tiada secret/token pada browser;
- logout kedua-dua auth method berfungsi seperti direka.

**Keputusan awal 26 Julai 2026:** COMPLETE secara dormant. UI kedua dikawal oleh
feature flag, locale BM/English serta generic flash errors telah ditambah, dan
logout MyDigital ID menggunakan local-first cleanup sebelum provider
end-session.

**Supersession staging:** UI telah diaktifkan, digunakan dan direka semula;
password login/logout kekal tersedia. Rejection account-switch diteruskan dalam
F7. Evidence berada dalam
`docs/MYDIGITALID_F5_FLAGGED_UI_ERRORS_LOGOUT.md`.

### Fasa 6 — Automated security dan regression suite

**Tujuan:** membuktikan fail-closed behavior sebelum provider sebenar.

Test minimum:

- state missing/mismatch/replay;
- nonce missing/mismatch/replay;
- authorization code missing/reuse;
- invalid signature/issuer/audience/algorithm;
- expired/not-yet-valid token;
- UserInfo subject mismatch;
- NRIC blank/non-digit/bukan 12 digit;
- zero/one/multiple local matches;
- inactive/suspended account;
- existing link NRIC mismatch;
- database write/audit failure;
- provider timeout/TLS error;
- open redirect attempt;

**Keputusan awal 26 Julai 2026:** LOCAL PRE-PUSH COMPLETE.

**Supersession staging:** One-command suite kini menjalankan 23 command
merangkumi F0-F7, online read-only preflight, isolated schema/linking
rehearsals, password-token regression dan Composer audit. Semua lulus di
staging dengan zero local mutation, zero feature activation dan zero rehearsal
database tertinggal. ID token turut dipakukan kepada `RS256` + `kid`. Evidence
dan baki acceptance manual berada dalam
`docs/MYDIGITALID_F6_AUTOMATED_SECURITY_REGRESSION.md`.
- session fixation;
- feature flag disabled;
- password login regression;
- ACL/session timeout/multiple-session regression;
- raw NRIC/token/secret log scan.

### Fasa 7 — UAT pilot, rejection UX dan log hardening

**Tujuan:** end-to-end test dengan provider sebenar.

Prerequisite:

- registered redirect/post-logout URI;
- outbound connectivity;
- valid TLS/NTP;
- feature flag activation approval;
- backup/migration evidence;
- pilot account verified;
- observation dan rollback owner tersedia.

Pilot positif:

```text
Owner reference: 0530-09
Expected: MyDigital ID NRIC matches exactly one active staff OneID account
```

Pilot negatif hendaklah menggunakan fixture/approved test identity; jangan
mencipta atau mengubah akaun sebenar tanpa kelulusan.

Evidence:

- correlation ID;
- timestamp;
- auth method;
- masked/hashed identity only;
- local account outcome;
- session/token/ACL result;
- logout result;
- tiada token/NRIC penuh dalam evidence.

**Keputusan 26 Julai 2026:** PARTIALLY ACCEPTED. Pilot positif `0530-09`
berjaya login ke dashboard. Identiti tanpa akses ditolak
`MYDID_USER_NOT_FOUND` tanpa auto-registration. F7 kemudian menambah generic
rejection actions, POST+CSRF account switching, verified rejected-token state
TTL 300 saat dan official provider logout. Automated F7 `9/9` serta contract
`8/8` lulus. Nginx safe access-log format telah applied dan query canary tidak
direkod. Manual chained browser acceptance bagi rejection → account switch →
QR baharu → pilot login masih pending.

### Fasa 8 — Controlled rollout dan observation

**Tujuan:** mengembangkan penggunaan selepas pilot diterima.

Deliverable:

- UAT acceptance;
- activation runbook;
- dashboard/queries audit;
- thresholds provider error, unmatched identity dan callback rejection;
- incident/secret rotation procedure;
- 24 jam primary observation dan tempoh enhanced monitoring yang diluluskan;
- production readiness review berasingan;
- removal atau secure archival bahan rujukan sensitif.

UAT acceptance tidak memberi automatic production approval.

## 20. UAT Matrix

| ID | Senario | Hasil |
|---|---|---|
| UAT-01 | `0530-09`, MyDigital ID sah, exact-one active match | Login berjaya |
| UAT-02 | MyDigital ID sah tetapi tiada akaun OneID | Ditolak |
| UAT-03 | Akaun OneID inactive | Ditolak |
| UAT-04 | Duplicate NRIC | Ditolak dan diaudit |
| UAT-05 | State salah | Ditolak sebelum token/session |
| UAT-06 | Nonce salah | Ditolak |
| UAT-07 | Token invalid/expired | Ditolak |
| UAT-08 | Existing link tetapi NRIC berubah | Ditolak |
| UAT-09 | Password login staf | Kekal berjaya |
| UAT-10 | Password login pelajar tempatan | Kekal berjaya |
| UAT-11 | Pelajar antarabangsa login nombor matrik | Kekal berjaya |
| UAT-12 | ACL selepas MyDigital ID login | Sama seperti akaun OneID |
| UAT-13 | Logout MyDigital ID | Local + provider session ditamatkan |
| UAT-14 | Feature flag off | Tiada active MyDigital ID action |
| UAT-15 | Provider unavailable | Fail selamat; password login masih ada |

## 21. Monitoring dan Audit

Metric/event minimum:

- initiation count;
- callback success/rejection count;
- provider timeout/error;
- state/nonce/token validation failure;
- user-not-found;
- inactive user;
- ambiguous identity;
- identity mismatch;
- login success mengikut `auth_method`;
- logout success/failure.

Log tidak boleh mengandungi:

- client secret;
- authorization code;
- access/refresh/ID token;
- raw NRIC;
- full provider payload;
- session ID atau cookie mentah.

Gunakan correlation ID dan reason code stabil. Alert threshold dan retention
perlu diluluskan sebelum rollout.

## 22. Rollback Strategy

Rollback segera:

1. set `ONEID_MYDID_ENABLED=false`;
2. sahkan login form masih berfungsi;
3. hentikan endpoint initiation baharu;
4. kekalkan audit evidence untuk incident review;
5. revoke/rotate credential jika disyaki terdedah.

Rollback kod:

- revert thin endpoints, service, UI dan dependency secara change-scoped;
- jangan gunakan destructive workspace reset;
- jalankan password/session/ACL regression.

Rollback schema:

- hanya selepas feature disabled dan tiada runtime writer;
- export/retain evidence mengikut retention;
- jalankan approved down migration;
- reconcile row count dan FK;
- schema deletion memerlukan backup/owner approval.

Data profil `user_tbl` tidak memerlukan rollback kerana design melarang
perubahannya.

## 23. Risiko dan Mitigasi

| Risiko | Tahap | Mitigasi |
|---|---:|---|
| Client secret berada dalam reference file | Tinggi | Jangan commit/deploy; pindah ke secret store; rotate sebelum penggunaan rasmi |
| Callback belum dibuktikan registered | Tinggi | Provider confirmation sebelum UAT |
| External authenticated user mendapat akses tanpa local gate | Kritikal | Exact-one active local account gate |
| Duplicate NRIC | Tinggi | Fail closed; tiada arbitrary first-row match |
| Raw NRIC boleh bocor melalui log | Tinggi | HMAC/masking dan negative log scan |
| Token validation tidak lengkap | Kritikal | Validate signature, issuer, audience, expiry, nonce, subject |
| Parallel session system memintas OneID | Kritikal | Adapter kepada local session/token lifecycle |
| Provider outage mengunci semua pengguna | Sederhana | Password login dikekalkan |
| Pelajar antarabangsa tidak mempunyai MyDigital ID | Expected | Login nombor matrik/password kekal |
| Sample browser menyimpan secret | Kritikal | Backend confidential-client flow sahaja |
| VPN/mobile callback tidak boleh dicapai | Sederhana | Desktop VPN pilot; sahkan redirect device path |
| Dependency sample menjadi obsolete | Sederhana | Fasa 1 library/security review |

## 24. Perkara Masih Terbuka

| Item | Owner | Gate |
|---|---|---|
| Manual F7 rejected-user/account-switch chained acceptance | OneID owner/tester | Sebelum staging acceptance close-out |
| Manual password student/international-student regression | OneID owner | Sebelum staging acceptance close-out |
| Manual ACL parity dan authenticated provider logout | OneID owner | Sebelum staging acceptance close-out |
| Inactive/ambiguous live negative fixture | Data/OneID owner | Optional controlled UAT; automated/isolated PASS |
| Monitoring threshold, channel dan observation window | Operations | Sebelum controlled rollout |
| Historical callback log retention/rotation | Infra/Security | Selepas safe logging; jangan padam ad hoc |
| Production callback/post-logout registration | MyDigital ID/UPNM owner | Sebelum production |
| Production claims/client settings revalidation | MyDigital ID/UPNM owner | Sebelum production |
| Production audit-event retention/purge approval | Security/data owner | Sebelum production migration |
| Production HMAC key custody/rotation | Security/operations | Sebelum production |
| Production backup/restore/change/rollback | DBA/change owner | Sebelum production migration |
| Reference secret deletion dan credential rotation | OneID owner/Security | Sebelum task/production close-out |

## 25. Reference Folder Disposition

Semasa pembangunan, folder reference boleh kekal private untuk pemahaman.
Ia tidak boleh:

- berada dalam `public/`;
- disertakan dalam deployment artifact;
- di-commit ketika mengandungi secret atau dokumen restricted;
- digunakan sebagai runtime dependency.

Cadangan ignore yang scoped:

```gitignore
/resources/references/mydigital-id/
```

Jangan ignore seluruh `resources/`. Sebelum task ditutup:

1. pastikan implementation tidak bergantung kepada reference folder;
2. padam fail yang mengandungi secret seperti diarahkan owner;
3. semak `git status` dan Git history;
4. rotate secret sekurang-kurangnya sebelum production, dan disyorkan sebelum
   UAT rasmi;
5. simpan hanya dokumentasi dalaman yang tidak mengandungi credential/provider
   restricted content.

## 26. Gate Keseluruhan

| Gate | Status semasa |
|---|---|
| Requirement dua login | PASS |
| Existing-active-user-only policy | PASS |
| Staff/student NRIC mapping | PASS BY SOURCE BASELINE |
| International student path | PASS |
| No-overwrite/no-auto-register policy | PASS |
| Provider endpoint inventory | PASS |
| NRIC format | PASS |
| Client ID/realm | PASS |
| Redirect URI value selected | PASS AS PROPOSAL |
| Redirect URI recognized oleh provider | PASS STAGING LIVE |
| Claim payload verified live | PASS STAGING LIVE |
| Network/TLS/NTP preflight | PASS |
| Persistence schema/rehearsal | PASS STAGING; 2 TABLE/3 FK/3 CHECK |
| Non-production HMAC provisioning | PASS STAGING |
| Production retention/key custody approval | PENDING |
| Implementation | FASA 0–7 IMPLEMENTED STAGING |
| Positive end-to-end pilot | PASS |
| Negative authorization gate | PASS BY LIVE AUDIT |
| F7 account-switch manual acceptance | PENDING |
| Nginx callback query redaction | PASS STAGING |
| Production readiness/approval | NOT STARTED |

## 27. Keputusan Audit

Integrasi adalah feasible untuk OneID dengan syarat MyDigital ID diperlakukan
sebagai external authenticator dan bukan authority bagi authorization atau
profil OneID.

Reka bentuk yang diterima ialah:

```text
DUAL LOGIN
EXISTING ACTIVE ONEID USER ONLY
EXACT-ONE NRIC MATCH
ISSUER + SUBJECT FEDERATED LINK
NO AUTO-REGISTRATION
NO PROFILE OVERWRITE
ONEID SESSION/TOKEN/ACL REMAIN AUTHORITATIVE
FEATURE-FLAGGED AND FAIL-CLOSED
```

Pembangunan dormant, migration dan activation staging telah selesai. Controlled
staging acceptance masih mempunyai item manual yang disenaraikan dalam Seksyen
24 dan dokumen close-out. Tiada keputusan staging memberi automatic production
approval.

## 28. Rekod Pelaksanaan Staging dan Status Akhir Audit

Rekod canonical terperinci berada dalam:

```text
docs/MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md
```

Ringkasan evidence:

- full non-production DB backup + SHA-256 sebelum migration;
- migration readiness `10/10`, apply berjaya dan schema gate ditutup semula;
- `user_tbl` 9,793 row dan structure unchanged;
- Composer authoritative autoload, PHP 8.3 intl dan secret/HMAC validation PASS;
- positive live MyDigital ID pilot login PASS;
- live unmatched identity rejection/no-auto-registration PASS;
- redesigned bilingual flagged UI active;
- F7 one-use rejected provider logout state/CSRF account switching implemented;
- automated staging suite `23/23`, zero mutation;
- Nginx `oneid_safe` log format, config validation/reload/canary PASS; dan
- committed source evidence sehingga `5592929a25220ecf5a549540b0a9524d1ba06d56`.

Keputusan audit semasa:

```text
STAGING IMPLEMENTATION: COMPLETE
STAGING POSITIVE PILOT: PASS
STAGING SECURITY/REGRESSION: PASS
STAGING MANUAL ACCEPTANCE: PARTIAL / ITEMS PENDING
PRODUCTION: NO-GO UNTIL SEPARATE DBA/SECURITY/INFRA/PROVIDER APPROVAL
```
