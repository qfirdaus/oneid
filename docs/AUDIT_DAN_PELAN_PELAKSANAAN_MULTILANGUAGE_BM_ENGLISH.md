# Audit dan Pelan Pelaksanaan Multi-Language BM–English

**Tarikh audit asal:** 17 Julai 2026
**Tarikh audit semula:** 24 Julai 2026
**Tarikh kemas kini status:** 26 Julai 2026
**Baseline audit sejarah:** OneID v2.6.2
**Status Local WSL:** ML0 hingga ML8C PASS / CLOSED mengikut skop
authorization; English User Manual deferred by owner dan bukan blocker
**Status tambahan Local WSL:** External Sync multilingual dan Admin Step-Up
multilingual PASS / CLOSED
**Administrator Multilingual Completeness:** PASS / CLOSED
**Baki belum dilaksanakan:** ML9, staging dan Production
**Bahasa sasaran:** Bahasa Melayu (`ms`) dan English (`en`)
**Default dicadangkan:** Bahasa Melayu (`ms`)
**Skop:** Login, dashboard pengguna, dashboard Administrator, API/AJAX,
operational feedback, e-mel, accessibility, kandungan pangkalan data, dokumen
bantuan, audit dan operasi

> **Cara membaca dokumen:** Bahagian 2 merekod finding daripada baseline audit
> v2.6.2 dan dikekalkan untuk provenance. Ia bukan gambaran implementation
> semasa. Status authoritative selepas pelaksanaan berada dalam Bahagian 19.

## 1. Objektif

Mewujudkan sokongan dua bahasa yang konsisten, selamat dan boleh diselenggara
tanpa menukar business rule, authorization, audit code atau data teknikal OneID.
Pelaksanaan bukan sekadar pertukaran teks pada browser; locale perlu menjadi
sebahagian daripada presentation contract bagi server, JavaScript dan e-mel.

Pada audit asal, dokumen ini hanya merekod keputusan reka bentuk dan urutan
pelaksanaan. Selepas itu, perubahan Local WSL dilaksanakan melalui authorization
dan evidence berasingan yang direkod dalam Bahagian 11 hingga 18.

Audit semula v2.6.2 tidak mengaktifkan locale, tidak menambah translation
catalogue dan tidak mengubah teks production. Ia mengemas kini keperluan selepas
Admin Step-Up 2FA, Configuration History, External Sync, Active Sessions,
Password Recovery dan reka bentuk Sync User ditambah.

## 2. Hasil Audit Baseline v2.6.2 (Sejarah)

Semua finding dalam bahagian ini ialah keadaan pada tarikh audit asal. Finding
yang telah diselesaikan tidak dipadam supaya sebab reka bentuk, migration dan
contract kekal boleh diaudit. Disposition semasa diringkaskan dalam Bahagian 19.

### ML-A01 — Tiada translation layer pusat

Sistem belum mempunyai locale resolver, translation catalogue, helper
translation, fallback locale atau preference bahasa pengguna. Teks ditulis
terus dalam PHP dan JavaScript.

### ML-A02 — Bahasa UI bercampur

Login, dashboard pengguna dan dashboard Administrator menggunakan campuran BM
dan English. SweetAlert, toast, loading state, empty state, validation dan
confirmation turut tidak menggunakan satu language contract yang konsisten.

### ML-A03 — Metadata dokumen tidak konsisten

`index.php`, `page/dashboard.php` dan `admin/dashboard.php` masih menggunakan
`<html lang="en">` walaupun banyak kandungan ialah BM.
`page/admin_step_up.php` dan template e-mel pusat menggunakan `lang="ms"`.
Perbezaan ini ialah pembetulan manual per-surface, bukan locale resolver, dan
masih menjejaskan screen reader serta interpretasi bahasa oleh browser.

### ML-A04 — Surface terjemahan bertambah dan baseline lama telah luput

Baseline v2.6.2 mempunyai 375 fail PHP bukan vendor dalam repository, termasuk
page, service, tools dan tests; bukan semuanya menghasilkan UI. Surface kritikal
berikut sahaja mempunyai 15,953 baris:

| Surface | LOC baseline |
|---|---:|
| `index.php` | 1,145 |
| `page/dashboard.php` | 1,948 |
| `page/admin_step_up.php` | 52 |
| `admin/dashboard.php` | 10,311 |
| `lib/q_func.php` | 1,904 |
| `lib/request_security.php` | 349 |
| `app/Mail/OneIdEmailTemplate.php` | 112 |

Carian reproducible bagi `swal`, toast, `alert`, `confirm`, `.text()` dan
`.html()` pada login, user dashboard, Admin dashboard, Step-Up dan `q_func`
menemui sekurang-kurangnya 416 lokasi dynamic-feedback. Ini ialah lower bound,
bukan acceptance count, kerana static label, placeholder, aria text, e-mel,
PHP response dan dokumen belum semuanya diklasifikasikan.

### ML-A05 — Backend message bercampur dengan machine response

Sebahagian JSON response memulangkan stable `code` bersama teks `msg` atau
`message` hardcoded. Machine code, correlation ID dan audit event mesti kekal
invariant; presentation message perlu dipetakan kepada translation key.

### ML-A06 — JavaScript mempunyai teks dinamik hardcoded

Sebahagian label, confirmation, error, carian, loading dan empty state dibina
semasa runtime. Menterjemah HTML sahaja akan meninggalkan mixed-language UI.

### ML-A07 — E-mel belum locale-aware

`OneIdEmailTemplate` kini memusatkan sebahagian OTP dan delivery-test markup,
melakukan escaping dan menetapkan `lang="ms"`, tetapi content masih BM sahaja.
Password Recovery, Admin Step-Up OTP, ujian penghantaran dan notifikasi
keselamatan belum mempunyai pasangan BM/English serta aturan pemilihan locale
penerima.

### ML-A08 — Kandungan pangkalan data bukan translation catalogue

Nama/deskripsi aplikasi dan nama kategori ialah kandungan yang dimasukkan oleh
admin. Ia tidak patut diterjemahkan secara automatik atau disamakan dengan label
UI. Sokongan metadata bilingual memerlukan kontrak dan schema berasingan.

### ML-A09 — Dokumen bantuan berada di luar UI

Manual, FAQ, release notes, polisi dan PDF tidak menjadi bilingual hanya kerana
UI diterjemahkan. Availability setiap dokumen mengikut locale perlu jelas.

### ML-A10 — Modul keselamatan dan sync baharu belum berada dalam inventori asal

Audit asal mendahului Admin Step-Up 2FA, Configuration History, Password
Recovery configuration, Active Sessions, reka bentuk semula Audit Log dan
Version Releases, serta source-scoped External Sync Staff/Prasiswazah/ODL.
Semua static, dynamic, timeout, blocked, confirmation dan post-Apply state bagi
modul ini mesti dimasukkan dalam coverage ML5/ML6.

### ML-A11 — Teks presentation terikat kepada characterization contract

Beberapa contract semasa mengesahkan literal UI bagi status sync, butang,
release card dan security feedback. Menggantikan literal secara terus akan
menyebabkan test gagal walaupun behavior selamat. ML0 mesti mengklasifikasikan
assertion yang patut berpindah kepada translation key/DOM contract dan assertion
canonical yang mesti kekal literal.

### ML-A12 — Exact confirmation dan evidence tidak boleh diterjemahkan bebas

External Sync menggunakan typed confirmation yang terikat kepada counts dan
plan hash. Security configuration menggunakan purpose, reason code dan
correlation ID canonical. Arahan manusia boleh diterjemahkan, tetapi exact
confirmation phrase, source code, plan hash, approval ID, audit event dan
machine code kekal canonical kecuali protocol baharu direka dan diuji.

### ML-A13 — Pertukaran locale boleh berlanggar dengan state sensitif

Dashboard kini mempunyai modal Preview/Apply, one-time approval, form dirty,
Admin Step-Up grant dan AJAX request aktif. Pertukaran bahasa tidak boleh:

- membakar atau mengguna semula approval secara tidak sengaja;
- menghantar Apply dua kali;
- menghilangkan input atau change reason;
- menukar security purpose atau CSRF/session binding; atau
- membawa admin kembali sebagai privileged apabila Step-Up telah tamat.

## 3. Keputusan Reka Bentuk Dicadangkan

### 3.1 Locale yang disokong

Senarai locale dikawal oleh kod/deployment:

```php
[
    'ms' => 'Bahasa Melayu',
    'en' => 'English',
]
```

Admin tidak boleh mencipta locale baharu melalui UI. Input locale selain `ms`
atau `en` mesti ditolak dan tidak boleh digunakan untuk membina file path.

### 3.2 Keutamaan locale

```text
Preference pengguna authenticated
    -> preference session/cookie yang sah
    -> default_locale dalam Configuration
    -> hard fallback ms
```

Bahasa browser boleh digunakan sebagai cadangan awal sahaja dan tidak mengatasi
pilihan eksplisit pengguna atau default organisasi.

### 3.3 Setting Administrator

Tambah seksyen `Language & Localization` dalam Configuration dengan setting:

```text
default_locale = ms | en
```

Setting ini hanya mempengaruhi guest dan pengguna tanpa preference. Ia tidak
memaksa semua pengguna menukar bahasa dan tidak mengubah audit data. Perubahan
direkod sebagai `SYSTEM_DEFAULT_LOCALE_UPDATED` bersama actor, old/new value,
IP dan correlation ID.

Pilihan bahasa pengguna hendaklah sentiasa tersedia selepas rollout stabil.
Jika `language_switcher_enabled` diperlukan semasa controlled rollout, ia ialah
feature flag sementara dan bukan kawalan bahasa kekal.

### 3.4 Translation catalogue

```text
resources/lang/
├── ms/
│   ├── common.php
│   ├── auth.php
│   ├── user.php
│   ├── admin.php
│   ├── email.php
│   └── validation.php
└── en/
    ├── common.php
    ├── auth.php
    ├── user.php
    ├── admin.php
    ├── email.php
    └── validation.php
```

Key mesti semantik seperti `auth.login.password`, bukan teks asal. Helper perlu
menyokong safe escaping, parameter interpolation dan pluralization. HTML mentah
dalam catalogue dielakkan kecuali melalui API khusus dan allowlist yang diuji.

### 3.5 API dan operational feedback

Response contract mengekalkan:

```json
{
  "status": 0,
  "code": "UC2_CURRENT_PASSWORD_INVALID",
  "correlation_id": "..."
}
```

Frontend memetakan `code` kepada translation key. Server-rendered response dan
e-mel boleh diterjemahkan pada server berdasarkan locale yang telah disahkan.
Legacy `msg` dikekalkan sementara sepanjang compatibility window, kemudian
dikeluarkan hanya selepas semua consumer dipetakan dan diuji.

### 3.6 Data yang kekal invariant

Perkara berikut tidak diterjemahkan:

- correlation ID, error code dan audit event;
- App ID, Site API Code, URL dan user ID;
- nama individu dan organisasi rasmi;
- token, protocol value dan security diagnostic;
- log detail asal serta timestamp canonical; dan
- metadata aplikasi/kategori sehingga ML7 dilaksanakan.

Label paparan audit boleh diterjemahkan tetapi evidence asal kekal stabil.

### 3.7 Formatting dan accessibility

- `<html lang>` dijana sebagai `ms` atau `en`.
- `aria-label`, screen-reader-only text, alt/title UI diterjemahkan.
- Tarikh/nombor dipaparkan mengikut locale tanpa mengubah nilai database.
- API/export/audit menggunakan format canonical seperti ISO 8601 apabila
  diperlukan.
- Language switcher boleh dicapai dengan keyboard dan fokus dikekalkan selepas
  pertukaran.

### 3.8 Cache, session dan keselamatan

- Cache key bagi content terjemahan memasukkan locale.
- Cookie locale menggunakan allowlist, `Secure`, `SameSite` dan scope minimum.
- Locale tidak memberi kesan kepada authentication atau authorization.
- Missing key tidak boleh mendedahkan path, stack trace atau secret.
- Pertukaran bahasa ketika borang dirty/request aktif tidak boleh menghilangkan
  input tanpa confirmation.

### 3.9 Boundary External Sync dan Admin Step-Up

- Locale hanya mempengaruhi label dan penerangan, bukan action count, source
  scope, threshold, plan hash atau approval expiry.
- Exact confirmation phrase dipaparkan dalam blok canonical dan tidak
  diterjemahkan pada rollout awal.
- Locale mesti dikekalkan apabila pengguna bergerak ke Admin Step-Up dan kembali
  ke Admin, tanpa memanjangkan lifetime grant.
- Tamat Admin Step-Up mesti kembali ke dashboard pengguna dalam locale semasa;
  tamat kedua-dua session tetap kembali ke Login.
- Error code dan correlation ID dipaparkan tanpa perubahan, bersama penerangan
  localized yang dipetakan daripada code.

### 3.10 Translation ownership dan release discipline

- Setiap key mempunyai domain owner dan content reviewer.
- Perubahan catalogue BM/English mesti melalui parity contract.
- Key tidak boleh dipadam semasa masih dirujuk oleh PHP, JavaScript atau e-mel.
- Release tidak boleh lulus jika key kosong, duplicate, orphan kritikal atau
  interpolation placeholder tidak sepadan antara locale.
- Machine translation boleh digunakan sebagai draft sahaja dan tidak menjadi
  content rasmi tanpa semakan owner.
- Coverage report mesti membezakan translated, intentionally canonical,
  database content, deprecated dan belum dipetakan.

## 4. Glossary Awal

| Bahasa Melayu | English |
|---|---|
| Log Masuk | Sign In |
| Log Keluar | Sign Out |
| ID Pengguna | User ID |
| Kata Laluan | Password |
| Lupa Kata Laluan | Forgot Password |
| Tetapan Semula Kata Laluan | Password Reset |
| Aplikasi | Applications |
| Akaun Pengguna | User Accounts |
| Sesi Aktif | Active Sessions |
| Log Audit | Audit Log |
| Konfigurasi | Configuration |
| Sinkronisasi Pengguna | User Synchronization |
| Ringkasan Sinkronisasi | Synchronization Summary |
| Semakan | Preview |
| Laksanakan | Apply |
| Pengguna baharu | New Users |
| Kemas kini | Update |
| Nyahaktif | Deactivate |
| Aktifkan semula | Reactivate |
| Akaun manual yang dilindungi | Protected Manual Accounts |
| Konflik identiti | Identity Conflicts |
| Pengesahan Pentadbir | Administrator Verification |
| Kod pengesahan sekali guna | One-Time Verification Code |
| Simpan | Save |
| Batal | Cancel |

Glossary perlu disahkan owner dan digunakan oleh UI, e-mel, manual serta help
desk. Istilah rasmi UPNM tidak diterjemahkan tanpa kelulusan content owner.

## 5. Pelan Pelaksanaan Berfasa

Bahagian ini menerangkan pelan asal. Penomboran authorization pelaksanaan
kemudiannya menggabungkan skop ML3 ke dalam ML2 Pilot. Status dan perbezaan skop
authoritative direkod dalam Bahagian 19.

### ML0 — Baseline, owner decision dan language contract

**Aktiviti:**

- inventori semua server-rendered, JavaScript, API, e-mel dan dokumen surface;
- hasilkan manifest reproducible yang menyimpan file, domain, key candidate,
  render context, owner dan classification;
- masukkan Login, User, Admin, Admin Step-Up, External Sync, e-mel dan direct
  endpoint dalam baseline;
- tetapkan BM sebagai default, English sebagai pilihan dan fallback BM;
- sahkan precedence preference pengguna/session/default sistem;
- sediakan glossary dan content owner;
- petakan stable response code kepada semantic translation key;
- klasifikasikan UI text, user data, technical data dan translatable content;
- baseline mixed-language, missing key dan HTML `lang`; dan
- petakan characterization test yang bergantung pada literal presentation; dan
- sediakan rollback serta compatibility contract.

**Exit criteria:** Inventori, glossary, decision register dan boundary data
telah disahkan; tiada perubahan business behavior.

### ML1 — Infrastruktur locale dan automated contracts

**Aktiviti:**

- bina locale resolver, catalogue loader dan `trans()` helper;
- asingkan helper server, bootstrap dictionary JavaScript dan template e-mel
  tanpa memuatkan seluruh catalogue ke browser;
- tambah safe interpolation, pluralization dan fallback;
- tambah session/cookie preference dengan locale allowlist;
- jadikan `<html lang>` dinamik;
- bina JavaScript dictionary/bootstrap tanpa menyuntik HTML tidak selamat;
- pastikan cache locale-aware; dan
- tambah contract bagi parity key BM/English, missing/duplicate/empty key,
  invalid locale, escaping, fallback serta placeholder parity;
- tambah unused/orphan-key report tanpa menjadikannya destructive cleanup; dan
- pastikan missing key production menghasilkan fallback dan diagnostic
  tersanitasi, bukan path atau exception mentah.

**Exit criteria:** Infrastruktur boleh bertukar locale dengan selamat tanpa
mengubah akses, data atau stable response code.

### ML2 — Configuration default language dan preference pengguna

**Aktiviti:**

- tambah `default_locale` melalui migration forward/rollback;
- tambah seksyen `Language & Localization` dalam Admin Configuration;
- server-side validation, confirmation, loading/double-submit protection;
- audit `SYSTEM_DEFAULT_LOCALE_UPDATED`;
- tambah language switcher bagi guest dan authenticated user;
- simpan preference pengguna dalam stor khusus/profil dan cookie untuk guest;
- kekalkan locale merentas Login, dashboard pengguna, Admin Step-Up dan
  dashboard Admin tanpa memanjangkan session/grant;
- jelaskan impak default terhadap pengguna yang mempunyai preference; dan
- pastikan perubahan default tidak memerlukan logout.

**Exit criteria:** Admin boleh menetapkan default; pengguna boleh override;
preference kekal selepas refresh/login dan invalid locale ditolak.

### ML3 — Pilot public authentication dan Password Recovery

**Aktiviti:**

- terjemah login, validation inline, loading dan authentication failure;
- terjemah Forgot Password, OTP dan reset password;
- sediakan template e-mel BM/English dengan subject/body/footer sepadan;
- refactor `OneIdEmailTemplate` supaya locale dipilih secara explicit dan semua
  dynamic value kekal escaped;
- pilih e-mel locale melalui preference penerima kemudian system fallback;
- kekalkan anti-enumeration, correlation ID dan security code; dan
- UAT guest/session/cookie dalam BM dan English.

**Exit criteria:** Flow login/recovery lengkap dalam kedua-dua bahasa tanpa
mixed-language, regression keselamatan atau perbezaan business outcome.

### ML4 — Dashboard pengguna dan self-service security

**Aktiviti:**

- menu, application directory, carian, kategori dan Favourite;
- profil dan Tukar Kata Laluan;
- forced-change, confirmation dan session feedback;
- loading, empty, validation, error serta success states;
- aria-label, alt/title dan mobile UI; dan
- pastikan nama aplikasi/kategori kekal sebagai data asal.

**Exit criteria:** Semua user journey dan dynamic state lulus parity BM/English,
accessibility serta security contract.

### ML5 — Dashboard Administrator secara modul

Pelaksanaan dibuat satu modul pada satu masa:

1. shell, navigation, common modal dan feedback component;
2. Configuration, Password Recovery dan Admin Step-Up 2FA;
3. User Account, Manual Add User dan ACL;
4. External Sync Summary serta Staff/Prasiswazah/ODL Preview/Apply;
5. Active Sessions, Audit Log dan Sync Log;
6. Web Apps;
7. Version Releases; dan
8. modal/utility admin yang masih aktif.

Setiap modul merangkumi static HTML, JavaScript-generated UI, confirmation,
toast/SweetAlert, loading/empty/error state dan backend feedback. Setiap modul
mempunyai contract dan UAT sendiri sebelum modul berikutnya.

**Exit criteria:** Semua fungsi admin aktif mempunyai parity dan direct endpoint
tidak bergantung pada localized text untuk authorization atau validation.

### ML6 — API message normalization, e-mel dan notifikasi

**Aktiviti:**

- selesaikan pemisahan machine code daripada presentation message;
- klasifikasikan 572 token uppercase machine-like yang ditemui oleh audit
  automatik; hanya response/error code aktif menjadi mapping wajib;
- sediakan mapping semua code kepada key BM/English;
- inventori dan terjemah semua e-mel transactional/security;
- pastikan satu e-mel tidak mencampurkan locale;
- rekod missing mapping secara selamat;
- uji consumer compatibility sebelum mengurangkan legacy `msg`; dan
- kekalkan audit/log dalam format canonical.

**Exit criteria:** Semua response code aktif mempunyai mapping; tiada UI
bergantung pada perbandingan teks manusia; semua e-mel mempunyai fallback.

### ML7 — Metadata aplikasi, kategori dan content database

Fasa ini hanya dilaksanakan jika owner memerlukan content bilingual. Gunakan
translation table seperti:

```text
sp_app_translation
- sp_id
- locale
- name
- description
```

Kategori atau content lain menggunakan pola setara. Tetapkan unique constraint,
fallback kepada content asal, UI pengurusan translation, audit perubahan dan
search merentas locale. Jangan gunakan machine translation automatik sebagai
data rasmi tanpa semakan content owner.

**Exit criteria:** Missing translation mempunyai fallback; ACL/App ID/URL tidak
berubah; search dan Add/Edit mempunyai validation serta audit yang konsisten.

### ML8 — Manual, FAQ, release notes dan sokongan operasi

**Aktiviti:**

- petakan manual/PDF/FAQ mengikut locale;
- paparkan availability dengan jelas jika satu bahasa belum tersedia;
- selaraskan glossary help desk dan template komunikasi;
- dokumentasikan proses menambah key dan semakan content;
- tetapkan ownership translation serta release checklist; dan
- sediakan coverage report untuk setiap release.

**Exit criteria:** Pautan bantuan membuka dokumen locale yang betul atau fallback
yang dinyatakan; proses penyelenggaraan mempunyai owner.

### ML9 — UAT menyeluruh, controlled rollout dan monitoring

**Aktiviti:**

- automated parity, fallback, escaping, pluralization dan locale validation;
- UAT login, recovery, user, admin, e-mel, mobile dan accessibility;
- uji pertukaran bahasa ketika session aktif dan borang dirty;
- uji pertukaran bahasa ketika Preview approval masih hidup, Apply sedang
  dihantar, Step-Up tamat dan modal mempunyai input confirmation;
- uji cache isolation dan direct URL;
- pilot dengan kumpulan kecil menggunakan feature flag sementara;
- monitor missing key, mixed-language report dan operational error; dan
- rollback kepada default BM tanpa memadam preference/data translation.

**Exit criteria:** Tiada critical/high defect, tiada security regression, owner
mengesahkan glossary/content dan observation window selesai.

## 6. UAT Minimum

- guest bertukar BM/English dan preference kekal selepas refresh;
- authenticated user mengekalkan preference selepas logout/login;
- perubahan default admin hanya mempengaruhi pengguna tanpa preference;
- invalid/tampered locale ditolak dan fallback kepada BM;
- login, recovery, OTP dan e-mel menggunakan locale yang sama;
- Admin Step-Up e-mel/TOTP, challenge page dan return page menggunakan locale
  yang sama tanpa mengubah purpose atau lifetime grant;
- user/admin static dan dynamic state tidak bercampur bahasa;
- plural, tarikh dan masa dipaparkan dengan betul;
- direct URL, AJAX timeout dan error path mempunyai translation;
- correlation ID, code, App ID dan audit evidence kekal invariant;
- source code, plan hash, action count dan exact confirmation External Sync
  kekal invariant;
- pertukaran bahasa tidak mengguna semula approval, menggandakan Apply atau
  menukar hasil reconciliation;
- cache tidak membocorkan bahasa antara session/pengguna;
- screen reader menerima `<html lang>` dan `aria-label` yang betul;
- missing key tidak mendedahkan path/stack trace;
- pertukaran bahasa tidak membuang input borang tanpa confirmation; dan
- fallback metadata/dokumen berfungsi apabila translation belum ada.

## 7. Risiko dan Mitigasi

| Risiko | Mitigasi |
|---|---|
| UI bercampur selepas rollout | Inventori per surface, coverage contract dan rollout per modul |
| Translation mengubah security behavior | Authorization/validation menggunakan code dan value canonical |
| XSS melalui interpolation | Escape by default, typed placeholder dan larang HTML mentah |
| Invalid locale/path traversal | Allowlist `ms`/`en`; tiada input digunakan terus sebagai path |
| Cache silang bahasa | Locale dalam cache key dan isolation test |
| E-mel salah bahasa | Preference penerima dengan deterministic fallback |
| Data aplikasi berubah | Metadata database di luar skop sehingga ML7 |
| Borang kehilangan input | Dirty-form guard dan controlled reload |
| Missing key production | Fallback BM, diagnostic selamat dan monitoring |
| Translation tidak konsisten | Glossary, content owner dan release review |

## 8. Rollback Prinsip

- Infrastructure locale boleh dinyahaktif melalui deployment/config terkawal dan
  kembali kepada BM tanpa memadam catalogue.
- Migration `default_locale` dan preference mempunyai rollback yang tidak
  menjejaskan akaun atau security data.
- Stable code/API kekal sepanjang pelaksanaan bagi mengelakkan consumer outage.
- Setiap modul ML5 boleh dirollback secara berasingan sebelum modul seterusnya.
- Metadata bilingual ML7 tidak menggantikan content asal sehingga rollout
  disahkan.

## 9. Keputusan Owner untuk ML0

Owner meluluskan language contract melalui
`ONEID-ML0-20260724-01` pada 24 Julai 2026:

1. BM (`ms`) ialah default dan hard fallback.
2. English (`en`) ialah bahasa pilihan kedua.
3. Pengguna boleh override default organisasi tanpa mengubah authorization.
4. Precedence ialah preference authenticated → session → cookie guest →
   default sistem → hard fallback `ms`.
5. Preference authenticated disimpan dalam jadual additive khusus, bukan note
   atau `data1`–`data12`.
6. Guest menggunakan cookie `oneid_locale`, allowlist `ms|en`, `Secure`,
   `HttpOnly`, `SameSite=Lax`, `Path=/` dan retention 180 hari tanpa PII.
7. Firdaus, System Analyst ialah content owner/reviewer BM dan English.
8. Firdaus, System Analyst/DBA ialah security reviewer.
9. Metadata aplikasi/kategori bilingual ditangguhkan ke ML7.
10. Exact confirmation phrase serta evidence teknikal kekal canonical.
11. Login help, recovery instruction, OTP/security e-mel dan security warning
    wajib bilingual bagi Pilot; dokumen lain ditangguhkan ke ML8.
12. Pilot UAT merangkumi dua pengguna BM, dua pengguna English dan seorang
    Administrator bagi Login, Password Recovery serta OTP e-mel.
13. Observation period minimum ialah tiga hari bekerja.
14. Legacy API `msg` kekal sehingga mapping/consumer mencapai 100%, regression
    lulus dan sekurang-kurangnya satu release observation selesai.

### 9.1 Decision register audit semula

| ID | Keputusan diperlukan | Cadangan | Status |
|---|---|---|---|
| ML-D01 | Default dan hard fallback | `ms` | APPROVED |
| ML-D02 | Locale kedua | `en` | APPROVED |
| ML-D03 | User override | Dibenarkan; tidak mengubah authorization | APPROVED |
| ML-D04 | Precedence | User → session → cookie → system → `ms` | APPROVED |
| ML-D05 | Stor preference authenticated | Jadual additive khusus, bukan note/medan bebas | APPROVED FOR DESIGN |
| ML-D06 | Cookie guest | `oneid_locale`; allowlist; Secure; HttpOnly; SameSite=Lax; 180 hari; tanpa PII | APPROVED |
| ML-D07 | Content/glossary owner | Firdaus, System Analyst bagi BM dan English | APPROVED |
| ML-D08 | Metadata database bilingual | Tangguh sehingga ML7 diluluskan | DEFERRED |
| ML-D09 | Exact confirmation | Kekal canonical; arahan manusia boleh diterjemah | APPROVED |
| ML-D10 | Dokumen wajib bilingual | Pilot help/recovery/OTP/security sahaja; baki ke ML8 | APPROVED / DEFERRED BY SCOPE |
| ML-D11 | Pilot group/window | 2 BM, 2 English, 1 Admin; minimum 3 hari bekerja | APPROVED |
| ML-D12 | Legacy `msg` compatibility | Kekal sehingga 100% mapping/consumer, regression dan satu release observation | APPROVED |

### 9.2 Disposition baseline v2.6.2

| Perkara | Status semasa |
|---|---|
| Translation catalogue/helper | Tiada |
| Locale resolver/preference | Tiada |
| Language switcher | Tiada |
| Dynamic `<html lang>` | Tiada |
| BM/English key parity test | Tiada |
| E-mel bilingual | Tiada; template pusat BM sahaja |
| Main page metadata | Login/User/Admin masih `lang="en"` |
| Admin Step-Up metadata | `lang="ms"` hardcoded |
| ML0 language contract | APPROVED — `ONEID-ML0-20260724-01` |
| ML0 inventory | PASS — manifest per-location, glossary v1 dan compatibility mapping disahkan |
| ML0 evidence | `ONEID-ML0-20260725-01` |
| ML1 implementation authorization | APPROVED — `ONEID-ML1-20260725-01` |
| ML1 local implementation | PASS — locale infrastructure dan contracts |
| ML1 Local WSL migration | APPLIED / PASS — `ONEID-ML1-UAT-20260725-01` |
| ML2/ML3 Pilot UI translation | PASS / CLOSED — `ONEID-ML2-LOCAL-20260725-02` |

## 10. Cadangan Titik Mula

ML0 telah ditutup sebagai **PASS / CLOSED** pada 25 Julai 2026 selepas manifest
per-location, glossary BM–English v1 dan compatibility mapping disahkan melalui
evidence `ONEID-ML0-20260725-01`.

Urutan kerja yang disyorkan:

1. kekalkan manifest, glossary v1 dan canonical boundary sebagai baseline;
2. dapatkan authorization berasingan bagi ML1 infrastructure;
3. selepas ML1 stabil, gunakan Login dan Password Recovery sebagai pilot ML3;
4. lanjutkan kepada dashboard pengguna; dan
5. laksanakan Admin secara modul, dengan External Sync dan Admin Step-Up
   menerima security regression gate khusus.

Jangan terus menggantikan teks di seluruh sistem. Dashboard pengguna dan setiap
modul Administrator hanya diterjemahkan selepas pilot membuktikan locale,
fallback, e-mel dan operational feedback berfungsi secara konsisten.

## 11. Penutupan ML0

Kelulusan penutupan diterima pada 25 Julai 2026:

- Per-string/per-location inventory: reviewed and accepted.
- UI, JavaScript, API/AJAX dan e-mel coverage: accepted.
- Unresolved critical strings: 0.
- Glosari BM–English v1: approved and frozen.
- Literal-dependent tests: identified and mapped kepada stable response code
  serta translation key untuk migrasi berfasa.
- Legacy `msg`: retained sepanjang compatibility window.
- Exact confirmation dan identifier teknikal: canonical/invariant.
- Inventory contract, manifest validation dan documentation checks: PASS.
- Evidence: `ONEID-ML0-20260725-01`.

**Decision:** ML0: PASS / CLOSED. ML1 implementation dan Production tidak
dibenarkan oleh kelulusan ini.

**Approver:** Firdaus, System Analyst/DBA
**Approval date:** 25 Julai 2026
**Change reference:** `ONEID-ML0-20260724-01`

## 12. ML1 Locale Infrastructure

Implementation and test authorization diterima melalui
`ONEID-ML1-20260725-01`. ML1 menyediakan:

- resolver dengan precedence authenticated preference → session → guest cookie
  → system default → hard fallback `ms`;
- allowlist ketat `ms|en` dan penolakan nilai/path locale tidak sah;
- catalogue/helper BM–English dengan parity dan safe missing-key fallback;
- repository preference authenticated yang terasing daripada note dan
  `data1`–`data12`;
- cookie helper `oneid_locale` dengan `Secure`, `HttpOnly`, `SameSite=Lax`,
  `Path=/` dan retention 180 hari;
- additive migration up/down `user_locale_preference`;
- dynamic `<html lang>` pada Login, User, Admin, Admin user list dan Admin
  Step-Up;
- response seam `code`, `translation_key` dan legacy `msg`; dan
- characterization serta infrastructure contract.

Rehearsal migration menggunakan database sementara rawak dan membuktikan nilai
`ms/en`, penolakan locale lain, zero mutation pada `user_tbl`, rollback table
dan pembuangan database rehearsal.

ML1 tidak menterjemah UI Pilot, tidak membuang legacy `msg`, tidak mengubah
authentication/authorization/ACL, tidak menterjemah exact confirmation atau
metadata database, dan tidak menjalankan migration pada UAT. Pilot serta
production kekal tidak dibenarkan tanpa gate berikutnya.

### 12.1 Controlled UAT migration/activation gate

Gate UAT telah disediakan secara dormant melalui
`tools/ml1_uat_migration_gate.php` dan didokumenkan dalam
`docs/ML1_UAT_MIGRATION_AND_PILOT_GATE.md`.

Preview read-only pada 25 Julai 2026 menunjukkan:

- table `user_locale_preference`: absent;
- preference rows: `0`;
- locale infrastructure: disabled; dan
- mutation statements: `0`.

Live Apply kekal fail-closed dan memerlukan approval baharu yang menetapkan
backup reference, change window serta expected existing preference rows.
Activation infrastructure dan Pilot UI juga belum dibenarkan.

### 12.2 Local WSL migration evidence

Controlled local migration dilaksanakan pada 25 Julai 2026, 1:40:03 PM MYT
dalam window yang diluluskan:

- change reference: `ONEID-ML1-UAT-20260725-01`;
- backup reference: `ONEID-LOCAL-BACKUP-20260725-01`;
- expected/existing preference rows: `0 / 0`;
- `user_locale_preference`: present;
- user mutations: `0`;
- locale infrastructure: disabled; dan
- schema Apply flag ditutup semula selepas reconciliation.

Pelaksanaan ini hanya pada database local WSL untuk `https://oneid.local`.
Tiada Git push, staging deployment, Pilot UI atau Production activation dibuat.

## 13. ML2 Local Pilot UI

ML2 dilaksanakan pada local WSL melalui authorization
`ONEID-ML2-LOCAL-20260725-01`. Skop terhad kepada:

- Login;
- Forgot Password / Password Recovery;
- modal, countdown dan feedback OTP;
- tetapan kata laluan baharu;
- e-mel Password Recovery; dan
- e-mel OTP keselamatan Administrator.

Pemilih BM/English hanya dipaparkan pada Login. Guest preference menggunakan
cookie `oneid_locale`; locale yang dipilih dipromosikan ke jadual
`user_locale_preference` selepas authentication berjaya. Kegagalan persistence
preference tidak menggagalkan authentication.

Stable response code dan `translation_key` ditambah sambil mengekalkan medan
legacy `msg`/`login_response_msg`. OTP, correlation ID, audit code dan identifier
teknikal kekal canonical. Dashboard User/Admin, External Sync, Admin Step-Up UI,
metadata database dan Production kekal di luar skop.

Evidence local:

- ML2 Pilot contract: PASS;
- BM/English catalogue parity: PASS;
- HTML dan plain-text OTP e-mel BM/English: PASS;
- authenticated preference transaction/rollback rehearsal: PASS;
- Login API stable code/key/legacy response: PASS;
- direct HTTPS BM/English/fallback rendering: PASS;
- SC6 Password Recovery regression: PASS; dan
- dashboard characterization: PASS.

ML2 kini **READY FOR LOCAL OBSERVATION**, bukan Production-ready. Observation
minimum tiga hari bekerja dengan dua pengguna BM, dua pengguna English dan
seorang Administrator masih diperlukan sebelum closure.

### 13.1 Observation window

Observation ML2 direkod bermula dengan Day 0 readiness pada 25 Julai 2026.
Tiga hari bekerja terawal ialah 27–29 Julai 2026. Matrix peserta, pemeriksaan
wajib dan closure gate direkod dalam `docs/ML2_LOCAL_PILOT_OBSERVATION.md`.
Snapshot observation hanya mengeluarkan aggregate locale count tanpa identity
atau OTP.

### 13.2 Revised solo observation

Melalui `ONEID-ML2-LOCAL-20260725-02`, Firdaus, System Analyst/DBA menjadi
penguji tunggal bagi peranan pengguna BM, pengguna English dan Administrator.
Keperluan lima peserta serta minimum tiga hari bekerja diketepikan untuk
local-only testing dan risiko observation singkat diterima oleh approver.

Gate penutupan memerlukan PASS bagi semua senario browser/mailbox dan zero
defect security/critical; syarat tersebut dipenuhi melalui evidence penutupan
di bawah.

### 13.3 ML2 closure

Solo observation manual diterima pada 25 Julai 2026 dengan semua senario BM,
English dan Administrator PASS. Invalid locale fallback, accessibility,
authentication/authorization/ACL regression turut PASS dan defect
security/critical ialah `0`.

**Decision:** ML2 PASS / CLOSED
**Tester and approver:** Firdaus, System Analyst/DBA
**Evidence reference:** `ONEID-ML2-LOCAL-20260725-02`

Closure ini hanya untuk local WSL. Ia tidak memberi kebenaran Git push, staging,
Production atau peluasan translation kepada dashboard dan modul lain.

## 14. ML4 User Dashboard Multilanguage

ML4 dilaksanakan secara local WSL melalui authorization
`ONEID-ML4-LOCAL-20260725-01`. Skop implementation merangkumi:

- shell, navigasi dan pemilih bahasa Dashboard Pengguna;
- direktori aplikasi, carian, kategori, Favourite, loading dan empty/error
  states;
- profil pengguna dan accessibility label foto profil;
- Change Password termasuk forced password change, validation, success, error,
  rate-limit dan reauthentication feedback; dan
- paparan sesi serta confirmation untuk menamatkan sesi lain.

Teks statik PHP dan kandungan yang dijana JavaScript menggunakan katalog
`dashboard.*` BM/English. Endpoint Change Password mengeluarkan stable
`translation_key` sambil mengekalkan legacy `msg`, response code dan
correlation ID. Authentication, authorization, ACL, session lifetime serta
exact confirmation tidak diubah.

Nama dan keterangan aplikasi serta nama kategori kekal terus daripada metadata
database dan tidak diterjemahkan. Kandungan penuh FAQ pula kekal BM dan
ditandakan `lang="ms"` kerana terjemahan FAQ/dokumen penuh masih ditangguhkan ke
ML8 berdasarkan language contract.

Boundary ML4:

- Administrator Dashboard, External Sync, Admin Step-Up dan ACL administration
  tidak diterjemahkan;
- tiada Git push atau deployment staging/Production;
- locale infrastructure boleh dimatikan untuk fallback selamat kepada BM; dan
- data akaun, sesi, Favourite serta ACL tidak dimutasi oleh wiring bahasa.

Evidence automatik local:

- ML4 User Dashboard characterization: PASS;
- ML4 scope/security contract: PASS;
- katalog BM/English ordered parity: PASS;
- ML1 dan ML2 regression contract: PASS;
- User Dashboard characterization: PASS;
- Change Password UI/service, forced-change, session/rate-limit dan password
  quality regression: PASS; dan
- Password Recovery regression: PASS.

Status semasa ialah **READY FOR LOCAL MANUAL OBSERVATION**. Penutupan ML4
memerlukan semakan browser BM/English bagi desktop/mobile, Favourite, carian,
Change Password, forced password change, session feedback, invalid-locale
fallback dan regression authentication/authorization/ACL. ML5 Administrator
Configuration, staging dan Production memerlukan authorization berasingan.

## 15. ML5 Administrator Multilanguage

ML5 dilaksanakan secara local WSL melalui authorization
`ONEID-ML5-LOCAL-20260725-01`. Skop implementation merangkumi:

- shell, profil, navigasi dan statistik Dashboard Administrator;
- pengurusan Web Apps dan Categories tanpa menterjemah metadata database;
- User Account, Active Sessions dan Audit Log;
- loading, empty, validation, success dan error states bagi permukaan utama;
- label accessibility dan paparan responsif; dan
- konfigurasi default locale sistem yang versioned, diaudit dan dilindungi oleh
  `SECURITY_CONFIGURATION_CHANGE`.

Katalog `admin.*` BM/English mempunyai ordered parity. Teks PHP dan state
JavaScript utama menggunakan translation key sambil mengekalkan legacy `msg`.
Preference pengguna authenticated kekal mempunyai precedence lebih tinggi
daripada default sistem.

Boundary ML5:

- External Sync dan Admin Step-Up tidak diterjemahkan;
- nama/keterangan aplikasi, kategori dan metadata database kekal invariant;
- exact confirmation, response code, correlation ID dan identifier teknikal
  kekal canonical;
- authentication, authorization, ACL dan session lifetime tidak diubah; dan
- tiada Git push atau deployment staging/Production.

Konfigurasi default locale menggunakan additive column
`sys_config.default_locale` dengan allowlist database `ms|en`. Up/down rehearsal
dalam database sementara membuktikan default `ms`, penyimpanan `en`, penolakan
locale tidak sah, zero mutation pada data dilindungi dan rollback bersih.
Migration tersebut **belum dilaksanakan pada database local aktif** kerana
authorization ML5 membenarkan implementation dan local test sahaja, bukan
unattended database migration.

Evidence automatik local:

- ML5 Administrator characterization: PASS;
- ML5 scope/security contract: PASS;
- katalog BM/English ordered parity: PASS;
- default-locale migration up/down rehearsal: PASS;
- ML1, ML2 dan ML4 regression contract: PASS;
- User/Administrator Dashboard characterization: PASS; dan
- `git diff --check`: PASS.

Historical ML5 implementation gate ialah **CONTROLLED SCHEMA ACTIVATION
REQUIRED**. Langkah pada ketika itu memerlukan authorization migration local terkawal dengan
backup reference, change window dan expected current `sys_config` rows.
Selepas migration, semakan browser BM/English serta ujian konfigurasi default
locale diperlukan sebelum ML5 boleh ditutup. Production kekal tidak dibenarkan.

### 15.1 Controlled local schema activation

Controlled activation dilaksanakan pada 25 Julai 2026, 4:02 PM MYT dalam
revised window 4:00–4:30 PM melalui:

- change reference: `ONEID-ML5-LOCAL-20260725-02`;
- backup reference: `ONEID-LOCAL-BACKUP-20260725-02`;
- expected/actual `sys_config` rows: `1 / 1`;
- column `default_locale`: present;
- constraint `chk_sys_config_default_locale`: present; dan
- default akhir: Bahasa Melayu (`ms`).

Guarded configuration path diuji `ms → en → ms`. Kedua-dua perubahan
meningkatkan `configuration_version` daripada `9` kepada `11` dan menghasilkan
dua rekod audit `UPDATE_SYSTEM_DEFAULT_LOCALE / SUCCESS`. Correlation evidence:
`febee796443347e7` dan `98c51d714dbd5d40`.

Reconciliation selepas activation:

- ML5 contract termasuk migration rehearsal: PASS;
- Dashboard characterization: PASS;
- ML4 regression: PASS;
- rollback script SHA-256:
  `13e8f911daa62daaf194f212613d77b20674ee38c9347812be2f86793f589cbc`;
- rollback readiness: `true`; dan
- mutation selepas reconciliation: `0`.

Historical gate selepas schema activation ialah **LOCAL BROWSER OBSERVATION
REQUIRED**. Pada ketika itu ML5 belum boleh ditutup sehingga Administrator mengesahkan BM/English pada
permukaan yang diluluskan, preference persistence, default-locale behaviour,
invalid locale fallback, accessibility dan regression security. Tiada Git push,
staging atau Production deployment dibuat.

### 15.2 ML4 dan ML5 local observation closure

Firdaus, System Analyst/DBA mengesahkan local observation pada 25 Julai 2026
melalui evidence `ONEID-ML45-LOCAL-20260725-01`.

ML4 User Dashboard:

- BM/English switching dan preference persistence: PASS;
- desktop/mobile presentation: PASS;
- Favourite, search, password dan session feedback: PASS; dan
- authentication/authorization/ACL regression: PASS.

ML5 Administrator:

- BM/English switching dan preference persistence: PASS;
- Web Apps, Categories, User Account, Active Sessions, Audit Log dan
  Configuration: PASS;
- default-locale BM/English serta invalid-locale fallback: PASS;
- accessibility/mobile presentation: PASS;
- boundary External Sync dan Admin Step-Up: PASS; dan
- authentication/authorization/ACL regression: PASS.

Critical atau security defects: `0`.

**Decision:** ML4 PASS / CLOSED; ML5 PASS / CLOSED

**Tester and approver:** Firdaus, System Analyst/DBA

**Evidence reference:** `ONEID-ML45-LOCAL-20260725-01`

Closure ini hanya untuk local WSL. Git push, staging, Production, peluasan
External Sync/Admin Step-Up, penterjemahan metadata database dan pembuangan
legacy `msg` kekal tidak dibenarkan tanpa authorization baharu.

## 16. ML6 API, E-mail dan Notification Multilanguage

ML6 dilaksanakan secara local WSL melalui authorization
`ONEID-ML6-LOCAL-20260725-01`.

Implementation:

- response aktif bagi authentication, Configuration, Password Recovery, Web
  Apps/Categories, User Account, Active Sessions, Change Password dan Favourite
  dipetakan daripada stable response `code` kepada translation key;
- JSON compatibility layer menambah `translation_key` dan `localized_msg`
  tanpa membuang atau menulis semula medan legacy `msg/message`;
- frontend baharu mengutamakan `localized_msg` dengan fallback kepada legacy
  message;
- e-mel ujian Password Recovery menggunakan katalog locale yang sama bagi
  subject, HTML dan plain text;
- BM kekal hard fallback dan katalog BM/English mempunyai ordered parity; dan
- inventory reproducible mengasingkan code yang diluluskan daripada boundary
  yang ditangguhkan.

External Sync, per-user external Resync, Admin Step-Up, TOTP dan Admin 2FA
disekat daripada ML6 enrichment. Code, correlation ID, exact confirmation,
audit/log dan metadata database kekal canonical. Legacy `msg` masih wujud dan
tiada authentication, authorization, ACL atau session lifetime diubah.

Evidence automatik local:

- ML6 API/e-mail/notification characterization: PASS;
- ML6 scope, compatibility dan boundary contract: PASS;
- active response inventory unresolved codes: `0`;
- BM/English transactional e-mail parity: PASS;
- ML1 hingga ML5 regression: PASS;
- Password Recovery dan Change Password regression: PASS; dan
- Dashboard characterization serta `git diff --check`: PASS.

Status semasa ialah **IMPLEMENTED / READY FOR LOCAL OBSERVATION**. Penutupan ML6
memerlukan direct endpoint evidence BM/English, semakan toast/modal/validation,
e-mel HTML/plain-text, invalid-locale fallback, zero mixed-language defect dan
zero security regression. Git push, staging dan Production kekal tidak
dibenarkan.

### 16.1 ML6 local observation closure

Firdaus, System Analyst/DBA mengesahkan local observation melalui evidence
`ONEID-ML6-LOCAL-20260725-01`.

Keputusan:

- API/AJAX BM dan English, stable response code/translation key, legacy `msg`
  compatibility serta invalid-locale fallback: PASS;
- toast, modal, validation, loading dan empty state bagi Dashboard Pengguna dan
  Administrator: PASS;
- mixed-language critical defects: `0`;
- e-mel Password Recovery BM/English dalam HTML dan plain text serta parity
  subject/body: PASS;
- boundary External Sync dan Admin Step-Up: PASS;
- technical identifier dan exact confirmation kekal invariant: PASS; dan
- authentication/authorization/ACL regression: PASS.

Critical atau security defects: `0`.

**Decision:** ML6 PASS / CLOSED

**Tester and approver:** Firdaus, System Analyst/DBA

**Evidence reference:** `ONEID-ML6-LOCAL-20260725-01`

Closure ini hanya untuk local WSL. Legacy `msg` masih dikekalkan. Git push,
staging, Production, metadata bilingual ML7 dan dokumen penuh ML8 kekal tidak
dibenarkan tanpa authorization baharu.

## 17. ML7 Bilingual Database Metadata

ML7 implementation dan read-only preview dilaksanakan secara local WSL melalui
authorization `ONEID-ML7-LOCAL-20260725-01`.

Reka bentuk additive:

- `sp_app_translation` menyimpan nama dan keterangan aplikasi mengikut locale;
- `sp_group_translation` menyimpan nama kategori mengikut locale;
- `metadata_translation_history` menyimpan perubahan versioned dan correlated;
- unique entity + locale serta CHECK `ms|en` diwajibkan; dan
- foreign key mengikat translation kepada metadata asal.

`sp_list` dan `sp_group` kekal authoritative serta tidak diubah oleh migration
up/down. Repository membaca translation berdasarkan locale dan fallback kepada
nama/keterangan asal apabila translation atau schema tiada. ID aplikasi,
category assignment, URL, ACL, SSO configuration dan aset tidak boleh dimutasi
melalui repository ML7.

Dashboard Pengguna dan Administrator telah mempunyai locale-aware read path;
carian menggunakan metadata yang dipaparkan. Administrator translation UI
menyediakan Application/Category, BM/English, optimistic
`translation_version`, change reason, audit dan
`SECURITY_CONFIGURATION_CHANGE` step-up. Ketika schema dormant, UI hanya
menunjukkan readiness/completeness dan Save kekal disabled.

Read-only preview active local:

- applications: `77`;
- categories: `7`;
- translation tables: absent;
- translations: `0`;
- fallback to original: `true`;
- migration Apply capability: `false`; dan
- mutation statements: `0`.

Database rehearsal sementara membuktikan up migration, BM/English lookup,
original fallback, audit, stale-write rejection serta down migration tanpa
perubahan pada metadata asal.

Status semasa ialah **IMPLEMENTED / READ-ONLY PREVIEW READY / SCHEMA DORMANT**.
Live schema Apply memerlukan authorization exact berasingan dengan backup,
change window, expected application/category rows dan zero existing translation
tables. Git push, staging, Production dan machine translation kekal tidak
dibenarkan.

### 17.1 Controlled local schema activation

Controlled activation dilaksanakan pada 25 Julai 2026, 8:05 PM MYT dalam
window 8:05–8:35 PM melalui:

- change reference: `ONEID-ML7-LOCAL-20260725-02`;
- backup reference: `ONEID-LOCAL-BACKUP-20260725-03`;
- expected/actual applications: `77 / 77`;
- expected/actual categories: `7 / 7`;
- existing/created translation tables: `0 / 3`; dan
- initial translation serta history rows: `0`.

Checksum `sp_list` + `sp_group` sebelum dan selepas schema activation ialah
`d66dd66d08c6b293d6fc4fa5558b6de44ba82330cf4ad9d12416b403e5c7a5e8`;
metadata asal tidak berubah.

Controlled content verification:

- application `0Y4IIXKILT`, English: `E-PTW System` /
  `Permit to Work System`;
- category `2`, English: `Human Resources`;
- English display dan search: PASS;
- BM missing-translation fallback kepada `Sistem E-PTW` dan `HR`: PASS;
- audit rows: `2`;
- stale-write rejection: PASS; dan
- checksum metadata asal selepas translation: tidak berubah.

Correlation evidence: `66ab47824423cd1e` dan `4ee4c1271ab997d2`.

Historical gate selepas controlled translation ialah **LOCAL BROWSER
OBSERVATION REQUIRED**. Pada ketika itu penutupan ML7 memerlukan semakan UI
Administrator, Dashboard Pengguna BM/English, carian localized, fallback
metadata asal, audit/version conflict dan regression ACL/SSO. Git push, staging
dan Production kekal tidak dibenarkan.

### 17.2 ML7 local observation closure

Firdaus, System Analyst/DBA mengesahkan local observation melalui evidence
`ONEID-ML7-LOCAL-20260725-02`.

Keputusan:

- Administrator translation UI: PASS;
- English application dan category metadata: PASS;
- English localized search: PASS;
- BM original-metadata serta missing-translation fallback: PASS;
- audit dan optimistic version conflict: PASS;
- application ID, URL dan category assignment kekal invariant: PASS;
- ACL dan SSO regression: PASS;
- desktop/mobile/accessibility: PASS; dan
- critical atau security defects: `0`.

**Decision:** ML7 PASS / CLOSED

**Tester and approver:** Firdaus, System Analyst/DBA

**Evidence reference:** `ONEID-ML7-LOCAL-20260725-02`

Closure ini hanya untuk local WSL dan controlled translation yang telah
direkodkan. Penterjemahan metadata tambahan masih memerlukan semakan content
owner. Git push, staging, Production dan automatic/machine translation kekal
tidak dibenarkan.

## 18. ML7A Metadata Translation Content Completion

ML7A inventory, draft dan read-only Preview dilaksanakan secara local WSL
melalui authorization `ONEID-ML7A-LOCAL-20260725-01`.

Baseline:

- applications/categories: `77 / 7`;
- total review items: `84`;
- existing approved translations: `2`;
- pending owner review: `82`; dan
- completion: `2.38%`.

Setiap item mempunyai entity identity, active status, metadata asal, draft BM,
draft English, classification, review decision, translation version serta
source digest. Classification semasa:

- existing translation approved: `2`;
- intentionally fallback (archived bukan ujian): `32`;
- proper noun/invariant: `6`;
- review required: `8`; dan
- translation required: `36`.

Data ujian lama, description kosong dan encoding tidak normal diklasifikasikan
`REVIEW_REQUIRED`; ia tidak diterjemah atau diluluskan secara automatik.
Content duplicate groups direkod untuk semakan owner tetapi entity identity
kekal unik. Unresolved atau duplicate identity ialah `0`.

Administrator mempunyai read-only content review table yang memaparkan metadata
asal, draft English, classification dan keputusan. Preview mempunyai manifest
digest, `automatic_approval=false`, `can_apply=false` dan
`mutation_statements=0`. Bulk Apply endpoint tidak disediakan dalam fasa ini.

Historical ML7A gate ialah **INVENTORY/DRAFT PREVIEW READY / OWNER REVIEW REQUIRED**.
`ML7A_OWNER_REVIEW_INCOMPLETE` menyekat Apply sehingga semua 84 item menerima
keputusan explicit daripada content owner. Draft bukan kandungan rasmi dan
tidak boleh dikira completed melalui silent fallback.

Manifest draft semasa mempunyai digest
`d816998148fbbe06e3a7545b5a1caa06f0933e0dab602c61f455fc875d466aac`.
English draft bagi semua aplikasi aktif menggunakan mapping explicit yang
disediakan untuk owner review; archived bukan ujian dicadangkan sebagai
`INTENTIONALLY_FALLBACK`, manakala data ujian/bermasalah kekal
`REVIEW_REQUIRED`.

### 18.1 ML7A content-owner approval

Firdaus, System Analyst dan System Analyst/DBA meluluskan manifest tepat
`d816998148fbbe06e3a7545b5a1caa06f0933e0dab602c61f455fc875d466aac`
melalui evidence `ONEID-ML7A-LOCAL-20260725-01`.

Keputusan owner:

- existing approved translations `2`: ACCEPT;
- translation-required drafts `36`: ACCEPT;
- proper noun/invariant `6`: ACCEPT AS INVARIANT;
- archived records `32`: ACCEPT INTENTIONALLY FALLBACK; dan
- test/problematic records `8`: EXCLUDE / QUARANTINE.

Unresolved dan duplicate identity ialah `0`. Automatic machine approval kekal
tidak dibenarkan dan metadata asal mesti kekal tidak berubah.

Approval ini meluluskan kandungan manifest sahaja. Ia tidak memberi capability
Bulk Apply. Database translation dan review-decision records tidak dimutasi
semasa approval ini. Implementation batch plan, decision persistence serta live
Apply memerlukan authorization berasingan.

### 18.2 ML7A bulk implementation dan Preview

Implementation dan zero-mutation Preview dibenarkan melalui
`ONEID-ML7A-BULK-LOCAL-20260725-01`. Skop ini menghasilkan:

- migration additive `metadata_content_review` dengan unique identity
  `entity_type + entity_id + locale`, allowlist classification/decision dan
  down migration yang hanya membuang jadual additive tersebut;
- planner yang terikat kepada manifest diluluskan
  `d816998148fbbe06e3a7545b5a1caa06f0933e0dab602c61f455fc875d466aac`;
- CLI serta Administrator Preview tanpa endpoint Apply; dan
- characterization untuk completeness, original-metadata protection,
  quarantine protection, stale manifest, plan hash dan rollback readiness.

Planner menetapkan `can_apply=false`, `live_apply_authorized=false` dan
`mutation_statements=0`. Live Bulk Apply tidak diimplementasikan.

Preview local selepas implementation mengesan perubahan kandungan yang berlaku
selepas approval manifest. Baseline semasa berubah daripada `2` kepada `8`
existing translations, menyebabkan calon translation baharu berubah daripada
`36` kepada `33` dan proper noun/invariant daripada `6` kepada `3`. Oleh itu
Preview disekat dengan:

- `ML7A_APPROVED_MANIFEST_DIGEST_MISMATCH`; dan
- `ML7A_APPROVED_DECISION_COUNT_MISMATCH`.

Blocked plan hash semasa ialah
`5f42a2584a2033c2ac6ab9d11dee30297c4df1941f8390868c754ecbe04bf191`.
Ia merupakan evidence fail-closed sahaja dan tidak boleh digunakan untuk
authorization Apply. Sebelum live Apply boleh dipertimbangkan, inventory semasa
mesti disemak semula dan manifest baharu memerlukan explicit owner approval.

### 18.3 ML7A revised content-owner approval

Firdaus, System Analyst dan System Analyst/DBA meluluskan revised manifest
`6c4524393cd86fdab4beaa76e88feb63f24e6691b191457e044408e3446eb444`
melalui evidence `ONEID-ML7A-REVISED-LOCAL-20260725-01`.

Revised decisions ialah:

- existing approved English translations `8`: ACCEPT;
- translation-required drafts `33`: ACCEPT;
- proper noun/invariant `3`: ACCEPT AS INVARIANT;
- archived records `32`: ACCEPT INTENTIONALLY FALLBACK; dan
- test/problematic records `8`: EXCLUDE / QUARANTINE.

Existing translations E-PTW System, Celik Madani (ASNB), HR, Student, Non SSO,
Support, Finance dan UPNM30 diterima. Unresolved dan duplicate identity kekal
`0`. Automatic machine approval tidak dibenarkan dan metadata asal mesti kekal
tidak berubah.

Approval ini membenarkan planner dibekukan kepada revised digest sahaja.
Schema activation dan live Bulk Apply masih tidak dibenarkan. Exact Bulk Apply
memerlukan authorization berasingan.

Selepas planner dibekukan, dua zero-mutation Preview berturut-turut adalah
identik dan menghasilkan:

- status `ML7A_BULK_PREVIEW_READY`;
- blocking codes `0`;
- review decision inserts `84`;
- translation inserts `33`;
- translation history inserts `33`;
- original metadata updates `0`;
- quarantine translation inserts `0`;
- mutation statements `0`; dan
- plan hash
  `3ade2d6bf970c2f87c9f6889cf5584c6d06c7ab66da62c5956681941d8c8c664`.

Plan hash ini ialah candidate exact plan untuk authorization seterusnya. Ia
belum membenarkan schema activation atau Apply.

### 18.4 ML7A controlled local review schema activation

Additive review schema diaktifkan dalam approved change window
`25 Julai 2026, 9:25 PM–9:55 PM MYT` melalui
`ONEID-ML7A-SCHEMA-LOCAL-20260725-01` dan backup reference
`ONEID-LOCAL-BACKUP-20260725-04`.

Preflight mengesahkan table count `0`, plan hash tepat
`3ade2d6bf970c2f87c9f6889cf5584c6d06c7ab66da62c5956681941d8c8c664`
dan blocking codes `0`. Migration hanya mencipta
`metadata_content_review`.

Post-activation verification:

- table exists: PASS;
- columns: `13`;
- indexes: `3`, termasuk unique `entity_type + entity_id + locale`;
- CHECK constraints: `4`;
- initial review rows: `0`;
- translation inserts: `0`;
- original metadata mutation: `0`;
- `sp_list` checksum kekal
  `6f0df57e238fc4ee8bb6a625a038eb894008721b7d468f2b0b803c80494ca21b`;
- `sp_group` checksum kekal
  `f72b03bbeff10c0d22a557735391ff6638d71e507ac7f43f76f41e2074dd7bf8`;
  dan
- down migration tersedia dan hanya membuang jadual additive: PASS.

Schema activation tidak memasukkan review decision atau translation. Live Bulk
Apply masih tidak dibenarkan.

### 18.5 ML7A controlled local Bulk Apply

Exact Bulk Apply dilaksanakan dalam approved change window
`25 Julai 2026, 9:35 PM–10:05 PM MYT` melalui
`ONEID-ML7A-BULK-LOCAL-20260725-02` dan backup reference
`ONEID-LOCAL-BACKUP-20260725-05`.

Preflight mengesahkan manifest
`6c4524393cd86fdab4beaa76e88feb63f24e6691b191457e044408e3446eb444`,
plan hash
`3ade2d6bf970c2f87c9f6889cf5584c6d06c7ab66da62c5956681941d8c8c664`,
English translations `8`, review rows `0` dan blocking codes `0`.

Transactional result:

- status `ML7A_BULK_APPLY_COMMITTED`;
- review decisions inserted `84`;
- English translations inserted `33`;
- translation history inserted `33`;
- existing translation updates `0`;
- original metadata updates `0`; dan
- quarantine translation inserts `0`.

Post-Apply reconciliation:

- English translations `41`;
- review decisions `84`;
- approved completeness `100%`;
- pending owner review `0`;
- accepted translation rows present `33`;
- quarantine translation rows present `0`;
- bulk history rows `33`;
- `sp_list` checksum kekal
  `6f0df57e238fc4ee8bb6a625a038eb894008721b7d468f2b0b803c80494ca21b`;
- `sp_group` checksum kekal
  `f72b03bbeff10c0d22a557735391ff6638d71e507ac7f43f76f41e2074dd7bf8`;
  dan
- replay Apply disekat oleh baseline/manifest fail-closed checks.

Pre-Apply plan menjadi tidak boleh dimainkan semula selepas commit. Post-Apply
read-only manifest digest ialah
`23903f9a50d6f29287927b3b0a6de6a21a8699f7663328b1ce7f925d1f390d74`.

### 18.6 ML7A local observation dan closure

Firdaus, System Analyst/DBA melaksanakan local observation pada User Dashboard
dan Administrator Dashboard. Evidence
`ONEID-ML7A-BULK-LOCAL-20260725-02` mengesahkan:

- content completeness `100%`;
- review decisions `84`;
- English translation inserts `33`;
- existing English translations retained `8`;
- translation audit history `33`;
- English application/category metadata dan localized search: PASS;
- BM original metadata serta fallback: PASS;
- intentional fallback dan proper noun/invariant handling: PASS;
- quarantined records excluded: PASS;
- Administrator completeness display: PASS;
- original metadata checksum unchanged: PASS;
- ACL, URL, SSO dan category assignment regression: PASS;
- rollback readiness: PASS; dan
- critical atau security defects `0`.

Keputusan: **ML7A PASS / CLOSED**.

Closure ini terhad kepada Local WSL. Git push, staging deployment dan production
rollout tidak dibenarkan oleh authorization ML7A ini.

## 19. Status Authoritative dan Future Work

Bahagian ini menggantikan status ringkas atau ayat `belum` daripada baseline
sejarah apabila menentukan readiness semasa. Evidence dan boundary setiap fasa
kekal dirujuk kepada seksyen pelaksanaan masing-masing.

### 19.1 Reconciliation status

| Fasa | Disposition Local WSL | Evidence / catatan |
|---|---|---|
| ML0 | PASS / CLOSED | `ONEID-ML0-20260725-01` |
| ML1 | PASS — infrastructure dan schema local applied | `ONEID-ML1-UAT-20260725-01` |
| ML2 / ML3 | PASS / CLOSED | Skop Login, Recovery, OTP UI/e-mel dan public authentication digabungkan di bawah authorization ML2; `ONEID-ML2-LOCAL-20260725-02` |
| ML4 | PASS / CLOSED | User Dashboard; `ONEID-ML45-LOCAL-20260725-01` |
| ML5 | PASS / CLOSED mengikut authorized scope | Administrator Dashboard; External Sync dan Admin Step-Up dikecualikan secara explicit |
| ML6 | PASS / CLOSED | API/AJAX, e-mel dan notification; `ONEID-ML6-LOCAL-20260725-01` |
| ML7 | PASS / CLOSED | Schema dan controlled metadata; `ONEID-ML7-LOCAL-20260725-02` |
| ML7A | PASS / CLOSED | Content completeness `100%`; `ONEID-ML7A-BULK-LOCAL-20260725-02` |
| ML8A | PASS / CLOSED | Inventory `149` identities; `ONEID-ML8A-LOCAL-20260725-01` |
| ML8B | PASS / CLOSED | Shared `8` FAQ BM/English dan Login multilingual audit; `ONEID-ML8B-LOCAL-20260725-01` |
| ML8C | VERSION RELEASE ACTIVATION PASS / CLOSED | `37/37` releases and `217/217` items; `ONEID-ML8C-ACTIVATE-LOCAL-20260725-01` |
| ML8D | NOT REQUIRED / ABSORBED BY ML8C | Controlled activation dan observation telah selesai dalam ML8C; English User Manual deferred by owner |
| External Sync multilingual | PASS / CLOSED | Parent/child modal, Summary, Staff, UG dan ODL; `ONEID-ML-EXTSYNC-LOCAL-20260726-01` |
| Admin Step-Up multilingual | PASS / CLOSED | Challenge, e-mel OTP, TOTP, enrollment/reset dan purpose presentation; `ONEID-ML-STEPUP-LOCAL-20260726-01` |
| Administrator Multilingual Completeness | PASS / CLOSED | Active Sessions, Audit Log, Sync Audit, Configuration dan category user list; `ONEID-ML-ADMIN-COMPLETE-LOCAL-20260726-01` |
| ML9 | FUTURE WORK | Comprehensive UAT, controlled rollout, monitoring, staging dan Production |

ML3 tidak hilang atau belum dibuat. Aktiviti asal ML3 dilaksanakan dan diuji
di bawah ML2 Pilot authorization. Crosswalk ini digunakan untuk mengelakkan
duplikasi implementation dan evidence.

### 19.2 Implemented scope yang telah disahkan

- BM ialah default dan hard fallback; English ialah secondary locale.
- Guest cookie, session locale dan authenticated preference menggunakan
  allowlist serta precedence ML0.
- Login, Password Recovery, OTP UI/e-mel, User Dashboard dan authorized
  Administrator surfaces mempunyai BM/English catalogue.
- API/AJAX aktif dalam skop ML6 mempunyai stable response mapping; unresolved
  active mappings ialah `0`.
- Legacy `msg` dikekalkan sepanjang compatibility window.
- Metadata aplikasi/kategori menggunakan translation tables additive,
  original fallback, audit dan optimistic concurrency.
- ML7A merekod `84` explicit review decisions, `33` translation inserts,
  `33` history records dan content completeness `100%`.
- Technical identifier, audit code, correlation ID, source code, plan hash dan
  exact confirmation kekal invariant.
- Authentication, authorization, ACL, URL, SSO dan session lifetime tidak
  diubah oleh multilingual implementation.
- Active Sessions, Audit Log, Sync Audit, Configuration dan category user list
  telah dilengkapkan dengan label serta dynamic state BM/English. Observation
  BM/English, External Sync, Admin Step-Up dan authentication/authorization/ACL
  regression semuanya PASS; mixed-language critical defects ialah `0`.

### 19.3 Future work dan skop yang masih menunggu closure

#### FW-ML01 — External Sync multilingual

Implementation Local WSL telah disiapkan di bawah
`ONEID-ML-EXTSYNC-LOCAL-20260726-01`. External Sync Summary serta Staff,
Undergraduate dan ODL Preview/Apply kini menggunakan katalog BM/English untuk
static copy, dynamic state, blocked result, notification dan post-Apply
presentation. Exact confirmation diterima daripada server, dibanding dan
dihantar semula tanpa translation. Source code, action payload, counts, plan
hash, Preview digest, approval dan audit identifier kekal invariant.

Observation owner `ONEID-ML-EXTSYNC-LOCAL-20260726-01` mengesahkan BM dan
English presentation, Summary read-only, Staff/UG/ODL Preview/Apply,
warning/blocked/error, post-Apply dan audit feedback semuanya PASS. Exact
confirmation kekal canonical; source isolation dan ACL regression PASS;
mixed-language critical defects ialah `0`.

Status: **PASS / CLOSED** pada Local WSL. Tiada Git push, staging atau
Production dibenarkan oleh authorization ini.

#### FW-ML02 — Admin Step-Up multilingual

Implementation Local WSL telah disiapkan di bawah
`ONEID-ML-STEPUP-LOCAL-20260726-01`. Challenge page, e-mel OTP, TOTP,
enrollment/reset, purpose-specific guidance, error/correlation feedback dan
return presentation kini menggunakan katalog BM/English.

Purpose dan factor code, OTP, challenge/grant/correlation identifier, exact
bootstrap confirmation, security event code dan return allowlist kekal
canonical. Grant purpose, lifetime, retry, lockout, rate limit,
authentication, authorization, ACL serta session handling tidak berubah.

Observation owner `ONEID-ML-STEPUP-LOCAL-20260726-01` mengesahkan BM dan
English challenge presentation, purpose-specific explanation, e-mel OTP,
TOTP, invalid/reused/expired OTP, cooldown/rate-limit, enrollment/reset, OTP
security e-mel, locale persistence, fallback dan validated return flow
semuanya PASS. Exact confirmation kekal canonical;
authentication/authorization/ACL regression PASS; critical atau security
defects ialah `0`.

Status: **PASS / CLOSED** pada Local WSL. Tiada Git push, staging atau
Production dibenarkan.

#### FW-ML03 — ML8 support content disposition

Skop pengguna aktif ML8 telah direconcile seperti berikut:

- ML8A inventory/contract: PASS / CLOSED;
- ML8B shared FAQ dan Login multilingual audit: PASS / CLOSED;
- ML8C Version Releases `37/37`, changelog `217/217`, dua policy/design
  documents dan controlled Local activation: PASS / CLOSED;
- English User Manual: DEFERRED BY OWNER / NOT A CURRENT BLOCKER;
- `MANUAL_SALAM.pdf` BM kekal authoritative dengan explicit English
  availability notice dan tanpa silent fallback; dan
- `132` internal technical documents kekal canonical/invariant.

Sebelas release Markdown, help-desk glossary dan communication template bukan
active user-facing blocker dalam current scope. Ia boleh dinaik taraf secara
selective melalui approval baharu apabila owner memerlukannya. ML8D berasingan
tidak lagi diperlukan kerana activation dan observation telah diserap serta
ditutup di bawah ML8C.

#### FW-ML04 — ML9 comprehensive UAT

Masih diperlukan sebelum staging atau Production:

- UAT menyeluruh untuk login, recovery, user, admin, e-mel, mobile dan
  accessibility;
- locale switch ketika form dirty, request aktif, modal mempunyai input dan
  session/Step-Up tamat;
- cache isolation, direct URL, timeout dan fallback verification;
- pilot/observation menggunakan feature gate yang diluluskan;
- monitoring missing key, mixed-language presentation dan operational error;
  dan
- rollback kepada BM tanpa memadam preference atau translation data.

#### FW-ML05 — Staging dan Production rollout

Semua closure ML1 hingga ML8C dalam dokumen ini terhad kepada Local WSL.
Git push, staging migration/activation dan Production rollout memerlukan
authorization, backup, change window, deployment evidence serta post-deployment
observation yang baharu.

#### FW-ML06 — Legacy compatibility retirement

Legacy `msg` bukan defect semasa dan tidak menyekat Local WSL closure. Ia hanya
boleh dikeluarkan selepas semua active response code mempunyai mapping, semua
consumer dipindahkan, regression lulus, satu release observation selesai dan
change approval berasingan diterima.

### 19.4 Current gate

Keputusan semasa ialah:

- **Local WSL implemented scope: PASS / CLOSED**;
- **Full multilingual programme: OPEN — FUTURE WORK REMAINS**;
- **Staging: NOT AUTHORIZED**; dan
- **Production: NOT AUTHORIZED**.

Fasa baharu tidak boleh dianggap implied oleh closure Local WSL. Setiap future
work di Bahagian 19.3 memerlukan scope, owner, security boundary, rollback dan
authorization tersendiri.

## 20. ML8A Inventory and Document Contract

ML8A dilaksanakan secara read-only di Local WSL pada 25 Julai 2026. Full
inventory dan owner decision register berada dalam
`docs/ML8A_INVENTORY_AND_DOCUMENT_CONTRACT.md`.

Evidence:

- manifest identities `149`;
- public document `1`;
- FAQ surfaces `2`, masing-masing mempunyai `8` entries;
- active Administrator release UI `1` dengan `37` release entries;
- release Markdown documents `11`;
- policy/design documents `2`;
- internal technical documents `132`;
- translation/fallback/review backlog items `17`;
- duplicate identities `0`;
- missing targets `0`;
- blocking codes `0`;
- automatic translation disabled;
- mutation statements `0`;
- manifest digest
  `598e46cbb5e55fe72ae227be70fba7f7b2f59d9ed2ca6c966a7e35797fb66530`;
  dan
- ML8A contract/characterization: PASS.

Owner approval `ONEID-ML8A-LOCAL-20260725-01` menetapkan:

- `132` internal technical documents sebagai canonical/invariant;
- satu shared source untuk `8` FAQ BM/English pada Login dan User Dashboard;
- explicit BM fallback untuk missing English FAQ;
- BM `MANUAL_SALAM.pdf` kekal authoritative dengan explicit English notice
  sehingga English PDF diluluskan;
- semua `37` active release entries wajib bilingual;
- Version Numbering Policy dan Email Design Standard wajib bilingual; dan
- automatic machine approval tidak dibenarkan.

Status: **ML8A PASS / CLOSED**.

ML8B shared FAQ delivery telah di-authorize dan ditutup melalui
`ONEID-ML8B-LOCAL-20260725-01`. Ia menggunakan satu source dengan `8` identiti
FAQ yang sama untuk Login dan User Dashboard, parity BM/English `8/8`, serta
explicit BM fallback notice. Observation turut mengesahkan Login navigation,
manual notice, contact/support information, loading, login-attempt feedback,
accessibility dan security regression. Critical atau security defects ialah
`0`. **ML8B PASS / CLOSED** pada Local WSL. Pada gate tersebut ML8C/ML8D tidak
dibenarkan secara implied; ML8C kemudiannya menerima authorization berasingan,
activation dan observation sehingga PASS / CLOSED. ML8D tidak lagi diperlukan.

## 21. ML8C Bilingual Content Preview

ML8C di-authorize melalui `ONEID-ML8C-LOCAL-20260725-01` untuk Local WSL,
implementation dan Preview sahaja. Implementation berada dalam
`docs/ML8C_BILINGUAL_CONTENT_PREVIEW.md`.

Current disposition:

- authoritative BM manual `1`;
- approved English manual `0`;
- English manual review source tersedia tetapi belum diterbitkan;
- active Version Release identities `37`;
- canonical BM release change items `217`;
- English release summaries `37`, semuanya `REVIEW_REQUIRED`;
- approved English release entries `0`;
- duplicate/unresolved release identities `0`;
- dua policy/design documents mempunyai bahagian BM dan English;
- explicit fallback untuk manual dan release English;
- automatic translation approval disabled;
- Apply dan English PDF publication disabled; dan
- mutation statements `0`.

Read-only manifest digest:
`c00a94b674f9d8e0bff4007a9bb26afd75771b39b789d0bd77485f4507323086`.

Owner telah meluluskan manifest
`c00a94b674f9d8e0bff4007a9bb26afd75771b39b789d0bd77485f4507323086`
melalui `ONEID-ML8C-LOCAL-20260725-01`. Semua `37` stable identities serta
version/date diterima sebagai invariant. `37` English summaries diterima untuk
content development, tetapi bukan full changelog parity; semua `217` English
changelog items kekal `REVIEW_REQUIRED`.

BM manual kekal authoritative, English outline diterima untuk development dan
English PDF publication tidak diluluskan. Dua polisi bilingual diterima,
manakala code, commands, version, OTP values dan technical identifiers kekal
invariant.

Historical ML8C gate selepas manifest approval:
**FULL ENGLISH PARITY REQUIRED**. Pada ketika itu live locale activation masih
belum dibenarkan.

Full draft development seterusnya menghasilkan `217/217` BM/English item pairs
dengan empty/source/duplicate/HTML/code-token mismatch semuanya `0`. Semua item
kekal `REVIEW_REQUIRED`; ini membuktikan structural parity sahaja, bukan
linguistic approval. Draft manifest digest ialah
`908b16565a1ea5a676b636bee543bbd384564add1a8fb6a6fd65884efa8125f8`.

Historical gate selepas full draft:
**FULL ENGLISH DRAFT READY / OWNER REVIEW REQUIRED**.

Owner kemudiannya meluluskan semua `217` English changelog items melalui
`ONEID-ML8C-CHANGELOG-LOCAL-20260725-01`, terikat kepada digest
`908b16565a1ea5a676b636bee543bbd384564add1a8fb6a6fd65884efa8125f8`.
Dormant approved repository pada gate Preview membuktikan parity release
`37/37`, item `217/217` dan invariant identity/version/date. Pada ketika itu
repository belum dirujuk oleh `admin/dashboard.php`; activation seterusnya
memerlukan authorization berasingan.

Exact Local WSL activation kemudiannya diluluskan melalui
`ONEID-ML8C-ACTIVATE-LOCAL-20260725-01`. Administrator Version Releases kini
memilih canonical BM untuk `ms` dan exact approved catalogue untuk `en`.
Heading/current/latest/date label adalah locale-aware. Jika digest, count atau
catalogue tidak sah, English diblock sepenuhnya dan canonical BM dipaparkan
dengan explicit notice; partial English tidak dibenarkan.

Historical gate selepas controlled activation:
**OBSERVATION REQUIRED**. English User Manual publication, Git push, staging dan
Production kekal tidak dibenarkan.

Observation `ONEID-ML8C-ACTIVATE-LOCAL-20260725-01` mengesahkan BM/English
display, parity `37/37` releases dan `217/217` changelog items, invariant
version/date, localized labels/date, preference persistence, accordion,
accessibility serta canonical BM fallback semuanya PASS. Partial English ialah
`0`, original BM tidak berubah, security regression dan boundary External
Sync/Admin Step-Up PASS, serta critical/security defects `0`.

Status: **ML8C VERSION RELEASE ACTIVATION PASS / CLOSED**.

English User Manual kekal deferred. Git push, staging dan Production kekal
tidak dibenarkan.

Owner mengesahkan bahawa kandungan dan PDF English User Manual akan disediakan
kemudian oleh team berkaitan dan tidak termasuk dalam current multilingual
scope. `MANUAL_SALAM.pdf` kekal satu-satunya manual rasmi/authoritative.
Pengguna locale English menerima explicit availability notice sebelum manual
BM diberikan; silent fallback dan placeholder/machine-generated English PDF
tidak dibenarkan.

Disposition: **ENGLISH USER MANUAL DEFERRED BY OWNER / NOT A CURRENT
BLOCKER**. Sebarang pembangunan atau publication English manual pada masa depan
memerlukan content review dan authorization baharu.

## 22. Pre-ML9 Reconciliation — 26 Julai 2026

Audit reconciliation penuh telah dijalankan selepas closure External Sync,
Admin Step-Up dan Administrator Multilingual Completeness. Status ML0 hingga
ML8C, evidence reference, document inventory, catalogue parity dan security
boundary disemak semula.

Dokumen gate sejarah ML1 dan ML8C kini mempunyai nota status semasa supaya label
lama seperti `DORMANT`, `REVIEW_REQUIRED` atau `OBSERVATION REQUIRED` tidak
disalah tafsir sebagai blocker aktif. Approved historical digest tidak diubah;
current read-only inventory direkod secara berasingan.

Keputusan: **READY TO REQUEST ML9 AUTHORIZATION**.

Rujukan lengkap:
`docs/MULTILANGUAGE_PRE_ML9_RECONCILIATION_20260726.md`.

## 23. ML9A Release Baseline dan Pre-Push Verification

Owner meluluskan bilingual release `v2.6.3` melalui
`ONEID-ML9A-RELEASE-20260726-01`. Exact content digest ialah
`e5bf274cb52098d0ea3e82771688b4feaaf8bd022dcd71dc96fdcea4d49c23ee`.

Canonical identity `release:2.6.3` dan approved catalogue `38/38` release serta
`229/229` BM/English changelog items diikat kepada digest:

`1eba6fbee555b918adab56366b5bc28f5c4b963c1663c0c3782c9f32d0f5de66`.

Authorization ML9A membenarkan release activation, Git commit dan push sahaja.
Staging migration, staging activation dan Production kekal belum dibenarkan.
