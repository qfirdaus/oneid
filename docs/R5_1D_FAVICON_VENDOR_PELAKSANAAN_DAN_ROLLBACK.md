# R5.1D — Physicalize Favicon dan Vendor Runtime

**Change ID:** `R5-1D-20260714-025144`  
**Tarikh:** 14 Julai 2026  
**Status:** COMPLETE — visual/network validation lulus; observation window aktif  
**Change owner / rollback owner:** Pemilik sistem UAT

## 1. Skop

Menggantikan enam symlink terakhir dalam public-root dengan salinan fizikal:

| Public path | Source lama | Jenis |
|---|---|---|
| `public/favicon.ico` | `favicon.ico` | fail |
| `public/admin/favicon.ico` | `admin/favicon.ico` | fail |
| `public/page/favicon.ico` | `page/favicon.ico` | fail |
| `public/vendors/bower_components` | `vendors/bower_components` | direktori |
| `public/vendors/typeahead.js` | `vendors/typeahead.js` | fail |
| `public/vendors/vectormap` | `vendors/vectormap` | direktori |

Selepas change, `public/` tidak lagi mengandungi symlink. Source lama tidak
dipadam dan dependency tidak di-upgrade, dibina semula atau dibersihkan.

## 2. Baseline

| Item | Nilai |
|---|---:|
| Fail vendor | 3,907 |
| Favicon | 3 fail, checksum sama, 1,150 bait setiap satu |
| Jumlah manifest | 3,910 fail |
| `bower_components` | kira-kira 70 MB |
| `typeahead.js` | kira-kira 100 KB |
| `vectormap` | kira-kira 568 KB |
| Internal/broken symlink | 0 / 0 |
| URL vendor gabungan log/source | 26 |
| URL tersedia sebelum switch | 24 |
| Pre-existing missing Typeahead references | 2; redirect 302 ke front controller |
| Script server-side/demo ditemui | 6 |

Dua reference berikut tidak mempunyai source sebelum change dan menghasilkan
HTTP 302 melalui front controller:

```text
/vendors/bower_components/typeahead.js/data/countries.js
/vendors/bower_components/typeahead.js/data/nfl.js
```

R5.1D sengaja mengekalkan tingkah laku tersebut. Ia direkod sebagai technical
debt dan bukan diperbaiki dengan mencipta dummy file.

Enam fail PHP/shell demo/test wujud dalam Bower. Nginx R4 memblok extension
server-side di folder vendor. Probe PHP dan shell telah menghasilkan 404 sebelum
dan selepas switch.

## 3. Approved Continuation

Owner mengarahkan R5.1D diteruskan walaupun screenshot/hard-refresh console
R5.1C tidak dihantar. Automated R5.1C lulus dan source/rollback dikekalkan.
Keputusan ini ialah approval meneruskan change, bukan bukti visual review.

## 4. Staging dan Evidence

```text
storage/quarantine/R5-1D-20260714-025144/
```

Evidence:

```text
source.sha256
source.pre-switch.sha256
staged.sha256
public-after-switch.sha256
runtime-vendor-urls.before.txt
static-vendor-urls.before.txt
vendor-urls.combined.before.txt
original-symlinks/favicon.ico
original-symlinks/admin-favicon.ico
original-symlinks/page-favicon.ico
original-symlinks/vendor-bower_components
original-symlinks/vendor-typeahead.js
original-symlinks/vendor-vectormap
```

Checksum 3,910 fail, recursive diff dan pre-switch race-check lulus.

## 5. Validation Selepas Switch

| Pemeriksaan | Keputusan |
|---|---|
| Smoke `oneid.local` | 10/10 lulus |
| Smoke `oneid-next.local` | 10/10 lulus |
| 24 URL vendor tersedia, dua host | 48/48 status 200/304 |
| 2 baseline Typeahead redirect, dua host | 4/4 kekal HTTP 302 |
| 3 favicon, dua host | 6/6 HTTP 200 `image/x-icon`, 1,150 bait |
| `typeahead.js` dan vectormap CSS, dua host | 4/4 HTTP 200 |
| PHP/shell vendor probe, dua host | 4/4 HTTP 404 |
| Broken symlink | 0 |
| Jumlah symlink dalam `public/` | 0 |
| Fatal PHP / Nginx critical baharu | 0 |

## 6. Rollback

```bash
cd /var/www/app/oneid-uat

change_id='R5-1D-20260714-025144'
base="storage/quarantine/${change_id}"

mv public/favicon.ico "$base/favicon.failed"
mv "$base/original-symlinks/favicon.ico" public/favicon.ico

mv public/admin/favicon.ico "$base/admin-favicon.failed"
mv "$base/original-symlinks/admin-favicon.ico" public/admin/favicon.ico

mv public/page/favicon.ico "$base/page-favicon.failed"
mv "$base/original-symlinks/page-favicon.ico" public/page/favicon.ico

mv public/vendors/bower_components "$base/vendor-bower_components.failed"
mv "$base/original-symlinks/vendor-bower_components" public/vendors/bower_components

mv public/vendors/typeahead.js "$base/vendor-typeahead.failed"
mv "$base/original-symlinks/vendor-typeahead.js" public/vendors/typeahead.js

mv public/vendors/vectormap "$base/vendor-vectormap.failed"
mv "$base/original-symlinks/vendor-vectormap" public/vendors/vectormap

php tools/restructure_smoke.php https://oneid.local --insecure
php tools/restructure_smoke.php https://oneid-next.local
```

Nginx reload tidak diperlukan. Source root vendor/favicon mesti dikekalkan
sepanjang observation dan sehingga R5.2 mempunyai rollback baseline sendiri.

## 7. Gate Penutupan R5.1

- [x] Semua public symlink diphysicalize.
- [x] Dua hostname lulus automated smoke.
- [x] Upload icon menulis ke physical public-root.
- [x] Vendor script execution kekal diblok.
- [x] Owner hard-refresh login, user dan admin selepas R5.1D; visual dilaporkan normal.
- [x] Network tiada 404/5xx aset baharu selain probe terkawal; dua baseline Typeahead kekal 302.
- [ ] Console tiada error baharu — tidak disertakan sebagai evidence berasingan.
- [ ] Observation window tamat tanpa regression.

R5.2 application-layer refactor tidak boleh dilakukan sebagai sambungan segera.
Ia memerlukan characterization test, caller map dan observation kerana perubahan
seterusnya melibatkan wrapper PHP, bukan lagi aset statik.

### Visual/network confirmation — 03:01 +0800

Owner mengesahkan telah melakukan hard refresh dan semua paparan kelihatan
normal. Access/error log menyokong pengesahan tersebut:

- pada 02:59, CSS, JavaScript, font, banner, logo, vendor dan icon menerima
  HTTP 200/304;
- `GET /page/dashboard` selepas hard refresh menerima HTTP 200;
- tiada 404/5xx baharu pada aset runtime;
- 404 yang kelihatan ialah security probe terkawal R5.1;
- tiada `Permission denied`, missing physical file, fatal/parse/uncaught PHP atau
  Nginx critical baharu.

R5.1 ditutup dari aspek implementation dan visual/network validation. Source
lama serta rollback evidence mesti kekal sepanjang observation sebelum R5.2.
