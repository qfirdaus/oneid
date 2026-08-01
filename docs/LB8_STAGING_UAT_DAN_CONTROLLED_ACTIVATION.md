# LB8 — Staging UAT dan Controlled Activation Gate

**Status:** STAGING UAT COMPLETE / OWNER ACCEPTED / FEATURE ACTIVE

LB8 menyediakan proses staging yang mengeluarkan `GO_STAGING_ACTIVATION` hanya
apabila semua prerequisite teknikal, backup, restore, UAT dan owner acceptance
lulus. Gate bersifat read-only; ia tidak menjalankan migration, mengubah runtime
atau memindahkan aset.

## Fasa A — Preparation

1. Rekod commit tepat yang akan diuji dan Change ID staging.
2. Pastikan `ONEID_ENVIRONMENT=staging` dan
   `ONEID_LOGIN_BANNER_ENABLED=false`.
3. Ambil backup pre-change yang diluluskan.
4. Aplikasikan migration LB1 melalui DBA/change procedure yang diluluskan.
5. Sediakan direktori berikut dengan owner web process dan permission minimum:
   - `storage/runtime/login-banner-staging` — private dan writable;
   - `public/login_banners` — writable untuk publish, readable oleh web.
6. Jalankan backup LB7 `--create`, restore kepada target isolated dan tambah
   evidence berikut selepas disahkan:
   - `restore_rehearsal: PASS`;
   - `exact_row_count_reconciliation: PASS`;
   - `asset_checksum_reconciliation: PASS`.
7. Salin template
   `docs/templates/LB8_STAGING_UAT_EVIDENCE.example.json` ke lokasi evidence
   private di luar repository. Jangan simpan token, OTP, password atau PII.

Selepas backup LB7 dan full S4D post-migration restore rehearsal selesai, ikat
kedua-dua evidence tanpa suntingan manual:

```bash
php tools/lb8_attest_backup_evidence.php \
  /private/lb7/evidence.json \
  /private/S4D/EVIDENCE.txt
```

## Matriks UAT wajib

Semua 19 result mesti `PASS`: same asset BM/EN, asset berbeza, explicit English
fallback, missing-asset static fallback, invalid upload, schedule boundary waktu
Malaysia, reorder, inactivate, rollback, stale concurrent admin, desktop, mobile,
slow network, cache/hard refresh, password login, MyDigital ID login,
accessibility, audit correlation dan reconciliation bersih.

Ujian jadual hendaklah memasukkan tepat sebelum/pada/selepas start dan end UTC,
kemudian dibandingkan dengan paparan `Asia/Kuala_Lumpur`. Missing/corrupt asset
test mesti menggunakan controlled fixture dan dipulihkan sebelum gate akhir.

## Fasa B — Gate

```bash
export ONEID_LB8_CHANGE_ID='ONEID-LB8-UAT-CHANGE-ID'
export ONEID_LB8_BACKUP_EVIDENCE='/private/path/lb7/evidence.json'
export ONEID_LB8_UAT_EVIDENCE='/private/path/lb8-uat-evidence.json'
php tools/lb8_staging_activation_gate.php
```

Gate memeriksa staging exact, dynamic flag OFF, Change ID, DB, lima jadual,
GD WebP, kedua-dua direktori, checksum backup, isolated restore evidence, commit
40 aksara, 19 result PASS, owner `GO` dan reconciliation semasa tanpa missing
atau checksum mismatch. Mana-mana kegagalan menghasilkan `NO_GO` dan exit bukan
sifar. Output tidak mengandungi secrets.

## Fasa C — Activation

Activation hanya selepas output gate disimpan sebagai evidence dan Change owner
memberi kebenaran:

1. tetapkan private runtime `ONEID_LOGIN_BANNER_ENABLED=true`;
2. reload PHP/runtime mengikut prosedur deployment;
3. hard-refresh halaman login BM dan EN;
4. smoke-test password login dan MyDigital ID;
5. sahkan tepat satu slide active dan controls hanya jika lebih satu banner;
6. jangan buat content mutation tambahan dalam observation window.

Gate tidak melakukan langkah ini secara automatik supaya perubahan runtime
kekal tindakan operator yang jelas dan boleh diaudit.

## Fasa D — Observation

Pantau sekurang-kurangnya satu window yang diluluskan:

- HTTP availability dan latency halaman login;
- PHP/DB error serta log correlation LB6;
- missing asset/404 dan checksum reconciliation;
- password serta MyDigital ID success/failure baseline;
- paparan BM/EN desktop/mobile dan cache behavior.

Trigger rollback: gangguan login, locale salah, carousel unusable, peningkatan
error, aset corrupt/missing atau kandungan tidak diterima owner. Tindakan pertama
ialah `ONEID_LOGIN_BANNER_ENABLED=false`, reload terkawal dan smoke-test kedua-dua
kaedah login. Ikut runbook LB7 untuk content/code/asset/database/schema; schema
down bukan langkah pertama.

## Keputusan semasa

Pada 1 Ogos 2026, pemilik sistem mengesahkan semua ujian banner login yang
dijalankan di staging berjaya dan menerima feature ini sebagai lengkap serta
selesai. Rekod matriks 19 keputusan terdapat dalam
`docs/evidence/LB8_STAGING_UAT_EVIDENCE_20260801.json`.

Migration lima jadual, backup LB7, checksum, isolated restore rehearsal,
reconciliation aset, pengurusan banner Administrator, BM/EN, activation dan
smoke test telah selesai. Dynamic banner aktif di staging. Keputusan LB8 ialah
`STAGING_COMPLETE_OWNER_ACCEPTED`; Production kekal di luar skop dan memerlukan
authorization/deployment tersendiri.
