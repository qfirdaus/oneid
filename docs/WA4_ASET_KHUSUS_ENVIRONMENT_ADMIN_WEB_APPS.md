# WA4 — Aset Khusus Environment Admin Web Apps

**Tarikh:** 17 Julai 2026  
**Status:** COMPLETE — WSL/STAGING ISOLATION DAN OWNER VISUAL UAT LULUS
**Migration:** `20260717_wa4_environment_app_asset_up.sql` — applied sekali

## 1. Keputusan seni bina

- WSL=`local`, staging=`staging`, production=`production`;
- database OneID kekal dikongsi;
- filesystem dan file upload tidak dikongsi/disalin;
- metadata aplikasi kekal global dalam `sp_list`;
- gambar baharu disimpan dalam `sp_app_asset` mengikut `(sp_id, environment)`;
- `sp_list.sp_image` kekal sebagai fallback read-only untuk icon legacy.

Committed runtime default sengaja kosong/fail-closed. Setiap deployment mesti
mempunyai nilai private sendiri supaya staging tidak boleh tersalah menggunakan
namespace `local`.

## 2. Schema

`sp_app_asset` mempunyai:

- composite primary key `(sp_id, environment)`;
- foreign key ke `sp_list.sp_id` dengan `ON DELETE CASCADE`;
- filename, updated timestamp dan actor;
- check constraint environment dan filename.

Migration bersifat expanding. Tiada row legacy dibackfill dan tiada column lama
dibuang. Oleh sebab database dikongsi, migration tidak perlu dijalankan semula
pada staging.

## 3. Behavior Add/Edit

- Add dengan icon memasukkan metadata global dengan `sp_image=''`, kemudian
  menulis row aset environment semasa dalam transaction yang sama;
- Edit metadata tidak mengubah `sp_list.sp_image`;
- Edit dengan replacement icon hanya upsert aset environment semasa;
- audit Add/Edit menyimpan `environment=<value>`;
- publish fail kekal pada `public/public_img` deployment semasa sahaja;
- semua atomicity/compensation WA3 dikekalkan.

## 4. Behavior read dan compatibility

Semua caller admin/user memilih:

```sql
COALESCE(NULLIF(environment_asset.image_filename, ''), sp_list.sp_image)
```

Ini bermaksud:

1. aset environment mengatasi fallback legacy;
2. icon legacy terus berfungsi tanpa backfill;
3. app WA4 baharu yang hanya mempunyai aset local akan menunjukkan placeholder
   di staging sehingga staging memuat naik asetnya sendiri;
4. tiada lookup atau fetch ke filesystem environment lain.

## 5. Konfigurasi staging wajib

Sebelum atau bersama deployment kod WA4, fail runtime private staging mesti
mengandungi:

```php
'ONEID_ENVIRONMENT' => 'staging',
```

Jangan infer environment daripada hostname. Selepas staging pull, sahkan:

```bash
php -r 'require "lib/config.php"; echo $operation->admin_get_environment(), PHP_EOL;'
```

Output mestilah `staging`. Migration tidak dijalankan semula.

Sepanjang mixed-version deployment window, elakkan mutation gambar pada staging
lama kerana kod lama masih menulis `sp_list.sp_image` global. Deploy/configure
staging WA4 secepat satu change window yang sama.

## 6. Automated verification

```bash
php tools/wa4_app_asset_schema_migrate.php
php tools/wa4_environment_asset_contract.php
```

Contract membuat probe local/staging dalam transaction dan sentiasa rollback;
ia tidak meninggalkan asset row atau fail.

## 7. Manual UAT dua environment

Gunakan satu app pilot aktif:

1. Di WSL, upload gambar A; jangka
   `WA4_APP_UPDATED_ENVIRONMENT_ASSET` dan audit `environment=local`.
2. Sahkan gambar A dipaparkan di WSL dan row `(app,local)` wujud.
3. Di staging sebelum upload, sahkan gambar A tidak muncul jika app tiada legacy
   fallback/staging asset; placeholder dipaparkan.
4. Di staging, upload gambar B; audit mesti `environment=staging`.
5. Sahkan gambar B dipaparkan di staging dan gambar A masih dipaparkan di WSL.
6. Sahkan kedua-dua filename/fail berbeza dan berada pada filesystem masing-masing.
7. Archive app pilot hanya selepas semua evidence diambil.

### Evidence deployment staging — owner, 17 Julai 2026

- staging fast-forward daripada `7d9a384` kepada `c146e37`;
- `.private/runtime.php` staging lulus PHP syntax;
- `Database::admin_get_environment()` tersedia selepas pull;
- runtime identity memulangkan tepat `staging`;
- WA1 UI contract: 13/13 PASS;
- WA2 validation/failure regression: 14/14 PASS;
- WA3 atomic contract: 13/13 PASS;
- WA4 environment asset contract: 12/12 PASS;
- migration tidak dijalankan semula pada staging kerana database dikongsi.

Status deployment gate: **PASS**. Baseline filesystem staging dan manual
isolation UAT gambar A/B masih berbaki.

### Evidence baseline filesystem staging — 17 Julai 2026 15:27:58 +08:00

- runtime identity: `staging`;
- shared DB: 75 app, 37 aktif, 38 inactive;
- staging filesystem: 89 icon files;
- environment asset rows ketika baseline: 0;
- missing local files: 2;
- orphan candidates staging: 28;
- missing references ialah app `2WJ4USYRS9` dan `EJEN8QNV9N`, kedua-duanya
  merujuk filename legacy yang wujud/berasal daripada WSL tetapi tidak wujud pada
  filesystem staging.

Keputusan ini membuktikan filesystem tidak dikongsi dan bukan alasan untuk
menyalin fail. UI admin/user ditambah `onerror` fallback kepada icon default
supaya legacy reference yang tiada secara lokal tidak memaparkan broken image.

Pada 15:28:59 shared DB mempunyai row pertama WA4 untuk app `2WJ4USYRS9`:
`environment=local`, filename
`app_icon_a8cbaf3960a81c047f060c029351025d.png`, actor `820705025923`. Row yang sama
kelihatan apabila query dibuat dari staging dan WSL kerana database dikongsi;
staging tetap tidak memilihnya kerana runtime identity ialah `staging`.

Audit event 14 mengesahkan `environment=local`, outcome `success`, icon `stored`
dan correlation `02a7533439e75cbb`. Fail WSL wujud dengan saiz 42,103 byte dan
mode `0644`. Local-side upload gambar A: **PASS**.

Ujian susulan WSL pada 15:34:58 turut PASS dengan
`WA4_APP_UPDATED_ENVIRONMENT_ASSET`, correlation `3e376927565981b4` dan local
filename `app_icon_a2542fa568ac60c2aba95f8fdc6e2185.png`.

Percubaan staging correlation `0633553751879c5b` menghasilkan
`WA3_APP_UPDATE_FAILED`. Shared DB mengesahkan rollback lengkap: tiada row
`environment=staging` dan tiada success audit dengan correlation tersebut.
Log mengesahkan `rename()` gagal dengan `Permission denied`: PHP-FPM berjalan
sebagai `www-data`, sedangkan `public/public_img` ialah `iqs:www-data` mode
`0755`. Staging directory private `storage/runtime` ialah `www-data:www-data`
mode `0700` dan berfungsi seperti direka. Pembetulan minimum ialah
`public/public_img` kekal owner `iqs`, group `www-data`, mode setgid `2775`;
permission `777` dan recursive chown tidak dibenarkan. Status staging isolation
UAT: **BLOCKED PENDING PERMISSION FIX AND RETEST**, bukan partial database
mutation. UI juga diperbetulkan supaya exception ini dipaparkan sebagai “App
changes were not saved”, bukan “No changes saved”.

Permission fix staging kemudiannya dilaksanakan dan disahkan oleh owner:

- `public/public_img` = `iqs:www-data`, mode `2775`;
- write probe sebagai `www-data` = PASS;
- tiada penggunaan `777` atau recursive ownership change;
- query sebelum retry masih menunjukkan row `local` sahaja, mengesahkan failure
  terdahulu tidak meninggalkan row staging.

Status: **PERMISSION GATE PASS — RETRY UPLOAD GAMBAR B BERBAKI**.

### Evidence isolation upload — 17 Julai 2026

| Environment | Code/reference | Filename | Audit |
| --- | --- | --- | --- |
| `local` | `WA4_APP_UPDATED_ENVIRONMENT_ASSET`; `1a124143111da60b` | `app_icon_14c0ea3c959ee23414599a9d1614270f.png` | Event 14, actor `820705025923`, IP `127.0.0.1`, outcome success, icon stored, 15:40:50. |
| `staging` | `WA4_APP_UPDATED_ENVIRONMENT_ASSET`; `030d48a667c4bc6d` | `app_icon_84483bd9f13959bd000be9222d21b2c3.png` | Event 14, actor `820705025923`, IP `2.0.1.6`, outcome success, icon stored, 15:41:22. |

Shared database mempunyai tepat dua row bagi app `2WJ4USYRS9`, satu untuk setiap
environment. Filesystem WSL mengandungi local filename (42,103 byte, mode 0644)
dan tidak mengandungi staging filename; staging directory dalaman WSL kosong.
Ini membuktikan database reference dikongsi tetapi fail staging tidak disalin ke
WSL. Pengesahan visual A kekal di WSL/B dipaparkan di staging dan reciprocal
filesystem check staging masih berbaki sebelum gate WA4 ditutup.

Reciprocal staging filesystem check kemudian lulus:

- local filename `app_icon_14c0ea3c959ee23414599a9d1614270f.png` = ABSENT;
- staging filename `app_icon_84483bd9f13959bd000be9222d21b2c3.png`
  = EXISTS, 37,825 byte, mode `0644`;
- query staging memulangkan tepat dua row shared DB: `local` dan `staging` dengan
  filename masing-masing.

Filesystem isolation gate: **PASS**. Baki tunggal ialah owner visual confirmation
bahawa WSL memaparkan gambar A dan staging memaparkan gambar B.

Owner kemudian mengesahkan secara visual bahawa:

- WSL memaparkan gambar A daripada row/filename `environment=local`;
- staging memaparkan gambar B daripada row/filename `environment=staging`;
- tiada cross-environment image leakage atau broken image pada app pilot.

Kesimpulan WA4: shared database menyimpan dua reference environment berasingan,
setiap deployment membaca row sendiri dan setiap filesystem menyimpan failnya
sendiri. **WA4 owner UAT: PASS; phase closed.**

## 8. Rollback

Code rollback boleh kembali membaca `sp_list.sp_image`; jangan drop table ketika
rollback segera. Down migration bersifat destructive dan hanya boleh dijalankan
selepas export aset serta semua deployment kembali ke kod lama. Fail pada setiap
environment tidak dipadam secara automatik.
