# R5.1A — Physicalize `public_docs` dan `videos`

**Change ID:** `R5-1A-20260714-023748`  
**Tarikh:** 14 Julai 2026  
**Status:** COMPLETE — validation teknikal lulus  
**Change owner / rollback owner:** Pemilik sistem UAT

## 1. Skop

Menukar dua symlink public-root kepada direktori fizikal tanpa memadam source
lama:

| URL path | Sebelum | Selepas |
|---|---|---|
| `/public_docs/` | `public/public_docs -> ../public_docs` | direktori fizikal `public/public_docs/` |
| `/videos/` | `public/videos -> ../videos` | direktori fizikal `public/videos/` |

Tiada perubahan Nginx, PHP, database, URL, dependency atau kandungan fail dibuat.

## 2. Approved Deviation

R5.1A dimulakan sebelum cadangan stabilization 24 jam tamat berdasarkan arahan
owner untuk `proceed`. Risiko dikurangkan kerana:

- batch hanya mengandungi dua fail statik;
- source lama tidak dipadam atau diubah;
- copy dan checksum disahkan sebelum switch;
- symlink asal disimpan di luar public-root;
- kedua-dua hostname menggunakan public-root yang sama dan diuji selepas switch.

## 3. Inventori Sebelum Switch

| Source | Saiz | Mode | SHA-256 |
|---|---:|---:|---|
| `public_docs/MANUAL_SALAM.pdf` | 1,638,720 | `0644` | `e7d4004ccc4f9a00195c8e73da40f2429dfa2ea420ca3d77e0c6ab01d1797a1a` |
| `videos/video1.mp4` | 4,504,142 | `0644` | `814be0dcedb6fbbd93d926c26598c471a6783a979734f20e4d6ac761e4172998` |

- tiada PHP/PHTML/PHAR/CGI/Perl/Python/shell script;
- `MANUAL_SALAM.pdf` dirujuk oleh login page aktif;
- `video1.mp4` tidak ditemui dalam static reference scan, tetapi dikekalkan;
- ruang disk sebelum copy: 951 GB tersedia.

## 4. Staging dan Evidence

Direktori change:

```text
storage/quarantine/R5-1A-20260714-023748/
```

Evidence runtime dalam direktori tersebut:

```text
source.sha256
staged.sha256
public-after-switch.sha256
original-symlinks/public_docs
original-symlinks/videos
```

`diff -qr` dan checksum source/staging lulus sebelum switch. Selepas switch,
`diff -qr` antara source lama dengan direktori public baharu turut lulus.

## 5. Permission

Source lama kekal `iqs:www-data`, direktori `0755` dan fail `0644`. Salinan public
baharu ialah `iqs:iqs`, direktori `0755` dan fail `0644` kerana user pelaksana
tidak mempunyai autoriti `chgrp www-data`.

Perbezaan group tidak menjejaskan runtime: Nginx/PHP mempunyai read/execute
melalui bit `other`. Tiada permission dilonggarkan melebihi baseline mode asal.
Jika standard deployment mewajibkan group `www-data`, jalankan change permission
berasingan dengan `sudo chgrp -R www-data public/public_docs public/videos` dan
verify semula; ia bukan syarat runtime batch ini.

## 6. Keputusan Validation

| Pemeriksaan | `oneid.local` | `oneid-next.local` |
|---|---:|---:|
| Restructure smoke | 10/10 | 10/10 |
| Manual PDF | 200 `application/pdf`, 1,638,720 bait | 200 `application/pdf` |
| Video | 200 `video/mp4`, 4,504,142 bait | 200 `video/mp4` |
| `/public_docs/test.php` | 404 | N/A |
| `/videos/test.php` | 404 | N/A |

- broken symlink selepas switch: 0;
- jumlah symlink dalam `public/`: turun daripada 12 kepada 10;
- PHP fatal/parse/uncaught dan Nginx critical baharu: 0.

## 7. Rollback

Rollback hanya diperlukan jika URL statik menghasilkan 404/5xx, checksum tidak
sepadan, atau regression sah dikesan.

```bash
cd /var/www/app/oneid-uat

change_id='R5-1A-20260714-023748'
base="storage/quarantine/${change_id}"

mv public/public_docs "$base/public_docs.failed"
mv "$base/original-symlinks/public_docs" public/public_docs

mv public/videos "$base/videos.failed"
mv "$base/original-symlinks/videos" public/videos

php tools/restructure_smoke.php https://oneid.local --insecure
php tools/restructure_smoke.php https://oneid-next.local
```

Source `public_docs/` dan `videos/` di root projek tidak boleh dipadam sepanjang
rollback/observation window. Rollback R5.1A tidak memerlukan Nginx reload.

## 8. Langkah Seterusnya

Pantau access/error log dan uji manual PDF daripada login page. Batch R5.1B
`img/public_img` hanya boleh bermula selepas manifest reference database/icon,
upload write-path dan rollback permission lengkap.
