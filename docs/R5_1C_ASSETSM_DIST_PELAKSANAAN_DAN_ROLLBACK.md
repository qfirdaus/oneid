# R5.1C — Physicalize `assetsM` dan `dist`

**Change ID:** `R5-1C-20260714-024812`  
**Tarikh:** 14 Julai 2026  
**Status:** TECHNICAL COMPLETE — hard-refresh UI confirmation pending  
**Change owner / rollback owner:** Pemilik sistem UAT

## 1. Skop

Menukar dua symlink aset frontend kepada direktori fizikal byte-identical:

| URL path | Sebelum | Selepas |
|---|---|---|
| `/assetsM/` | `public/assetsM -> ../assetsM` | direktori fizikal `public/assetsM/` |
| `/dist/` | `public/dist -> ../dist` | direktori fizikal `public/dist/` |

Tiada dependency upgrade, rebuild Grunt/Bower, minification, cleanup demo atau
perubahan HTML/JavaScript/CSS dibuat dalam change ini.

## 2. Baseline

| Item | Nilai |
|---|---:|
| Fail `assetsM/` | 880 |
| Fail `dist/` | 141 |
| Jumlah fail | 1,021 |
| Jumlah bait | 36,624,916 |
| Symlink dalaman / broken | 0 / 0 |
| Script server-side | 0 |
| URL `assetsM` aktif daripada access log | 19 |
| URL `dist` kritikal daripada source aktif | 6 |
| Ruang disk sebelum copy | 951 GB |

`assetsM` digunakan oleh login/public page. `dist` dirujuk oleh dashboard user
dan admin, termasuk stylesheet, slimscroll, bootstrap extension, init, widget
dan file-upload JavaScript.

## 3. Approved Deviation dan Permission

Owner mengarahkan R5 diteruskan sebelum stabilization 24 jam tamat. Source lama
dan symlink rollback dikekalkan sebagai compensating control.

Source lama kebanyakannya `iqs:www-data`; salinan fizikal ialah `iqs:iqs` kerana
pelaksana tidak mempunyai autoriti `chgrp www-data`. Fail statik kekal mode
`0644` dan direktori `0755`, maka Nginx boleh membacanya tanpa world-write.

## 4. Staging dan Evidence

```text
storage/quarantine/R5-1C-20260714-024812/
```

Evidence:

```text
source.sha256
source.pre-switch.sha256
staged.sha256
public-after-switch.sha256
source.metadata.tsv
staged.metadata.tsv
runtime-urls.before.txt
dist-runtime-urls.txt
original-symlinks/assetsM
original-symlinks/dist
```

Checksum 1,021 fail, recursive diff dan pre-switch race-check semuanya lulus.
Source `assetsM/` serta `dist/` di root projek tidak diubah atau dipadam.

## 5. Validation

| Pemeriksaan | Keputusan |
|---|---|
| Smoke `oneid.local` | 10/10 lulus |
| Smoke `oneid-next.local` | 10/10 lulus |
| 19 URL `assetsM`, dua host | 38/38 HTTP 200 |
| 6 URL `dist`, dua host | 12/12 HTTP 200 |
| Script probe dua folder, dua host | 4/4 HTTP 404 |
| Source/public recursive diff | Lulus |
| Broken symlink | 0 |
| Symlink public selepas change | 6; turun daripada 8 |
| Fatal PHP / Nginx critical baharu | 0 |

Automated check mengesahkan fail dihantar, tetapi tidak membuktikan visual
rendering dan JavaScript interaction. Owner perlu hard-refresh login, user
dashboard dan admin dashboard serta semak console sebelum R5.1D.

## 6. Rollback

```bash
cd /var/www/app/oneid-uat

change_id='R5-1C-20260714-024812'
base="storage/quarantine/${change_id}"

mv public/assetsM "$base/assetsM.failed"
mv "$base/original-symlinks/assetsM" public/assetsM

mv public/dist "$base/dist.failed"
mv "$base/original-symlinks/dist" public/dist

php tools/restructure_smoke.php https://oneid.local --insecure
php tools/restructure_smoke.php https://oneid-next.local
```

Selepas rollback, lakukan hard refresh dan pastikan tiada 404 aset atau console
error. Nginx reload tidak diperlukan.

## 7. Gate Owner

- [ ] Hard-refresh halaman login; logo, banner, font dan layout normal.
- [ ] Login user; dashboard, scroll, modal dan toast normal.
- [ ] Login admin; table, modal, dropify/upload dan toast normal.
- [ ] Firefox/Chrome console tiada error baharu.
- [ ] Network tab tiada 404/5xx aset `assetsM` atau `dist`.

R5.1D akan menilai enam symlink terakhir: tiga favicon dan tiga subset vendor.
Ia belum boleh bermula sehingga gate visual/console di atas disahkan.

**Continuation decision:** Owner mengarahkan `proceed sekarang` tanpa memberikan
evidence console/network berasingan. Arahan ini direkod sebagai approval untuk
meneruskan R5.1D, bukan sebagai bukti bahawa screenshot/console review telah
dilakukan. Automated validation R5.1C kekal lulus.
