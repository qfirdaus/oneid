# WA6 — Reconciliation dan Cleanup Terkawal Web Apps

**Tarikh mula:** 17 Julai 2026
**Status:** INVENTORI READ-ONLY TERSEDIA — CLEANUP BELUM DIBENARKAN

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
