# R5.2D5 — Pure SyncPlan dan Test-only Dry-run

Tarikh: 14 Julai 2026

Change ID: `R5-2D5-20260714-041927`

Change owner: Pemilik sistem OneID

Rollback owner: Pemilik sistem OneID

Status: **SELESAI — TEST-ONLY DRY-RUN, TIADA PRODUCTION WIRING**

**Kemaskini R5.2D6:** `TestSyncPlanner.php` telah diextract menjadi
`app/Sync/SyncPlanner.php`; test-only dry-run kini menggunakan class application
itu secara terus. Rujukan path/checksum/rollback TestSyncPlanner di bawah ialah
rekod keadaan asal D5. D6 purity 17/17 dan D5 selepas extraction kekal 25/25.

## 1. Tujuan dan Skop

R5.2D5 menyediakan model plan immutable dan membuktikan keputusan sync boleh
dihasilkan tanpa transaction atau database mutation. Ia menjadi bukti awal bagi
dry-run/shadow design D4.

Subfasa ini tidak:

- mengakses live database atau external API;
- menjalankan production sync;
- mencipta header, audit row atau staging body;
- mengubah user;
- menambah environment/feature flag;
- menghubungkan planner kepada dashboard, `q_func` atau cron;
- membetulkan transaction boundary D0-W01.

## 2. Komponen

### `SyncPlan`

`app/Sync/DTO/SyncPlan.php` ialah DTO dengan public readonly properties:

- ordered actions;
- raw source row count;
- invalid row count;
- excluded row count;
- warnings.

Ia menyediakan:

- `legacyCounts()` untuk `New`, `Update`, `Deactivate`, `Reactivate`;
- `safeProjection()` yang mengganti raw UID dengan SHA-256 digest dan tidak
  membawa full source row;
- `planHash()` yang deterministik bagi snapshot/plan sama.

Readonly array di PHP tidak menjadikan setiap nested value immutable secara
rekursif. Consumer tidak boleh mengubah atau log `actions` mentah.

### Test-only planner

`tests/Support/Sync/TestSyncPlanner.php` memproyeksikan decision logic legacy:

- invalid/excluded filtering;
- staf/pelajar matching;
- deactivate dan update detection;
- duplicate behavior C1;
- NEW/REACTIVATE classification;
- category dan change-hash calculation;
- audit snapshot/changed-field projection.

Ia pure: input array dan policy menghasilkan `SyncPlan`; tiada source atau
persistence dependency.

### Test-only dry-run

`tests/Support/Sync/TestSyncDryRun.php` mengambil external snapshot sekali. Bagi
source tidak kosong, ia hanya membaca active users dan inactive IDs sebelum
memanggil planner. Empty source terus menghasilkan empty plan tanpa membaca
persistence, selari dengan behavior legacy yang tidak deactivate semua user
apabila upstream memulangkan list kosong.

## 3. Zero-mutation Contract

Matrix penuh tersedia dalam `R5_2D5_DRY_RUN_CONTRACT.tsv`.

Operation spy menetapkan semua method mutation untuk melemparkan
`LogicException`. Run berjaya hanya dengan call trace berikut:

```text
sync_get_all_sso_user
sync_get_inactive_user_ids
```

Ia tidak memanggil:

- begin/commit/rollback;
- create/update header;
- deactivate/update/insert user;
- stage/update body;
- append change log atau update summary;
- read header selepas mutation.

Jika upstream melempar exception, dry-run membuat **sifar persistence call**.
Ini sengaja lebih selamat daripada transaction weakness production legacy.

## 4. Fixture dan Keputusan

Arahan:

```bash
php tests/characterization/r52_sync_dry_run_zero_mutation.php
```

Keputusan: **25/25 PASS**.

Fixture merangkumi:

- immutable/readonly plan contract;
- external fetch tepat sekali;
- exact read-only persistence trace;
- action/audit/order/count parity dengan legacy;
- category mapping parity;
- UPDATE dan INSERT change-hash parity;
- invalid/excluded counters;
- deterministic plan hash;
- redacted safe projection tanpa nama atau raw UID;
- empty-source zero persistence calls;
- upstream failure zero persistence calls;
- no-production-wiring guard;
- empat runtime checksum tidak berubah.

Regression tambahan:

| Suite | Keputusan |
|---|---:|
| R5.2D5 dry-run | 25/25 PASS |
| R5.2D3 orchestrator parity | 17/17 PASS |
| R5.2D2 adapter parity | 21/21 PASS |
| R5.2D1 seam design | 18/18 PASS |
| R5.2D0 legacy orchestration | 18/18 PASS |
| PHP lint empat fail D5 | PASS |

## 5. Data dan Logging Boundary

`SyncPlan::actions` mengandungi row yang diperlukan untuk future executor,
termasuk data pengguna. Oleh itu:

- jangan `json_encode($plan->actions)` ke log;
- jangan simpan plan mentah dalam document root atau CI artifact terbuka;
- gunakan `safeProjection()` untuk evidence;
- gunakan `planHash()` untuk comparison;
- correlation/change ID disimpan berasingan daripada PII.

UID digest tidak semestinya anonymization sempurna jika ruang UID mudah diteka.
Ia hanya mengurangkan pendedahan dalam log; access control dan retention masih
wajib.

## 6. Batas Bukti D5

D5 menggunakan fixture in-memory, bukan representative live UAT snapshot.
`TestSyncPlanner` kekal dalam namespace test dan belum layak menjadi production
planner. D5 membuktikan zero mutation dan parity bagi scenario fixture, tetapi
belum menutup:

- D4-G09 single-snapshot shadow implementation;
- D4-G10 representative UAT zero mismatch;
- production adapter;
- concurrency lock;
- monitoring/reconciliation;
- database backup/restore;
- caller feature flag.

## 7. Bukti Tiada Production Wiring

Static scan tidak menemui `SyncPlan`, `TestSyncPlanner` atau `TestSyncDryRun`
dalam enam runtime/caller files. Checksum kritikal:

| Runtime file | SHA-256 |
|---|---|
| `lib/sync_user_runner.php` | `7a5fbe4e9d176661eda4f7d26449641edef683e77d355f9b9d5e227a9331b9df` |
| `lib/Database.php` | `f9aff1d051c3cc7aa9d8902b299292fe49e70b34b997a69a2feb931109547658` |
| `lib/q_func.php` | `b44534b888c0809ffc3185f73456a8415663d65871e9c7fa8c07c441f5e6a33b` |
| `cron/run_sync.php` | `9c8017a5774a9ea5d12daa70e893ee3d909eeb967df75cceed6dbb438afb3e59` |

## 8. Fail dan Checksum

| Fail | SHA-256 |
|---|---|
| `app/Sync/DTO/SyncPlan.php` | `03c765c2169e3152545a8587ac0639dd1d811a17450ea97a8c2ba0699ca8199f` |
| `tests/Support/Sync/TestSyncPlanner.php` | `d10c0c00e0b57aa9d350fe9e2203c64fb82584a6b0af9c24f92aaf8d8587c4ab` |
| `tests/Support/Sync/TestSyncDryRun.php` | `01bc2814fa0d50918d0fef83300abd9b14b11f5d1ff69646bb117634aeaff7f4` |
| `tests/characterization/r52_sync_dry_run_zero_mutation.php` | `3dbd98c07e77296aa3b7d28ecda59a488b0f06860266f4c33c9f8ecad0387d98` |

Dokumen/TSV tidak dimasukkan dalam checksum dirinya sendiri.

## 9. Rollback

Tiada runtime rollback atau service restart diperlukan. Buang:

```text
app/Sync/DTO/SyncPlan.php
tests/Support/Sync/TestSyncPlanner.php
tests/Support/Sync/TestSyncDryRun.php
tests/characterization/r52_sync_dry_run_zero_mutation.php
docs/R5_2D5_DRY_RUN_CONTRACT.tsv
docs/R5_2D5_PURE_SYNC_PLAN_DRY_RUN_DAN_ROLLBACK.md
```

Kemudian pulihkan rujukan D5 dalam `app/Sync/README.md`, `tests/README.md`, D4
readiness/gate register dan pelan Fasa 7. Jangan buang transformer C1,
interface D1, adapter D2 atau D3 fixture.

## 10. Gate Selepas D5

- [x] Pure plan DTO tersedia.
- [x] Test-only planner tersedia.
- [x] Test-only dry-run tersedia.
- [x] Zero mutation dibuktikan oleh throwing spy.
- [x] Fixture legacy parity lulus.
- [x] Redacted projection dan deterministic hash tersedia.
- [x] Production runtime tidak berubah.
- [x] Planner production telah diextract ke `app/Sync` dalam R5.2D6.
- [ ] Representative UAT read-only snapshot belum diuji.
- [ ] Single-snapshot shadow belum dilaksanakan.
- [ ] Production adapter/feature flag belum dibina.

## 11. Langkah Seterusnya

Langkah paling selamat ialah **R5.2D6 — extract production-grade pure
`SyncPlanner` ke `app/Sync` tanpa persistence/caller wiring**, sambil mengekalkan
test-only dry-run sebagai consumer pertama. Selepas parity D5 kekal lulus,
barulah production adapter dormant boleh dipertimbangkan.
