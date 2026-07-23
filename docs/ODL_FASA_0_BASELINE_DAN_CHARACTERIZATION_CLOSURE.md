# ODL Fasa 0 — Baseline dan Characterization Closure

**Tarikh:** 23 Julai 2026

**Status:** `PASS / CLOSED`

**Skop:** Baseline read-only sebelum schema provenance, adapter ODL atau wiring
Shadow Preview dilaksanakan.

## 1. Keputusan

Fasa 0 ditutup. Runtime sync semasa, entry point, safety contract, dashboard
seam dan quarantine endpoint telah dikenal pasti serta mempunyai regression
yang boleh diulang. Tiada connection ODL, schema change atau mutation dilakukan
oleh closure ini.

Gate F berstatus `PROCEED WITH CONDITIONS`. Fasa seterusnya yang dibenarkan oleh
pelan ialah Fasa 1, schema provenance additive dan dormant.

## 2. Runtime wiring baseline

| Fungsi | Entry point/runtime |
|---|---|
| External Sync Preview | `lib/q_func.php` action `admin_preview_sync_user` |
| Preview UI | `admin/dashboard.php` |
| External source baseline | `lib/external_data_source_API.php` |
| Pure planning | `app/Sync/SyncPlanner.php` |
| Read-only preview service | `app/Sync/SyncPreviewService.php` |
| Safe coordinator factory | `app/Sync/SyncEngineFactory.php` |
| Operational Apply | `createOperationalCoordinator()`; configuration-bound |
| Controlled Full Apply | `createFullCoordinator()`; configuration-bound |
| Controlled Pilot Apply | `createPilotCoordinator()`; configuration-bound |
| Legacy runner | `lib/sync_user_runner.php`; tidak dipilih oleh endpoint aktif |
| Retired cron | `cron/run_sync.php` tidak wujud |
| SKP/IDMS endpoints | Dikuarantin; bukan dependency ODL |

ODL belum mempunyai runtime class, configuration key, query atau wiring. Carian
repository tidak menemui `OdlStudentSource`, `STUDENT_ODL_PG`,
`student_basic_info` di runtime, atau jadual provenance sasaran.

## 3. Baseline behavior

Apabila ODL disabled/tidak diwujudkan:

- external source kekal Staf dan student source sedia ada;
- semua pelajar kekal kategori 10;
- matching pelajar kekal No. Matrik + IC;
- manual/protected account kekal dilindungi;
- Preview kekal zero-mutation dan `can_apply=false`;
- empty source dan kategori tidak dikenali fail closed;
- ODL tidak boleh mempengaruhi plan, Apply atau deactivation.

Fixture baseline meliputi `NEW`, `UPDATE`, `DEACTIVATE`, `REACTIVATE`, empty
source, rollback, protected collision dan zero-mutation Preview.

## 4. Verification boleh ulang

Arahan utama:

```bash
php tools/s2_sync_preview_contract.php
php tests/characterization/s2_sync_preview_zero_mutation.php
php tests/characterization/s3_sync_operational_safety.php
php tests/characterization/r52_sync_orchestration.php
php tests/characterization/r52_sync_adapter_parity.php
php tests/characterization/r52_sync_planner_purity.php
php tests/characterization/r52_sync_orchestrator_parity.php
php tests/characterization/r52_sync_dry_run_zero_mutation.php
php tests/characterization/r52_sync_production_adapter_contracts.php
php tests/characterization/r52_sync_production_orchestrator_parity.php
php tools/r52_dashboard_characterization.php
php tools/s4a_sync_wiring_contract.php
php tools/s4g_operational_sync_contract.php
php tools/s4f_full_sync_contract.php
php tools/s4e_controlled_pilot_contract.php
```

Keputusan closure:

| Set | Hasil |
|---|---|
| Core sync regression | 10 suite, 219 checks, 0 failure |
| Dashboard static baseline | 21 checks, 0 failure |
| Safe wiring contract | 16 checks, 0 failure |
| Operational contract | 24 checks, 0 failure |
| Controlled Full contract | 17 checks, 0 failure |
| Controlled Pilot contract | 17 checks, 0 failure |

## 5. Runtime hashes

| Fail | SHA-256 |
|---|---|
| `lib/sync_user_runner.php` | `965fd187492e1f120b074601746b031474405480f234412e458f64189108c8bb` |
| `lib/external_data_source_API.php` | `13993593885d8db098294dd596c7e9c433916b50f45217288bc2f737c15cd1ef` |
| `app/Sync/SyncPlanner.php` | `4ba98e0bd4864440ce649e6518f5ff6df878a35dae7fc91e0832748f03b5ee92` |
| `app/Sync/SyncPreviewService.php` | `ab6786355e18a6b0043bd8f863da6097db69c65377f84006abf52ed052d87002` |
| `app/Sync/SyncEngineFactory.php` | `a701288a1896bc9f892d5146d8eef0c10a3955bec8c4d9bd8375b37ad1abc84d` |
| `lib/q_func.php` | `9f53ef34248c9a8f93f26757d75b4fa1563882b8b4a59a785637c7de8e1d2ee9` |
| `admin/dashboard.php` | `94e9eee8cff7ac9366c0b25b67d58319c70c9f9ccb60ff05ec0c8394fc52def5` |

Hash ialah tamper/change checkpoint, bukan bukti fungsi secara bersendirian.
Perubahan yang diluluskan memerlukan regression dan rebaseline yang disengajakan.

## 6. Exit gate

| Exit criterion | Keputusan |
|---|---|
| Characterization sync sedia ada lulus | PASS |
| Source dan Apply path dikenal pasti | PASS |
| Baseline artifact boleh diulang | PASS |
| SKP/IDMS bukan dependency ODL | PASS |
| Tiada external connection atau schema change oleh Fasa 0 | PASS |
| Tiada mutation pengguna | PASS |

## 7. Authorization seterusnya

Fasa 1 dibenarkan untuk:

- migration up/down jadual provenance;
- isolated migration rehearsal;
- registration `STUDENT_ODL_PG` secara dormant;
- schema contract dan non-regression tests.

Fasa 1 tidak dibenarkan untuk:

- membaca datasource ODL melalui runtime adapter;
- backfill production/UAT live;
- wiring Preview;
- Apply atau mutation pengguna.

Rollback Fasa 0 tidak diperlukan kerana closure ini hanya mengemas kini
characterization dan dokumentasi.
