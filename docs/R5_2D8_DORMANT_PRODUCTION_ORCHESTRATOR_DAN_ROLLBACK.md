# R5.2D8 — Dormant Production Orchestrator dan Full Legacy Parity

Tarikh: 14 Julai 2026

Change ID: `R5-2D8-20260714-093807`

Change owner: Pemilik sistem OneID

Rollback owner: Pemilik sistem OneID

Status: **SELESAI — APPLICATION RESTRUCTURING CHECKPOINT, TIADA CALLER WIRING**

## 1. Tujuan dan Batas

R5.2D8 menyediakan `app/Sync/SyncOrchestrator.php` menggunakan contract,
`SyncPlanner`, DTO dan production adapter yang dibina dalam D1–D7.

Class ini dormant. Tiada runtime file require atau instantiate orchestrator.

Subfasa ini tidak:

- mengubah atau menggantikan `run_admin_sync_user`;
- mengubah `q_func`, cron atau dashboard;
- menambah engine factory/feature flag;
- mengakses live database/external source;
- menjalankan live sync;
- mengaktifkan semula cron;
- membetulkan transaction weakness D0-W01.

## 2. Dependency dan Result Contract

Constructor menerima:

- `ExternalUserSourceInterface`;
- `SyncPersistenceInterface`;
- `SyncPlanner`;
- `InitialPasswordFactoryInterface`.

`run(string $triggeredBy)` memulangkan `SyncRunSummary`. Future compatibility
wrapper mesti memanggil `toLegacyArray()` supaya caller kekal menerima key
`ext_head_id`, `New`, `Update`, `Deactivate` dan `Reactivate`.

## 3. Execution Lifecycle

Untuk compatibility parity, lifecycle dikekalkan:

1. begin transaction;
2. create header;
3. fetch external rows;
4. empty-source branch atau baca active/inactive snapshot;
5. bina pure `SyncPlan`;
6. deactivate/update;
7. update initial-source header;
8. stage semua NEW/REACTIVATE;
9. insert/reactivate dan mark body;
10. update uploaded header;
11. append audit dan summary;
12. commit dan read header.

Mutation exception dalam execution block memanggil rollback. Upstream/planning
masih berlaku di luar catch untuk mengekalkan D4 compatibility-first decision.

## 4. Full Legacy Parity

```bash
php tests/characterization/r52_sync_production_orchestrator_parity.php
```

Keputusan: **18/18 PASS**.

Matrix: `docs/R5_2D8_PRODUCTION_ORCHESTRATOR_PARITY_MATRIX.tsv`.

### Success

Fixture merangkumi update, deactivate, matched student, NEW, REACTIVATE, invalid
row dan excluded UID. Exact persistence call trace dan full legacy result adalah
sama. Initial password hash sahaja dinormalisasi selepas kedua-dua path disahkan
menghantar hash tidak kosong.

### Empty dan no-pending

Empty source mempunyai exact call/result parity. Source yang semuanya sudah
matched dan tidak berubah turut menghasilkan exact trace/result serta header
status `4`.

### Failure

Mutation exception menghasilkan exception dan rollback trace yang sama. Upstream
exception mengekalkan current weakness: transaction/header bermula tetapi
rollback tidak dipanggil oleh orchestration. Ini bukti parity, bukan approval
untuk mengekalkan weakness selama-lamanya.

## 5. Regression Keseluruhan

| Suite | Keputusan |
|---|---:|
| R5.2D8 production orchestrator | 18/18 PASS |
| R5.2D7 production adapters | 32/32 PASS |
| R5.2D6 planner purity | 17/17 PASS |
| R5.2D5 dry-run/zero mutation | 25/25 PASS |
| R5.2D3 test orchestrator | 17/17 PASS |
| R5.2D2 test adapters | 21/21 PASS |
| R5.2D1 seam design | 18/18 PASS |
| R5.2D0 legacy orchestration | 18/18 PASS |

Jumlah khusus D0–D8 yang aktif: **166 automated checks PASS**. D4 ialah
design/gate documentation dan tidak mempunyai runtime test count tersendiri.

## 6. No-production-wiring Evidence

Static scan tidak menemui `SyncOrchestrator` dalam:

- `lib/sync_user_runner.php`;
- `lib/Database.php`;
- `lib/q_func.php`;
- `cron/run_sync.php`;
- `page/dashboard.php`;
- `admin/dashboard.php`.

| Runtime file | SHA-256 |
|---|---|
| `lib/sync_user_runner.php` | `7a5fbe4e9d176661eda4f7d26449641edef683e77d355f9b9d5e227a9331b9df` |
| `lib/Database.php` | `f9aff1d051c3cc7aa9d8902b299292fe49e70b34b997a69a2feb931109547658` |
| `lib/q_func.php` | `b44534b888c0809ffc3185f73456a8415663d65871e9c7fa8c07c441f5e6a33b` |
| `cron/run_sync.php` | `9c8017a5774a9ea5d12daa70e893ee3d909eeb967df75cceed6dbb438afb3e59` |
| `page/dashboard.php` | `9077b77174d7ec33fcc91b9a66ca349c7f6a105ccbc764dc06511e9f195fc361` |
| `admin/dashboard.php` | `24b2028f0d978a0ce38d1915d2a7bf60445e7ac36c0a360fea04b3db089be7dc` |

## 7. Fail dan Checksum

| Fail | SHA-256 |
|---|---|
| `app/Sync/SyncOrchestrator.php` | `50aa57e55befd743c15aaf4370d0fa4339c6b136ad13d190eaddb9d69583c635` |
| `tests/characterization/r52_sync_production_orchestrator_parity.php` | `6a6ef02a7c70d12a13d604759694ea615245b71df31b3baa626ce5017f49b91a` |

Dokumen/matrix tidak dimasukkan dalam checksum dirinya sendiri.

## 8. Risiko dan Gate Yang Kekal

- Orchestrator belum pernah menggunakan representative UAT snapshot.
- D0-W01 transaction boundary masih dikekalkan.
- Engine factory/strict feature flag belum wujud.
- Single-snapshot shadow D4-G09 dan UAT zero mismatch D4-G10 belum lulus.
- Concurrency lock, monitoring, reconciliation, backup dan restore belum siap.
- Production wiring dan live sync kekal NO-GO.

## 9. Rollback D8

Tiada runtime/service/database rollback. Buang:

```text
app/Sync/SyncOrchestrator.php
tests/characterization/r52_sync_production_orchestrator_parity.php
docs/R5_2D8_PRODUCTION_ORCHESTRATOR_PARITY_MATRIX.tsv
docs/R5_2D8_DORMANT_PRODUCTION_ORCHESTRATOR_DAN_ROLLBACK.md
```

Pulihkan rujukan D8 dalam README, D7, D4 register/readiness dan pelan Fasa 7.
Jangan buang planner, DTO, interface atau adapter D1–D7.

## 10. Checkpoint Restructuring

Application-layer restructuring sync kini mempunyai:

- contracts;
- DTO/result/plan;
- deterministic transformer;
- pure planner;
- production adapters;
- production orchestrator;
- independent parity dan zero-mutation fixtures.

Semua masih di belakang legacy runtime. Ini ialah titik selamat untuk berhenti
dan review tanpa menjejaskan feature semasa.

## 11. Langkah Selepas Review

Jika owner mahu meneruskan ke penggunaan sebenar, langkah berikutnya ialah
**R5.2D9 — representative UAT read-only single-snapshot shadow**. Ia memerlukan
live read authority, data handling/retention dan operator window. D9 tidak patut
dimulakan secara automatik hanya kerana D8 lulus.
