# S4C — Approval-aware Single-snapshot Coordinator

Tarikh: 14 Julai 2026
Change owner: Pemilik sistem OneID
Rollback owner: Pemilik sistem OneID
Status: **DORMANT SIAP — ENDPOINT/UI APPLY/LIVE SYNC KEKAL NO-GO**

## 1. Objektif dan Batas

S4C mengikat one-time approval S4B kepada writer safety core S3. Coordinator
memastikan external/internal snapshot dibaca sekali, satu `SyncPlan` dibina,
plan itu divalidasi dan object yang sama dihantar kepada writer.

S4C tidak:

- menambah atau mengubah endpoint runtime;
- menambah butang atau request Apply pada dashboard;
- mengaktifkan `ONEID_SYNC_APPLY_ENABLED` atau engine `safe`;
- menjalankan external fetch, database transaction atau live sync;
- mengaktifkan cron/scheduler;
- menulis ke staff/student production source.

## 2. Transaction dan Approval Boundary

Urutan fail-closed ialah:

```text
lock → fetch source sekali → read active/inactive sekali → plan sekali
     → consume+validate approval → S3 safety policy
     → BEGIN → execute plan yang sama → reconcile → COMMIT → release lock
```

Approval menyumbang `acceptedBaseline` kepada `SyncSafetyPolicy`. Oleh itu
baseline tidak diambil daripada request browser. Approval mismatch, expired,
wrong-admin atau replay berlaku sebelum `BEGIN`, header dan mutation.

Lock contention pula berhenti sebelum source fetch dan sebelum approval
consume. Ini mengelakkan token dibakar oleh request yang tidak memperoleh lock.

## 3. Komponen

- `SyncPlanApprovalGateInterface` — contract consume/validate yang tidak
  bergantung kepada session implementation tertentu;
- `SyncApprovalService` — kini melaksanakan approval gate;
- `SafeSyncOrchestrator::runApproved()` — laluan approval-aware yang membina
  dan menggunakan satu plan;
- `ApprovedSyncCoordinator` — satu-satunya public writer seam daripada factory;
- `SyncEngineFactory::createApprovedCoordinator()` — menerima approval store
  secara dependency injection dan tidak hardcode session store.

`SafeSyncOrchestrator::run()` dikekalkan untuk S3 characterization/backward
compatibility dalaman. Factory tidak mendedahkan method public untuk membina
raw orchestrator, jadi wiring S4 kelak perlu melalui approval coordinator.

## 4. Bukti Characterization

Fixture in-memory membuktikan:

- external source dipanggil tepat sekali;
- active dan inactive user snapshot masing-masing dibaca sekali;
- approval consume berlaku selepas plan dan sebelum transaction;
- changed snapshot menghasilkan `SYNC_APPROVAL_PLAN_MISMATCH` tanpa mutation;
- wrong admin dan expired approval berhenti sebelum transaction;
- replay tidak boleh membuka transaction kedua;
- lock contention tidak menyentuh source, approval atau persistence;
- reconciliation kekal berlaku sebelum commit;
- semua path melepaskan advisory lock.

Semua fixture menggunakan memory fake. Tiada database, ODBC, HTTP, session
runtime atau external production access berlaku.

## 5. Artefak

- `app/Sync/Contracts/SyncPlanApprovalGateInterface.php`;
- `app/Sync/ApprovedSyncCoordinator.php`;
- perubahan approval-aware dalam `SafeSyncOrchestrator.php`;
- factory public seam dalam `SyncEngineFactory.php`;
- `tests/characterization/s4c_approved_single_snapshot.php`;
- `tools/s4c_sync_coordinator_contract.php`;
- `npm run check:sync-coordinator`.

## 6. Status Gate

- S4-G08 lulus pada domain/coordinator level: satu fetched snapshot dan plan
  yang telah divalidasi digunakan oleh writer;
- S4-G07 kekal pending: preview runtime belum menggunakan approval issuance dan
  full S3 policy binding;
- S4-G10 kekal pending: endpoint Apply belum wujud;
- S4-G17 kekal pending sehingga S4C diberi checkpoint/release commit dan
  regression direkod terhadap commit tersebut.

## 7. Rollback

Rollback S4C ialah revert fail/interface/coordinator/factory/test/tool dan
rujukan dokumentasi S4C. Tiada database restore, external rollback, Nginx atau
PHP-FPM reload diperlukan kerana code kekal dormant dan flags tidak diaktifkan.

## 8. Baki Langkah

Selepas S4C, tinggal dua fasa besar:

1. **S4D — dormant deployment dan pre-pilot readiness:** checkpoint/release,
   regression deployment, topology approval store, backup/restore rehearsal,
   read-only privilege evidence dan fresh preview acceptance.
2. **S4E — satu controlled pilot:** memerlukan arahan GO baharu; enable
   `true/safe` hanya untuk satu request, disable semula, reconcile, monitor dan
   rekod ACCEPT atau ROLLBACK.

Status semasa kekal **NO-GO** untuk endpoint, UI Apply dan live sync.
