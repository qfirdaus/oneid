# R5.2D2 — Test-only Adapter dan Parity Fixture

Tarikh: 14 Julai 2026

Change ID: `R5-2D2-20260714-035942`

Owner perubahan: Pemilik sistem OneID

Owner rollback: Pemilik sistem OneID

Status: **SELESAI — TEST-ONLY, TIADA PRODUCTION WIRING**

## 1. Tujuan dan Sempadan

R5.2D2 membuktikan interface D1 boleh memetakan API legacy secara tepat melalui
fake operation dan callable yang deterministik. Adapter sengaja diletakkan di
`tests/Support/Sync`, bukan `app/Sync`, supaya ia tidak dianggap implementation
production.

Subfasa ini tidak:

- mengubah atau memanggil `run_admin_sync_user`;
- menghubungkan adapter kepada dashboard, cron, `q_func` atau `Database` sebenar;
- mengakses database, network, session atau external source;
- menjalankan live sync;
- mengubah transaction boundary D0-W01;
- membetulkan matching, exclusion, category atau password behavior legacy.

## 2. Adapter Yang Dibina

- `CallableExternalUserSourceAdapter` membalut callable fake bagi `fetchAll()`.
- `CallableInitialPasswordFactoryAdapter` menghasilkan hash fixture yang
  deterministik tanpa random generator production.
- `LegacySyncPolicyAdapter` mengunci exclusion `['10']`, enam mapping kategori
  legacy dan fallback kategori `0`.
- `LegacyOperationPersistenceAdapter` memetakan semua 15 capability persistence
  kepada nama method dan susunan argumen `Database` legacy.

Mapping penuh tersedia dalam `R5_2D2_TEST_ADAPTER_METHOD_PARITY.tsv`.

## 3. Parity Fixture

Jalankan:

```bash
php tests/characterization/r52_sync_adapter_parity.php
```

Keputusan: **21/21 PASS**.

Fixture memeriksa:

- callable dipanggil sekali dan return value dikekalkan;
- exclusion dan semua mapping kategori adalah sama;
- urutan method legacy, `data1` hingga `data12`, UID, status dan hash adalah
  tepat;
- return header/body/user list diteruskan tanpa perubahan;
- tiada mana-mana enam production files merujuk class adapter test-only;
- checksum empat runtime files kritikal kekal sama.

Regression tambahan:

- D1 seam design: **18/18 PASS**;
- D0 in-memory orchestration: **18/18 PASS**;
- PHP lint lima fail baharu: **PASS**.

## 4. Bukti Tiada Production Wiring

| Runtime file | SHA-256 selepas D2 | Keputusan |
|---|---|---|
| `lib/sync_user_runner.php` | `7a5fbe4e9d176661eda4f7d26449641edef683e77d355f9b9d5e227a9331b9df` | tidak berubah |
| `lib/Database.php` | `f9aff1d051c3cc7aa9d8902b299292fe49e70b34b997a69a2feb931109547658` | tidak berubah |
| `lib/q_func.php` | `b44534b888c0809ffc3185f73456a8415663d65871e9c7fa8c07c441f5e6a33b` | tidak berubah |
| `cron/run_sync.php` | `9c8017a5774a9ea5d12daa70e893ee3d909eeb967df75cceed6dbb438afb3e59` | tidak berubah |

Static guard turut menyemak `page/dashboard.php` dan `admin/dashboard.php` dan
tidak menemui sebarang nama class adapter D2.

## 5. Risiko dan Perkara Yang Sengaja Kekal

Adapter mengakses key `data1` hingga `data12` secara terus kerana itulah kontrak
legacy semasa. Ia tidak menambah default atau coercion baharu yang boleh
menyembunyikan malformed upstream row.

Production runner masih memulakan transaction sebelum upstream fetch, sedangkan
`try/catch` bermula selepas fetch. Oleh itu exception upstream masih boleh
melangkaui rollback. D0 fixture terus mengunci kelemahan ini supaya pembetulannya
dibuat sebagai functional change berasingan.

`LegacyOperationPersistenceAdapter` bukan calon untuk di-require oleh production.
Production adapter kelak perlu diletakkan di namespace aplikasi, mempunyai
runbook wiring tersendiri dan melepasi regression/live-sync gate yang berasingan.

## 6. Fail Ditambah dan Checksum

| Fail | SHA-256 |
|---|---|
| `tests/Support/Sync/CallableExternalUserSourceAdapter.php` | `b5693ce5ae8ec583a3afeabaf3164f0933cd91f1d1d66a0f6b6f8849b03c3f93` |
| `tests/Support/Sync/CallableInitialPasswordFactoryAdapter.php` | `7d3c91322534535b400cc585f68bf69c5b43413d6fbc36d079f62edfc509da08` |
| `tests/Support/Sync/LegacyOperationPersistenceAdapter.php` | `55b6a433f19c692c943bb3dde99b7bb6563d718e587d4800e8a5f7ad6b361617` |
| `tests/Support/Sync/LegacySyncPolicyAdapter.php` | `9cc3d612c2842f5bc53cc13b4c4b7662e23c5d4b8f2879a9ae0171e7cd697854` |
| `tests/characterization/r52_sync_adapter_parity.php` | `096648b8b9a66e53511cb88d537b166834b1e3f9f65a3b8091e07ca6669006ad` |

Dokumen dan TSV tidak dimasukkan ke dalam checksum dirinya sendiri. Tiada fail
runtime diubah dalam D2.

## 7. Rollback

Tiada runtime rollback atau service restart diperlukan. Untuk membatalkan D2,
buang hanya:

```text
tests/Support/Sync/CallableExternalUserSourceAdapter.php
tests/Support/Sync/CallableInitialPasswordFactoryAdapter.php
tests/Support/Sync/LegacyOperationPersistenceAdapter.php
tests/Support/Sync/LegacySyncPolicyAdapter.php
tests/characterization/r52_sync_adapter_parity.php
docs/R5_2D2_TEST_ADAPTER_METHOD_PARITY.tsv
docs/R5_2D2_TEST_ADAPTER_PARITY_DAN_ROLLBACK.md
```

Kemudian pulihkan kemas kini rujukan D2 dalam `tests/README.md`, dokumen D1 dan
pelan Fasa 7. Jangan buang interface/DTO D1 atau transformer C1.

## 8. Gate Selepas D2

- [x] Empat adapter test-only tersedia.
- [x] Exact method/argument parity lulus.
- [x] Category/exclusion/callable parity lulus.
- [x] No-production-wiring guard lulus.
- [x] Runtime checksum tidak berubah.
- [x] D0 dan D1 regression kekal lulus.
- [x] Orchestrator baharu dicirikan menggunakan interface/fake (R5.2D3, 17/17).
- [ ] Transaction boundary functional change dipisahkan dan diluluskan.
- [ ] Production adapter dan wiring mempunyai rollback tersendiri.
- [ ] Live sync validation mendapat approval khusus.

## 9. Keputusan

R5.2D2 selesai dengan selamat. Interface D1 kini mempunyai bukti executable
bahawa mapping kepada API legacy adalah tepat, tetapi production masih kekal
sepenuhnya pada `run_admin_sync_user` asal.
