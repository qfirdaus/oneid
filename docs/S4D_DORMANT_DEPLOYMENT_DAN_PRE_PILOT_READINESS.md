# S4D — Dormant Deployment dan Pre-pilot Readiness

Tarikh: 14 Julai 2026
Change owner: Pemilik sistem OneID
Rollback owner: Pemilik sistem OneID
Status: **DORMANT RUNTIME SIAP — OPERATING GATES BELUM LENGKAP, S4E NO-GO**

## 1. Keputusan Ringkas

S4D menyambungkan preview, approval dan safe coordinator kepada runtime dalam
keadaan fail-closed. Nginx dan PHP-FPM tidak menetapkan Apply flags, maka
effective state kekal `false/disabled`. Dashboard masih tiada butang Apply.

Selepas checkpoint S4D, verifikasi operasi berasingan telah menjalankan ODBC
SELECT, backup dan isolated restore rehearsal. Tiada Apply atau live sync
dijalankan. External production source hanya dibaca oleh tool verification;
privilege read-only sebenar masih memerlukan evidence DBA.

## 2. Perubahan Runtime Dormant

### Preview

`admin_preview_sync_user` kini:

1. menggunakan server-side session approval store;
2. mengambil external dan internal snapshot sekali;
3. membaca baseline daripada completed OneID sync header terakhir;
4. menjalankan full `SyncSafetyPolicy` S3;
5. mengeluarkan approval ID maksimum lima minit hanya jika tiada blocking code
   atau warning;
6. kekal memulangkan `can_apply=false` dan tidak memaparkan approval ID di UI.

Baseline dianggap authoritative hanya daripada header status `2` atau `4`
dengan `ext_head_initial_sourcedata > 0`. Jika baseline tiada, preview masih
boleh menunjukkan counts tetapi `SOURCE_BASELINE_UNAVAILABLE` menghalang
approval.

### Apply endpoint

Nama action compatibility `admin_add_sync_user` dikekalkan, tetapi implementasi
legacy telah dibuang daripada caller tersebut. Endpoint kini:

- admin-only, CSRF protected dan exactly-one-action melalui request guard;
- menggunakan strict `SyncRuntimeConfig`;
- hanya menerima gabungan tepat `true/safe`;
- mewajibkan `sync_approval_id` server-bound;
- hanya membina `ApprovedSyncCoordinator`;
- memberi response generik dan allowlisted diagnostic code;
- tidak boleh memilih `run_admin_sync_user` atau loose boolean flag.

Dengan flag missing/unset semasa, request Apply berhenti pada
`SYNC_APPLY_DISABLED` sebelum source fetch, approval consume atau transaction.

### UI

Dashboard hanya mempunyai butang preview. Ia boleh menunjukkan:

- `BLOCKED` jika baseline/safety gagal;
- `READY FOR CONTROLLED PILOT — Apply remains disabled` jika approval dapat
  disediakan;
- tiada butang, form atau AJAX Apply;
- tiada approval bearer dipindahkan ke DOM.

## 3. Evidence Operasi Read-only

Pemeriksaan 14 Julai 2026 mendapati:

| Item | Evidence | Keputusan |
| --- | --- | --- |
| Release S4D | commit `68f10ff` | Lulus |
| Nginx site | `local-projects` enabled; root `/var/www/app/oneid-uat/public` | Lulus |
| Apply flags Nginx | tiada `ONEID_SYNC_APPLY_ENABLED`/`ONEID_SYNC_ENGINE` | Default disabled |
| Apply flags FPM/systemd | tiada flag ditemui | Default disabled |
| Shell flags | kedua-duanya unset | Default disabled |
| Session | PHP `files`, path `/var/lib/php/sessions` | Sesuai untuk single-node semasa |
| Scheduler project/user/system refs | tiada OneID/run_sync ref ditemui | Perlu sahkan root crontab secara operasi |
| Project cron artifact | tiada `cron/run_sync.php` | Retired |
| Backup S4D | `storage/backups/S4D-20260714-160232`, 73,881,422 bait, SHA-256 direkod | Lulus |
| Restore rehearsal | 15 jadual, exact row-count digest sama, rehearsal DB dibuang | Lulus |
| OneID error log | boleh dibaca; satu sync marker dalam 500 baris terakhir | Perlu review semasa window |
| PHP-FPM log | path biasa tidak boleh dibaca/tiada | Operations perlu sahkan lokasi sebenar |
| ODBC source read | staff 1,062 + student 5,423 = 6,485 melalui SELECT sahaja | Lulus untuk runtime read; grant exclusivity belum dibuktikan DBA |

Tiada credential atau raw PII direkodkan dalam evidence.

## 4. Session Topology Decision

Deployment semasa ialah satu Nginx host kepada satu PHP-FPM socket dan session
file lokal. `SessionSyncApprovalStore` sesuai untuk pilot satu node ini kerana
preview dan Apply daripada browser/session sama mencapai session store sama.

Jika OneID ditambah load balancer atau lebih daripada satu PHP node sebelum
pilot, S4E mesti diblok sehingga shared session store atau session affinity
disediakan dan diuji. Approval tidak boleh dipindahkan melalui client storage
selain opaque approval ID.

## 5. Verification Automatik

`npm run check:sync-readiness` membuktikan:

- runtime class map melakukan zero I/O semasa load;
- preview approval menggunakan baseline dan full S3 policy;
- preview tidak mempunyai transaction/mutation capability;
- Apply menggunakan strict config, safe factory dan approval coordinator;
- legacy writer tidak boleh dipilih oleh endpoint;
- response failure generik;
- UI tiada Apply atau approval bearer;
- fixture healthy mengeluarkan approval, manakala missing baseline dan unsafe
  category menyimpan sifar approval.

Semua ujian menggunakan fake/in-memory dan tidak menyentuh live source/database.

## 6. Gate Yang Masih Memerlukan Owner/DBA/Operations

S4E kekal NO-GO sehingga sekurang-kurangnya:

1. root/system scheduler inventory disahkan tiada job OneID;
2. DBA membuktikan external staff/student credential ialah SELECT-only;
3. maintenance window dan pilot admin ditetapkan;
4. log path/monitoring serta observation owner disahkan.

## 7. Backup dan Restore Evidence Template

Kaedah backup sebenar mesti menggunakan prosedur DBA dan secret file yang
diluluskan. Jangan letak password pada command line atau dump dalam Git.

Evidence minimum:

```text
change_id=
backup_started_at=
backup_completed_at=
backup_path=
owner=
permissions=
sha256=
source_database=OneID UAT
restore_target=separate non-production database
restore_started_at=
restore_completed_at=
restore_result=PASS|FAIL
reconciliation_result=PASS|FAIL
```

## 8. Rollback S4D

Selagi flags kekal disabled dan tiada pilot:

1. revert checkpoint S4D melalui Git/deployment procedure biasa;
2. pastikan `ONEID_SYNC_APPLY_ENABLED=false` dan engine `disabled` atau unset;
3. reload PHP-FPM hanya jika environment/deployment procedure memerlukannya;
4. jalankan admin auth, preview read-only dan public-root smoke;
5. tiada database restore diperlukan kerana tiada Apply dijalankan.

Jika mana-mana flag didapati `true/safe` secara tidak sengaja, matikan dahulu
sebelum rollback code dan semak advisory lock/header/audit state. Jangan rerun
legacy writer.

## 9. Langkah Berikutnya

Rujuk `docs/S4D_VERIFIKASI_OPERASI_SEBELUM_S4E.md`. Admin
login/fresh preview/logout dan satu SSO consumer telah lulus. Langkah seterusnya
ialah DBA mengesahkan privilege external benar-benar SELECT-only dan owner
melengkapkan baki gate pre-pilot. S4E hanya boleh dimulakan selepas semua gate
wajib lengkap dan arahan GO baharu diberikan.

Dokumen handoff untuk menyambung empat baki gate tersedia dalam
`docs/S4E_BAKI_GATE_DAN_HANDOFF.md`.
