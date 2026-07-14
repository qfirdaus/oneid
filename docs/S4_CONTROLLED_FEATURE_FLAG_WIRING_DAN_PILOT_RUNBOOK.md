# S4 — Controlled Feature-Flag Wiring dan Pilot Runbook

Tarikh pelan: 14 Julai 2026
Change owner: Pemilik sistem OneID
Rollback owner: Pemilik sistem OneID
Status: **S4A DORMANT SIAP — S4B–S4E BELUM, NO-GO UNTUK APPLY**
Baseline code: commit `243ff2c`

## 1. Objektif dan Boundary

Dokumen ini menerangkan cara menyambung `SafeSyncOrchestrator` kepada satu
manual admin pilot secara terkawal. Penyediaan dokumen ini tidak:

- mengubah `lib/q_func.php`, dashboard atau Nginx/PHP-FPM environment;
- mengaktifkan `ONEID_SYNC_APPLY_ENABLED`;
- menambah butang Apply;
- menjalankan live sync atau transaksi OneID;
- menjalankan write ke external staff/student database;
- mengaktifkan cron atau scheduler.

External staff dan student production source kekal **read-only**. Semua mutation
pilot hanya boleh berlaku pada database OneID UAT selepas GO eksplisit.

## 2. Keadaan Semasa

| Komponen | Keadaan semasa |
| --- | --- |
| Preview | Aktif, admin-only, read-only, `can_apply=false` |
| Apply action legacy | Masih wujud tetapi master flag default `false` |
| Dashboard | Hanya menghantar `admin_preview_sync_user` |
| Safe orchestrator | Dormant; tiada runtime caller |
| Single-run lock/reconciliation | Dormant; lulus contract S3 |
| Cron | Retired; kekal di luar S4 |
| S2 preview terakhir | 6,485 rows; 71 New; 4 Update; 0 Deactivate; 0 Reactivate |

Preview terakhir hanya evidence S2. Hash dan expiry tersebut tidak boleh diguna
semula untuk pilot S4; preview baharu wajib dijana dalam maintenance window.

## 3. Keputusan Feature Flag

S4 menggunakan dua lapisan server-side:

```text
ONEID_SYNC_APPLY_ENABLED=false
ONEID_SYNC_ENGINE=disabled
```

Nilai engine yang dibenarkan:

- `disabled` — default; semua Apply ditolak sebelum external fetch;
- `safe` — membina `SafeSyncOrchestrator` sahaja.

`legacy` tidak menjadi nilai normal S4. Jika safe engine bermasalah, rollback
ialah **disable Apply**, bukan menjalankan writer legacy yang masih mempunyai
transaction weakness. Flag hanya boleh dibaca daripada server environment;
nilai daripada POST, GET, cookie atau header mesti diabaikan.

Untuk membuka pilot, kedua-dua syarat mesti benar:

```text
ONEID_SYNC_APPLY_ENABLED=true
ONEID_SYNC_ENGINE=safe
```

Missing, malformed, unknown atau kombinasi separuh aktif mesti fail-closed.

## 4. Preview-to-Apply Binding Wajib

S2 kini memulangkan plan hash kepada browser tetapi tidak menyimpan approval.
S4 tidak boleh mempercayai hash daripada browser sahaja dan tidak boleh fetch
snapshot sekali untuk validation kemudian fetch kali kedua untuk writer.

Preview S4 mesti menggunakan `SyncSafetyPolicy` yang sama seperti Apply,
termasuk source completeness, invalid rows, source shrink, protected collision,
unknown category dan deactivation threshold. Fingerprint plan mesti dikanonikal
dan stabil walaupun upstream memulangkan row yang sama dalam susunan berbeza.
Jangan bergantung pada row order ODBC yang tidak dijamin.

Implementation S4 perlu satu approval record server-side yang mengandungi:

- opaque approval ID;
- admin user ID yang menjana preview;
- plan hash penuh dan counts;
- source row count serta accepted baseline;
- generated/expiry time maksimum lima minit;
- status `pending`, `consumed` atau `expired`;
- correlation/change ID tanpa PII.

Raw external rows, IC, email dan password tidak boleh disimpan dalam approval.
Session server-side boleh digunakan untuk pilot tunggal; jika approval perlu
merentas node, storage server-side khusus diperlukan.

Safe orchestrator/coordinator mesti:

1. acquire single-run lock;
2. fetch external dan internal snapshot sekali;
3. bina plan serta jalankan semua S3 safety gates;
4. semak admin, expiry, expected canonical plan hash dan counts;
5. tandakan approval consumed secara atomik;
6. hanya kemudian membuka transaction dan menjalankan plan yang sama.

Hash mismatch, expired approval, admin berbeza atau replay mesti berhenti sebelum
`BEGIN`, header atau mutation. Approval tidak boleh digunakan dua kali.

## 5. Bentuk Wiring Yang Dirancang

### S4A — Dormant factory

- **Selesai secara dormant:** tambah factory production yang strict-allowlist
  `disabled|safe`;
- factory membina `ExternalApiUserSource`, `DatabaseSyncPersistenceAdapter`,
  `DatabaseSyncRunLock`, `DatabaseSyncReconciliationReader`, `SyncPlanner`,
  `SyncSafetyPolicy`, `SyncReconciler` dan `SecureInitialPasswordFactory`;
- factory tidak melakukan external fetch atau membuka transaction;
- missing/invalid flags menghasilkan safe diagnostic code;
- deploy dengan `false/disabled`; dashboard masih preview-only.

Implementation dan contract S4A dirujuk dalam
`docs/S4A_DORMANT_FACTORY_DAN_STRICT_FLAG_CONTRACT.md`. Class belum mempunyai
runtime caller dan tiada deployment flag diubah.

### S4B — Approval contract dan test

- tambah preview approval server-side;
- selaraskan preview dengan `SyncSafetyPolicy` dan canonical plan fingerprint
  yang turut digunakan oleh Apply;
- tambah endpoint Apply admin-only dengan CSRF dan exactly-one-action guard;
- response browser hanya generic status, correlation ID, header ID dan counts;
- test missing/invalid/disabled/safe flag matrix;
- test hash mismatch, expiry, wrong admin, replay dan double-click;
- buktikan semua rejection path mempunyai zero mutation;
- buktikan success path menggunakan satu snapshot dan satu writer sahaja.

### S4C — Dormant deployment/soak

- deploy code dengan Apply masih `false/disabled`;
- jalankan S1, S2, S3, auth dan public-root regression;
- sahkan preview browser kekal read-only;
- perhatikan Nginx/PHP-FPM log tanpa mengaktifkan Apply;
- cron kekal retired.

### S4D — Pre-pilot readiness

- tetapkan maintenance window, pilot admin dan DBA/backup owner;
- ambil backup OneID UAT dan rehearsal restore ke lokasi berasingan;
- jana preview baharu dan terima counts/baseline secara rasmi;
- pastikan plan `risk_level=normal`, collision=0 dan Deactivate dalam jangkaan;
- sahkan external account benar-benar read-only;
- lengkapkan semua gate dalam register S4.

### S4E — Satu controlled pilot

Fasa ini memerlukan arahan GO baharu daripada change owner. Urutan:

1. hentikan perubahan user/admin lain sepanjang window;
2. sahkan tiada sync lock atau run aktif;
3. sahkan backup checksum dan restore evidence;
4. jana preview baharu dan rekod approval ID/hash prefix/counts;
5. aktifkan `true/safe`, reload service dan sahkan effective flags;
6. seorang pilot admin membuat satu confirmation dan satu Apply;
7. disable kembali kepada `false/disabled` sebaik request selesai;
8. reconcile header, audit, user counts dan sample digest;
9. jalankan login/SSO smoke serta semak log;
10. keputusan akhir ialah ACCEPT atau ROLLBACK/INVESTIGATE.

Jangan auto-retry request gagal atau timeout. Semak correlation/header dan
commit state dahulu untuk mengelakkan mutation berganda.

## 6. Pre-pilot Evidence dan Backup

Backup mesti merangkumi sekurang-kurangnya:

- `user_tbl`;
- `ext_data_temp_header`;
- `ext_data_temp_body`;
- `sync_change_log`;
- schema objek berkaitan, atau full OneID UAT database backup.

Backup hendaklah berada di luar document root, permission ketat, mempunyai
SHA-256 dan diuji restore ke database berasingan. Jangan masukkan dump atau
credential ke Git. Kaedah/command sebenar mesti menggunakan prosedur DBA dan
credential file yang diluluskan; jangan letakkan password pada command line.

Evidence pre-pilot tanpa PII:

```sql
SELECT avail_status, COUNT(*) AS total
FROM user_tbl
GROUP BY avail_status
ORDER BY avail_status;

SELECT u_category, avail_status, COUNT(*) AS total
FROM user_tbl
GROUP BY u_category, avail_status
ORDER BY u_category, avail_status;

SELECT ext_head_id, ext_head_status, ext_head_initial_sourcedata,
       ext_head_uploaded_data, total_new, total_updated,
       total_deactivated, total_reactivated, triggered_by,
       ext_head_dt_start, ext_head_dt_end
FROM ext_data_temp_header
ORDER BY ext_head_id DESC
LIMIT 10;
```

## 7. Reconciliation Selepas Pilot

Dengan `@header_id` pilot:

```sql
SELECT action, COUNT(*) AS total
FROM sync_change_log
WHERE ext_head_id = @header_id
GROUP BY action
ORDER BY action;

SELECT COUNT(*) AS staged_rows,
       SUM(ext_body_status = 2) AS processed_rows
FROM ext_data_temp_body
WHERE ext_head_id = @header_id;
```

Acceptance memerlukan:

- planned = executed = audited bagi empat action;
- header bukan status running/0 dan totals tepat;
- staged/processed/uploaded counts konsisten;
- perubahan active/inactive boleh diterangkan oleh counts;
- tiada protected manual account berubah;
- tiada duplicate, fatal, rollback atau reconciliation mismatch;
- sample digest setiap action disahkan melalui read-only lookup;
- admin login, logout dan sekurang-kurangnya satu SSO consumer lulus.

## 8. Monitoring Window

Pantau minimum sepanjang pilot dan sekurang-kurangnya 60 minit selepasnya:

- `/var/log/nginx/oneid-r4.error.log` dan access log berkaitan;
- PHP-FPM error log;
- correlation ID dan safe sync event;
- `SYNC_ALREADY_RUNNING`, safety block dan reconciliation mismatch;
- header yang kekal status 0;
- count perubahan luar jangka;
- kegagalan login/SSO atau user lookup selepas pilot.

Log tidak boleh mengandungi raw source row, IC, email, password, token atau
ODBC credential.

## 9. Abort dan Rollback

### Sebelum commit / safety block

1. disable `ONEID_SYNC_APPLY_ENABLED=false` dan engine `disabled`;
2. reload environment secara terkawal;
3. sahkan Apply ditolak dan preview masih read-only;
4. simpan correlation ID serta safe diagnostic code;
5. jangan restore database jika transaksi telah rollback dan reconciliation
   membuktikan zero committed mutation.

### Selepas commit atau commit state tidak pasti

1. disable Apply serta hentikan semua perubahan user berkaitan;
2. jangan rerun safe atau legacy writer;
3. kenal pasti header, correlation ID dan exact audit counts;
4. tentukan commit state bersama DBA;
5. jika diperlukan, restore backup menggunakan prosedur yang telah direhearsal;
6. ulang reconciliation dan login/SSO verification;
7. kekalkan `false/disabled` sehingga RCA dan GO baharu.

Code rollback target ialah checkpoint S3 `243ff2c`, tetapi rollback deployment
mesti melalui revert/deploy procedure biasa—bukan destructive Git reset pada
working tree.

## 10. Automatic NO-GO Conditions

S4 pilot kekal NO-GO jika mana-mana keadaan berlaku:

- backup atau restore rehearsal belum disahkan;
- pilot admin/window/rollback owner belum ditetapkan;
- effective flag tidak boleh dibuktikan;
- approval tidak server-bound, boleh replay atau hash tidak disemak dalam run;
- preview warning/block/collision atau Deactivate tidak diterima owner;
- external DB user tidak disahkan read-only;
- S1/S2/S3/auth/smoke regression gagal;
- lock, transaction rollback atau reconciliation fixture gagal;
- cron/scheduler aktif;
- monitoring atau log access tiada;
- code/working tree bukan checkpoint yang diluluskan.

## 11. Keputusan Yang Masih Perlu Diisi

| Item | Nilai |
| --- | --- |
| Maintenance window | Belum ditetapkan |
| Pilot admin | Belum ditetapkan |
| DBA/backup operator | Belum ditetapkan |
| Backup location/checksum | Belum ditetapkan |
| Restore rehearsal evidence | Belum ditetapkan |
| Accepted fresh source baseline | Belum ditetapkan |
| Accepted action counts | Belum ditetapkan |
| Observation window | Cadangan minimum 60 minit; belum diterima |
| Final GO authority | Pemilik sistem OneID |

Register pelaksanaan: `docs/S4_PILOT_GATE_REGISTER.tsv`.

## 12. Baseline Checksum Sebelum S4 Wiring

| Fail | SHA-256 |
| --- | --- |
| `lib/q_func.php` | `6715f149be5a22aca57ca31eb74a2c445fc104b30cb7422fb0f8d693efc60e7a` |
| `lib/sync_user_runner.php` | `965fd187492e1f120b074601746b031474405480f234412e458f64189108c8bb` |
| `app/Sync/SafeSyncOrchestrator.php` | `e14b74d02b011d04a9761bc02b5a9ba1fc0549250722b29a75f39eed8a53f15a` |
| `app/Sync/SyncPreviewService.php` | `64f744e7ac2d87df544dfc9479dbb039ded2afee29df0a1476c6a1cdf671fa46` |
| `lib/Database.php` | `ef82c7ac8d3898e8ead942bb0991007b3fe6b475bd3b697a6b00f0643e0cfb4e` |
| `admin/dashboard.php` | `8aedadb1374b7e11bb42b844fbc2dbc0f1969cd618d426c4ea92e93fe38455b5` |

## 13. Exit Pelan

Dokumentasi S4 lengkap dan S4A dormant tidak bermaksud pilot mendapat GO.
Langkah berikutnya ialah **S4B server-bound approval dan zero-mutation rejection
contracts**, masih tanpa butang Apply, runtime endpoint atau live sync.
