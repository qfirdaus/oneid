# R5.4B — Public Asset Cleanup dan Rollback

Tarikh: 14 Julai 2026  
Change ID: `R5-4B-20260714-102434`  
Status: `PASS`

## Objektif

R5.4B memetakan caller dan access evidence bagi setiap public asset root, membetulkan reference `/app-assets` yang tidak wujud dan membuang metadata bukan runtime. Tiada frontend vendor atau upload pengguna dibuang dalam change ini.

## Caller dan access inventory

Pemetaan lengkap direkodkan dalam `docs/R5_4B_ASSET_CALLER_MAP.tsv`.

Access evidence diambil daripada log Nginx OneID yang tersedia sehingga 14 Julai 2026 sekitar 10:23 +0800. Jumlah tersebut mengandungi request pengguna, smoke test, characterization dan deliberate security probes. Oleh itu status 404 terhadap `/assetsM/test.php`, `/dist/test.php` dan executable vendor test paths bukan bukti aset runtime rosak.

Penemuan utama:

- `assetsM`, `dist`, `img`, `vendors`, `public_img` dan `public_docs` mempunyai caller atau runtime evidence aktif.
- `videos/video1.mp4` tidak mempunyai production caller statik; ia dikekalkan sementara kerana masih berada dalam verification contract.
- `/app-assets` tidak wujud dan tidak pernah dilihat dalam access log yang tersedia.
- Satu-satunya caller `/app-assets` ialah `admin/user_list.php`.

## Keputusan `/app-assets`

`admin/user_list.php` memuatkan `vendors.min.js` dan `jquery.tabletoCSV.js` daripada `/app-assets`, tetapi:

1. directory tersebut tidak wujud;
2. butang `#export` telah dikomen;
3. satu-satunya penggunaan jQuery ialah handler bagi butang yang tidak wujud;
4. paparan senarai dan database caller tidak memerlukan JavaScript.

Keputusan: buang dua script reference dan dead export handler. Compatibility directory palsu tidak dicipta.

## Cleanup fizikal

`public/assetsM/images/Thumbs.db` ialah Windows thumbnail metadata, bukan application asset. Fail ini dipindahkan ke:

```text
storage/quarantine/R5-4B-20260714-102434/payload/public/assetsM/images/Thumbs.db
```

Checksum sebelum move:

```text
60563cd6ca77f1288da081c7d33f9a786045d2ee0a3d7e5213c8eba092851b62
```

## Perubahan contract

- Characterization `admin user list` dikemas kini daripada tiga kepada sifar script tag.
- `tools/r54_asset_contract.php` memastikan `/app-assets` tidak kembali, role/data caller kekal, aset kritikal wujud dan public tree bebas OS metadata.

## Item tidak dibuang

- 3,907 frontend vendor files kekal kerana dashboard masih mempunyai runtime caller.
- 89 icon upload kekal kerana filename mungkin dirujuk database.
- `video1.mp4` kekal pending owner retention decision.
- Duplicate icon checksum tidak digunakan sebagai bukti orphan.

## Verification

```bash
php tools/r54_asset_contract.php
php tools/r52_dashboard_characterization.php
php tools/r54_structure_contract.php
php tools/restructure_smoke.php https://oneid.local --insecure
php tools/restructure_smoke.php https://oneid-next.local
```

Keputusan pelaksanaan:

| Verification | Keputusan |
|---|---|
| PHP lint `admin/user_list.php` | PASS |
| PHP lint R5.4B asset contract | PASS |
| R5.4B asset contract | PASS — `27/27` selepas URL routing guard |
| Dashboard characterization | PASS — `21/21` |
| R5.4A structure regression | PASS — `31/31` |
| R5.2 full characterization `oneid.local` | PASS — `70/70` |
| Smoke `oneid.local` | PASS — `10/10` |
| Smoke `oneid-next.local` | PASS — `10/10` |
| `/app-assets` dan `tableToCSV` reference scan | PASS — tiada reference |
| Quarantine checksum | PASS |
| Authenticated visual test `admin/user_list.php` | PASS — page dan data dipaparkan; asset errors hilang selepas URL fix |

Manual gate hanya perlu memastikan senarai kategori masih dipaparkan apabila dibuka melalui butang `User (...)` pada admin dashboard. Export tidak perlu diuji kerana fungsi itu memang tidak aktif sebelum R5.4B.

### Penemuan manual gate pertama

Ujian owner membuka URL berikut:

```text
/admin/user_list.php/?category_id=0&category_name=Pending%20Assign
```

Slash selepas `user_list.php` menyebabkan Nginx menganggap URL tersebut sebagai PATH_INFO/fallback route dan bukan PHP wrapper yang dimaksudkan. Browser kemudian menerima halaman lain pada URL bersarang dan menyelesaikan asset relatif sebagai `/admin/user_list.php/assetsM/...`, menghasilkan asset 404 serta `$ is not defined`.

Caller dalam `admin/dashboard.php` dibetulkan kepada:

```text
/admin/user_list.php?category_id=...&category_name=...
```

`category_name` turut melalui `encodeURIComponent()`. Asset contract kini mengunci ketiadaan `user_list.php/?`. Manual gate perlu diulang menggunakan URL tanpa slash selepas `.php`.

Semasa verification selepas penemuan ini, PHP-FPM menunjukkan `10` process aktif, `0` idle dan socket queue melebihi `40`; request CLI mendapat timeout/504 walaupun lint dan static contracts lulus. Ini ialah pool saturation pada host, bukan PHP syntax failure. Owner perlu restart `php8.3-fpm` sebelum mengulang manual gate dan kemudian memastikan pool kembali mempunyai idle worker.

### Manual gate kedua dan CSP warning

Selepas PHP-FPM direstart, status kembali sihat (`0` active, `4` idle) dan owner mengesahkan `admin/user_list.php` dipaparkan melalui URL tanpa trailing slash. Tiada lagi nested asset request atau `$ is not defined`; manual gate ditutup sebagai PASS.

Firefox masih memaparkan warning bahawa `Content-Security-Policy-Report-Only` tiada `report-uri` atau `report-to`. Header tersebut datang daripada `lib/config.php`. Warning ini tidak menyekat resource dan bukan regresi R5.4B. Ia direkodkan sebagai kerja security-header berasingan: sama ada menyediakan authenticated/rate-limited CSP report collector, atau membuat keputusan rasmi untuk enforcement/removal. R5.4B tidak mengubah CSP secara ad hoc.

## Rollback

Selepas commit, rollback code paling selamat ialah:

```bash
git revert <R5.4B-commit-hash>
```

Restore metadata hanya jika benar-benar diperlukan:

```bash
cd /var/www/app/oneid-uat
mkdir -p public/assetsM/images
mv storage/quarantine/R5-4B-20260714-102434/payload/public/assetsM/images/Thumbs.db \
  public/assetsM/images/Thumbs.db
```

Tiada rollback database, vendor atau upload diperlukan.
