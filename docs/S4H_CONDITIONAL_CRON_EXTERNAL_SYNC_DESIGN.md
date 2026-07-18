# S4H Conditional Cron External Sync

## Status

**DESIGN ONLY - NOT IMPLEMENTED - DO NOT INSTALL A CRONTAB YET**

Dokumen ini merekodkan reka bentuk yang dicadangkan untuk menjalankan External
Sync secara automatik setiap hari. Ia tidak mengaktifkan scheduler, tidak
menambah writer CLI dan tidak mengubah database atau private runtime.

## Objektif

1. Scheduler membuat pemeriksaan read-only pada waktu yang ditetapkan.
2. Jika plan `New/Update/Deactivate/Reactivate` semuanya sifar, proses tamat
   sebagai `SKIP_NO_CHANGES` tanpa transaction, sync header atau change log.
3. Jika terdapat perubahan yang selamat dan berada dalam threshold cron, cron
   menjalankan safe writer yang sama seperti Operational Sync.
4. Deactivate, anomaly, collision dan perubahan besar kekal memerlukan semakan
   Administrator.
5. Cron legacy tidak dihidupkan semula dan endpoint HTTP/admin tidak dipanggil
   oleh scheduler.

## Penemuan Audit Semasa

- `cron/run_sync.php` legacy telah retired dan tidak wujud dalam runtime aktif.
- `deployment/cron/oneid-uat.crontab.example` masih dikomen dan tidak mempunyai
  command production.
- Operational Sync menggunakan approval session Administrator yang tidak sesuai
  digunakan terus oleh proses CLI tanpa session.
- `SafeSyncOrchestrator` menyediakan advisory lock, safety policy, transaction,
  reconciliation dan rollback yang boleh diguna semula.
- Orchestrator akan mencipta header apabila dipanggil dengan plan kosong. Oleh
  itu CLI cron mesti berhenti selepas precheck dan sebelum memanggil writer.
- Scheduled backup/PITR biasa mencukupi untuk batch harian yang kecil; full dump
  untuk setiap run bukan keperluan reka bentuk ini.

## Aliran Dicadangkan

```text
Scheduler start
  -> validate CLI-only execution and cron feature flag
  -> acquire OS-level non-overlap lock
  -> fetch external Staff + Student snapshot (read-only)
  -> read active/inactive OneID users
  -> build normalized SyncPlan
  -> apply source-completeness and safety policy
  -> total changes = 0?
       yes -> output SKIP_NO_CHANGES; exit 0; no database mutation
       no  -> apply cron-specific absolute thresholds
  -> unsafe / over threshold / any Deactivate?
       yes -> output BLOCKED_REQUIRES_ADMIN; no database mutation
  -> issue one-use in-process approval bound to admin/service identity,
     source baseline, exact counts and full plan fingerprint
  -> safe coordinator fetches a fresh second snapshot
  -> fingerprint/counts drifted?
       yes -> burn approval; output BLOCKED_PLAN_DRIFT; no transaction
  -> acquire database advisory lock
  -> transaction, writer, reconciliation and commit
  -> write cron completion audit marker
  -> output APPLIED with header and non-PII counts
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
'ONEID_SYNC_CRON_MAX_NEW' => '50',
'ONEID_SYNC_CRON_MAX_UPDATE' => '250',
'ONEID_SYNC_CRON_MAX_REACTIVATE' => '20',
'ONEID_SYNC_CRON_MAX_DEACTIVATE' => '0',
'ONEID_SYNC_CRON_MAX_TOTAL' => '300',
'ONEID_SYNC_CRON_DRY_RUN' => 'true',
```

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
- Log diletakkan dalam `storage/logs` atau system journal yang tidak boleh
  dicapai melalui HTTP dan mempunyai rotation/retention.

## Cadangan Jadual

Pilih waktu selepas source HR/student selesai dikemas kini. Contoh sahaja:

```cron
15 2 * * * flock -n /run/lock/oneid-external-sync.lock \
  /usr/bin/php /var/www/oneid-uat/cron/run_conditional_sync.php
```

Command ini **belum wujud dan tidak boleh dipasang sekarang**. Working directory,
PHP binary, OS user, timezone dan log destination mesti disahkan pada staging.

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
- jalankan scheduler selama sekurang-kurangnya 3–7 hari;
- banding plan cron dengan Preview Admin tanpa Apply automatik;
- sahkan zero-change menghasilkan tiada header baharu.

### Fasa 3 - Controlled auto-Apply

- luluskan threshold dan monitoring owner;
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

Sehingga semua gate dipenuhi, External Sync kekal menggunakan Operational Mode
melalui Administrator.
