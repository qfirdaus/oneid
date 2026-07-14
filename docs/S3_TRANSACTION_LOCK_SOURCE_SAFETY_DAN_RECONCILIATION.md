# S3 — Transaction, Lock, Source Completeness dan Reconciliation Safety

Tarikh: 14 Julai 2026
Owner perubahan: Pemilik sistem OneID
Owner rollback: Pemilik sistem OneID
Status: **SAFETY CORE DORMANT DIIMPLEMENTASIKAN — BELUM DISAMBUNG KE PRODUCTION CALLER**

## 1. Keputusan Ringkas

S3 menyediakan laluan writer baharu yang fail-closed melalui
`app/Sync/SafeSyncOrchestrator.php`. Laluan ini belum digunakan oleh
`run_admin_sync_user`, `lib/q_func.php`, dashboard, cron atau mana-mana caller
production. Feature gate Apply sedia ada kekal default-disabled.

Tiada live sync, perubahan pengguna, transaksi ke external database atau
production writer dijalankan semasa implementasi dan ujian S3. Semua ujian
orchestration S3 menggunakan fake/in-memory.

## 2. Masalah Yang Ditemui

Characterization terhadap writer legacy dan orchestrator dormant terdahulu
menemui empat risiko utama:

1. transaksi dan sync header diwujudkan sebelum external snapshot selesai;
2. kegagalan upstream boleh berlaku di luar rollback guard;
3. tiada single-run lock untuk menghalang dua full sync serentak;
4. commit tidak mensyaratkan kiraan plan, writer dan audit log sepadan.

Keadaan itu boleh menghasilkan transaksi panjang, run bertindih, state separuh
siap atau laporan sync yang tidak sepadan dengan mutation sebenar.

## 3. Transaction Boundary Baharu

Urutan dormant S3 ialah:

```text
acquire lock
  → fetch external snapshot
  → baca internal snapshot
  → bina pure SyncPlan
  → jalankan source/blast-radius policy
  → BEGIN transaction
  → create header dan jalankan writer
  → tulis audit dan summary
  → reconcile planned = executed = audited
  → COMMIT
  → release lock
```

External I/O dan planning berlaku sebelum `BEGIN`. Selepas transaksi bermula,
semua `Throwable` memasuki rollback guard. Kegagalan rollback tidak menutup
exception asal. Lock dilepaskan melalui `finally`.

## 4. Single-run Lock

`DatabaseSyncRunLock` menggunakan MySQL advisory lock:

- nama tetap: `oneid:external-user-sync`;
- acquisition default tidak menunggu (`waitSeconds=0`);
- run kedua menerima kod stabil `SYNC_ALREADY_RUNNING`;
- lock connection-scoped dan turut dilepaskan oleh MySQL jika connection mati;
- input nama lock dihadkan kepada aksara selamat dan maksimum 64 aksara.

Lock ini bukan row/table lock dan tidak menyentuh external database. Ia hanya
akan digunakan apabila S4 secara eksplisit menyambung orchestrator baharu.

## 5. Source Completeness dan Blast-radius Gate

Default polisi `SyncSafetyPolicy`:

| Gate | Default | Keputusan |
| --- | ---: | --- |
| External snapshot kosong | 0 row | Block |
| Staff source tiada | 0 staff | Block |
| Student source tiada | 0 student | Block |
| Deactivation luar biasa | lebih 5% sync scope | Block |
| Source menyusut | lebih 20% berbanding baseline | Block |
| Invalid source row | lebih 1% | Block |
| Protected manual collision | sekurang-kurangnya 1 | Block |
| Category baharu/tidak dikenali | category ID 0 | Block |

Jika baseline run terdahulu belum dibekalkan, policy mengeluarkan warning
`SOURCE_BASELINE_UNAVAILABLE`; gate lain masih berkuat kuasa. Baseline source
yang authoritative mesti ditentukan semasa S4 sebelum live Apply dibenarkan.

## 6. Reconciliation Sebelum Commit

Tiga set kiraan mesti sama tepat:

- `planned`: kiraan daripada immutable `SyncPlan`;
- `executed`: kiraan action yang benar-benar dilalui writer;
- `audited`: `COUNT(*) GROUP BY action` daripada `sync_change_log` bagi header.

Perbandingan meliputi `New`, `Update`, `Deactivate` dan `Reactivate`. Sebarang
perbezaan menghasilkan `SYNC_RECONCILIATION_MISMATCH`, kemudian transaksi
di-rollback dan tidak boleh commit.

Production persistence adapter turut menolak `rowCount=0` bagi deactivate,
update dan insert, serta menolak jumlah audit insert yang tidak sama dengan
bilangan log yang diminta. Oleh itu action yang tidak benar-benar memberi kesan
tidak boleh dinaikkan sebagai `executed` secara senyap.

## 7. Artefak Implementasi

- `app/Sync/SafeSyncOrchestrator.php` — orchestration fail-closed dormant;
- `app/Sync/SyncSafetyPolicy.php` — pure safety decision;
- `app/Sync/SyncReconciler.php` — exact count reconciliation;
- `app/Sync/SyncSafetyViolation.php` dan `DTO/SyncSafetyDecision.php`;
- `app/Sync/Contracts/SyncRunLockInterface.php`;
- `app/Sync/Contracts/SyncReconciliationReaderInterface.php`;
- `app/Sync/Adapters/DatabaseSyncRunLock.php`;
- `app/Sync/Adapters/DatabaseSyncReconciliationReader.php`;
- affected-row enforcement dalam `DatabaseSyncPersistenceAdapter.php`;
- tiga primitive dormant dalam `lib/Database.php`;
- `tests/characterization/s3_sync_operational_safety.php`;
- `tools/s3_sync_safety_contract.php` dan `npm run check:sync-safety`.

## 8. Bukti Ujian

Fixture S3 membuktikan:

- lock contention menyebabkan sifar source/persistence access;
- upstream failure memulakan sifar transaksi/header dan lock dilepaskan;
- source tidak lengkap berhenti sebelum transaksi;
- writer exception di-rollback dan tidak commit;
- audit mismatch di-rollback sebelum commit;
- happy path melakukan fetch sebelum transaction dan reconciliation sebelum
  commit;
- mass deactivation, source shrink dan protected collision fail-closed.
- category sumber tidak dikenali fail-closed walaupun row itu bukan action NEW.
- zero-row mutation dan audit-write count mismatch fail-closed.

Ujian ini tidak mengesahkan live MySQL mutation. Ia sengaja hanya mengesahkan
contract dan ordering tanpa risiko data.

Keputusan verification 14 Julai 2026:

- S3 static/contract: 26/26 lulus;
- S3 in-memory operational fixture: 24/24 lulus;
- S2 preview regression: 29/29 lulus;
- S1 provisioning regression: 39/39 lulus;
- HTTP public-root smoke: 10/10 lulus;
- PHP lint: 102 fail tracked, 0 gagal.

## 9. Perkara Yang Belum Diaktifkan

- tiada factory/runtime require bagi `SafeSyncOrchestrator`;
- tiada perubahan pada caller `run_admin_sync_user`;
- tiada butang Apply baharu;
- tiada cron/scheduler diaktifkan;
- tiada transaksi atau write ke external staff/student production database;
- belum ada controlled pilot terhadap OneID UAT writer;
- baseline previous-source authoritative belum diwiring;
- operational correlation/monitoring dan backup rehearsal perlu disahkan dalam
  runbook S4 sebelum live pilot.

## 10. Rollback

Selagi S3 dormant, rollback kod ialah revert fail S3 dan tiga method dormant
Database; behavior production tidak berubah. Jika S4 kelak diaktifkan, rollback
mesti mematikan feature flag dahulu, memastikan tiada lock/run aktif, kemudian
memulihkan data OneID daripada checkpoint yang diluluskan jika pilot telah
melakukan mutation.

## 11. Exit dan Langkah Seterusnya

S3 safety core dianggap lengkap apabila contract S3, S2 preview, S1
provisioning dan regression suite lulus serta static check membuktikan tiada
production wiring.

Langkah selepas itu ialah **S4 — controlled feature-flag wiring dan pilot
runbook**. S4 tidak boleh terus menjalankan Apply: ia perlu menetapkan baseline,
backup, observation window, owner approval, monitoring dan rollback rehearsal
terlebih dahulu.

Runbook tersebut kini tersedia di
`docs/S4_CONTROLLED_FEATURE_FLAG_WIRING_DAN_PILOT_RUNBOOK.md`. Status kekal
NO-GO sehingga semua gate S4 yang wajib mempunyai evidence dan arahan GO baharu
diberikan oleh change owner.
