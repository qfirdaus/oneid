# WA4 — Aset Khusus Environment Admin Web Apps

**Tarikh:** 17 Julai 2026  
**Status:** IMPLEMENTED DI WSL/SHARED DATABASE — STAGING CONFIG DAN UAT BERBAKI  
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

## 8. Rollback

Code rollback boleh kembali membaca `sp_list.sp_image`; jangan drop table ketika
rollback segera. Down migration bersifat destructive dan hanya boleh dijalankan
selepas export aset serta semua deployment kembali ke kod lama. Fail pada setiap
environment tidak dipadam secara automatik.
