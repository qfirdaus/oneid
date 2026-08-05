# Audit Semula Cronjob External Sync Staff, UG dan ODL

**Tarikh audit:** 5 Ogos 2026

**Environment/codebase:** OneID UAT

**Skop:** `STAFF_HR`, `STUDENT_UG`, `STUDENT_ODL_PG`

**Status:** `IMPLEMENTED DORMANT / DEPLOYMENT NOT YET APPROVED`

**Implementation update (5 Ogos 2026):** CLI runner, strict cron config,
source-specific conditional precheck, process-local one-use approval,
zero-change skip, dry-run, threshold/deactivation block dan audit outcome telah
dilaksanakan. Default committed kekal `enabled=false` dan `dry_run=true`.

## 1. Keputusan Audit

Code semasa mempunyai asas yang sesuai untuk cronjob. Cronjob tidak memanggil
endpoint Admin dan tidak terus memanggil writer tanpa precheck. Seam CLI khusus
telah dilaksanakan untuk:

1. menggunakan source scope, planner, safety policy, advisory lock,
   transaction dan reconciliation sedia ada;
2. membuat preflight/plan read-only dan berhenti sebelum writer apabila plan
   kosong, blocked, warning atau melebihi threshold cron;
3. mempunyai authorization, feature flag, dry-run, audit marker dan monitoring
   khusus scheduler yang tidak bergantung pada session/CSRF/typed confirmation;
4. menjalankan Staff, UG dan ODL sebagai tiga run source-specific, bukan satu
   aggregate writer; dan
5. mengekalkan `Deactivate=0` untuk auto-Apply pada fasa awal.

Keputusan semasa ialah **implementation dan contract test siap**, tetapi **belum
layak mengaktifkan unattended mutation** sehingga dry-run dan deployment gate
lulus.

## 2. Evidence Code Semasa

### 2.1 Source dan source scope

| Sumber | Code | Upstream | Category scope | Provenance |
|---|---|---|---|---|
| Staff | `STAFF_HR` | Sybase/ODBC `SSO_Staf_Aktif` | `2,3` | Optional, dikawal `ONEID_SYNC_STAFF_PROVENANCE_ENABLED` |
| Undergraduate | `STUDENT_UG` | Sybase/ODBC `v210_sso_student_aktif` | `10,11,12` | Wajib |
| ODL postgraduate | `STUDENT_ODL_PG` | MySQL/TLS `student_basic_info` | `10` | Wajib |

Canonical mapping berada dalam `app/Sync/SyncSourceScope.php`. Ketiga-tiga
source menggunakan fixed query dan menambah `source_code` pada row yang telah
dinormalisasi. Source kosong atau connection/query failure gagal secara
fail-closed.

ODL mempunyai kawalan tambahan: TLS mesti aktif, private Preview/Apply gate,
exact plan authorization/change window bagi mode biasa, dan on-demand hanya
untuk `staging`/`uat`.

### 2.2 Planning, writing dan database safety

`SyncEngineFactory` membina source-specific `SafeSyncOrchestrator` dengan:

- source-scoped persistence;
- pure `SyncPlanner`;
- `SyncSafetyPolicy(requiredSourceCode: ...)`;
- MySQL advisory lock `oneid:external-user-sync`;
- transaction dan rollback;
- exact reconciliation planned/executed/audited sebelum commit; dan
- source membership/ownership guard untuk source yang provenance-enforced.

Lock database adalah global kepada External Sync. Oleh itu Staff, UG, ODL dan
Admin Apply tidak boleh menulis serentak. Ini perlu dikekalkan.

### 2.3 Manual authorization semasa

Flow Admin sekarang ialah:

```text
HTTP POST + Admin session + CSRF
  -> source-specific Preview
  -> SessionSyncApprovalStore (one-use, 5 minit)
  -> exact plan fingerprint/counts
  -> typed confirmation
  -> fresh source fetch dan plan validation
  -> safe writer
```

Flow ini sesuai untuk manusia tetapi tidak sesuai untuk scheduler kerana tiada
Admin session, cookie, CSRF atau typed confirmation dalam cron. Endpoint
`admin_apply_operational_sync` tidak boleh dipanggil melalui `curl`, synthetic
session atau credential Administrator.

### 2.4 Zero-change behavior

UI semasa tidak menawarkan Apply apabila jumlah plan sifar. Walau bagaimanapun,
`SafeSyncOrchestrator` sendiri tetap mencipta header jika dipanggil dengan plan
kosong. Cron runner mesti membuat zero-change decision sebelum memanggil writer,
atau implementation perlu menambah coordinator seam yang menjamin
`SKIP_NO_CHANGES` dengan:

- zero transaction;
- zero `ext_data_temp_header`;
- zero `sync_change_log`; dan
- zero database audit marker.

Ini ialah blocker implementation, bukan isu yang boleh diselesaikan dalam
crontab sahaja.

## 3. Penemuan Mengikut Sumber

### 3.1 Staff (`STAFF_HR`)

**Kekuatan**

- Fixed read-only ODBC query dan empty-source rejection tersedia.
- Category read scope dihadkan kepada Staff (`2,3`).
- Manual/protected account dimasukkan untuk collision protection.
- Menggunakan planner, global lock, transaction dan reconciliation yang sama.

**Gap/keputusan cron**

- Staff provenance masih feature flag dan default `false`. Tanpa provenance,
  scoping bergantung pada category, berbeza daripada UG/ODL yang membership-
  scoped. Sebelum auto-Apply Staff, audit live perlu membuktikan category scope
  tidak mengandungi akaun bukan `STAFF_HR`, atau aktifkan dan reconcile Staff
  provenance terlebih dahulu.
- Baseline menggunakan key legacy bernama
  `ONEID_ODL_SHADOW_STAFF_BASELINE_ROWS`; ia boleh digunakan tetapi patut
  dinormalisasi kepada key cron/source baseline yang jelas atau dikekalkan
  melalui documented compatibility mapping.

**Status:** `CONDITIONAL`; dry-run boleh dibina, auto-Apply menunggu bukti
provenance/source ownership.

### 3.2 Undergraduate (`STUDENT_UG`)

**Kekuatan**

- Fixed read-only ODBC query dan empty-source rejection tersedia.
- Active/inactive reads dan writes provenance-scoped kepada `STUDENT_UG`.
- Category scope `10,11,12` menyokong student dan mixed staff/student mapping.
- Other-active-source check mengelakkan akaun dinyahaktifkan apabila masih
  mempunyai membership aktif daripada sumber lain.

**Gap/keputusan cron**

- Baseline menggunakan key legacy `ONEID_ODL_SHADOW_UG_BASELINE_ROWS`.
- Cron masih memerlukan hard thresholds khusus; threshold Operational semasa
  hanyalah advisory/typed-confirmation policy manusia.

**Status:** `READY FOR CRON CONTRACT + DRY-RUN`; auto-Apply hanya selepas
threshold dan monitoring gate diluluskan.

### 3.3 ODL (`STUDENT_ODL_PG`)

**Kekuatan**

- MySQL connection mewajibkan TLS evidence dan native prepares.
- Source/category/provenance isolation paling ketat antara tiga sumber.
- Blank e-mail ODL tidak memadam e-mail OneID sedia ada.
- Cross-source identity/membership collision dan source snapshot isolation
  mempunyai gate khusus.

**Gap/keputusan cron**

- `SyncSourceScope::fromCode()` mewajibkan ODL operational Preview enabled.
- Manual ODL Apply biasa terikat kepada expected source rows, exact counts,
  exact plan hash, change reference, backup reference dan change window.
  Kontrak ini sengaja tidak sesuai untuk recurring unattended sync.
- On-demand bypass hanya dibenarkan di staging/UAT dan masih bergantung kepada
  Admin approval. Ia tidak boleh dijadikan authorization cron production.
- Factory writer tidak memasukkan callback snapshot-isolation tambahan yang
  digunakan oleh Preview ODL; writer masih mendapat plan fingerprint dan
  source-scoped persistence, tetapi cron design patut mewajibkan validation
  isolation yang sama pada setiap fresh snapshot.

**Status:** `CONDITIONAL`; dry-run boleh dibina. Auto-Apply memerlukan cron
authorization policy ODL yang berasingan dan kelulusan owner ODL/production.

## 4. Gap Merentas Semua Sumber

| ID | Gap | Severity | Keperluan sebelum cron aktif |
|---|---|---:|---|
| C-01 | CLI runner | Closed | `cron/run_conditional_external_sync.php`, CLI-only |
| C-02 | Strict `ONEID_SYNC_CRON_*` config | Closed | Default disabled/dry-run dan per-source threshold |
| C-03 | Session-independent one-use approval | Closed | Process-local, exact-plan-bound approval store |
| C-04 | Empty plan menghasilkan header jika writer dipanggil | Closed at cron seam | `SKIP_NO_CHANGES` sebelum coordinator/writer |
| C-05 | Hard cron thresholds per source | Closed | Threshold private runtime per source/action/total |
| C-06 | Operational warnings tidak menyekat writer | Closed at cron seam | Cron block semua warning pada rollout awal |
| C-07 | Secondary audit marker berlaku selepas commit dan boleh gagal | Partial | `APPLIED_AUDIT_WARNING` tersedia; monitoring deployment masih perlu |
| C-08 | Tiada cron run state/heartbeat/idempotency record | High | Correlation/run ID, start/end/outcome dan stale-run monitoring |
| C-09 | Tiada OS service account/log rotation definition | High | Deployment unit, permission, retention dan HTTP denial |
| C-10 | Staff provenance default `false` | High | Reconcile/enable provenance atau bukti ownership scope |
| C-11 | ODL recurring authorization | Closed in code, pending owner gate | Cron seam berasingan dan tidak reuse on-demand bypass |
| C-12 | Tiada notification owner/channel | High | Alert untuk `BLOCKED`, `FAILED`, audit warning dan stale run |

## 5. Kontrak Cronjob Yang Dicadangkan

### 5.1 Satu invocation, tiga run terasing

Satu scheduler invocation boleh mengorkestrasi ketiga-tiga sumber, tetapi setiap
sumber mesti mempunyai plan, decision dan result sendiri dalam susunan tetap:

```text
STAFF_HR -> STUDENT_UG -> STUDENT_ODL_PG
```

Kegagalan satu source tidak boleh menyebabkan writer bagi source itu diteruskan.
Untuk rollout awal, keputusan fail-fast disyorkan: hentikan invocation selepas
source `BLOCKED`/`FAILED` supaya operator menyiasat source stability sebelum
source seterusnya. Selepas bukti operasi mencukupi, polisi continue-on-failure
boleh dinilai semula tanpa berkongsi transaction antara sumber.

Jangan gabungkan semua row ke aggregate legacy source kerana category overlap
UG/ODL dan provenance membership memerlukan source-specific planning.

### 5.2 Aliran wajib setiap source

```text
validate PHP_SAPI=cli + environment + enabled/dry-run flags
  -> acquire OS flock untuk keseluruhan invocation
  -> resolve canonical SyncSourceScope
  -> fetch snapshot #1 dan source-scoped OneID reads
  -> build plan + safety decision + cron hard-threshold decision
  -> no changes? SKIP_NO_CHANGES (zero mutation)
  -> any blocking/warning/deactivate/over-limit? BLOCKED_REQUIRES_ADMIN
  -> dry-run? DRY_RUN_CHANGES_FOUND (zero mutation)
  -> issue ephemeral plan-bound machine approval
  -> safe coordinator fetches snapshot #2 under DB advisory lock
  -> plan/baseline/count drift? BLOCKED_PLAN_DRIFT
  -> transaction + source-scoped writes + reconciliation + commit
  -> durable result/audit marker + non-PII output
```

Catatan concurrency: orchestrator sekarang mengambil DB advisory lock sebelum
external fetch. Ini selamat dan menghalang Admin Apply semasa fresh-plan fetch,
tetapi upstream yang perlahan akan memegang lock lebih lama. Kekalkan behavior
untuk correctness pada fasa awal; ukur lock duration sebelum sebarang optimasi.

### 5.3 Config minimum

Default committed mesti kekal fail-closed:

```php
'ONEID_SYNC_CRON_ENABLED' => 'false',
'ONEID_SYNC_CRON_DRY_RUN' => 'true',
'ONEID_SYNC_CRON_SOURCES' => 'STAFF_HR,STUDENT_UG,STUDENT_ODL_PG',
'ONEID_SYNC_CRON_MAX_NEW_STAFF_HR' => '50',
'ONEID_SYNC_CRON_MAX_UPDATE_STAFF_HR' => '250',
'ONEID_SYNC_CRON_MAX_REACTIVATE_STAFF_HR' => '20',
'ONEID_SYNC_CRON_MAX_TOTAL_STAFF_HR' => '300',
'ONEID_SYNC_CRON_MAX_NEW_STUDENT_UG' => '50',
'ONEID_SYNC_CRON_MAX_UPDATE_STUDENT_UG' => '250',
'ONEID_SYNC_CRON_MAX_REACTIVATE_STUDENT_UG' => '20',
'ONEID_SYNC_CRON_MAX_TOTAL_STUDENT_UG' => '300',
'ONEID_SYNC_CRON_MAX_NEW_STUDENT_ODL_PG' => '20',
'ONEID_SYNC_CRON_MAX_UPDATE_STUDENT_ODL_PG' => '100',
'ONEID_SYNC_CRON_MAX_REACTIVATE_STUDENT_ODL_PG' => '10',
'ONEID_SYNC_CRON_MAX_TOTAL_STUDENT_ODL_PG' => '120',
'ONEID_SYNC_CRON_MAX_DEACTIVATE' => '0',
```

Nilai di atas ialah baseline konservatif untuk approval, bukan nilai yang telah
diluluskan. Parser mesti menolak whitespace/format tidak canonical, integer
negatif, source tidak dikenali, duplicate source dan threshold tidak konsisten.
Config tidak boleh dioverride melalui CLI arguments.

Operational Admin flags dan ODL manual flags mesti kekal berasingan daripada
cron flags. Emergency stop cron tidak boleh menutup manual recovery path.

### 5.4 Exit code dan output

Cadangan stable outcomes:

| Outcome | Exit | Mutation | Alert |
|---|---:|---:|---|
| `SKIP_NO_CHANGES` | 0 | Tidak | Tidak |
| `DRY_RUN_CHANGES_FOUND` | 0 | Tidak | Informational |
| `SKIP_ALREADY_RUNNING` | 0 | Tidak | Jika berulang |
| `APPLIED` | 0 | Ya | Informational/reconcile |
| `BLOCKED_REQUIRES_ADMIN` | 20 | Tidak | Ya |
| `BLOCKED_PLAN_DRIFT` | 21 | Tidak | Ya |
| `FAILED` | 1 | Tidak, atau unknown selepas connection loss | Ya segera |
| `APPLIED_AUDIT_WARNING` | 22 | Ya | Ya segera; jangan retry writer |

Output hanya boleh mengandungi timestamp, environment, source code, run ID,
header ID, counts, duration dan stable code. Jangan log IC, matrik, nama, e-mail,
raw action, DSN, credential, full exception message atau plan rows.

### 5.5 Idempotency dan retry

- Jangan auto-retry writer dalam invocation yang sama.
- `APPLIED_AUDIT_WARNING` tidak boleh diulang kerana commit mungkin telah
  berjaya; reconcile header dahulu.
- Selepas timeout/connection loss, semak durable header/change log dengan run ID
  sebelum operator menjalankan semula.
- Setiap run menggunakan random correlation ID dan stable service identity,
  contoh `ONEID External Sync Cron`, bukan ID Administrator manusia.

## 6. Contract dan Test Wajib

Sebelum staging scheduler dipasang, tambah contract/fixture berikut:

1. CLI-only dan all-defaults-disabled.
2. Invalid flag/source/threshold fail sebelum external/database I/O.
3. Zero plan bagi Staff, UG dan ODL menghasilkan zero mutation/header/audit.
4. Dry-run dengan perubahan menghasilkan zero mutation.
5. Deactivate `>0` sentiasa blocked bagi setiap source.
6. Invalid row, excluded identity, protected collision, unknown category,
   missing source dan shrink warning semuanya blocked.
7. Per-source New/Update/Reactivate/Total threshold boundary (`= limit` pass,
   `> limit` block).
8. Snapshot #2 drift membakar approval dan zero transaction.
9. Cross-source identity/membership collision khusus UG/ODL.
10. Blank ODL email preserve behavior.
11. Staff provenance off/on ownership fixtures.
12. OS lock dan DB lock contention.
13. Exception pada setiap persistence stage menyebabkan rollback.
14. Reconciliation mismatch menyebabkan rollback.
15. Commit-success/audit-marker-failure menghasilkan
    `APPLIED_AUDIT_WARNING`, bukan retry.
16. Logs dan exception projection bebas PII/secrets.
17. Source order, fail-fast dan source result isolation.
18. Disable flag berhenti sebelum source fetch.

Contract sedia ada yang lulus pada audit ini:

```text
tools/s4g_operational_sync_contract.php        24/24 PASS
tools/source_scoped_sync_apply_contract.php    11/11 PASS
tools/odl_f9_manual_operational_contract.php   23/23 PASS
tools/s3_sync_safety_contract.php              26/26 PASS
tools/s4c_sync_coordinator_contract.php        15/15 PASS
tools/s4h_conditional_sync_cron_contract.php   15/15 PASS
```

Kelulusan contract sedia ada membuktikan manual safe flow, bukan kelulusan
unattended cron.

## 7. Rollout Gate

### Fasa A — Implementation dormant

- Bina config, machine approval gate, precheck/coordinator dan CLI runner.
- Semua defaults `false`/dry-run.
- Jangan ubah crontab deployment aktif.

### Fasa B — Manual CLI dry-run

- Jalankan sebagai service account bukan `root`, web server atau PHP-FPM user.
- Banding exact counts/hash dengan Preview Admin bagi setiap source.
- Sahkan zero-change tidak mencipta header.

### Fasa C — Scheduled dry-run 7 hari minimum

- Jadual hanya selepas ketiga-tiga upstream selesai refresh.
- Rekod duration, source rows, counts, warning/block frequency dan lock conflict.
- Owner Staff, UG dan ODL mengesahkan data stability window masing-masing.

### Fasa D — Controlled auto-Apply per source

- Aktifkan **satu source pada satu masa**, bermula UG.
- Staff hanya selepas provenance/ownership closure.
- ODL hanya selepas recurring authorization dan production gate diluluskan.
- Kekalkan `Deactivate=0` dan observe sekurang-kurangnya tiga successful Apply
  yang direconcile bagi setiap source sebelum source seterusnya.

### Fasa E — Normal operation

- Backup/PITR dan restore readiness aktif.
- Log rotation/retention dan stale-run alert aktif.
- Quarterly threshold/source-owner review.
- Emergency disable diuji tanpa menutup manual Admin flow.

## 8. Deployment Shape Selepas Semua Gate Lulus

Cadangan command akhir (belum boleh dipasang):

```cron
15 2 * * * /usr/bin/flock -n /run/lock/oneid-external-sync.lock \
  /usr/bin/php /var/www/app/oneid-uat/cron/run_conditional_external_sync.php \
  >> /var/log/oneid/external-sync.log 2>&1
```

Jadual `02:15 Asia/Kuala_Lumpur` hanyalah placeholder. Waktu sebenar mesti
diluluskan selepas owner Staff, UG dan ODL mengesahkan upstream completion SLA.
Prefer systemd timer jika deployment standard menyokong unit identity,
environment, timeout, journald retention dan failure notification yang lebih
jelas; safety contract aplikasi kekal sama bagi cron atau systemd timer.

## 9. Gate Kelulusan Semasa

- [ ] Owner Staff sahkan source refresh window dan provenance/ownership.
- [ ] Owner UG sahkan source refresh window.
- [ ] Owner ODL sahkan source refresh window dan recurring authorization.
- [ ] Threshold per source diluluskan berdasarkan volume sebenar.
- [ ] `Deactivate=0` diterima.
- [ ] Service account, PHP binary, working directory dan permission disahkan.
- [ ] Log destination, rotation, retention dan HTTP denial disahkan.
- [ ] Monitoring channel serta on-call owner ditetapkan.
- [ ] Backup/PITR dan restore readiness disahkan.
- [ ] Dry-run acceptance criteria dan minimum tujuh hari diterima.
- [ ] Emergency disable dan post-commit uncertainty runbook diluluskan.

Sehingga semua blocker C-01 hingga C-12 yang berkaitan ditutup, External Sync
kekal melalui source-specific Admin Preview/Apply dan cronjob tidak dipasang.
