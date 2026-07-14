# R5.2D4 — Production-Wiring Readiness Plan

Tarikh: 14 Julai 2026

Change ID: `R5-2D4-20260714-041411`

Change owner: Pemilik sistem OneID

Rollback owner: Pemilik sistem OneID

Status: **SELESAI — DESIGN/RUNBOOK SAHAJA, TIADA PRODUCTION WIRING**

## 1. Objektif dan Batas

Dokumen ini menetapkan keputusan dan gate sebelum orchestration berasaskan
interface dibenarkan menyentuh production caller. R5.2D4 tidak menambah class
production, environment flag, database migration atau caller switch.

Tiada live database, external API atau sync dijalankan semasa D4. Semua nama
flag dan struktur class di bawah ialah contract cadangan untuk subfasa akan
datang, bukan konfigurasi yang sudah aktif.

## 2. Keadaan Semasa Yang Disahkan

### Caller

| Caller | Lokasi | Status operasi |
|---|---|---|
| Admin manual sync | `lib/q_func.php`, action `admin_add_sync_user` | caller pilot yang dicadangkan |
| Scheduled CLI sync | `cron/run_sync.php` | retired melalui keputusan owner; jangan aktifkan semula dalam cutover ini |

Kedua-dua caller kini memanggil `run_admin_sync_user($operation, $triggered_by)`
secara terus. Dashboard menerima result legacy yang mengandungi
`ext_head_id`, `New`, `Update`, `Deactivate` dan `Reactivate`.

### Persistence dan audit

Sync menulis kepada sekurang-kurangnya:

- `user_tbl`;
- `ext_data_temp_header`;
- `ext_data_temp_body`;
- `sync_change_log`.

`action_add_new_user_from_external_source` menggunakan `INSERT ... ON DUPLICATE
KEY UPDATE`, jadi label `REACTIVATE` boleh mengemas kini row sedia ada. Audit log
ialah bukti reconciliation, tetapi `old_data` semasa hanya dibina daripada
subset field log. Ia bukan full database rollback image.

## 3. Keputusan Architecture R5.2D4

### D4-DEC01 — Compatibility-first untuk pilot pertama

**Keputusan:** initial production engine mesti mengekalkan transaction boundary
legacy yang telah lulus parity D3.

Sebabnya ialah memindahkan orchestration dan membetulkan failure semantics dalam
change sama akan menghilangkan baseline parity. D0-W01 diterima sementara untuk
pilot terkawal, bukan dianggap selesai.

### D4-DEC02 — Transaction fix ialah functional change berasingan

Selepas engine interface stabil, change khusus mesti memindahkan upstream fetch
ke boundary yang selamat atau memulakan transaction selepas snapshot berjaya.
Change itu perlu fixture baharu yang menjangkakan rollback/tiada open transaction
bagi upstream exception. Jangan ubah expectation D3 secara senyap.

### D4-DEC03 — Legacy kekal default

Contract flag yang dicadangkan:

```text
ONEID_SYNC_ENGINE=legacy
```

Nilai yang dibenarkan hanya:

- `legacy` — panggil `run_admin_sync_user` sedia ada;
- `interface` — panggil production interface orchestrator kelak.

Jika flag tiada, default mestilah `legacy`. Jika nilainya selain allowlist,
request mesti gagal sebelum external fetch atau transaction; jangan memilih
engine secara longgar. Flag hanya boleh datang daripada server environment atau
secret/config store, bukan `GET`, `POST`, cookie atau header pengguna.

### D4-DEC04 — Hanya satu writer bagi setiap run

Legacy dan interface engine tidak boleh dijalankan berturut-turut terhadap
database yang sama untuk tujuan comparison. Kedua-duanya boleh deactivate,
update, insert dan menulis audit. “Shadow” yang memanggil dua engine mutating
akan menghasilkan side effect berganda dan comparison yang tidak sah.

### D4-DEC05 — Cron di luar pilot

Pilot pertama hanya melalui admin manual sync. `cron/run_sync.php` kekal retired.
Jika scheduler diperlukan semula pada masa depan, ia memerlukan change dan UAT
berasingan serta mesti menggunakan engine factory yang sama; jangan hidupkan
semula hanya kerana class baharu sudah tersedia.

## 4. Bentuk Production Component Yang Dicadangkan

Komponen berikut belum dibina:

```text
app/Sync/
├── Adapters/
│   ├── DatabaseSyncPersistenceAdapter.php
│   ├── ExternalApiUserSource.php
│   ├── LegacySyncPolicy.php
│   └── SecureInitialPasswordFactory.php
├── SyncOrchestrator.php
├── SyncEngineFactory.php
└── SyncPlan.php
```

Syarat implementation:

- jangan `require` class daripada `tests/Support/Sync`;
- production adapter mesti lulus method/argument parity D2;
- `SyncOrchestrator` mesti lulus D3 fixture tanpa bergantung pada global state;
- engine factory membaca flag sekali dan menggunakan strict allowlist;
- caller mengekalkan bentuk JSON/result dan syslog sedia ada;
- dependency creation tidak membuka transaction sebelum engine dipilih;
- tiada password, token, credential atau full external row direkodkan.

## 5. Dry-run dan Shadow Strategy

### 5.1 Dry-run yang selamat

Dry-run mesti menghasilkan `SyncPlan` tanpa memanggil method mutation:

- `begin`, `commit` atau `rollback`;
- create/update header;
- deactivate/update/insert user;
- stage/body status;
- append audit atau update summary.

Ia hanya boleh membaca satu snapshot external source dan satu snapshot user
state. Output yang dibenarkan ialah counts, UID digest/hashed identifiers,
category counts, warning dan deterministic plan hash. Jangan output full row PII.

### 5.2 Shadow comparison

Shadow yang selamat bermaksud dua **planner pure** menerima snapshot input yang
sama dan plan mereka dibandingkan. Ia bukan menjalankan dua writer.

Gate shadow:

1. snapshot external diambil sekali;
2. snapshot active/inactive user diambil sekali;
3. legacy-compatible planner dan interface planner menerima snapshot sama;
4. compare action, UID digest, category, changed fields, count dan plan hash;
5. mismatch mesti sifar sebelum pilot;
6. tiada persistence mutation berlaku semasa comparison.

Code semasa belum mempunyai pure legacy planner. Oleh itu shadow mode belum
boleh diaktifkan; extraction planner ialah prasyarat implementation seterusnya.

## 6. Reconciliation Plan

### 6.1 Evidence sebelum pilot

Simpan di lokasi change evidence berpermission ketat di luar document root:

- masa, Change ID dan operator;
- engine flag efektif;
- jumlah `user_tbl` aktif/tidak aktif;
- bilangan row mengikut `u_category` dan `avail_status`;
- digest teratur bagi `u_id`, status dan `u_changes_hash`;
- ID/header sync terakhir;
- database backup yang boleh dipulihkan.

Jangan simpan credential atau dump tanpa encryption/permission yang diluluskan.

### 6.2 Read-only SQL contoh

Semak nama schema sebenar dan jalankan melalui client database yang diluluskan:

```sql
SELECT avail_status, COUNT(*) AS total
FROM user_tbl
GROUP BY avail_status
ORDER BY avail_status;

SELECT u_category, avail_status, COUNT(*) AS total
FROM user_tbl
GROUP BY u_category, avail_status
ORDER BY u_category, avail_status;

SELECT ext_head_id, ext_head_status,
       ext_head_initial_sourcedata, ext_head_uploaded_data,
       total_new, total_updated, total_deactivated, total_reactivated,
       triggered_by, ext_head_dt_start, ext_head_dt_end
FROM ext_data_temp_header
ORDER BY ext_head_id DESC
LIMIT 10;

SET @header_id = 0; -- ganti dengan ext_head_id pilot
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

### 6.3 Acceptance selepas run

- header wujud dan bukan tersangkut pada status `0`;
- header totals sama dengan `sync_change_log` bagi setiap action;
- staged/body counts konsisten dengan uploaded count;
- perubahan active/inactive counts boleh diterangkan oleh NEW/DEACTIVATE/
  REACTIVATE;
- tiada duplicate `u_id`, failed transaction atau open transaction;
- sample user bagi setiap action disahkan melalui UI/read-only lookup;
- tiada unexplained plan/reconciliation mismatch.

## 7. Monitoring dan Observability

Setiap run kelak perlu satu correlation ID dan structured log minimum:

```text
event=sync_complete change_id=<id> engine=<legacy|interface>
header_id=<id> status=<status> new=<n> updated=<n>
deactivated=<n> reactivated=<n> duration_ms=<n> outcome=<ok|error>
```

Log error mesti menyimpan exception class, safe error code, engine dan
correlation ID. Jangan log exception payload upstream jika ia mungkin membawa
credential/PII.

Monitor:

- run duration dan timeout;
- header status `0` terlalu lama;
- error/fatal/rollback count;
- count NEW/UPDATE/DEACTIVATE/REACTIVATE;
- reconciliation mismatch;
- concurrent run rejection;
- PHP-FPM/Nginx error semasa admin action.

Threshold mesti menggunakan baseline UAT. Cadangan awal untuk hard stop manual:

- sebarang mismatch parity atau reconciliation;
- sebarang fatal/uncaught exception;
- header kekal status `0` lebih 15 minit;
- deactivate melebihi nilai yang owner jangka atau lebih 5% active users;
- jumlah perubahan melonjak lebih dua kali median run yang telah diterima.

Nilai 5%, 15 minit dan dua kali median ialah cadangan, bukan threshold aktif.
Owner mesti menetapkan nilai akhir sebelum cutover.

## 8. Concurrency

UI boleh menerima double-click atau dua admin boleh memulakan sync serentak.
Sebelum interface pilot, mesti ada single-run lock dengan owner, TTL dan safe
release. Lock perlu diuji bagi success, exception dan process termination.

Jangan gunakan kewujudan header status `0` sahaja sebagai lock tanpa recovery
policy kerana D0-W01 boleh meninggalkan header/transaction pada failure upstream.

## 9. Cutover Berperingkat

### Stage A — implementation tanpa caller

1. bina production adapter/orchestrator;
2. jalankan D0-D3 serta production contract tests;
3. pastikan flag belum dibaca oleh caller;
4. checksum dan review code.

### Stage B — dry-run/read-only

1. bina pure planner;
2. buktikan mutation call count sifar;
3. jalankan snapshot representative UAT;
4. capai zero mismatch;
5. rekod reconciliation baseline.

### Stage C — dormant wiring

1. tambah engine factory pada kedua-dua code path tetapi default `legacy`;
2. pastikan result JSON/syslog tidak berubah;
3. uji missing/legacy/invalid flag;
4. cron kekal disabled/retired;
5. deploy dan soak dengan legacy engine.

### Stage D — satu pilot interface

1. buka maintenance window dan halang concurrent run;
2. sahkan backup boleh dibaca/dipulihkan;
3. ambil pre-run reconciliation evidence;
4. tetapkan flag `interface` pada PHP-FPM environment;
5. reload service secara terkawal dan sahkan flag efektif;
6. jalankan satu admin manual sync;
7. reconcile header, logs, counts dan sample users;
8. jika lulus, kembalikan flag kepada `legacy` atau teruskan soak mengikut
   keputusan change owner;
9. cron tidak disentuh.

### Stage E — soak dan default switch

Tukar default hanya selepas bilangan run berjaya dan observation window yang
dipersetujui. Legacy runner tidak dibuang dalam stage yang sama.

## 10. Rollback

### 10.1 Code-path rollback

Jika kegagalan berlaku sebelum commit atau semasa wiring:

1. hentikan run baharu;
2. tetapkan `ONEID_SYNC_ENGINE=legacy`;
3. reload PHP-FPM/environment secara terkawal;
4. sahkan engine efektif sebelum membenarkan admin sync;
5. simpan correlation ID, header ID dan log;
6. jalankan regression legacy dan reconciliation.

### 10.2 Post-commit data recovery

Feature flag tidak mengundurkan perubahan yang telah committed. Jangan cuba
membina semula semua nilai lama hanya daripada `sync_change_log`: snapshot audit
semasa tidak menyimpan keseluruhan `data1`–`data12`, password dan semua metadata.

Jika post-commit reconciliation gagal:

1. blok semua sync dan perubahan akaun berkaitan;
2. jangan rerun legacy secara automatik;
3. kenal pasti header/correlation ID;
4. nilai skop row melalui change log dan backup;
5. pulihkan database menggunakan backup/prosedur DBA yang telah diuji;
6. ulang reconciliation sebelum membuka sistem;
7. kekalkan engine `legacy` sehingga RCA dan approval baharu.

Backup minimum sebelum pilot mesti merangkumi `user_tbl`,
`ext_data_temp_header`, `ext_data_temp_body` dan `sync_change_log`, atau full
database backup jika polisi operasi mensyaratkannya.

## 11. GO/NO-GO

Register mesin-baca tersedia di
`docs/R5_2D4_PRODUCTION_WIRING_GATE_REGISTER.tsv`.

**GO** hanya apabila semua gate `pending/planned` yang berkaitan Stage D menjadi
PASS/ACCEPTED dengan evidence. **NO-GO** jika backup tidak disahkan, dry-run
mutates data, parity mismatch bukan sifar, concurrent lock gagal, threshold
tidak ditetapkan atau rollback database belum boleh dilaksanakan.

## 12. Baseline Checksum D4

| Fail | SHA-256 |
|---|---|
| `lib/sync_user_runner.php` | `7a5fbe4e9d176661eda4f7d26449641edef683e77d355f9b9d5e227a9331b9df` |
| `lib/Database.php` | `f9aff1d051c3cc7aa9d8902b299292fe49e70b34b997a69a2feb931109547658` |
| `lib/q_func.php` | `b44534b888c0809ffc3185f73456a8415663d65871e9c7fa8c07c441f5e6a33b` |
| `cron/run_sync.php` | `9c8017a5774a9ea5d12daa70e893ee3d909eeb967df75cceed6dbb438afb3e59` |
| `page/dashboard.php` | `9077b77174d7ec33fcc91b9a66ca349c7f6a105ccbc764dc06511e9f195fc361` |
| `admin/dashboard.php` | `24b2028f0d978a0ce38d1915d2a7bf60445e7ac36c0a360fea04b3db089be7dc` |

## 13. Rollback R5.2D4

D4 hanya dokumentasi. Untuk membatalkannya, buang:

```text
docs/R5_2D4_PRODUCTION_WIRING_READINESS_PLAN.md
docs/R5_2D4_PRODUCTION_WIRING_GATE_REGISTER.tsv
```

Kemudian pulihkan rujukan D4 dalam dokumen D3 dan pelan Fasa 7. Tiada service,
database atau runtime rollback diperlukan.

## 14. Keputusan

R5.2D4 menutup readiness design tetapi **tidak memberi GO kepada production
wiring**. Langkah implementation paling selamat selepas approval ialah membina
pure `SyncPlan` dan dry-run dengan zero-mutation proof, kemudian production
adapter secara dormant. Caller switch kekal di belakang gate berasingan.

**Kemaskini R5.2D5:** `SyncPlan`, test-only pure planner dan test-only dry-run
telah disediakan. Zero-mutation/parity fixture lulus 25/25 dan D4-G08 ditutup
sebagai PASS bagi test-only readiness. G09/G10 kekal pending kerana production
single-snapshot shadow dan representative UAT snapshot belum dilaksanakan.

**Kemaskini R5.2D6:** pure planner telah diextract ke
`app/Sync/SyncPlanner.php`. Purity guard lulus 17/17 dan D5 kekal 25/25. Planner
masih dormant tanpa source/persistence adapter atau caller wiring; G09/G10 dan
semua cutover gate kekal pending.

**Kemaskini R5.2D7:** empat production adapter dormant telah dibina dan contract
suite lulus 32/32. D4-G07 ditutup sebagai PASS. Tiada runtime/caller/feature-flag
wiring atau live I/O; G09/G10 dan cutover gates kekal pending.

**Kemaskini R5.2D8:** dormant production orchestrator telah dibina menggunakan
production adapter dan full legacy parity lulus 18/18. D4-G25 ditutup. Ini ialah
checkpoint selamat application restructuring; G09/G10, feature flag, lock,
monitoring, backup dan cutover gates kekal pending/NO-GO.
