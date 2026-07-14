# Sync Application Seam

R5.2D1 menyediakan contract design tanpa production wiring:

- `ExternalUserSourceInterface` mengasingkan upstream fetch;
- `SyncPersistenceInterface` mengasingkan transaction dan persistence;
- `SyncPolicyInterface` mengasingkan exclusion serta category mapping;
- `InitialPasswordFactoryInterface` mengasingkan random password hash;
- `SyncRunSummary` mengekalkan bentuk result legacy melalui projection.

`lib/sync_user_runner.php` belum menggunakan interface ini. Adapter production
dan orchestration switch memerlukan batch, rollback dan validation berasingan.

R5.2D5 menambah `DTO/SyncPlan.php` sebagai immutable plan projection. R5.2D6
mengekstrak pure `SyncPlanner.php` ke application namespace; test-only dry-run
ialah consumer pertamanya. Tiada runtime/caller menggunakan planner atau DTO.
Hanya `safeProjection()`/`planHash()` sesuai untuk evidence—`actions` mentah
mengandungi data pengguna dan tidak boleh dilog.

R5.2D7 menyediakan empat class production dalam `Adapters/`. Semua masih dormant
dan hanya diuji melalui fake/spy; `run_admin_sync_user`, caller dan feature flag
belum menggunakannya.

R5.2D8 menambah dormant `SyncOrchestrator.php`. Full production-adapter parity
dengan legacy lulus, tetapi tiada runtime require/factory/caller wiring.

S3 menambah `SafeSyncOrchestrator.php` sebagai writer safety core yang masih
dormant. Ia mengambil advisory lock, menyiapkan snapshot/planning sebelum
transaction, menjalankan source/blast-radius gate, dan memerlukan exact parity
antara planned, executed dan audited counts sebelum commit. Adapter lock dan
reconciliation juga belum mempunyai production caller; Apply kekal disabled
sehingga controlled S4 wiring.

S4A menambah pure `SyncRuntimeConfig.php` dan dormant `SyncEngineFactory.php`.
Hanya exact `false/disabled` atau `true/safe` diterima; legacy tidak boleh
dipilih. Factory hanya membina dependency graph dan tidak melakukan I/O. Kedua-
dua class belum direquire atau dipanggil oleh runtime production.

S4B menambah canonical `SyncPlanFingerprinter`, one-time `SyncApprovalService`
dan dormant server-side session store. Approval ID, admin, expiry, plan counts
dan accepted baseline kini mempunyai contract.

S4C menambah `ApprovedSyncCoordinator` dan approval gate pada
`SafeSyncOrchestrator`. External/internal snapshot dibaca sekali, satu plan
divalidasi sebelum transaction dan plan object sama diserahkan kepada writer.
Factory hanya mendedahkan coordinator approval-aware. Semua ini masih dormant:
preview runtime, endpoint, UI Apply, cron dan live sync belum diwiring.
