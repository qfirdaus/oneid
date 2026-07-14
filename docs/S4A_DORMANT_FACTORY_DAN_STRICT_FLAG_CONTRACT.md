# S4A — Dormant Factory dan Strict Flag Contract

Tarikh: 14 Julai 2026
Change owner: Pemilik sistem OneID
Rollback owner: Pemilik sistem OneID
Status: **DIIMPLEMENTASIKAN SECARA DORMANT — APPLY KEKAL DISABLED**

## 1. Objektif dan Batas

S4A menyediakan keputusan flag pure dan factory dependency untuk safe sync.
Ia belum disambung kepada `lib/q_func.php`, `run_admin_sync_user`, dashboard,
Nginx, PHP-FPM, cron atau scheduler.

Tiada external fetch, database query, transaction, header, audit, user mutation
atau live sync dijalankan semasa S4A.

## 2. Strict Flag Matrix

`SyncRuntimeConfig` hanya menerima dua keadaan lengkap:

| Apply flag | Engine flag | Keputusan |
| --- | --- | --- |
| missing/empty/`false` | missing/empty/`disabled` | Valid; Apply disabled |
| `true` | `safe` | Valid untuk pembinaan safe orchestrator kelak |
| `false` | `safe` | Invalid combination |
| `true` | `disabled` | Invalid combination |
| nilai boolean lain | apa-apa | Invalid Apply flag |
| apa-apa | `legacy`/unknown | Invalid engine flag |

Parsing bersifat exact dan case-sensitive. Nilai seperti `TRUE`, `yes`, `on`,
whitespace atau `legacy` tidak dicoerce dan mesti fail-closed.

Stable diagnostic codes:

- `SYNC_APPLY_FLAG_INVALID`;
- `SYNC_ENGINE_INVALID`;
- `SYNC_FLAG_COMBINATION_INVALID`;
- `SYNC_APPLY_DISABLED`.

## 3. Dormant Factory

`SyncEngineFactory` hanya boleh menghasilkan `SafeSyncOrchestrator`. Factory
membina adapter S3 yang telah diuji, tetapi construction tidak:

- memanggil `fetchAll()`;
- membuka transaction;
- mendapatkan advisory lock;
- membuat database operation;
- memanggil `run()`.

Apabila config `false/disabled`, factory menolak pembinaan writer. Legacy runner
tidak mempunyai laluan pilihan dalam config/factory S4A.

## 4. Runtime State Yang Disahkan

Semakan read-only pada 14 Julai 2026 mendapati:

- shell `ONEID_SYNC_APPLY_ENABLED` tidak ditetapkan;
- shell `ONEID_SYNC_ENGINE` tidak ditetapkan;
- tiada rujukan kedua-dua flag dalam Nginx sites atau PHP-FPM pool config yang
  dapat dibaca;
- `lib/q_func.php` masih default `ONEID_SYNC_APPLY_ENABLED` kepada `false`;
- `q_func`, legacy runner dan dashboard tidak merujuk class S4A;
- dashboard kekal preview-only.

Oleh sebab tiada runtime wiring, `true/safe` dalam unit fixture hanya input pure
in-memory dan bukan perubahan environment deployment.

## 5. Artefak

- `app/Sync/SyncRuntimeConfig.php`;
- `app/Sync/SyncEngineFactory.php`;
- `tests/characterization/s4a_sync_factory_flags.php`;
- `tools/s4a_sync_wiring_contract.php`;
- `npm run check:sync-wiring` dalam `package.json`;
- kemaskini `app/Sync/README.md` dan register S4.

## 6. Verification

Contract S4A membuktikan:

- missing flags menjadi `false/disabled`;
- hanya exact `true/safe` boleh menghasilkan `canApply=true`;
- partial, malformed, legacy dan unknown values ditolak;
- disabled factory melakukan zero operation I/O;
- safe factory construction melakukan zero operation I/O;
- factory menghasilkan `SafeSyncOrchestrator` sahaja;
- tiada caller production atau UI Apply wiring.

S4A tidak menguji live writer kerana itu dilarang dalam subfasa ini.

Keputusan verification 14 Julai 2026:

- S4A static/fixture contract: 16/16 lulus;
- S3 safety regression: 26/26 lulus;
- S2 preview regression: 29/29 lulus;
- S1 provisioning regression: 39/39 lulus;
- seluruh characterization suite: lulus;
- PHP lint semasa: 117 fail, 0 gagal;
- HTTP public-root smoke: 10/10 lulus.

## 7. Baseline Checksum

| Fail | SHA-256 |
| --- | --- |
| `app/Sync/SyncRuntimeConfig.php` | `be6b8467b74eb7caffdf8f169ae172b1660bc932b380afc8bb31c87795747162` |
| `app/Sync/SyncEngineFactory.php` | `82b0f3171fa7419df4db808144f97e6548a355a5656ebdea62e6116f1e8f1472` |
| `lib/q_func.php` | `6715f149be5a22aca57ca31eb74a2c445fc104b30cb7422fb0f8d693efc60e7a` |
| `lib/sync_user_runner.php` | `965fd187492e1f120b074601746b031474405480f234412e458f64189108c8bb` |
| `admin/dashboard.php` | `8aedadb1374b7e11bb42b844fbc2dbc0f1969cd618d426c4ea92e93fe38455b5` |

## 8. Rollback

S4A rollback hanya membuang dua class, fixture, contract, package script dan
rujukan dokumentasi S4A. Tiada service reload atau data rollback diperlukan
kerana class tidak mempunyai production caller dan environment tidak diubah.

## 9. Langkah Berikutnya

Langkah berikutnya ialah **S4B — server-bound preview approval dan zero-mutation
rejection contracts**. Ia mesti dibina dahulu secara test-only/dormant. Apply UI,
runtime endpoint dan live sync masih tidak boleh diaktifkan.
