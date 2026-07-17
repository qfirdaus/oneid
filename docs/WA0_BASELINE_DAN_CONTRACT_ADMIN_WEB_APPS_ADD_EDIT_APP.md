# WA0 — Baseline dan Contract Admin Web Apps Add/Edit App

**Tarikh:** 17 Julai 2026  
**Status teknikal:** SELESAI UNTUK WSL/SHARED DATABASE  
**Gate WA0:** TERBUKA — baseline filesystem staging dan keputusan owner masih berbaki  
**Jenis aktiviti:** Audit read-only dan dokumentasi sahaja

## 1. Objektif

WA0 membekukan gambaran sebenar aliran Add/Edit App sebelum pembetulan dibuat,
menetapkan sempadan shared database berbanding filesystem khusus environment dan
mewujudkan kontrak yang akan digunakan oleh WA1–WA7.

Aktiviti ini tidak membuat migration, tidak mengubah behavior aplikasi dan tidak
memadam, memindah atau menyalin fail gambar.

## 2. Evidence yang diwujudkan

- caller map: `docs/WA0_WEB_APPS_ADD_EDIT_CALLER_MAP.tsv`;
- decision register: `docs/WA0_WEB_APPS_ADD_EDIT_DECISION_REGISTER.tsv`;
- audit berulang: `tools/wa0_web_app_asset_audit.php`;
- dokumen audit induk:
  `docs/AUDIT_DAN_PELAN_PENAMBAHBAIKAN_ADMIN_WEB_APPS_ADD_EDIT_APP.md`.

Arahan audit WSL:

```bash
php tools/wa0_web_app_asset_audit.php
```

Skrip hanya melaksanakan `SELECT` database dan pemeriksaan filesystem; ia tidak
mengubah state.

## 3. Baseline shared database dan filesystem WSL

Snapshot diambil pada **17 Julai 2026, 13:25:57 +08:00**.

| Metrik | Nilai |
| --- | ---: |
| Jumlah aplikasi | 72 |
| Aplikasi aktif | 36 |
| Aplikasi inactive | 36 |
| Row dengan `sp_image` tidak kosong | 61 |
| Rujukan gambar unik | 61 |
| Aplikasi aktif tanpa gambar | 1 |
| Kumpulan nama duplicate selepas trim/lowercase | 16 |
| Kumpulan domain duplicate selepas trim/lowercase | 6 |
| URL bukan HTTPS atau tidak dapat diparse sebagai absolute URL | 12 |
| Jadual `sp_app_asset` | Belum wujud |
| Fail icon aplikasi pada filesystem WSL | 90 |
| Rujukan DB yang failnya hilang di WSL | 0 |
| Fail calon orphan di WSL | 29 |

Target upload dan resolved path ketika audit ialah
`/var/www/app/oneid-uat/public/public_img`.

Interpretasi penting:

- 29 fail hanyalah **calon orphan WSL**, bukan kelulusan untuk deletion;
- angka duplicate merangkumi active dan inactive legacy records dan bukan alasan
  untuk mutation automatik;
- 12 URL perlu diklasifikasi semasa WA2 kerana sebahagiannya mungkin data legacy
  yang memerlukan exception/normalization;
- zero missing reference hanya mengesahkan WSL, bukan staging.

## 4. Baseline staging

Filesystem staging tidak boleh diinfer daripada WSL atau shared database. Audit
yang sama mesti dijalankan dari checkout staging selepas fail audit tersedia di
server tersebut:

```bash
php tools/wa0_web_app_asset_audit.php
```

Output staging perlu direkodkan tanpa menyalin atau membersihkan fail. Gate WA0
tidak ditutup sehingga sekurang-kurangnya metrik berikut diterima:

- resolved upload directory staging;
- jumlah local icon files;
- jumlah missing referenced files;
- jumlah orphan candidate files.

## 5. Contract metadata aplikasi

1. `sp_list` kekal sumber metadata global aplikasi.
2. Nama, description, URL, kategori, status dan mod SSO ialah metadata global;
   perubahan sah akan kelihatan pada WSL dan staging kerana database dikongsi.
3. Backend, bukan JavaScript, menjadi authority validation.
4. App ID mesti dijana secara kriptografi dan collision mesti ditangani tanpa
   overwrite.
5. Semantik SSO mesti eksplisit; nilai `0/1` legacy tidak boleh diterangkan hanya
   melalui checkbox kabur.
6. Polisi duplicate tidak dikuatkuasakan sehingga legacy duplicates dinilai dan
   owner mengesahkan keputusan WA0-D10.

## 6. Contract aset environment

1. Setiap deployment mempunyai identiti runtime eksplisit; identiti tidak boleh
   diteka daripada HTTP `Host`.
2. Rujukan aset sasaran menggunakan `(sp_id, environment)`.
3. WSL hanya menulis/membaca fail WSL; staging hanya menulis/membaca fail
   staging.
4. Tiada fallback merentas filesystem atau URL environment lain.
5. Aset yang tiada menggunakan placeholder standard dan menghasilkan state yang
   boleh dikenal pasti; ia bukan broken image senyap.
6. Nama fail client tidak digunakan sebagai target; server menjana nama selamat.
7. Validation mesti merangkumi saiz byte, MIME/signature, dimensi/pixel count,
   format dan polisi animation.
8. Fail akhir perlu dihasilkan melalui decode/re-encode server mengikut format
   yang diluluskan owner.

## 7. Contract mutation dan atomicity

1. Add/Edit tidak boleh memulangkan success apabila upload yang diminta gagal.
2. Upload baharu bermula sebagai fail sementara dan tidak mengganti aset aktif
   sebelum validation serta persistence berjaya.
3. Kegagalan database/audit/publish mencetuskan compensation yang selamat dan
   tidak meninggalkan rujukan baharu kepada fail yang tiada.
4. Gambar lama dikekalkan sehingga gambar baharu disahkan; cleanup lama berlaku
   berasingan selepas retention/quarantine.
5. Edit tanpa perubahan ialah outcome jelas, bukan kegagalan generik.
6. Setiap response mempunyai `status`, `code`, mesej selamat dan
   `correlation_id`.
7. Audit merekod actor, environment, app ID, action, outcome dan correlation;
   ia tidak boleh mendakwa success jika mutation gagal.
8. UI mesti mempunyai confirmation yang sesuai, loading state dan double-submit
   guard.

## 8. Contract compatibility dan rollout

1. Migration database dilaksanakan sekali sahaja kerana database dikongsi.
2. Migration WA4 mesti expanding dan tidak memecahkan kod lama yang masih
   membaca `sp_list.sp_image`.
3. Kod lama dan baharu mesti boleh hidup bersama sepanjang deployment window.
4. `sp_list.sp_image` tidak dibuang dalam rollout awal.
5. Rollback kod tidak boleh memerlukan deletion jadual/column secara segera.
6. Cleanup filesystem sentiasa berasingan mengikut environment dan memerlukan
   inventori, manifest serta kelulusan owner.

## 9. Gate sebelum WA1

WA1 (UI dan operational feedback) boleh dimulakan tanpa migration selepas owner
menerima contract response/feedback. Walau bagaimanapun, WA4 tidak boleh bermula
sehingga perkara berikut selesai:

- baseline filesystem staging diterima;
- nilai rasmi `ONEID_ENVIRONMENT` diputuskan;
- polisi icon, URL, duplicate dan retention diputuskan;
- strategi compatibility serta deployment order diluluskan.

Keputusan tertunggak disenaraikan dalam decision register. Nilai `PROPOSED`
bukan persetujuan owner dan tidak boleh dianggap sebagai authorization untuk
implementation.

## 10. Rollback WA0

WA0 tidak mempunyai rollback data kerana tiada mutation dibuat. Jika rekod perlu
ditarik balik, hanya tiga fail dokumentasi WA0 dan skrip audit read-only perlu
dikeluarkan. Fail aplikasi, database dan `public/public_img` tidak disentuh.
