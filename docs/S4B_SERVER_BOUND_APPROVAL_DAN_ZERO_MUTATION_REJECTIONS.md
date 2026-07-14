# S4B — Server-bound Approval dan Zero-mutation Rejections

Tarikh: 14 Julai 2026
Change owner: Pemilik sistem OneID
Rollback owner: Pemilik sistem OneID
Status: **APPROVAL DOMAIN DORMANT SIAP — TIADA ENDPOINT/APPLY WIRING**

## 1. Objektif dan Batas

S4B menyediakan one-time approval yang disimpan server-side serta canonical
plan fingerprint. Ia belum disambung kepada preview controller,
`SafeSyncOrchestrator`, factory, `q_func`, dashboard, cron atau Nginx/PHP-FPM.

Tiada external fetch, database query, application transaction, user mutation,
live sync atau perubahan environment dijalankan semasa S4B.

## 2. Canonical Plan Fingerprint

`SyncPlanFingerprinter` menggunakan `safeProjection()` yang menggantikan raw
user ID dengan digest dan tidak membawa nama/email/source row. Action disusun
secara canonical sebelum SHA-256 dikira, jadi fingerprint stabil walaupun ODBC
memulangkan set row sama dalam susunan berbeza.

Fingerprint turut mengikat:

- empat action counts;
- source row count;
- invalid/excluded counts;
- protected manual user/collision counts;
- category, changed fields dan change hash;
- planner warnings yang disusun canonical.

Perubahan material kepada plan mesti menghasilkan fingerprint berbeza.

## 3. Approval Record dan Safe Receipt

Record server-side mengandungi:

- random 256-bit approval ID;
- random correlation ID berasingan;
- bound admin ID;
- canonical plan fingerprint dan counts;
- source rows dan accepted baseline;
- issued/expiry time.

Approval ID ialah bearer secret dan tidak boleh dimasukkan ke log. Correlation
ID berasingan boleh digunakan untuk safe operational logging. Receipt kepada
caller tidak mengandungi bound admin ID atau raw plan PII.

TTL default dan maksimum ialah 300 saat. Empty admin, missing baseline, invalid
TTL dan malformed approval ID ditolak dengan stable diagnostic code.

## 4. Consume-before-validate

`consumeAndValidate()` membuang approval daripada store dahulu, kemudian
menyemak:

1. expiry;
2. admin binding menggunakan `hash_equals`;
3. canonical fingerprint;
4. counts dan source row count.

Wrong-admin, expired atau mismatch attempt membakar approval. Replay seterusnya
menerima `SYNC_APPROVAL_NOT_AVAILABLE`. Pendekatan fail-closed ini mengelakkan
token gagal digunakan berulang kali; operator perlu menjana preview baharu.

## 5. Server-side Session Store

`SessionSyncApprovalStore`:

- memerlukan PHP session aktif;
- menyimpan record dalam server-side `$_SESSION`;
- membuang record expired;
- mengehadkan pending approvals kepada lima secara default;
- remove-before-return bagi one-time consume;
- belum direquire atau digunakan oleh web runtime.

S4C perlu memastikan session storage sesuai untuk topology deployment. Jika
lebih daripada satu PHP node digunakan tanpa shared session store, session
affinity atau centralized approval store diperlukan sebelum runtime wiring.

## 6. Maksud Zero Mutation

Issuance/consume sememangnya mengubah approval state server-side. “Zero
mutation rejection” dalam S4B bermaksud rejection path tidak mempunyai akses
kepada:

- `SyncPersistenceInterface` atau `Database`;
- `begin`, `commit` atau rollback;
- external source;
- user/header/staging/audit writer.

Oleh itu invalid approval tidak boleh mengubah database aplikasi. Integrasi
dengan transaction writer masih belum dibuat dan kekal gate S4C.

## 7. Stable Diagnostic Codes

- `SYNC_APPROVAL_TTL_INVALID`;
- `SYNC_APPROVAL_ADMIN_INVALID`;
- `SYNC_APPROVAL_BASELINE_INVALID`;
- `SYNC_APPROVAL_INVALID`;
- `SYNC_APPROVAL_NOT_AVAILABLE`;
- `SYNC_APPROVAL_EXPIRED`;
- `SYNC_APPROVAL_ADMIN_MISMATCH`;
- `SYNC_APPROVAL_PLAN_MISMATCH`;
- `SYNC_APPROVAL_SESSION_REQUIRED`;
- `SYNC_APPROVAL_STORE_LIMIT_INVALID`;
- `SYNC_APPROVAL_CORRUPT`.

Runtime response kelak mesti memetakan code kepada mesej generik dan hanya
correlation ID boleh dilog. Jangan log full approval ID atau bound admin data.

## 8. Artefak

- `app/Sync/Contracts/SyncApprovalStoreInterface.php`;
- `app/Sync/DTO/SyncApproval.php`;
- `app/Sync/DTO/SyncApprovalReceipt.php`;
- `app/Sync/SyncPlanFingerprinter.php`;
- `app/Sync/SyncApprovalService.php`;
- `app/Sync/Adapters/SessionSyncApprovalStore.php`;
- `tests/characterization/s4b_sync_approval_rejections.php`;
- `tools/s4b_sync_approval_contract.php`;
- `npm run check:sync-approval`.

## 9. Verification

- S4B static contract: 19/19 lulus;
- S4B in-memory rejection fixture: 26/26 lulus;
- S4A strict factory regression: 16/16 lulus;
- S3 safety regression: 26/26 lulus;
- S2 preview regression: 29/29 lulus;
- S1 provisioning regression: 39/39 lulus;
- seluruh characterization suite: lulus;
- PHP lint semasa: 125 fail, 0 gagal;
- HTTP public-root smoke: 10/10 lulus.

Fixture membuktikan canonical order, material mismatch, safe receipt, exact
expiry boundary, wrong admin, replay, malformed ID, invalid TTL/baseline serta
consume-on-failure tanpa database capability.

## 10. Baseline Checksum

Checksum di bawah ialah baseline tepat pada checkpoint S4B `a565c99`. Fail
`SyncApprovalService.php` dan `SyncEngineFactory.php` kemudiannya berubah dalam
S4C untuk interface/coordinator wiring dormant; nilai ini tidak mendakwa sebagai
checksum working tree selepas S4C.

| Fail | SHA-256 |
| --- | --- |
| `SyncApprovalStoreInterface.php` | `cbf031e9c9accf7aefce46985bfcfa41fa57d1a3298a339f340159844af47804` |
| `DTO/SyncApproval.php` | `a7b0eeca892731c0e6a4909645c849ef2700606da0c3567655fd0f351e10d02e` |
| `DTO/SyncApprovalReceipt.php` | `7673264d8f16391cd80f4a63fb4e45982ac933f378346fe6b1344c0d55ae1be6` |
| `SyncPlanFingerprinter.php` | `1ab27b793594d8aae388a525a83c049ee1b18f1c94c0850d4f1d9d75e34d6566` |
| `SyncApprovalService.php` | `86e3e3627eb34aa2127032b0f359b6073d806e0c517922dd42a5da2d63912596` |
| `Adapters/SessionSyncApprovalStore.php` | `4b23fc763a6d5f71a911fbfab9a28aff53c553aa0b401c14e8a6f5ec550b415e` |
| `lib/q_func.php` | `6715f149be5a22aca57ca31eb74a2c445fc104b30cb7422fb0f8d693efc60e7a` |
| `admin/dashboard.php` | `8aedadb1374b7e11bb42b844fbc2dbc0f1969cd618d426c4ea92e93fe38455b5` |
| `SyncEngineFactory.php` | `82b0f3171fa7419df4db808144f97e6548a355a5656ebdea62e6116f1e8f1472` |

## 11. Limitasi dan NO-GO

Limitasi same-snapshot/factory ini telah diselesaikan secara dormant oleh S4C.
Preview runtime masih belum menerbitkan approval dan endpoint Apply masih tidak
wujud. Oleh itu:

- G07 kekal pending untuk full preview-policy/runtime binding;
- G08 lulus pada coordinator/domain level melalui S4C;
- endpoint, UI Apply dan live pilot kekal NO-GO.

## 12. Rollback

Rollback S4B membuang approval contract/DTO/service/store, test/tool, package
script dan rujukan dokumentasi. Tiada database restore atau service reload
diperlukan kerana tiada runtime caller atau environment change.

## 13. Langkah Berikutnya

S4C telah dilaksanakan secara dormant. Langkah berikutnya ialah **S4D — dormant
deployment dan pre-pilot readiness**. Endpoint, UI Apply dan live sync masih
tidak boleh diaktifkan.
