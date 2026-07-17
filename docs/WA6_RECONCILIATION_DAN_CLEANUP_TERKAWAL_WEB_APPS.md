# WA6 — Reconciliation dan Cleanup Terkawal Web Apps

**Tarikh mula:** 17 Julai 2026
**Status:** IMPLEMENTATION COMPLETE — LOCAL/STAGING VISUAL UAT PASS; OBSERVATION 30 HARI AKTIF

## Objektif

WA6 mengasingkan laporan aset mengikut `ONEID_ENVIRONMENT`, mengenal pasti
reference yang hilang dan fail yang tidak dirujuk, serta menghasilkan hash
untuk semakan owner. Shared database tidak bermaksud filesystem local dan
staging boleh digabungkan.

## Kontrak keselamatan

- tool WA6 tidak menulis database;
- tool WA6 tidak memindah, menamakan semula atau memadam fail;
- `missing_references` menggunakan effective read contract WA4, termasuk
  legacy fallback hanya apabila environment row tiada;
- `orphan_candidates` ialah calon semakan, bukan arahan pemadaman;
- setiap calon mempunyai saiz, modification time dan SHA-256;
- setiap calon mempunyai umur fail, bilangan duplicate content dalam filesystem
  semasa dan reference database di luar effective environment;
- manifest local dan staging mesti dijana serta diluluskan berasingan;
- quarantine/grace period dan deletion hanya boleh dibina selepas keputusan
  owner terhadap manifest dan tempoh simpanan.

## Arahan inventori

```bash
php tools/wa6_web_app_asset_reconciliation.php
```

Output JSON menyatakan environment, ringkasan, missing reference, orphan
candidate dan safety gate. Output perlu disimpan sebagai bukti luar repository
atau disalin ke rekod UAT selepas disemak; runtime report tidak perlu
di-commit.

## Gate seterusnya

1. Jalankan laporan pada WSL/local.
2. Commit/push tool dan tarik ke staging sebelum laporan staging dijana.
3. Jalankan laporan pada staging.
4. Bandingkan manifest tanpa menganggap fail environment lain sebagai missing
   atau orphan bagi environment semasa.
5. Owner sahkan senarai tepat serta grace period.
6. Hanya selepas itu reka rehearsal quarantine dan rollback; deletion kekal
   langkah terakhir dengan kelulusan berasingan.

## Evidence manifest local — 17 Julai 2026

Manifest read-only selepas pilot WA5 diarkibkan menghasilkan:

- 76 rekod aplikasi database;
- 63 effective referenced files;
- 96 icon files dalam filesystem local;
- 0 missing reference;
- 33 orphan candidate dengan jumlah 1.65 MiB;
- 32 calon tidak dirujuk oleh mana-mana row database;
- 1 calon masih mempunyai reference legacy di luar effective local contract;
- 6 calon berumur kurang daripada 30 hari;
- 2 kumpulan duplicate content berdasarkan SHA-256.

Fail `app_icon_e4d7802cbca3ac33e90af6e6bb904a36.png` masih dirujuk oleh
legacy global row bagi app `2WJ4USYRS9`; ia dilindungi daripada quarantine.
Semua enam fail berumur kurang 30 hari turut dilindungi sehingga grace period
dipersetujui dan tamat. Baki 27 fail lama hanya menjadi shortlist semakan;
quarantine belum diluluskan.

## Evidence manifest staging — 17 Julai 2026

Manifest read-only staging selepas deployment commit `307d9aa` menghasilkan:

- runtime environment `staging`;
- 76 rekod aplikasi database;
- 64 effective referenced files;
- 95 icon files dalam filesystem staging;
- 0 missing reference;
- 31 orphan candidate dengan jumlah 1.58 MiB;
- kesemua 31 calon tidak dirujuk oleh mana-mana row database;
- 0 calon mempunyai reference di luar effective staging environment.

Perbezaan bilangan local/staging adalah dijangka kerana kedua-dua filesystem
tidak dikongsi. Senarai filename, umur dan hash staging masih perlu direkod
sebelum shortlist tepat boleh diluluskan. Tiada mutation dibuat.

### Klasifikasi staging

Daripada 31 calon staging:

- 27 fail berumur 95 hingga 365 hari dan masuk shortlist semakan quarantine;
- 4 fail berumur 0 hingga 3 hari dilindungi oleh cadangan grace period 30 hari;
- semua calon mempunyai 0 database reference;
- duplicate count hanya bukti kandungan sama dalam filesystem dan tidak memberi
  kebenaran untuk memadam salinan lain yang mungkin masih dirujuk.

Fail staging yang dilindungi oleh umur ialah:

- `app_icon_2687aba7bdd0ff1f720e996088248722.png` (0 hari);
- `app_icon_5a8c8ab9235c96ac5a6ef19a97bbcaf6.png` (3 hari);
- `app_icon_85e087c2b544d09c1dfc3fc22f53afd8.png` (0 hari);
- `app_icon_90e9bc16cc1bcfae741f41823c4031fd.png` (0 hari).

Shortlist 27 fail lama belum dipindahkan. Gate seterusnya memerlukan owner
mengesahkan grace period 30 hari dan meluluskan quarantine bagi 27 filename
lama dalam manifest staging. Permanent deletion tidak termasuk dalam
kelulusan quarantine ini.

## Keputusan owner dan quarantine contract

Owner meluluskan grace period 30 hari dan membenarkan quarantine rehearsal
terhadap shortlist lama. Kelulusan ini tidak membenarkan permanent deletion.

Tool `tools/wa6_web_app_asset_quarantine.php`:

- default kepada dry-run;
- memilih hanya calon tanpa sebarang database reference dan berumur minimum
  30 hari;
- revalidate saiz dan SHA-256 sebelum move;
- menyimpan manifest private per environment/batch;
- rollback semua move jika batch gagal;
- mempunyai operasi restore dengan hash verification;
- tidak mempunyai operasi permanent deletion.

Rehearsal pertama mesti menggunakan `--limit=1`, diikuti semakan reconciliation,
restore batch dan semakan hash/reconciliation semula. Batch penuh hanya boleh
dilaksanakan selepas rehearsal tersebut lulus.

## Evidence quarantine/restore rehearsal staging — 17 Julai 2026

- automated quarantine contract: 11/11 PASS;
- dry-run memilih `app_icon_1752725496.png`, umur 365 hari, tanpa mutation;
- quarantine batch: `20260717T171407-18a202a1`;
- selepas quarantine: filesystem 94, missing 0, orphan candidate 30;
- restore batch: PASS, 1 fail dipulihkan;
- selepas restore: filesystem 95, missing 0, orphan candidate 31;
- semua 31 calon kekal tanpa database reference selepas restore.

Rehearsal move, manifest, reconciliation dan rollback: **PASS**. Gate
quarantine penuh bagi 27 fail staging berumur minimum 30 hari dibuka berdasarkan
kelulusan owner. Empat fail baharu kekal dilindungi dan permanent deletion
masih tidak dibenarkan.

## Evidence quarantine penuh staging — 17 Julai 2026

- batch ID: `20260717T171913-77b21c8a`;
- environment: `staging`;
- 27 fail berumur 95 hingga 365 hari dipindahkan ke private quarantine;
- filesystem icon files turun daripada 95 kepada 68;
- orphan candidate turun daripada 31 kepada 4;
- kesemua 4 baki calon tidak dirujuk database tetapi dilindungi oleh umur;
- missing reference kekal 0;
- database mutation: tiada;
- permanent deletion: tidak dibenarkan.

Batch penuh staging memasuki observation period 30 hari. Restore keseluruhan
batch boleh dilakukan dengan:

```bash
php tools/wa6_web_app_asset_quarantine.php \
  --restore=20260717T171913-77b21c8a
```

Jangan jalankan restore kecuali berlaku regression, dan jangan lakukan deletion
selepas observation period tanpa reconciliation baharu serta kelulusan owner.

## Evidence quarantine local — 17 Julai 2026

- automated quarantine contract: 11/11 PASS;
- rehearsal batch: `20260717T172452-651142b0`;
- reconciliation selepas rehearsal: filesystem 95, missing 0, orphan 32;
- restore rehearsal: PASS dan baseline kembali kepada filesystem 96, missing 0,
  orphan 33;
- batch penuh: `20260717T172513-c6ea7e4d`;
- 27 fail berumur minimum 30 hari dipindahkan ke private quarantine;
- selepas batch penuh: filesystem 69, missing 0, orphan candidate 6;
- lima baki calon tidak dirujuk database tetapi dilindungi oleh umur;
- satu baki calon mempunyai legacy global reference dan dilindungi;
- database mutation dan permanent deletion: tiada.

Rollback batch penuh local, jika regression disahkan:

```bash
php tools/wa6_web_app_asset_quarantine.php \
  --restore=20260717T172513-c6ea7e4d
```

Quarantine teknikal local dan staging: **PASS**. Kedua-dua batch penuh memasuki
observation period 30 hari. Visual UAT selepas quarantine perlu disahkan pada
kedua-dua environment sebelum implementation gate ditutup; permanent deletion
kekal memerlukan reconciliation dan kelulusan baharu selepas observation.

## Visual UAT selepas quarantine — owner, 17 Julai 2026

Owner mengesahkan melalui paparan Admin Web Apps bahawa:

- local memaparkan ikon aplikasi bagi kategori HR dengan betul;
- staging memaparkan ikon aplikasi bagi kategori NON SSO dengan betul;
- jumlah 36 Web Apps dan pecahan kategori kekal dipaparkan;
- tiada broken image atau placeholder yang tidak dijangka kelihatan.

Visual UAT local/staging: **PASS**. Implementation gate WA6 ditutup. Batch local
`20260717T172513-c6ea7e4d` dan batch staging
`20260717T171913-77b21c8a` kekal dalam private quarantine sepanjang observation
30 hari. Permanent deletion bukan sebahagian daripada implementation complete
ini dan memerlukan reconciliation serta kelulusan baharu selepas tempoh tamat.
