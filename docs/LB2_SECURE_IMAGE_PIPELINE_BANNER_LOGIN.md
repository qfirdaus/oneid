# LB2 — Secure Image Pipeline Banner Login

**Tarikh:** 1 Ogos 2026  
**Status:** LOCAL PASS / BEHAVIOR 13 OF 13 PASS / SOURCE CONTRACT 10 OF 10 PASS  
**Database mutation:** Tiada  

## 1. Tujuan

LB2 menyediakan boundary fail yang selamat sebelum domain service atau endpoint
Admin dibina. Pipeline menerima upload, mengesahkan sumber dan kandungan,
menormalisasi imej kepada output canonical, menyimpan sementara di direktori
private dan menerbitkan fail immutable secara atomic.

## 2. Contract input

- upload provenance disahkan dengan `is_uploaded_file()` secara default;
- `UPLOAD_ERR_OK` wajib;
- actual size mesti sama dengan reported size;
- maksimum input 5 MB;
- format JPEG, PNG atau WebP statik sahaja;
- MIME diperoleh melalui `finfo`, disilang semak dengan `getimagesize`;
- client filename dan client MIME tidak dipercayai;
- minimum 1600x800, maksimum 4096x2048 dan 16 juta piksel;
- nisbah perlu berada dalam toleransi 0.02 daripada `2:1`;
- animated PNG (`acTL`) dan animated WebP (`ANIM`) ditolak; dan
- symlink source ditolak.

Had minimum mengelakkan upscale imej kecil yang menghasilkan banner kabur.
Toleransi ratio membenarkan perbezaan rounding kecil tetapi menghalang crop
besar yang boleh memotong teks/logo.

## 3. Normalization

Imej didecode menggunakan GD dan dirender semula kepada canvas baharu. Proses
ini membuang filename asal, metadata EXIF dan payload yang tidak menjadi piksel.
Orientasi JPEG yang sah digunakan sebelum semakan ratio akhir.

Output:

- WebP statik;
- tepat 1600x800;
- quality dicuba 82, 76, 70, 64, 58 kemudian 52;
- output mesti 1 hingga 512,000 byte;
- SHA-256 dikira daripada fail akhir; dan
- metadata output disahkan sekali lagi sebelum dikembalikan.

Jika output masih melebihi had pada quality 52, request ditolak. Pipeline tidak
menerbitkan fail yang melanggar contract semata-mata untuk menghasilkan
success.

## 4. Staging dan publish

Staging:

- direktori private dicipta mode 0700;
- fail `.pending_login_banner_{random}` mode 0600;
- nama live `login_banner_{32 hex}.webp` dijana server;
- normalisasi gagal akan membersihkan partial file.

Publish:

- staged file mesti regular file dan bukan symlink;
- filename, dimensions, size dan digest disahkan semula;
- target sedia ada tidak pernah dioverwrite;
- `rename()` digunakan untuk atomic publish pada filesystem sama;
- output live ditetapkan mode 0644; dan
- kegagalan permission selepas rename membersihkan target partial.

Caller LB3 bertanggungjawab memastikan staging dan published directory berada
pada filesystem sama. Jika deployment menggunakan mount berlainan, preflight
LB7 mesti menolaknya atau pipeline perlu menggunakan strategi publish lain yang
diluluskan.

## 5. Compensation

`discardStaged()` membuang output private yang belum dipublish.
`discardPublished()` hanya menerima exact path yang diketahui oleh caller.
Kedua-duanya turut menerima direktori yang diluluskan dan menolak path di luar
direktori atau filename yang tidak mengikut corak pipeline.
Domain service nanti perlu memanggil fungsi ini apabila transaction/audit gagal
selepas filesystem publish.

Pipeline tidak menjalankan scan atau cleanup direktori dan tidak memadam aset
lama. Reconciliation/quarantine kekal LB7.

## 6. Test coverage

Behavioral characterization merangkumi:

- valid PNG menjadi immutable WebP;
- exact dimensions/size/digest;
- atomic move;
- collision tanpa overwrite;
- cleanup staging;
- digest tamper rejection;
- ratio invalid tanpa orphan;
- reported/actual size mismatch;
- fake image rejection; dan
- exact published compensation.

Source contract mengunci upload provenance, inspection, limits, animation
rejection, re-encode, adaptive quality, private staging, immutable publish,
compensation dan dormant boundary.

## 7. Commands

```bash
php -l app/LoginBanner/LoginBannerImageException.php
php -l app/LoginBanner/LoginBannerImagePipeline.php
php tests/characterization/lb2_login_banner_image_pipeline.php
php tools/lb2_login_banner_contract.php
```

## 8. Boundary dan handoff

LB2 tidak:

- menerima HTTP request sebenar;
- menambah action map atau UI Admin;
- menulis database/history;
- apply migration LB1;
- menukar `index.php`; atau
- membuat cleanup aset lama.

Langkah seterusnya ialah LB3 domain service dan audit atomic. LB3 perlu
menggabungkan pipeline ini dengan persistence melalui transaction dan
compensation tanpa menambah endpoint dahulu.

Semua syntax, behavioral, source dan LB0/LB1 regression checks lulus pada
1 Ogos 2026.

**Keputusan semasa:** LB2 LOCAL PASS / LOGIN RUNTIME AND DATABASE UNCHANGED.
