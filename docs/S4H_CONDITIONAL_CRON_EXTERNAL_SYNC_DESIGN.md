# S4H Conditional Cron External Sync

## Status

**IMPLEMENTED DORMANT 5 AUGUST 2026 - DEPLOYMENT/ACTIVATION NOT YET APPROVED**

Dokumen ini telah dikemas kini selepas audit semula terhadap code semasa bagi
ketiga-tiga source External Sync:

- `STAFF_HR`;
- `STUDENT_UG`; dan
- `STUDENT_ODL_PG`.

Dokumen audit canonical yang mengandungi evidence code, penemuan setiap source,
12 implementation gap, contract test dan rollout gate ialah
[`AUDIT_CRON_EXTERNAL_SYNC_STAFF_UG_ODL_20260805.md`](AUDIT_CRON_EXTERNAL_SYNC_STAFF_UG_ODL_20260805.md).
Jika terdapat perbezaan, dokumen audit bertarikh 5 Ogos 2026 menjadi rujukan
utama.

Dokumen ini merekodkan ringkasan reka bentuk untuk menjalankan External Sync
secara automatik setiap hari. Ia tidak mengaktifkan scheduler, tidak menambah
writer CLI dan tidak mengubah database atau private runtime.

## Keputusan Audit Code Semasa

Codebase mempunyai asas yang sesuai melalui `SyncSourceScope`,
`SafeSyncOrchestrator`, source-scoped persistence, MySQL advisory lock,
transaction dan reconciliation. CLI runner, process-local one-use machine
authorization, hard threshold per source, dry-run dan fail-closed configuration
telah dilaksanakan. Crontab masih belum boleh diaktifkan sehingga live dry-run,
service account, log rotation, monitoring dan source-owner gate diluluskan.

Status readiness semasa:

| Source | Status | Syarat utama |
|---|---|---|
| `STAFF_HR` | Conditional | Tutup audit provenance/source ownership sebelum auto-Apply |
| `STUDENT_UG` | Ready for contract dan dry-run | Luluskan threshold dan monitoring sebelum auto-Apply |
| `STUDENT_ODL_PG` | Conditional | Bina recurring cron authorization khusus; jangan guna on-demand UAT sebagai bypass production |

Cron mesti menjalankan tiga plan source-specific. Jangan gabungkan Staff, UG
dan ODL ke dalam satu aggregate writer kerana category overlap dan provenance
membership memerlukan source isolation.

## Objektif

1. Scheduler membuat pemeriksaan read-only pada waktu yang ditetapkan.
2. Jika plan `New/Update/Deactivate/Reactivate` semuanya sifar, proses tamat
   sebagai `SKIP_NO_CHANGES` tanpa transaction, sync header atau change log.
3. Jika terdapat perubahan yang selamat dan berada dalam threshold khusus
   source, cron menjalankan safe writer yang sama seperti Operational Sync.
4. Deactivate, anomaly, collision dan perubahan besar kekal memerlukan semakan
   Administrator.
5. Cron legacy tidak dihidupkan semula dan endpoint HTTP/admin tidak dipanggil
   oleh scheduler.

## Penemuan Audit Semasa

- `cron/run_sync.php` legacy telah retired dan tidak wujud dalam runtime aktif.
- Runner baharu ialah `cron/run_conditional_external_sync.php`; committed
  defaults kekal disabled dan dry-run.
- `deployment/cron/oneid-uat.crontab.example` masih dikomen dan tidak mempunyai
  command production.
- Operational Sync menggunakan approval session Administrator yang tidak sesuai
  digunakan terus oleh proses CLI tanpa session. Cron memerlukan machine
  authorization khusus yang one-run, ephemeral dan terikat kepada exact plan.
- `SafeSyncOrchestrator` menyediakan advisory lock, safety policy, transaction,
  reconciliation dan rollback yang boleh diguna semula.
- Orchestrator akan mencipta header apabila dipanggil dengan plan kosong. Oleh
  itu CLI cron mesti berhenti selepas precheck dan sebelum memanggil writer.
- Scheduled backup/PITR biasa mencukupi untuk batch harian yang kecil; full dump
  untuk setiap run bukan keperluan reka bentuk ini.
- Staff provenance default masih `false`; dry-run dibenarkan tetapi auto-Apply
  Staff menunggu bukti ownership atau provenance closure.
- UG sudah provenance-scoped dan paling hampir untuk cron dry-run.
- ODL mempunyai private exact-plan/change-window gate dan on-demand UAT yang
  tidak boleh digunakan sebagai authorization recurring production.
- ODL Preview mempunyai snapshot-isolation validator tambahan; cron perlu
  mengekalkan validation setara pada fresh snapshot writer.

## Aliran Dicadangkan

```text
Scheduler start (one invocation)
  -> validate CLI-only execution and cron feature flag
  -> acquire OS-level non-overlap lock
  -> for STAFF_HR, STUDENT_UG, STUDENT_ODL_PG in fixed order:
       -> fetch source-specific snapshot (read-only)
       -> read source-scoped active/inactive OneID users
       -> build normalized source-specific SyncPlan
       -> apply source completeness, isolation and safety policy
       -> total changes = 0?
            yes -> output SKIP_NO_CHANGES; no database mutation
       -> warning / unsafe / over threshold / any Deactivate?
            yes -> output BLOCKED_REQUIRES_ADMIN; stop invocation
       -> dry-run enabled?
            yes -> output DRY_RUN_CHANGES_FOUND; no database mutation
       -> issue one-use in-process machine approval bound to service identity,
          source baseline, exact counts and full plan fingerprint
       -> safe coordinator fetches a fresh second snapshot
       -> fingerprint/counts drifted?
            yes -> burn approval; output BLOCKED_PLAN_DRIFT; no transaction
       -> acquire shared database advisory lock
       -> transaction, source-scoped writer, reconciliation and commit
       -> write durable cron result and completion audit marker
       -> output APPLIED with source, header and non-PII counts
```

Scheduler tetap bermula setiap hari kerana perubahan tidak boleh diketahui
tanpa read-only snapshot. `Tidak berjalan` dalam konteks operasi bermaksud
writer tidak dipanggil dan tiada rekod sync database dicipta apabila plan kosong.

## Polisi Auto-Apply Awal

| Action | Cadangan limit | Keputusan jika melebihi |
|---|---:|---|
| New | 50 | Block dan semakan admin |
| Update | 250 | Block dan semakan admin |
| Reactivate | 20 | Block dan semakan admin |
| Deactivate | 0 | Sentiasa block dan semakan admin |
| Jumlah keseluruhan | 300 | Block dan semakan admin |

Nilai ini ialah baseline konservatif untuk approval dan belum dianggap nilai
operasi yang diluluskan. Implementation terkini perlu menyokong threshold
`New`, `Update`, `Reactivate` dan `Total` secara berasingan bagi Staff, UG dan
ODL. `Deactivate=0` kekal global pada rollout awal.

Semua limit mesti datang daripada private runtime, menggunakan integer strict
dan tidak boleh dihantar melalui CLI argument, query string atau request body.

Polisi sedia ada kekal wajib:

- snapshot external tidak boleh kosong;
- source Staf dan Pelajar kedua-duanya mesti hadir;
- source shrink tidak boleh melebihi 20%;
- invalid external rows tidak boleh melebihi 1%;
- Deactivate tidak boleh melebihi safety percentage sedia ada;
- protected identity collision dan kategori tidak dikenali disekat;
- warning preview memerlukan semakan admin;
- exact source baseline, counts dan plan fingerprint mesti sepadan pada fresh
  snapshot kedua.

## Konfigurasi Dicadangkan

Default berikut perlu ditambah kepada `config/runtime.php` dalam keadaan
fail-closed apabila implementation diluluskan:

```php
'ONEID_SYNC_CRON_ENABLED' => 'false',
'ONEID_SYNC_CRON_DRY_RUN' => 'true',
'ONEID_SYNC_CRON_SOURCES' => 'STAFF_HR,STUDENT_UG,STUDENT_ODL_PG',
'ONEID_SYNC_CRON_MAX_NEW' => '50',
'ONEID_SYNC_CRON_MAX_UPDATE' => '250',
'ONEID_SYNC_CRON_MAX_REACTIVATE' => '20',
'ONEID_SYNC_CRON_MAX_DEACTIVATE' => '0',
'ONEID_SYNC_CRON_MAX_TOTAL' => '300',
```

Nama threshold tanpa suffix source di atas dikekalkan sebagai ringkasan reka
bentuk asal. Implementation mesti menggunakan key per-source seperti yang
ditetapkan dalam audit canonical supaya perubahan volume sesuatu source tidak
melonggarkan source lain secara tidak sengaja.

Deployment yang diluluskan perlu override nilai tersebut dalam
`.private/runtime.php`. Operational web flag dan cron flag mesti berasingan agar
cron boleh dihentikan tanpa menutup Apply manual Administrator.

## Identiti dan Authorization

- Gunakan OS service account OneID yang tidak mempunyai shell interaktif jika
  polisi host membenarkan.
- Jangan jalankan sebagai `root` atau user Nginx/PHP-FPM.
- Gunakan service identity stabil seperti `ONEID Sync Cron` untuk
  `triggered_by` dan marker audit; jangan menyamar sebagai Administrator manusia.
- CLI mesti menolak execution melalui HTTP dan tidak bergantung kepada cookie,
  session browser atau CSRF token.
- Database credential kekal dibaca melalui secret loader sedia ada dan tidak
  diletakkan dalam crontab.

## Concurrency

Gunakan dua lapisan:

1. `flock`/system scheduler lock untuk mengelakkan dua proses CLI pada host sama.
2. MySQL advisory lock `oneid:external-user-sync` yang sudah digunakan oleh safe
   orchestrator untuk mengelakkan cron dan Admin Apply berjalan serentak.

Lock wait cron dicadangkan `0`. Jika lock sedang digunakan, cron tamat sebagai
`SKIP_ALREADY_RUNNING` tanpa retry writer dalam run yang sama.

## Logging dan Audit

Output tidak boleh mengandungi IC, matrik, nama, e-mel atau raw plan rows.

Contoh output:

```text
SKIP_NO_CHANGES source=6485 new=0 update=0 deactivate=0 reactivate=0
BLOCKED_REQUIRES_ADMIN reason=DEACTIVATION_NOT_ALLOWED counts=0/2/1/0
BLOCKED_PLAN_DRIFT correlation=<random-id>
APPLIED header=44 source=6486 new=1 update=2 deactivate=0 reactivate=0
FAILED code=<stable-allowlisted-code> correlation=<random-id>
```

- `SKIP_NO_CHANGES` hanya masuk log OS/scheduler; tiada `ext_data_temp_header`,
  `sync_change_log` atau syslog database perlu dicipta.
- `BLOCKED` dan `FAILED` perlu dihantar kepada monitoring/owner tanpa PII.
- `APPLIED` mesti mempunyai header summary, exact change log reconciliation dan
  marker `ADMIN_SYNC_CRON_SAFE` atau nama marker khusus yang diluluskan.
- Jika commit berjaya tetapi secondary marker gagal, output mestilah
  `APPLIED_AUDIT_WARNING`; writer tidak boleh di-retry sebelum header
  direconcile.
- Setiap run memerlukan correlation/run ID dan service identity stabil. Tiada
  ID Administrator manusia boleh digunakan sebagai identiti scheduler.
- Log diletakkan dalam `storage/logs` atau system journal yang tidak boleh
  dicapai melalui HTTP dan mempunyai rotation/retention.

## Cadangan Jadual

Pilih waktu selepas source HR/student selesai dikemas kini. Contoh sahaja:

```cron
15 2 * * * flock -n /run/lock/oneid-external-sync.lock \
  /usr/bin/php /var/www/oneid-uat/cron/run_conditional_sync.php
```

Command runner kini wujud tetapi **belum boleh diaktifkan untuk unattended
Apply**. Working directory, PHP binary, OS user, timezone dan log destination
mesti disahkan pada staging. Jalankan manual CLI dry-run dan scheduled dry-run
sebelum memasang jadual auto-Apply.

## Rollout

### Fasa 1 - Contract dan fixture

- CLI-only/fail-closed config contract.
- zero-change fixture membuktikan mutation/header/syslog count sifar;
- threshold, Deactivate, warning, collision dan source anomaly fixtures;
- plan drift dan approval replay fixtures;
- concurrency dan audit-marker failure fixtures.

### Fasa 2 - Staging dry-run

- `ONEID_SYNC_CRON_DRY_RUN=true`;
- jalankan manual daripada shell menggunakan service account;
- jalankan scheduler selama sekurang-kurangnya 7 hari;
- banding plan cron dengan Preview Admin tanpa Apply automatik;
- sahkan zero-change menghasilkan tiada header baharu.

### Fasa 3 - Controlled auto-Apply

- luluskan threshold dan monitoring owner;
- aktifkan satu source pada satu masa, bermula dengan UG;
- Staff menunggu provenance/source ownership closure;
- ODL menunggu recurring authorization dan production owner gate;
- aktifkan writer hanya untuk New/Update/Reactivate dalam limit;
- Deactivate kekal manual;
- reconcile setiap header dan perhatikan sekurang-kurangnya beberapa run berjaya.

### Fasa 4 - Normal operation

- kekalkan backup/PITR dan log rotation;
- semak threshold secara berkala berdasarkan volume sebenar;
- uji disable flag dan restore procedure;
- jangan ubah polisi Deactivate tanpa change review baharu.

## Disable dan Rollback

Emergency stop mestilah satu private flag:

```php
'ONEID_SYNC_CRON_ENABLED' => 'false',
```

Selepas reload jika diperlukan, scheduler boleh kekal terpasang tetapi CLI mesti
keluar `SYNC_CRON_DISABLED` sebelum external fetch atau database mutation.
Operational Sync manual kekal tersedia untuk investigation dan recovery.

## Gate Sebelum Implementation

- [ ] Change owner meluluskan jadual dan timezone.
- [ ] External source owner mengesahkan waktu data stabil.
- [ ] Threshold New/Update/Reactivate/Total diterima.
- [ ] Deactivate=0 untuk cron diterima.
- [ ] Service account dan filesystem permission disahkan.
- [ ] Log destination, rotation dan HTTP denial disahkan.
- [ ] Monitoring/notification owner serta channel ditetapkan.
- [ ] Dry-run duration dan acceptance criteria ditetapkan.
- [ ] Backup/PITR serta restore readiness masih aktif.
- [ ] Rollback owner dan emergency disable procedure diterima.
- [ ] Staff provenance/source ownership closure diterima.
- [ ] ODL recurring cron authorization diluluskan tanpa menggunakan on-demand
      UAT sebagai production bypass.
- [ ] Zero-plan contract membuktikan zero transaction, header, change log dan
      database audit marker.
- [ ] Durable run state, heartbeat dan post-commit uncertainty handling tersedia.

Sehingga semua gate dipenuhi, External Sync kekal menggunakan Operational Mode
melalui Administrator.
