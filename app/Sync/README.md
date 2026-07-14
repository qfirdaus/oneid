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
