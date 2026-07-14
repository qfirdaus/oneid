# R5.2D3 — Test-only Orchestrator dan Legacy Parity

Tarikh: 14 Julai 2026

Change ID: `R5-2D3-20260714-040759`

Owner perubahan: Pemilik sistem OneID

Owner rollback: Pemilik sistem OneID

Status: **SELESAI — TEST-ONLY, TIADA PRODUCTION WIRING**

## 1. Tujuan

R5.2D3 membina projection orchestration berasaskan interface D1 dan adapter D2,
kemudian membandingkannya secara executable dengan `run_admin_sync_user` legacy.

Class diletakkan sebagai `tests/Support/Sync/TestSyncOrchestrator.php`. Nama dan
lokasinya menandakan ia bukan implementation production dan tidak boleh
di-require oleh runtime.

Subfasa ini tidak:

- memindahkan atau mengubah `run_admin_sync_user`;
- mengubah caller dalam dashboard, `q_func` atau cron;
- mengakses database, network, external API atau session;
- menjalankan live sync;
- membina production adapter;
- membetulkan transaction boundary atau kelemahan functional legacy.

## 2. Sempadan Orchestrator Ujian

`TestSyncOrchestrator` menerima empat dependency melalui constructor:

1. `ExternalUserSourceInterface`;
2. `SyncPersistenceInterface`;
3. `SyncPolicyInterface`;
4. `InitialPasswordFactoryInterface`.

Ia menggunakan `SyncDataTransformer` C1 untuk hashing, change detection, audit
snapshot dan duplicate handling. Result dipulangkan sebagai `SyncRunSummary`,
kemudian `toLegacyArray()` digunakan untuk membuktikan compatibility result.

Logic matching staf/pelajar, exclusion, staging, category mapping, audit order,
summary count dan status header dikekalkan mengikut production legacy semasa.

## 3. Parity Fixture

Arahan:

```bash
php tests/characterization/r52_sync_orchestrator_parity.php
```

Keputusan: **17/17 PASS**.

Setiap scenario menjalankan legacy dan projection baharu menggunakan dua
operation spy yang berasingan tetapi fixture input yang sama. Matrix penuh
tersedia dalam `R5_2D3_ORCHESTRATOR_PARITY_MATRIX.tsv`.

### Success flow

Fixture merangkumi user berubah, user dinyahaktif, pelajar matched, user baharu,
user reactivated, UID excluded dan row invalid. Perbandingan meliputi:

- exact persistence method dan argument call trace;
- urutan transaction, header, update, staging, insert, audit, summary dan commit;
- UID dan category ID bagi insert;
- audit payload dan summary counts;
- result array legacy penuh.

Initial password production menggunakan random bytes, jadi nilai hash sebenar
memang tidak boleh sama antara dua run. Fixture terlebih dahulu memastikan
kedua-dua path membekalkan hash tidak kosong, kemudian hanya nilai argumen
password itu dinormalisasi kepada marker sebelum exact call-trace comparison.
Argumen lain termasuk change hash tidak dinormalisasi.

### Empty, mutation failure dan upstream failure

- empty source menghasilkan call trace dan result yang sama;
- mutation exception menghasilkan exception dan rollback trace yang sama;
- upstream exception mengekalkan behavior semasa: transaction telah bermula
  tetapi rollback tidak dipanggil.

## 4. Transaction Weakness Sengaja Dikekalkan

`fetchAll()` dalam test orchestrator sengaja berada selepas `begin()` dan
`createHeader()`, tetapi sebelum blok `try/catch`. Ini mengekalkan D0-W01 untuk
parity. Ia bukan design transaction yang disyorkan untuk production baharu.

Memindahkan fetch ke dalam transaction guard atau memulakan transaction selepas
fetch akan mengubah failure semantics. Pembetulan itu mesti menjadi functional
change berasingan dengan rollback dan approval khusus.

## 5. Bukti Tiada Production Wiring

Static guard tidak menemui `TestSyncOrchestrator` dalam:

- `lib/sync_user_runner.php`;
- `lib/Database.php`;
- `lib/q_func.php`;
- `cron/run_sync.php`;
- `page/dashboard.php`;
- `admin/dashboard.php`.

| Runtime file | SHA-256 | Keputusan |
|---|---|---|
| `lib/sync_user_runner.php` | `7a5fbe4e9d176661eda4f7d26449641edef683e77d355f9b9d5e227a9331b9df` | tidak berubah |
| `lib/Database.php` | `f9aff1d051c3cc7aa9d8902b299292fe49e70b34b997a69a2feb931109547658` | tidak berubah |
| `lib/q_func.php` | `b44534b888c0809ffc3185f73456a8415663d65871e9c7fa8c07c441f5e6a33b` | tidak berubah |
| `cron/run_sync.php` | `9c8017a5774a9ea5d12daa70e893ee3d909eeb967df75cceed6dbb438afb3e59` | tidak berubah |

## 6. Regression

| Suite | Keputusan |
|---|---:|
| R5.2D3 orchestrator parity | 17/17 PASS |
| R5.2D2 adapter parity | 21/21 PASS |
| R5.2D1 seam design | 18/18 PASS |
| R5.2D0 legacy orchestration | 18/18 PASS |
| PHP lint dua fail D3 | PASS |

HTTP regression tidak diulang kerana D3 hanya menambah fail di bawah `tests/`
dan checksum semua production runtime kekal sama.

## 7. Fail Ditambah dan Checksum

| Fail | SHA-256 |
|---|---|
| `tests/Support/Sync/TestSyncOrchestrator.php` | `e05029105f1ced5c1d18e041c6bf839e3758640d674cfc962ca6c855135a0bbf` |
| `tests/characterization/r52_sync_orchestrator_parity.php` | `f9e3d68ec4fad05937c1aa34debe309075e23cb601dd0719d6348e0c9b2ea05b` |

Dokumen dan parity matrix tidak dimasukkan dalam checksum dirinya sendiri.

## 8. Rollback

Tiada runtime rollback, deployment atau service restart diperlukan. Buang:

```text
tests/Support/Sync/TestSyncOrchestrator.php
tests/characterization/r52_sync_orchestrator_parity.php
docs/R5_2D3_ORCHESTRATOR_PARITY_MATRIX.tsv
docs/R5_2D3_TEST_ORCHESTRATOR_PARITY_DAN_ROLLBACK.md
```

Kemudian pulihkan rujukan D3 dalam `tests/README.md`, dokumen D2 dan pelan Fasa 7.
Jangan buang interface D1, adapter fixture D2 atau transformer C1.

## 9. Gate Selepas D3

- [x] Interface-based orchestrator wujud sebagai test-only.
- [x] Success call trace dan result parity lulus.
- [x] Empty-source parity lulus.
- [x] Mutation exception/rollback parity lulus.
- [x] Upstream weakness parity lulus.
- [x] Production runtime dan checksum tidak berubah.
- [ ] Production class/adapter belum dibina.
- [ ] Transaction boundary decision belum diluluskan.
- [x] Production caller switch dan rollback telah direka sebagai runbook D4; belum dilaksanakan.
- [x] Shadow/dry-run strategy telah direka dalam D4; pure planner belum dilaksanakan.
- [ ] Live sync validation belum diluluskan.

## 10. Keputusan dan Langkah Seterusnya

R5.2D3 selesai. Projection berasaskan interface kini terbukti mempunyai behavior
yang sama dengan orchestration legacy bagi fixture utama dan failure paths.

Langkah selamat seterusnya ialah **R5.2D4 — production-wiring readiness plan**:
tentukan keputusan transaction boundary, bentuk production adapter, strategi
feature flag/cutover, observability, dry-run, data reconciliation dan rollback.
D4 patut kekal sebagai design/runbook dahulu, tanpa menukar caller production.

**Kemaskini D4:** readiness plan dan gate register telah disediakan. Keputusan
awal ialah compatibility-first transaction boundary, legacy sebagai default,
single writer, side-effect-free shadow dan admin-only pilot. D4 tidak mengubah
production caller atau runtime.
