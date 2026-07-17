# WA5 — Normalisasi dan Keselamatan Imej Web Apps

**Tarikh:** 17 Julai 2026
**Status:** COMPLETE — AUTOMATED CONTRACT DAN UAT PILOT LULUS; MANUAL OVERSIZED DITERIMA OWNER
**Schema migration:** Tiada

## 1. Baseline dan capability

- PHP GD 2.3.3 aktif dengan read/write JPEG, PNG, GIF dan WebP;
- Imagick tidak tersedia dan tidak diperlukan;
- baseline WSL mempunyai 96 app icon, semuanya PNG dan boleh didecode;
- dimensi legacy terbesar 1094×500, 437,600 pixel.

Fail legacy tidak dinormalisasi semula dan tidak dipadam.

## 2. Polisi upload baharu

- input: JPEG, PNG, GIF atau WebP;
- maksimum fail: 5 MB;
- maksimum width/height: 4096×4096;
- maksimum decoded pixel: 16,000,000;
- animated GIF, animated WebP dan APNG ditolak;
- server decode input menggunakan GD;
- JPEG EXIF orientation 3/6/8 digunakan sebelum resize apabila EXIF tersedia;
- output canonical: static PNG 256×256;
- aspect ratio dikekalkan menggunakan contain-fit dan transparent padding;
- output menggunakan nama rawak `app_icon_<random>.png`;
- re-encode membuang metadata/embedded payload daripada output yang disimpan.

Client MIME/size check kekal sebagai feedback awal sahaja. Backend decode,
dimension dan re-encode ialah authority keselamatan.

## 3. Integrasi WA3/WA4

Normalisasi berlaku ketika fail masih dalam non-public staging. Hanya canonical
PNG yang dipublish selepas metadata, environment asset dan audit bersedia.
Kegagalan decode/normalisasi menghasilkan `WA3_ICON_REJECTED` sebelum mutation.
Transaction, compensation dan environment isolation tidak berubah.

## 4. Failure behavior

- format/MIME mismatch: ditolak oleh validation sedia ada;
- dimensi/pixel terlalu besar: `Image dimensions exceed the allowed limit`;
- animated input: `Animated images are not allowed`;
- GD/decode/output failure: fail closed dan tiada metadata mutation;
- fail sementara/partial normalized output dibuang.

## 5. Automated verification

```bash
php tools/wa5_image_normalization_contract.php
php tools/wa1_web_app_ui_contract.php
php tools/wa2_web_app_service_contract.php
php tools/wa3_web_app_atomic_contract.php
php tools/wa4_environment_asset_contract.php
```

WA5 contract menghasilkan imej sintetik hanya dalam system temporary directory,
memeriksa output/dimensi/alpha/animation rejection dan memadam semuanya selepas
ujian. Ia tidak menulis database atau runtime upload directory.

## 6. Manual UAT

1. Upload JPEG landscape pada app pilot; jangka success dan output `.png`.
2. Sahkan file hasil tepat 256×256 PNG.
3. Upload PNG portrait/transparency; sahkan contain-fit tanpa distortion.
4. Cuba animated GIF/APNG/WebP; jangka `WA3_ICON_REJECTED`.
5. Cuba imej melebihi 4096 atau 16 MP; jangka rejection dan metadata lama kekal.
6. Sahkan isolation WSL/staging masih kekal selepas normalized upload.

### Evidence deployment staging — owner, 17 Julai 2026

- fast-forward staging daripada `5104dd5` kepada `dea7278`;
- runtime identity = `staging`;
- PHP GD = available;
- WA1 13/13, WA2 14/14, WA3 13/13, WA4 13/13 dan WA5 10/10 PASS;
- `public/public_img` = `iqs:www-data`, mode `2775`;
- write probe PHP-FPM user `www-data` = PASS.

Deployment/automated gate WA5: **PASS**. Pada masa deployment ini, manual
normalized-image UAT masih berbaki dan kemudiannya dilaksanakan seperti bukti
di bawah. Warning Git ketika tidak boleh traverse private
`storage/runtime` diselesaikan dengan ignore directory tersebut; permission
`0700` milik PHP-FPM tidak dilonggarkan.

### Evidence UAT normalisasi staging — owner, 17 Julai 2026

- app pilot: `T9U927YQ52` (`WA5 Image Normalization Pilot`);
- environment: `staging`;
- environment asset: `app_icon_85e087c2b544d09c1dfc3fc22f53afd8.png`;
- asset `updated_at`: `2026-07-17 16:00:30`;
- pemeriksaan server: `256x256 image/png`;
- keputusan probe: `PASS WA5 normalized image`;
- pengesahan visual owner: imej dipaparkan dengan betul tanpa distortion;
- semakan filesystem WSL: fail staging
  `app_icon_85e087c2b544d09c1dfc3fc22f53afd8.png` tidak wujud (`PASS`).

UAT normalisasi utama WA5: **PASS**. Fail legacy WA4 yang masih berukuran
500x500 tidak dikira kegagalan kerana WA5 tidak memigrasi aset lama secara
automatik. Isolation staging ke WSL bagi aset pilot turut disahkan.

### Evidence UAT animated GIF rejection — owner, 17 Julai 2026

- input: animated GIF pada app pilot `T9U927YQ52`;
- keputusan UI: `WA3_ICON_REJECTED`;
- correlation ID: `2e0c5faf4b4d6137`;
- mesej: operation rejected/rolled back dan tiada completed update direkodkan;
- keputusan: **PASS**.

Selepas ujian, rekod environment asset semasa ialah
`app_icon_90e9bc16cc1bcfae741f41823c4031fd.png` dengan `updated_at`
`2026-07-17 16:07:33`. Oleh sebab rekod ini berbeza daripada baseline awal,
ia ditetapkan sebagai baseline terkini untuk pengesahan rejection oversized.
Fail tersebut turut disahkan tidak wujud dalam filesystem WSL.

### UAT imej besar dan keputusan penutupan — owner, 17 Julai 2026

- satu imej yang dianggap besar oleh owner diterima kerana masih memenuhi
  had teknikal WA5;
- keputusan UI: `WA4_APP_UPDATED_ENVIRONMENT_ASSET`;
- correlation ID: `1c0359a49c28f000`;
- environment asset baharu:
  `app_icon_2687aba7bdd0ff1f720e996088248722.png`;
- asset `updated_at`: `2026-07-17 16:26:16`;
- dimensi fail sumber tidak disahkan melebihi 4096px, maka percubaan ini tidak
  diklasifikasikan sebagai PASS bagi manual oversized rejection;
- owner tidak mempunyai fixture oversized dan menerima penutupan WA5
  berdasarkan automated dimension-limit contract yang lulus.

Keputusan penutupan WA5: **COMPLETE**. Automated contract membuktikan input
melebihi had dimensi ditolak, manakala UAT pilot membuktikan normalisasi,
paparan visual, animated GIF rejection dan filesystem isolation. Manual UI
oversized rejection kekal sebagai ujian tambahan yang boleh diulang apabila
fixture sesuai tersedia; ia bukan blocker penutupan yang dipersetujui owner.

## 7. Rollback

Pulihkan staged upload kepada penyimpanan format asal. Tiada schema rollback.
Canonical PNG yang telah committed kekal valid dan tidak perlu ditukar semula.
