# Fasa 1 — Pelaksanaan dan Rollback OneID-UAT

**Tarikh pelaksanaan:** 13 Julai 2026  
**Rujukan:** `docs/PELAN_REMEDIASI_BERFASA_ONEID_UAT.md`  
**Skop:** Quick wins, kurangkan exposure dan hardening upload tanpa mengubah schema database atau flow authentication.

## 1. Objektif Fasa 1

Fasa ini bertujuan untuk:

- Mengurangkan fail sensitif yang boleh dimuat turun melalui web.
- Menghalang script daripada disimpan sebagai icon aplikasi.
- Menutup endpoint ujian yang tidak mempunyai fungsi production.
- Membetulkan reference aset rosak.
- Menyediakan kawalan Apache dan panduan Nginx.
- Mengekalkan page lama dan integration endpoint sehingga bukti penggunaan mencukupi.

## 2. Baseline Checksum Sebelum Perubahan Fasa 1

| Fail | SHA-256 sebelum Fasa 1 |
|---|---|
| `lib/q_func.php` | `6daa13a5416de5357c9991751124ff8c2f1269a55ecfb0487619b155bdd4c1b2` |
| `test.php` | `00477beeaee839186f5fd34e9e3081032a7b251afd885dc29e44c4b0a5e9a584` |
| `atest.php` | `387a357b175ac1d1d4e46b0cd08abe1425e1e3ee24eb0f3cd12cd15058a3b8f7` |
| `.htaccess` | `1a4d071d274c90ac430df9ee2c2bc097dbab0d685c307cbe2b7cd9c3918d5d62` |
| `public_img/.htaccess` | `9c56d27c07aba9becb47130613777909e43b8979d2a381061bd482155c58066e` |
| `page/dashboard.php` | `b0c36a31dc661972e8b8dd8adbed7e8a07ea5adfe66bc6f5f1bbf2ab5f204986` |

`lib/upload_security.php` ialah fail baharu dan tidak mempunyai checksum sebelum fasa ini.

## 3. Perubahan yang Dilaksanakan

### 3.1 Helper keselamatan upload baharu

Fail baharu: `lib/upload_security.php`

Helper ini menyediakan tiga fungsi:

1. `validate_app_icon_upload()`
2. `save_app_icon_upload()`
3. `sanitize_existing_app_icon()`

Kawalan yang ditambah:

- Maksimum saiz icon ialah 5 MB.
- Hanya MIME berikut dibenarkan:
  - JPEG
  - PNG
  - GIF
  - WebP
- MIME diperiksa menggunakan `finfo`.
- Kandungan turut diperiksa menggunakan `getimagesize()`.
- MIME daripada kedua-dua pemeriksaan mesti sepadan.
- Extension asal daripada browser tidak lagi dipercayai.
- Extension output ditentukan oleh MIME sebenar.
- Nama fail dijana menggunakan `random_bytes()`.
- Existing icon mesti berupa nama fail biasa dengan format `app_icon_*` dan extension imej yang dibenarkan.
- `basename()` dan regex digunakan untuk menolak path traversal.

### 3.2 Integrasi helper ke controller

Fail: `lib/q_func.php`

Perubahan dibuat pada:

- `action_add_new_app`
- `action_edit_app_info`

Behavior baharu:

- Add application tanpa icon masih dibenarkan seperti sebelumnya.
- Fail tidak sah tidak disimpan ke `public_img`.
- Edit application dengan upload tidak sah mengekalkan existing icon yang sah.
- Edit tanpa upload baharu mengekalkan existing icon.
- Response masih menggunakan field `app_icon`, jadi frontend sedia ada tidak perlu ditulis semula.

Perubahan ini tidak menyelesaikan authorization endpoint. Authorization akan dibuat dalam Fasa 4 mengikut pelan supaya perubahan security boundary tidak bercampur dengan quick wins.

### 3.3 Endpoint test ditutup

Fail:

- `test.php`
- `atest.php`

Kedua-dua fail kini:

- Memulangkan HTTP `404`.
- Menetapkan `Cache-Control: no-store`.
- Berhenti sebelum menjalankan atau memaparkan diagnostic lama.

Kod lama dikekalkan selepas `exit` untuk memudahkan rollback dan review. Ia tidak dijalankan melalui HTTP.

### 3.4 Exposure controls daripada permulaan Sprint 1

Kawalan yang telah dilaksanakan dan masih aktif:

- Root `.htaccess` menyekat SQL, log, BAT, PowerShell dan OS metadata bagi Apache.
- `public_img/.htaccess` menyekat script/executable bagi Apache.
- Permission lokal `600` menghalang Nginx worker membaca dump SQL, cron log dan host script.
- Snippet Nginx tersedia di `docs/nginx/oneid-sprint1-security.conf`.
- Reference `img/mock1.png` telah ditukar kepada `img/mock1.jpg` pada dashboard aktif.

## 4. Verification yang Dilaksanakan

### 4.1 PHP syntax

Fail berikut lulus `php -l`:

- `lib/upload_security.php`
- `lib/q_func.php`
- `test.php`
- `atest.php`

Semakan penuh sebelum Fasa 1 turut menunjukkan semua 43 fail PHP first-party lulus lint.

Selepas penambahan helper baharu, semua 44 fail PHP first-party lulus lint.

### 4.2 Unit-level validation helper

| Senario | Keputusan |
|---|---|
| PNG sedia ada yang sah | Diterima |
| Fail PHP dihantar sebagai icon | Ditolak |
| Reported size melebihi 5 MB | Ditolak |
| Existing filename yang sah | Diterima |
| Existing filename `../../shell.php` | Ditolak |

Semua assertion helper lulus.

### 4.5 Checksum selepas Fasa 1

| Fail | SHA-256 selepas Fasa 1 |
|---|---|
| `lib/upload_security.php` | `4f063dfbdc9e443eb1f3d7161d579f5aad6cd3c96c3adbbea92562d27b42d287` |
| `lib/q_func.php` | `e7937ecc507f91ac5c4ea9d16f7f9382a970e5280fae1f3d074bb0f2a6f8ea1a` |
| `test.php` | `9f705ebdcde05302f3ba975ce1813e5516fc02f36062d460ce93ad4da4ad17b9` |
| `atest.php` | `98b1be6ac222e951829d57d3886b09fa1810ba16c5a58e4c8cdffbf8fe86a594` |

HTTP verification akhir turut mengesahkan login root page masih memberikan `200`.

### 4.3 HTTP verification

| URL | Status selepas perubahan |
|---|---:|
| `/test.php` | 404 |
| `/atest.php` | 404 |
| `/sso_db.sql` | 403 |
| `/cron/logs/sync_cron.log` | 403 |
| `/cron/run_sync.bat` | 403 |
| `/cron/setup_scheduled_task.ps1` | 403 |
| `/public_img/app_icon_1752725496.png` | 200 |

### 4.4 Pemeriksaan folder upload

- 86 fail sedia ada diperiksa.
- Semua dikenal pasti sebagai MIME imej.
- Tiada signature PHP/script mencurigakan ditemui melalui carian statik.

## 5. Ujian Manual yang Masih Diperlukan

Ujian berikut memerlukan akaun dan/atau boleh mengubah database, maka tidak dijalankan secara automatik:

- [ ] Login sebagai administrator.
- [ ] Tambah aplikasi tanpa icon.
- [ ] Tambah aplikasi dengan PNG.
- [ ] Tambah aplikasi dengan JPEG.
- [ ] Edit aplikasi tanpa menukar icon.
- [ ] Edit aplikasi dengan icon baharu.
- [ ] Cuba upload `.php` dan pastikan aplikasi tidak menyimpan fail tersebut.
- [ ] Cuba upload imej melebihi 5 MB.
- [ ] Pastikan existing icon masih dipaparkan selepas failed edit upload.
- [ ] Pastikan audit log application management masih direkodkan.

Gunakan data ujian khas. Jangan gunakan aplikasi production sebenar untuk negative upload test.

## 6. Rollback Fasa 1

### Rollback endpoint test

Buang baris berikut daripada bahagian atas `test.php` dan `atest.php`:

```php
http_response_code(404);
header('Cache-Control: no-store');
exit;
```

### Rollback hardening upload

1. Buang `require_once './upload_security.php';` daripada `lib/q_func.php`.
2. Pulihkan dua blok upload asal dalam `action_add_new_app` dan `action_edit_app_info` daripada backup dengan checksum baseline di atas.
3. Buang `lib/upload_security.php`.
4. Jalankan `php -l lib/q_func.php`.
5. Uji add/edit application icon.

Rollback upload hanya patut dilakukan jika terdapat regression dan selepas akses application-management dihadkan. Logic asal menerima extension browser tanpa validation dan tidak selamat untuk dibiarkan aktif.

### Rollback exposure controls

Rujuk `docs/FASA_0_BASELINE_DAN_ROLLBACK.md` untuk rollback `.htaccess`, permission dan reference imej.

## 7. Perkara yang Sengaja Ditangguhkan

### Page lama dan salinan kod

Belum di-quarantine:

- `index1.php`
- `index_.php`
- `index_20250806.php`
- `page/dashboard2.php`
- `page/dashboard_old.php`
- `admin/dashboard_old.php`
- `api_old.php`
- `lib/q_func_old.php`
- Salinan `SSO_IDP_INC`

Sebab: access log lokal hanya meliputi kurang daripada tiga hari. Pelan mensyaratkan pemerhatian 30 hingga 90 hari atau pengesahan owner sebelum quarantine.

### Integration dan monitoring endpoint

Belum ditutup:

- `idms.php`
- `skp_api.php`
- `lib/skp_api.php`
- `diag/agent.php`
- `diagnostic/index.php`
- `lib/sso_IDP_index.php`
- `lib/sso_IDP_sub.php`

Sebab: mungkin mempunyai consumer luar yang tidak kelihatan daripada source aplikasi.

### Nginx server-level deny rule

Snippet telah disediakan tetapi belum dipasang ke `/etc/nginx` kerana konfigurasi dimiliki root dan akaun workspace tidak mempunyai sudo.

### Metadata files

`.DS_Store` dan `Thumbs.db` belum dipadam. Read permission untuk proses web telah ditarik sebagai containment sementara. Penghapusan kekal boleh dibuat bersama cleanup batch selepas backup disahkan.

## 8. Exit Criteria Fasa 1

Fasa 1 dianggap lengkap pada peringkat source apabila:

- [x] Fail sensitif tidak lagi boleh dimuat turun pada runtime lokal.
- [x] Endpoint test memberikan 404.
- [x] Upload helper menolak non-image, oversize dan path traversal.
- [x] Imej sedia ada masih boleh diserve.
- [x] PHP lint lulus.
- [x] Semua perubahan dan rollback direkodkan.
- [ ] Add/edit application icon disahkan menggunakan akaun admin.
- [ ] Snippet Nginx dipasang dan `nginx -t` lulus pada host yang memerlukan Nginx.
- [ ] Page lama melalui tempoh log observation sebelum quarantine.

## 9. Cadangan Langkah Seterusnya

Selepas manual upload smoke test selesai:

1. Lengkapkan pemasangan snippet Nginx oleh administrator.
2. Terus kumpulkan access log sehingga cukup sekurang-kurangnya 30 hari.
3. Mulakan Fasa 2: error handling, environment URL dan security headers.
4. Jangan mula quarantine page lama serentak dengan perubahan authentication.
