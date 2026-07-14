# Fasa 2 — Pelaksanaan dan Rollback OneID-UAT

**Tarikh pelaksanaan:** 13 Julai 2026  
**Rujukan:** `docs/PELAN_REMEDIASI_BERFASA_ONEID_UAT.md`  
**Skop:** Error handling, konfigurasi URL environment, security headers dan pembuangan debug log frontend aktif.

## 1. Objektif Fasa 2

Fasa ini bertujuan untuk:

- Menghentikan pendedahan exception dan error dalaman kepada browser.
- Menghapuskan hardcoded URL daripada flow aktif.
- Menyediakan environment override tanpa memutuskan local runtime.
- Menambah security headers berisiko rendah.
- Memulakan CSP dalam report-only mode.
- Membuang frontend debug log yang boleh mengandungi password, OTP, token atau data pengguna.

Fasa ini tidak memindahkan secrets database/SMTP. Kerja tersebut kekal dalam Fasa 3 kerana memerlukan rotation dan koordinasi consumer.

## 2. Baseline Sebelum Perubahan

### 2.1 Checksum

| Fail | SHA-256 sebelum Fasa 2 |
|---|---|
| `lib/config.php` | `4187c11bf19a91d3edf1b564005d5163ad7251a0d1d8f48d0aaa7e8adf60bddb` |
| `lib/Database.php` | `ee070c66f6e402c15bb1ca4c0674a16d4a5265cc5e234e6ee1fe234922d8bce6` |
| `lib/SSO_IDP_INC.php` | `685c2c83007a70297eaeb68a2185db9735498fb39c7e1ff81b928e9baeaf452b` |
| `page/logout.php` | `7eb6a3f5a17565d02f7dff8d95f83b3cb730a96b2e8d33629d57e5431103e1ee` |
| `admin/logout.php` | `747b202bdc63f335d8eae6d06c9e59c4eda1573a67c3780de0b1311cd2b44ee3` |
| `index.php` | `9c93ee8e9af0421aaaabca7b14d83367e6621506385a9be1ce0f74c42ce4cfd1` |
| `page/dashboard.php` | `b0c36a31dc661972e8b8dd8adbed7e8a07ea5adfe66bc6f5f1bbf2ab5f204986` |
| `admin/dashboard.php` | `102e368b69070185533bed5f479c5d73fe9e2b58592148b579c3db8afacc7ccc` |
| `.htaccess` | `1a4d071d274c90ac430df9ee2c2bc097dbab0d685c307cbe2b7cd9c3918d5d62` |

### 2.2 Debug log frontend

| Page aktif | Bilangan `console.log()` sebelum Fasa 2 |
|---|---:|
| `index.php` | 6 |
| `page/dashboard.php` | 18 |
| `admin/dashboard.php` | 53 |
| **Jumlah** | **77** |

Antara data yang boleh masuk ke console ialah serialized login form yang mengandungi password, OTP yang ditaip, AJAX response, user data dan diagnostic state.

## 3. Perubahan yang Dilaksanakan

### 3.1 Environment URL configuration

Fail: `lib/config.php`

Dua environment variable diperkenalkan:

| Variable | Default | Fungsi |
|---|---|---|
| `ONEID_APP_URL` | `https://oneid.local` | Base URL untuk login, dashboard, API callback dan logout |
| `ONEID_APP_DEBUG` | `false` | Mengawal `display_errors` |

Behavior:

- URL dibuang trailing slash sebelum disimpan sebagai `APP_URL`.
- Nilai kosong atau URL tidak sah menggunakan fallback `https://oneid.local`.
- `SSO_IDP_DOMAIN` dibina daripada `APP_URL`.
- `SSO_SP_DASHBOARD` dibina daripada `APP_URL`.
- Fallback memastikan local runtime semasa tidak terputus jika environment variable belum ditetapkan.

Contoh konfigurasi UAT:

```text
ONEID_APP_URL=https://oneid-uat.example.edu.my
ONEID_APP_DEBUG=false
```

Nilai domain di atas hanyalah contoh. Gunakan domain UAT sebenar semasa deployment.

### 3.2 Active SSO dan logout menggunakan centralized URL

Fail yang dikemas kini:

- `lib/SSO_IDP_INC.php`
- `page/logout.php`
- `admin/logout.php`

Perubahan:

- SSO include tidak lagi menetapkan `oneid.local` sekali lagi.
- Logout user dan admin menggunakan `SSO_IDP_DOMAIN`.
- `exit` ditambah selepas redirect logout untuk menghentikan execution dengan jelas.

Fail SSO lama/copy tidak dikemas kini kerana ia calon quarantine dan bukan sebahagian flow aktif.

### 3.3 Apache HTTPS redirect tidak lagi hardcoded

Fail: `.htaccess`

Redirect berubah daripada domain literal kepada `%{SERVER_NAME}`. Apache vhost perlu mempunyai `ServerName` yang betul bagi setiap environment.

Runtime lokal menggunakan Nginx dan tidak membaca `.htaccess`; HTTP-to-HTTPS redirect lokal kekal dikendalikan oleh vhost Nginx.

### 3.4 Error handling selamat

Fail:

- `lib/config.php`
- `lib/Database.php`

Perubahan:

- `display_errors` default kepada off.
- `log_errors` diaktifkan.
- `error_reporting(E_ALL)` dikekalkan untuk server-side logging.
- Exception handler didaftarkan sebelum sambungan database dibuat.
- Exception penuh direkodkan melalui server error log.
- Browser hanya menerima `Internal server error`.
- API/AJAX request menerima JSON generik:

```json
{"error":"Internal server error"}
```

- Database constructor tidak lagi melakukan silent `exit()`.
- Database connection failure dilog dan ditukar kepada `RuntimeException` generik.

Tiada credential atau exception detail dipulangkan kepada browser.

### 3.5 Security headers

Header berikut ditambah pada PHP response yang melalui `lib/config.php`:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- `Content-Security-Policy-Report-Only`

CSP diletakkan dalam report-only mode kerana aplikasi mempunyai banyak inline script/style dan dependency legacy. Polisi ini belum menyekat resource; ia digunakan untuk mengenal pasti violation sebelum enforcement.

CSP semasa masih membenarkan `'unsafe-inline'` dan `'unsafe-eval'` bagi compatibility. Kedua-duanya perlu dibuang secara berperingkat selepas inline JavaScript dipindahkan dan dependency legacy disemak.

### 3.6 HSTS ditangguhkan

`Strict-Transport-Security` belum diaktifkan kerana perkara berikut perlu disahkan dahulu:

- Semua domain/subdomain berkaitan menggunakan HTTPS.
- Certificate chain production/UAT sah.
- Tiada consumer lama yang masih bergantung kepada HTTP.
- Tempoh `max-age` dan pilihan `includeSubDomains` telah diluluskan.

### 3.7 Debug log frontend dibuang

Semua baris `console.log()` dibuang daripada:

- `index.php`
- `page/dashboard.php`
- `admin/dashboard.php`

Bilangan selepas Fasa 2 ialah sifar bagi ketiga-tiga page aktif. Logic aplikasi selain logging tidak diubah dalam langkah mekanikal ini.

## 4. Verification yang Dilaksanakan

### 4.1 PHP lint

- Semua 44 fail PHP first-party lulus `php -l`.
- Tiada syntax error ditemui.

### 4.2 Environment override

Ujian dengan nilai sementara:

```text
ONEID_APP_URL=https://uat-oneid.example.test/base/
```

Menghasilkan:

```text
APP_URL=https://uat-oneid.example.test/base
SSO_IDP_DOMAIN=https://uat-oneid.example.test/base/
SSO_SP_DASHBOARD=https://uat-oneid.example.test/base/page/dashboard
display_errors=0
```

### 4.3 HTTP security headers

`GET https://oneid.local/` memberikan HTTP `200` bersama semua header Fasa 2:

- `nosniff`
- `SAMEORIGIN`
- Referrer policy
- Permissions policy
- CSP report-only

### 4.4 Redirect

| Request | Keputusan |
|---|---|
| `http://oneid.local/` | `301` ke `https://oneid.local/` |
| `/page/logout` tanpa cookie | `302` ke `https://oneid.local/` |
| `/admin/logout` tanpa cookie | `302` ke `https://oneid.local/` |

### 4.5 Exception response

- Direct handler test memulangkan `Internal server error`.
- Exception detail tidak muncul pada response.
- Detail direkodkan ke server-side error log semasa test.

### 4.6 Runtime page

- Root login page kekal HTTP `200`.
- Response body login page berjaya dimuatkan.
- Tiada `console.log()` tinggal dalam tiga page aktif.

## 5. Checksum Selepas Fasa 2

| Fail | SHA-256 selepas Fasa 2 |
|---|---|
| `lib/config.php` | `94ff398076f3c4d4142841fcb9a4501cced7255930e01ac214dfe23e5b0bc746` |
| `lib/Database.php` | `5a007d59f2be413a1621600c70da2fce1e1f35b14ba0e72a882cea887fa5d58d` |
| `lib/SSO_IDP_INC.php` | `ef6f546a8c9d5a4ca7faddc68099edd10a3e2b635c9f1769e46cf60d5a8751dd` |
| `page/logout.php` | `26a3a2d8e20aa9fa411d95c4e4f75f52fd014bb23b18eb0042836ffe8487a765` |
| `admin/logout.php` | `26a3a2d8e20aa9fa411d95c4e4f75f52fd014bb23b18eb0042836ffe8487a765` |
| `index.php` | `f4c6baef9045cb416e45745cd4341bdd5e24e7d6ed5862b127f7420651f1fb1a` |
| `page/dashboard.php` | `0df6ed9bc63fb6832b4e673e2e112ac29460f915ce75a872a47049f29640d996` |
| `admin/dashboard.php` | `b0e092beec4d2cfb1cdae5a18c92122faa3b3a8dc8faa9d916aecb8bc587d7ae` |
| `.htaccess` | `34b4efba4af652351e0b94b1f4c399db7b29492923ea15cc725951af120d87cb` |

## 6. Manual Smoke Test yang Masih Diperlukan

- [ ] Login staf.
- [ ] Login pelajar.
- [ ] Redirect selepas login menuju environment URL yang betul.
- [ ] Dashboard user dan admin memuatkan semua resource.
- [ ] Browser console disemak untuk CSP report-only violation.
- [ ] Launch aplikasi SSO.
- [ ] Launch aplikasi non-SSO.
- [ ] Forgot password dan OTP.
- [ ] Logout dengan session sebenar.
- [ ] API consumer menerima response seperti sebelumnya apabila tiada error.
- [ ] Simulasikan database outage dalam maintenance window dan pastikan response generik serta error log lengkap.

## 7. Rollback Fasa 2

### 7.1 Environment URL

Jika redirect rosak:

1. Pastikan `ONEID_APP_URL` mempunyai scheme `https://` dan tiada typo.
2. Jika perlu rollback source, pulihkan constant `SSO_IDP_DOMAIN` dan `SSO_SP_DASHBOARD` daripada baseline.
3. Pulihkan dua assignment awal dalam `lib/SSO_IDP_INC.php`.
4. Pulihkan redirect logout hardcoded hanya sebagai rollback sementara.

### 7.2 Error handler

Jika format error mengganggu consumer:

1. Kekalkan `display_errors=0`.
2. Sesuaikan JSON detection/format tanpa memulangkan exception detail.
3. Jika perlu pulihkan constructor database, jangan gunakan silent `exit()`; pulangkan generic failure melalui mekanisme yang dipersetujui.

Rollback kepada error exposure lama tidak disyorkan.

### 7.3 Security headers

Jika resource/UI terganggu:

1. CSP kini report-only dan tidak sepatutnya menyekat resource.
2. Header boleh dibuang satu per satu daripada `lib/config.php` untuk mengenal pasti konflik.
3. `X-Frame-Options` boleh ditukar daripada `SAMEORIGIN` hanya jika ada integrasi iframe yang sah dan telah disahkan.

### 7.4 Frontend debug log

Pembuangan `console.log()` tidak mengubah business logic dan tidak memerlukan rollback. Jika developer memerlukan debugging, gunakan logging yang tidak mencetak credential, OTP, token atau full response dan kawal melalui environment flag.

### 7.5 Checksum rollback

Gunakan checksum baseline dalam Seksyen 2 untuk memastikan fail backup yang dipulihkan ialah versi sebelum Fasa 2.

## 8. Perkara yang Sengaja Ditangguhkan

- Rotation dan pemindahan database/SMTP/ODBC secrets: Fasa 3.
- Permission penuh seluruh project: Fasa 3 selepas OS ownership dikenal pasti.
- Authentication/role guard: Fasa 4.
- Cookie dan session flags: Fasa 5.
- Token SSO redesign: Fasa 5.
- HSTS: selepas HTTPS validation rasmi.
- CSP enforcement: selepas report-only observation dan pengurangan inline script.
- Hardcoded URL dalam fail legacy/copy: tunggu quarantine atau pengesahan consumer.

## 9. Exit Criteria Fasa 2

- [x] `display_errors` default off.
- [x] Exception detail hanya masuk server-side log.
- [x] Database failure tidak lagi silent exit.
- [x] Environment URL override berfungsi.
- [x] Flow SSO aktif dan logout menggunakan centralized URL.
- [x] Security headers asas hadir.
- [x] CSP berada dalam report-only mode.
- [x] 77 frontend console log dibuang daripada page aktif.
- [x] Semua PHP lint lulus.
- [x] Semua perubahan dan rollback didokumentasikan.
- [ ] Authenticated smoke test selesai.
- [ ] CSP report-only observation disemak pada browser/logging sebenar.

## 10. Cadangan Langkah Seterusnya

1. Jalankan authenticated smoke test Fasa 1 dan Fasa 2 bersama-sama.
2. Tetapkan `ONEID_APP_URL` secara eksplisit pada UAT walaupun fallback tersedia.
3. Semak CSP violation sebelum enforcement.
4. Mulakan Fasa 3 dengan inventori owner dan rotation window bagi setiap secret.

