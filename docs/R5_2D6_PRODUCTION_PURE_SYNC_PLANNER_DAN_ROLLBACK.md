# R5.2D6 — Production-grade Pure SyncPlanner Extraction

Tarikh: 14 Julai 2026

Change ID: `R5-2D6-20260714-042340`

Change owner: Pemilik sistem OneID

Rollback owner: Pemilik sistem OneID

Status: **SELESAI — PURE APPLICATION CLASS, TIADA PERSISTENCE/CALLER WIRING**

## 1. Tujuan dan Skop

R5.2D6 mengekstrak decision logic yang telah lulus D5 daripada test support ke
application namespace:

```text
tests/Support/Sync/TestSyncPlanner.php
    → app/Sync/SyncPlanner.php
```

Test-only dry-run kini menggunakan `OneId\App\Sync\SyncPlanner` secara terus.
Duplicate test planner telah dibuang.

Subfasa ini tidak:

- menghubungkan planner kepada `run_admin_sync_user`;
- menambah production source atau persistence adapter;
- mengubah dashboard, `q_func`, cron atau `Database`;
- membaca live database/external API;
- menambah feature flag;
- menjalankan live sync;
- membetulkan transaction boundary D0-W01.

## 2. Pure Contract

Constructor production planner hanya menerima:

```php
SyncPolicyInterface $policy
```

Public API hanya:

```php
__construct(SyncPolicyInterface $policy)
plan(array $externalRows, array $activeUsers, array $inactiveUserIds): SyncPlan
```

Ia tidak menerima atau merujuk:

- `SyncPersistenceInterface`;
- `ExternalUserSourceInterface`;
- `InitialPasswordFactoryInterface`;
- `Database`/PDO;
- session, cookie atau request globals;
- environment/config lookup;
- filesystem, cURL atau HTTP response functions.

Planner hanya mengira plan daripada tiga snapshot array dan policy. Ia tidak
boleh memulakan transaction atau melakukan I/O.

## 3. Behavior Yang Dikekalkan

Extraction tidak membetulkan behavior legacy. D5 fixture terus mengunci:

- invalid/excluded filtering;
- staf/pelajar identity matching;
- DEACTIVATE/UPDATE/NEW/REACTIVATE order;
- category mapping;
- change detection dan hash;
- duplicate handling C1;
- audit projection;
- empty-source safety;
- deterministic safe plan projection.

## 4. Ujian

### D6 purity guard

```bash
php tests/characterization/r52_sync_planner_purity.php
```

Keputusan: **17/17 PASS**.

Checks meliputi lint, final class, namespace, constructor dependency, input dan
return signature, minimal public API, forbidden side-effect symbol scan,
duplicate removal, no-caller-wiring dan runtime checksum.

### D5 parity/zero mutation selepas extraction

```bash
php tests/characterization/r52_sync_dry_run_zero_mutation.php
```

Keputusan: **25/25 PASS**.

Ini membuktikan pemindahan namespace/path tidak mengubah plan, audit, order,
counts, category, UPDATE/INSERT hash, empty-source atau upstream failure
behavior. Dry-run kekal membuat maksimum dua persistence reads dan sifar write.

Regression tambahan:

| Suite | Keputusan |
|---|---:|
| R5.2D3 orchestrator parity | 17/17 PASS |
| R5.2D2 adapter parity | 21/21 PASS |
| R5.2D1 seam design | 18/18 PASS |
| R5.2D0 legacy orchestration | 18/18 PASS |

## 5. No-production-wiring Guard

Static scan tidak menemui `SyncPlanner` dalam:

- `lib/sync_user_runner.php`;
- `lib/Database.php`;
- `lib/q_func.php`;
- `cron/run_sync.php`;
- `page/dashboard.php`;
- `admin/dashboard.php`.

Planner berada dalam `app/` tetapi masih dormant. Lokasi application namespace
tidak bermaksud ia sudah aktif dalam production flow.

## 6. Fail Berubah dan Checksum

### Sebelum D6

| Fail | SHA-256 |
|---|---|
| `tests/Support/Sync/TestSyncPlanner.php` | `d10c0c00e0b57aa9d350fe9e2203c64fb82584a6b0af9c24f92aaf8d8587c4ab` |

### Selepas D6

| Fail | SHA-256 |
|---|---|
| `app/Sync/SyncPlanner.php` | `c933f60c65607b4f810e06e1c7bf2b83db7e208cefe6c519e034648b28cfcce5` |
| `app/Sync/DTO/SyncPlan.php` tidak berubah | `03c765c2169e3152545a8587ac0639dd1d811a17450ea97a8c2ba0699ca8199f` |
| `tests/Support/Sync/TestSyncDryRun.php` | `eeaef613b1fb24510c347c6441590b9aaaefefd81f9b2696a35fe745e2b1f199` |
| `tests/characterization/r52_sync_dry_run_zero_mutation.php` | `b22c8e5c5b4cd37542a5aaf93be5367e1e9ea32cf906cf8d25d1a34ad918bcd7` |
| `tests/characterization/r52_sync_planner_purity.php` | `dace7cecc943aebbe054b7af341b18084ea85532cb8b0e9ac336f4bc2a0c2d66` |

Runtime checksums kekal:

| Runtime file | SHA-256 |
|---|---|
| `lib/sync_user_runner.php` | `7a5fbe4e9d176661eda4f7d26449641edef683e77d355f9b9d5e227a9331b9df` |
| `lib/Database.php` | `f9aff1d051c3cc7aa9d8902b299292fe49e70b34b997a69a2feb931109547658` |
| `lib/q_func.php` | `b44534b888c0809ffc3185f73456a8415663d65871e9c7fa8c07c441f5e6a33b` |
| `cron/run_sync.php` | `9c8017a5774a9ea5d12daa70e893ee3d909eeb967df75cceed6dbb438afb3e59` |

## 7. Batas dan Risiko Yang Kekal

- Policy yang digunakan dry-run masih adapter test-only.
- Production external source/persistence/password adapter belum wujud.
- Planner actions mengandungi PII dan tidak boleh dilog mentah.
- Representative UAT snapshot belum diuji.
- Single-snapshot shadow belum dibina.
- Concurrency, monitoring, reconciliation dan database recovery gates belum
  ditutup.
- Production wiring kekal NO-GO.

## 8. Rollback D6

Rollback hanya mengundurkan extraction:

1. pulihkan planner sebagai
   `tests/Support/Sync/TestSyncPlanner.php` dengan namespace
   `OneId\Tests\Support\Sync` dan nama `TestSyncPlanner`;
2. buang `app/Sync/SyncPlanner.php`;
3. pulihkan type hint/import dalam `TestSyncDryRun.php`;
4. pulihkan require/import/instantiation dalam D5 fixture;
5. buang D6 purity fixture dan dokumentasi;
6. jalankan D5 dan pastikan 25/25 PASS.

Tiada service restart atau database rollback diperlukan kerana tiada runtime
caller menggunakan planner.

## 9. Gate Selepas D6

- [x] Pure planner berada dalam application namespace.
- [x] Duplicate test planner dibuang.
- [x] Purity contract lulus 17/17.
- [x] D5 parity/zero-mutation kekal 25/25.
- [x] Production runtime tidak berubah.
- [ ] Production policy/source/persistence/password adapter belum dibina.
- [ ] Dormant production orchestration belum dibina.
- [ ] Single-snapshot shadow/live UAT belum dijalankan.
- [ ] Feature flag/caller wiring belum dibina.

## 10. Langkah Seterusnya

Langkah selamat berikutnya ialah **R5.2D7 — bina production adapter secara
dormant dan contract-test**, tanpa mengubah `run_admin_sync_user`, caller atau
feature flag. Adapter mesti mengulang D2 exact method mapping dan tidak boleh
menggunakan class daripada `tests/Support`.

**Kemaskini D7:** empat production adapter dormant telah tersedia dan lulus
32/32 contract checks tanpa test dependency atau runtime wiring. D4-G07 ditutup.
