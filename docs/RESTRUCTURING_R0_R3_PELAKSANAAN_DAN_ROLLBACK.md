# Restructuring OneID R0–R3 — Pelaksanaan dan Rollback

**Tarikh pelaksanaan:** 14 Julai 2026  
**Status:** R0–R4 lengkap; R5.0 baseline telah dimulakan.  
**Prinsip asal:** `oneid.local` dikekalkan pada root lama sehingga public-root lulus UAT penuh; R4 kemudian menutup cutover tersebut.

## 1. Keputusan Pelaksanaan

Struktur `public/` selari telah berjaya diwujudkan tanpa menukar document root Nginx `oneid.local`.

- root lama `/var/www/app/oneid-uat` masih melayan `oneid.local`;
- root baharu `/var/www/app/oneid-uat/public` boleh menjalankan entry point aktif;
- URL `/`, `/page/*`, `/admin/*`, `/api.php`, `/idms.php`, `/skp_api.php` dan `/lib/q_func` dikekalkan;
- kod dalaman, config, docs, cron, SQL dump dan diagnostic tidak tersedia melalui public-root;
- aset besar dikongsi melalui symlink peralihan supaya tiada duplicate deployment yang boleh drift;
- Nginx tidak diubah dan tiada cutover dibuat.

## 2. Skop Mengikut Fasa

### R0 — Baseline

- merekod contract HTTP bagi 10 route awam;
- menyediakan `tools/restructure_smoke.php` sebagai smoke test read-only;
- merekod current root, include pattern, URL aset dan endpoint compatibility;
- menyediakan konfigurasi vhost `oneid-next.local` sebagai template.

### R1 — Path normalization

- menambah `bootstrap/paths.php` dan `bootstrap/app.php`;
- menentukan `PROJECT_ROOT`, `PUBLIC_PATH`, `STORAGE_PATH` dan `LEGACY_PUBLIC_PATH`;
- menukar include aktif daripada working-directory relative kepada `__DIR__`;
- upload icon menggunakan `oneid_public_path('public_img')`;
- cron log menggunakan `oneid_storage_path('logs')`;
- Catatan semasa: default secret path telah dimigrasi selepas R5.5B ke
  `PROJECT_ROOT/.private/runtime.php`; lokasi lama di bawah ialah rekod sejarah
  pelaksanaan R0-R3.

### R2 — Parallel public structure

- menyediakan wrapper awam nipis;
- mengekalkan `/lib/q_func` sebagai compatibility endpoint;
- hanya frontend subset `vendors` dimasukkan ke public tree;
- library PHP Spyc dan Device Detector kekal di luar public tree;
- menyediakan Apache `.htaccess` dan Nginx vhost template.

### R3 — Parallel smoke test

- menjalankan PHP 8.3 built-in server sementara pada `127.0.0.1:18081` dengan document root `public/`;
- menggunakan `ONEID_APP_URL=http://127.0.0.1:18081` bagi menguji redirect contract;
- menjalankan route, boundary, API POST dan asset hash comparison;
- server sementara telah dihentikan selepas ujian.

## 3. Struktur Baharu Yang Disediakan

```text
oneid-uat/
├── bootstrap/
│   ├── app.php
│   └── paths.php
├── public/
│   ├── index.php
│   ├── api.php
│   ├── idms.php
│   ├── skp_api.php
│   ├── admin/
│   ├── page/
│   ├── lib/q_func.php
│   ├── assetsM -> ../assetsM
│   ├── dist -> ../dist
│   ├── img -> ../img
│   ├── public_docs -> ../public_docs
│   ├── public_img -> ../public_img
│   ├── videos -> ../videos
│   └── vendors/
├── storage/
│   ├── cache/
│   ├── logs/
│   └── quarantine/
├── tools/restructure_smoke.php
└── docs/nginx/oneid-next.local.conf
```

Folder `app/`, `config/`, `database/`, `resources/` dan `tests/` belum dibina kerana ia termasuk R5 refactor selepas cutover stabil.

## 4. Public Entry Point dan Compatibility

| URL contract | Wrapper public | Implementasi semasa di luar public |
|---|---|---|
| `/` dan `/index.php` | `public/index.php` | `index.php` |
| `/api.php` | `public/api.php` | `api.php` |
| `/idms.php` | `public/idms.php` | `idms.php` |
| `/skp_api.php` | `public/skp_api.php` | `skp_api.php` |
| `/page/dashboard` | `public/page/dashboard.php` | `page/dashboard.php` |
| `/page/logout` | `public/page/logout.php` | `page/logout.php` |
| `/admin/dashboard` | `public/admin/dashboard.php` | `admin/dashboard.php` |
| `/admin/user_list` | `public/admin/user_list.php` | `admin/user_list.php` |
| `/admin/logout` | `public/admin/logout.php` | `admin/logout.php` |
| `/lib/q_func` | `public/lib/q_func.php` | `lib/q_func.php` |

Wrapper tidak mengandungi business logic. Ia hanya memuatkan implementasi dari root lama supaya contract kekal semasa peralihan.

## 5. Symlink Peralihan

| Public path | Target | Sebab |
|---|---|---|
| `public/assetsM` | `../assetsM` | Aset login aktif |
| `public/dist` | `../dist` | Aset dashboard aktif |
| `public/img` | `../img` | Imej aktif |
| `public/public_docs` | `../public_docs` | Manual pengguna |
| `public/public_img` | `../public_img` | Kongsi icon dengan root lama semasa dual-root |
| `public/videos` | `../videos` | Compatibility aset |
| `public/favicon.ico` | `../favicon.ico` | Root favicon |
| `public/page/favicon.ico` | `../../page/favicon.ico` | Page-relative favicon |
| `public/admin/favicon.ico` | `../../admin/favicon.ico` | Admin-relative favicon |
| `public/vendors/bower_components` | `../../vendors/bower_components` | Frontend legacy dependency |
| `public/vendors/vectormap` | `../../vendors/vectormap` | Dashboard frontend |
| `public/vendors/typeahead.js` | `../../vendors/typeahead.js` | Dashboard frontend |

Semua symlink disahkan tidak rosak. Symlink ini bukan struktur akhir; R5 akan memindahkan aset runtime sebenar ke `public/assets` selepas dependency inventory.

## 6. Path Constants

| Constant/helper | Nilai lalai |
|---|---|
| `PROJECT_ROOT` | `/var/www/app/oneid-uat` |
| `PUBLIC_PATH` | `/var/www/app/oneid-uat/public` |
| `STORAGE_PATH` | `/var/www/app/oneid-uat/storage` |
| `LEGACY_PUBLIC_PATH` | `/var/www/app/oneid-uat` |
| `oneid_project_path()` | Path di bawah project root |
| `oneid_public_path()` | Path di bawah public root |
| `oneid_storage_path()` | Path runtime storage |

`ONEID_PUBLIC_PATH` boleh digunakan sebagai override deployment jika diperlukan, tetapi production disyorkan menggunakan nilai lalai.

## 7. Baseline dan Keputusan Ujian

### 7.1 Root lama selepas R1

| Route | Expected | Keputusan |
|---|---:|---|
| `/` | 200 HTML | Lulus |
| `/index.php` | 302 | Lulus |
| `/page/dashboard.php` tanpa session | 302 | Lulus |
| `/admin/dashboard.php` tanpa session | 302 | Lulus |
| `/api.php` tanpa JSON | 400 JSON | Lulus |
| `/idms.php` tanpa query | 200 JSON | Lulus |
| `/skp_api.php` tanpa query | 400 JSON | Lulus |
| `/lib/q_func.php` menggunakan GET | 405 JSON | Lulus |
| `/assetsM/css/custom.css` | 200 CSS | Lulus |
| `/public_docs/MANUAL_SALAM.pdf` | 200 PDF | Lulus |

**Hasil:** 10 pemeriksaan, 0 gagal.

### 7.2 Public-root selari

Route yang sama dijalankan terhadap `http://127.0.0.1:18081` dengan `public/` sebagai document root.

**Hasil:** 10 pemeriksaan, 0 gagal.

### 7.3 API POST

POST token ujian tidak sah ke `public/api.php` menghasilkan contract:

```json
{"flag":"1","respond":"0"}
```

Tiada token atau data test ditulis ke database.

### 7.4 Asset integrity

| Aset | Root lama | Public-root | Sama |
|---|---|---|---|
| `assetsM/css/custom.css` | `3088c033...9876acd` | `3088c033...9876acd` | Ya |
| `public_docs/MANUAL_SALAM.pdf` | `e7d4004c...1797a1a` | `e7d4004c...1797a1a` | Ya |

### 7.5 Public boundary

Semua laluan berikut menghasilkan HTTP 404 melalui public-root:

- `/README.md`;
- `/package.json`;
- `/docs/AUDIT_PROJEK_ONEID_UAT_2026-07-13.md`;
- `/lib/config.php`;
- `/lib/secrets.php`;
- `/vendors/spyc-master/Spyc.php`;
- `/cron/run_sync.php`;
- `/sso_db.sql`;
- `/test.php`;
- `/atest.php`;
- `/diag/agent.php`.

Ini mengesahkan document-root baharu mengecilkan exposure tanpa bergantung pada file permission.

### 7.6 Static verification

- semua fail PHP yang diubah dan wrapper lulus `php -l`;
- tiada symlink rosak;
- public upload target resolve ke `public_img` lama semasa transition;
- `storage/logs` wujud dan bukan world-writable;
- temporary server port `18081` telah ditutup.

### 7.7 Pengesahan login oleh pemilik sistem

Pada awal 14 Julai 2026, pemilik sistem memaklumkan login berjaya tanpa error pada `oneid.local`. Ujian tersebut mengesahkan document root lama masih berfungsi selepas perubahan R0–R3. Pengesahan public-root yang dibuat selepas itu direkodkan dalam Seksyen 7.8.

### 7.8 Nginx public-root dan login sebenar

Pada 14 Julai 2026, pemilik sistem berjaya mengaktifkan `https://oneid-next.local`, login dan menavigasi halaman melalui document root `/var/www/app/oneid-uat/public`.

Pengesahan automatik selepas aktivasi:

- hostname resolve ke `127.0.0.1`;
- certificate mempunyai SAN `DNS:oneid-next.local` dan sah sehingga 13 Oktober 2028;
- smoke test HTTP menghasilkan 10 lulus dan 0 gagal;
- hardening explicit sensitive-path telah dipasang;
- 13 sensitive path menghasilkan `404`;
- extensionless dashboard user/admin menghasilkan redirect authentication `302` seperti dijangka;
- extensionless `/lib/q_func` menghasilkan `405` seperti dijangka bagi request GET.

Gate teknikal public-root R3 telah lulus. Business UAT utama yang disahkan selepas ujian teknikal direkodkan dalam Seksyen 7.9; baki ujian disenaraikan dalam Seksyen 8.

### 7.9 Business UAT utama

Pada 14 Julai 2026, pemilik sistem mengesahkan perkara berikut berjaya melalui `oneid-next.local`:

- login admin;
- OTP dan reset password;
- upload icon;
- API, IDMS dan SKP menggunakan live credential;
- sekurang-kurangnya satu SSO consumer.

Filesystem mengesahkan icon baharu ditulis pada 01:15:42 ke `/var/www/app/oneid-uat/public_img`. Symlink `public/public_img` resolve ke target tersebut, maka upload menggunakan transitional dual-root path yang betul.

Access/error log juga diperhatikan. Tiada entri Nginx critical; structured integration audit `missing_credentials` yang direkodkan datang daripada smoke request tanpa credential dan bukan regression live integration.

## 8. Ujian Yang Belum Dibuat

R3 belum dianggap full business UAT kerana perkara berikut belum dijalankan:

- pengesahan visual semua 34 icon dalam satu sesi browser;
- authenticated user/admin AJAX action yang belum termasuk dalam ujian manual semasa.

## 9. Nginx Status

Template tersedia di `docs/nginx/oneid-next.local.conf`. Pemilik sistem telah memasang server block menggunakan akses pentadbir dan mengaktifkan `oneid-next.local`.

Semakan 14 Julai 2026 turut mendapati:

- `oneid-next.local` resolve ke `127.0.0.1`;
- dedicated certificate mempunyai SAN `oneid-next.local`;
- konfigurasi aktif berjaya melayan request HTTPS;
- document root aktif `oneid.local` masih `/var/www/app/oneid-uat`.

Runbook penuh tersedia di `docs/nginx/AKTIVASI_R3_ONEID_NEXT.md`. Template menggunakan dedicated certificate `/etc/nginx/ssl/oneid-next.local.crt` dan key `/etc/nginx/ssl/oneid-next.local.key`.

Sebelum R4:

1. jalankan baki ujian dari Seksyen 8 yang relevan dengan cutover;
2. gunakan compatibility wrapper SSO legacy dan pantau structured audit;
3. uji atau persetujui prosedur rollback Nginx;
4. rekod Change ID, backup config dan change window.

## 10. Gate Sebelum R4 Cutover

- [x] Template Nginx asas dipasang dan HTTPS boleh dicapai.
- [x] Template hardening sensitive-path terkini dipasang dan berfungsi.
- [x] Semua 13 sensitive path yang diuji menghasilkan `404`.
- [x] Extensionless `/page/dashboard`, `/admin/dashboard` dan `/lib/q_func` lulus response contract.
- [x] Login user dan navigasi melalui `oneid-next.local` lulus.
- [x] Login admin melalui `oneid-next.local` lulus.
- [x] Logout admin disahkan berjaya oleh pemilik sistem.
- [x] Reset password/OTP lulus.
- [x] Upload icon lulus dan file masuk ke transitional target yang betul.
- [ ] Semua 34 aplikasi memaparkan icon.
- [x] Sekurang-kurangnya satu SSO consumer lulus.
- [x] API/IDMS/SKP live credential dan response contract lulus.
- [x] Pemilik sistem mengesahkan cron/sync tidak diperlukan lagi; retirement diterima.
- [x] Pemilik sistem mengesahkan `diag/agent.php` tidak digunakan dan menerima 404 selepas R4.
- [x] Endpoint legacy dikekalkan melalui compatibility wrapper sementara untuk R4; sunset masih menunggu Fasa 6B.
- [x] Access/error log khas OneID diperhatikan; tiada Nginx critical error.
- [ ] Rollback Nginx root telah diuji/dipersetujui.

## 11. Rollback

### 11.1 Keadaan semasa

R0–R3 tidak memerlukan emergency Nginx rollback kerana `oneid.local` tidak ditukar.

### 11.2 Rollback R2

Jika public tree perlu dibatalkan:

1. pastikan tiada vhost menggunakan `/var/www/app/oneid-uat/public`;
2. buang `public/` wrappers dan symlink;
3. buang template vhost dan smoke tool jika tidak lagi diperlukan;
4. kekalkan root lama sebagai document root.

### 11.3 Rollback R1

Path normalization menggunakan lokasi setara dan boleh dikekalkan walaupun R2 dibatalkan. Jika rollback penuh diperlukan:

- pulihkan include relatif daripada checksum/revision sebelum R1;
- pulihkan `$uploadDir='../public_img/'`;
- pulihkan cron log ke `cron/logs`;
- pulihkan default secret resolution lama;
- buang `bootstrap/` dan `storage/` hanya selepas tiada caller.

Jangan rollback hardening security Fasa 1–6.

### 11.4 Rollback R4 akan datang

Jika cutover R4 gagal, pulihkan Nginx:

```nginx
root /var/www/app/oneid-uat;
```

Kemudian jalankan `nginx -t`, reload dan smoke test root lama.

## 12. Checksum Selepas Pelaksanaan

| Fail | SHA-256 |
|---|---|
| `bootstrap/paths.php` | `73ddb91d39efc5766cbc4496340bfd7e37b41ccf74ec8104c6562167c4b7c72b` |
| `bootstrap/app.php` | `148d45a079ef0ece3c2d0775a280bfaf1df2e3c162bf1851aa132aec6edafbe0` |
| `index.php` | `651004ca63bd0975bf4d744b73c1b049755456e2625351b020f7952a080ee43d` |
| `api.php` | `52829bc828ff1d75d9ce5ef71bf592373fdf5ce4000d0a52dbb74288a935b028` |
| `page/dashboard.php` | `9077b77174d7ec33fcc91b9a66ca349c7f6a105ccbc764dc06511e9f195fc361` |
| `page/logout.php` | `32c32afa0426b643cde72e06cf2d324a728a2b5a11e5e90fbb4e897b625cc117` |
| `admin/dashboard.php` | `24b2028f0d978a0ce38d1915d2a7bf60445e7ac36c0a360fea04b3db089be7dc` |
| `admin/user_list.php` | `d63cd414e51cf78399c2c4b54dae63ce310058dd08350977e32ab3c4a1ebe9d3` |
| `admin/logout.php` | `32c32afa0426b643cde72e06cf2d324a728a2b5a11e5e90fbb4e897b625cc117` |
| `lib/secrets.php` | `62eb08724e7d52e4019c3787f87218b66e7596bf04b32cea60f0466409c0072b` |
| `lib/config.php` | `636b0242a1761731844a0d5928c9b0a8fc56e048d83484a8f95271c02b3f68aa` |
| `lib/SSO_IDP_INC.php` | `7e5b5dbed9d593bda8e38aa74c1d8680c23d3614590d9af9e83452cdced178d4` |
| `lib/q_func.php` | `b44534b888c0809ffc3185f73456a8415663d65871e9c7fa8c07c441f5e6a33b` |
| `cron/run_sync.php` | `9c8017a5774a9ea5d12daa70e893ee3d909eeb967df75cceed6dbb438afb3e59` |
| `public/.htaccess` | `a7b093330d9e786efc176bc37988f572914afa966a44c1ca2d112f7d420abea6` |
| `public/lib/q_func.php` | `38650be4950d16f43a33fdc579576e2be9979e027168029f53a362c0644fe627` |
| `tools/restructure_smoke.php` | `fc76dfa864561988aea32dfe7356e69dbb0eed4d20814baff310d2e655783f38` |
| `docs/nginx/oneid-next.local.conf` | `7c3eab54ba7634ea326f77583b4fa52fd82690da0d951d3bc2952d17b86f2af8` |
| `docs/nginx/AKTIVASI_R3_ONEID_NEXT.md` | `5f184169d28830dfdfc6080f34f45daecfc593a08c57b3e450d42ce91ef017d7` |

Wrapper lain mempunyai kandungan minimum dan boleh disahkan menggunakan manifest Git semasa deployment.

## 13. Status Akhir

- **R0:** lengkap.
- **R1:** lengkap bagi active path yang dikenal pasti.
- **R2:** lengkap sebagai parallel transitional tree.
- **R3:** lengkap dari aspek teknikal public-root dan business UAT utama. Gate operasi/governance terpilih masih terbuka sebelum R4.
- **R4:** lengkap dan ditutup dalam `R4-20260714-014057`; public-root, boundary, business smoke dan SSO control pilot lulus.
- **R5:** R5.0 dan R5.1A–R5.1D lengkap; public-root mempunyai 0 symlink dan visual/network validation lulus. R5.2A–C1 characterization/logout/sync-transformer extraction lengkap. R5.2D0 orchestration/dashboard characterization lengkap. R5.2D1 empat interface dan summary DTO lulus 18/18 sebagai design-only seam tanpa production wiring. Semua source lama dan rollback evidence dikekalkan.

Runbook dan acceptance register R4 telah disediakan:

- `R4_RUNBOOK_CUTOVER_PUBLIC_ROOT_DAN_ROLLBACK.md`;
- `R4_GATE_ACCEPTANCE_REGISTER.md`;
- `nginx/oneid-r4.server-block.conf`.
- `R4_COMPATIBILITY_SSO_LEGACY.md`.

Gate cron, monitoring dan risiko outage SSO legacy telah ditutup melalui keputusan owner serta compatibility wrapper. R4 menunggu Change ID/window dan backup konfigurasi Nginx sebelum status GO.

**Regression konfigurasi 14 Julai 2026, 01:23:** block `oneid.local` didapati hilang daripada `local-projects`, menyebabkan hostname tersebut dijawab oleh default vhost e-BDR. `oneid-next.local` kekal 10/10. Template pemulihan baseline tersedia di `nginx/oneid-legacy-restore.server-block.conf`; R4 tidak boleh dimulakan sehingga `oneid.local` dipulihkan dan smoke kembali 10/10.

**Pemulihan:** Pemilik sistem memasang semula block legacy. Semakan akhir mengesahkan root `oneid.local` kembali ke `/var/www/app/oneid-uat`, root `oneid-next.local` kekal `/var/www/app/oneid-uat/public`, dan kedua-duanya lulus smoke 10/10. Gate baseline R4-G00 ditutup sebagai PASS.
