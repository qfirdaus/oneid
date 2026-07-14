# R5.1B — Physicalize `img` dan `public_img`

**Change ID:** `R5-1B-20260714-024146`  
**Tarikh:** 14 Julai 2026  
**Status:** COMPLETE — manual upload UI dan filesystem/DB confirmation lulus  
**Change owner / rollback owner:** Pemilik sistem UAT

## 1. Skop

Menukar dua symlink public-root kepada direktori fizikal tanpa memadam source
lama:

| URL path | Sebelum | Selepas |
|---|---|---|
| `/img/` | `public/img -> ../img` | direktori fizikal `public/img/` |
| `/public_img/` | `public/public_img -> ../public_img` | direktori fizikal `public/public_img/` |

`img` ialah aset statik aplikasi. `public_img` ialah aset icon aplikasi dan
write-path upload. Tiada URL, Nginx, database atau response contract diubah.

## 2. Approved Deviation

Seperti R5.1A, batch ini dijalankan sebelum stabilization 24 jam tamat atas
arahan owner untuk proceed. Copy-before-switch, checksum, DB reference map,
source retention dan rollback symlink digunakan sebagai compensating control.

## 3. Baseline Sebelum Switch

| Item | Nilai |
|---|---:|
| Fail `img/` | 143 |
| Fail `public_img/` termasuk `.htaccess` | 89 |
| Jumlah fail | 232 |
| Rekod `sp_list` | 70 |
| Aplikasi aktif | 35 |
| Icon unik dirujuk aplikasi aktif | 35 |
| Icon aktif kosong/hilang | 0 / 0 |
| Script executable dalam kedua-dua folder | 0 |
| Ruang disk tersedia | 951 GB |

Semua filename icon, selain `.htaccess`, mematuhi pattern
`app_icon_<identifier>.<image-extension>`. `public_img/.htaccess` menghalang
extension script pada Apache; Nginx R4 turut mempunyai location block 404 untuk
script di folder upload.

PHP-FPM pool `www` pada host UAT berjalan sebagai `iqs:iqs`. Oleh itu direktori
fizikal baharu `iqs:iqs 0755` boleh ditulis oleh proses upload tanpa permission
world-write.

## 4. Staging dan Evidence

Direktori change:

```text
storage/quarantine/R5-1B-20260714-024146/
```

Evidence yang disimpan:

```text
source.sha256
source.pre-switch.sha256
staged.sha256
public-after-switch.sha256
source.metadata.tsv
staged.metadata.tsv
db-icon-map.before.tsv
db-icon-map.pre-switch.tsv
db-icon-map.after-switch.tsv
original-symlinks/img
original-symlinks/public_img
```

Race-check sebelum switch mengesahkan source checksum dan DB icon map tidak
berubah semasa staging. `diff -qr` source/staging dan source/public selepas switch
lulus untuk kedua-dua folder.

## 5. Write Path

Kod upload menggunakan:

```php
$uploadDir = oneid_public_path('public_img');
```

Selepas switch, runtime resolve kepada:

```text
/var/www/app/oneid-uat/public/public_img
```

Pemeriksaan `is_dir`, `is_writable` dan create/remove fixture sebagai user
pelaksana/PHP-FPM semuanya lulus. Ujian ini mengesahkan path dan filesystem
permission; upload multipart sebenar melalui sesi admin masih menjadi business
gate sebelum R5.1C.

## 6. Keputusan Validation

| Pemeriksaan | Keputusan |
|---|---|
| Smoke `oneid.local` | 10/10 lulus |
| Smoke `oneid-next.local` | 10/10 lulus |
| 35 icon aktif melalui HTTP | 35/35 status 200 dan `image/*` |
| Logo, thumbnail dan fallback user | 4/4 status 200 dan `image/*` |
| Script probe dua folder pada dua host | 4/4 status 404 |
| Source/public checksum dan recursive diff | Lulus |
| DB map sebelum/selepas | Tiada perubahan |
| Upload directory writable | Lulus |
| Broken symlink | 0 |
| Symlink public selepas change | 8; turun daripada 10 |
| Fatal PHP / Nginx critical baharu | 0 |

## 7. Rollback

Sebelum rollback `public_img`, salin semula sebarang upload baharu daripada
direktori fizikal ke source lama. Filename upload menggunakan random identifier,
tetapi semak konflik sebelum overwrite dalam insiden sebenar.

```bash
cd /var/www/app/oneid-uat

change_id='R5-1B-20260714-024146'
base="storage/quarantine/${change_id}"

# Preserve sebarang icon yang dimuat naik selepas switch.
cp -a public/public_img/. public_img/

mv public/img "$base/img.failed"
mv "$base/original-symlinks/img" public/img

mv public/public_img "$base/public_img.failed"
mv "$base/original-symlinks/public_img" public/public_img

php tools/restructure_smoke.php https://oneid.local --insecure
php tools/restructure_smoke.php https://oneid-next.local
```

Selepas rollback, sahkan semua 35 icon dan lakukan upload fixture sekali lagi.
Source `img/` dan `public_img/` di root tidak boleh dipadam sepanjang observation.

## 8. Gate Owner Selepas Change

- [x] Login admin dan buka senarai aplikasi tanpa icon rosak.
- [x] Edit aplikasi fixture dan upload icon PNG yang sah.
- [x] Icon baharu wujud dalam `public/public_img`, bukan root lama.
- [x] Icon baharu boleh dicapai HTTP 200 `image/png`.
- [ ] Buang aplikasi fixture melalui flow aplikasi yang diluluskan; jangan delete
  fail secara manual jika rekod DB masih merujuknya.

### Evidence upload sebenar — 02:46 +0800

Owner memadam icon lama melalui UI sebelum memuat naik icon pengganti. Evidence
selepas upload:

```text
consumer: BTOG4WZNQP / IQS-Framework
path: public/public_img/app_icon_da1c4d6f3dab57b3e809fa5bd461a202.png
size: 42,103 bytes
HTTP: 200 image/png
legacy-root copy: tiada
active DB icon missing: 0 daripada 35
```

Ini mengesahkan aplikasi menulis ke physical public-root dan tidak lagi menulis
melalui source legacy. R5.1B ditutup sebagai complete dan R5.1C dibenarkan.
