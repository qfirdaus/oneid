# Audit dan Pelan Pelaksanaan Multi-Language BM–English

**Tarikh audit:** 17 Julai 2026
**Status:** PROPOSED / NOT IMPLEMENTED
**Bahasa sasaran:** Bahasa Melayu (`ms`) dan English (`en`)
**Default dicadangkan:** Bahasa Melayu (`ms`)
**Skop:** Login, dashboard pengguna, dashboard Administrator, API/AJAX,
operational feedback, e-mel, accessibility, kandungan pangkalan data, dokumen
bantuan, audit dan operasi

## 1. Objektif

Mewujudkan sokongan dua bahasa yang konsisten, selamat dan boleh diselenggara
tanpa menukar business rule, authorization, audit code atau data teknikal OneID.
Pelaksanaan bukan sekadar pertukaran teks pada browser; locale perlu menjadi
sebahagian daripada presentation contract bagi server, JavaScript dan e-mel.

Dokumen ini hanya merekod audit, keputusan reka bentuk dan urutan pelaksanaan.
Tiada perubahan kod, schema, setting atau UI dibuat dalam fasa dokumentasi ini.

## 2. Hasil Audit Semasa

### ML-A01 — Tiada translation layer pusat

Sistem belum mempunyai locale resolver, translation catalogue, helper
translation, fallback locale atau preference bahasa pengguna. Teks ditulis
terus dalam PHP dan JavaScript.

### ML-A02 — Bahasa UI bercampur

Login, dashboard pengguna dan dashboard Administrator menggunakan campuran BM
dan English. SweetAlert, toast, loading state, empty state, validation dan
confirmation turut tidak menggunakan satu language contract yang konsisten.

### ML-A03 — Metadata dokumen tidak tepat

`index.php`, `page/dashboard.php` dan `admin/dashboard.php` menggunakan
`<html lang="en">` walaupun banyak kandungan ialah BM. Ini menjejaskan screen
reader, accessibility dan interpretasi bahasa oleh browser.

### ML-A04 — Surface terjemahan besar

Baseline awal menemui kira-kira 705 lokasi teks paparan/mesej dalam login,
dashboard pengguna, dashboard Administrator, `lib/q_func.php` dan service
berkaitan. Repository mempunyai kira-kira 229 fail PHP first-party, tetapi
bukan semuanya menghasilkan UI. Inventori penuh masih perlu dihasilkan dalam
ML0 dan angka ini tidak boleh dianggap sebagai acceptance count muktamad.

### ML-A05 — Backend message bercampur dengan machine response

Sebahagian JSON response memulangkan stable `code` bersama teks `msg` atau
`message` hardcoded. Machine code, correlation ID dan audit event mesti kekal
invariant; presentation message perlu dipetakan kepada translation key.

### ML-A06 — JavaScript mempunyai teks dinamik hardcoded

Sebahagian label, confirmation, error, carian, loading dan empty state dibina
semasa runtime. Menterjemah HTML sahaja akan meninggalkan mixed-language UI.

### ML-A07 — E-mel belum locale-aware

Password Recovery, OTP, ujian penghantaran dan notifikasi keselamatan belum
mempunyai template BM/English serta aturan pemilihan locale penerima.

### ML-A08 — Kandungan pangkalan data bukan translation catalogue

Nama/deskripsi aplikasi dan nama kategori ialah kandungan yang dimasukkan oleh
admin. Ia tidak patut diterjemahkan secara automatik atau disamakan dengan label
UI. Sokongan metadata bilingual memerlukan kontrak dan schema berasingan.

### ML-A09 — Dokumen bantuan berada di luar UI

Manual, FAQ, release notes, polisi dan PDF tidak menjadi bilingual hanya kerana
UI diterjemahkan. Availability setiap dokumen mengikut locale perlu jelas.

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
| Simpan | Save |
| Batal | Cancel |

Glossary perlu disahkan owner dan digunakan oleh UI, e-mel, manual serta help
desk. Istilah rasmi UPNM tidak diterjemahkan tanpa kelulusan content owner.

## 5. Pelan Pelaksanaan Berfasa

### ML0 — Baseline, owner decision dan language contract

**Aktiviti:**

- inventori semua server-rendered, JavaScript, API, e-mel dan dokumen surface;
- tetapkan BM sebagai default, English sebagai pilihan dan fallback BM;
- sahkan precedence preference pengguna/session/default sistem;
- sediakan glossary dan content owner;
- petakan stable response code kepada semantic translation key;
- klasifikasikan UI text, user data, technical data dan translatable content;
- baseline mixed-language, missing key dan HTML `lang`; dan
- sediakan rollback serta compatibility contract.

**Exit criteria:** Inventori, glossary, decision register dan boundary data
telah disahkan; tiada perubahan business behavior.

### ML1 — Infrastruktur locale dan automated contracts

**Aktiviti:**

- bina locale resolver, catalogue loader dan `trans()` helper;
- tambah safe interpolation, pluralization dan fallback;
- tambah session/cookie preference dengan locale allowlist;
- jadikan `<html lang>` dinamik;
- bina JavaScript dictionary/bootstrap tanpa menyuntik HTML tidak selamat;
- pastikan cache locale-aware; dan
- tambah contract bagi parity key BM/English, missing/duplicate/empty key,
  invalid locale, escaping dan fallback.

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
- jelaskan impak default terhadap pengguna yang mempunyai preference; dan
- pastikan perubahan default tidak memerlukan logout.

**Exit criteria:** Admin boleh menetapkan default; pengguna boleh override;
preference kekal selepas refresh/login dan invalid locale ditolak.

### ML3 — Pilot public authentication dan Password Recovery

**Aktiviti:**

- terjemah login, validation inline, loading dan authentication failure;
- terjemah Forgot Password, OTP dan reset password;
- sediakan template e-mel BM/English dengan subject/body/footer sepadan;
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

1. Web Apps;
2. User Account dan ACL;
3. Active Sessions;
4. Audit Log;
5. Sync Log;
6. Configuration;
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
- user/admin static dan dynamic state tidak bercampur bahasa;
- plural, tarikh dan masa dipaparkan dengan betul;
- direct URL, AJAX timeout dan error path mempunyai translation;
- correlation ID, code, App ID dan audit evidence kekal invariant;
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

## 9. Perkara yang Perlu Disahkan Owner Sebelum ML0 Ditutup

1. BM (`ms`) sebagai default dan hard fallback.
2. English (`en`) sebagai bahasa pilihan kedua.
3. Pengguna boleh override default organisasi.
4. Language switcher sentiasa tersedia selepas rollout stabil.
5. Lokasi preference authenticated user dan retention cookie guest.
6. Glossary rasmi serta content owner BM/English.
7. Sama ada metadata aplikasi/kategori bilingual diperlukan dalam ML7.
8. Dokumen/PDF mana yang wajib tersedia dalam kedua-dua bahasa.
9. Pilot group, maintenance window dan observation period.
10. Compatibility window sebelum legacy API `msg` boleh dikurangkan.

## 10. Cadangan Titik Mula

Mulakan dengan **ML0 — Baseline, owner decision dan language contract**. Jangan
terus menggantikan teks di seluruh sistem. Selepas catalogue/helper dan security
contract ML1 stabil, gunakan login dan Password Recovery sebagai pilot ML3.
Dashboard pengguna dan setiap modul Administrator hanya diterjemahkan selepas
pilot membuktikan locale, fallback, e-mel dan operational feedback berfungsi
secara konsisten.
