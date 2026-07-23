# Gate F — Feasibility Register External Sync Pelajar ODL

**Status:** `PROCEED WITH CONDITIONS`

**Tarikh dibuka:** 22 Julai 2026

**Skop:** Penilaian kebolehlaksanaan dan connection read-only UAT sebelum
migration, Shadow Preview atau Apply.

## 1. Keputusan semasa

Gate F diluluskan dengan syarat pada 23 Julai 2026 oleh Firdaus,
System Analyst/DBA. Kelulusan terhad kepada implementasi adapter read-only,
data-quality audit dan Shadow Preview UAT. Apply, scheduler, pilot, Full Apply
dan production rollout tidak dibenarkan.

Keputusan Gate semasa:

```text
PROCEED WITH CONDITIONS
```

Syarat utama: credential kekal dalam private runtime secret store; adapter
memaksa TLS; `viewer@%` dan broad SELECT diterima untuk UAT sahaja; Apply kekal
disabled; security review baharu diperlukan sebelum production; ODL data owner
dan OneID operations owner ialah Firdaus.

## 2. Datasource candidate

Maklumat asas berikut telah diberikan oleh owner permintaan pada 22 Julai 2026
untuk environment UAT:

| Perkara | Nilai/evidence | Status Gate |
|---|---|---|
| Evidence date | 22 Julai 2026 | RECORDED |
| Environment | UAT | CONFIRMED |
| Host | `172.16.2.224` | TESTED dari OneID staging |
| Port | `3308` | TESTED |
| DBMS | MySQL `8.4.7` | CONFIRMED; driver OneID `pdo_mysql` tersedia |
| Database | `upnm` | TESTED read-only |
| View | `student_basic_info` | Authority/business population CONFIRMED |
| Username | Private configuration | TESTED read-only |
| Password | Private runtime secret; tidak direkod dalam Git/dokumen | CONFIRMED handling policy |
| Network/TLS | Origin OneID staging disahkan; TLSv1.3/AES-256-GCM | PASS |
| Database grant | Read-only PASS; broad SELECT/`viewer@%` accepted UAT condition | CONDITIONAL |

### 2.1 Topologi environment semasa

Owner mengesahkan topologi staging pada 23 Julai 2026:

```text
OneID web/application staging  172.16.2.153
            |
            +--> OneID database (oneiddb)  172.16.2.141
            |
            +--> ODL application/database  172.16.2.224
```

Kod PHP external sync berjalan pada web/application staging
`172.16.2.153`. Oleh itu connection ke datasource ODL mesti diuji dari host
tersebut. MySQL account host-specific perlu menggunakan source identity yang
ODL lihat untuk connection dari `172.16.2.153` (atau NAT address jika laluan
network menterjemahkannya), bukan IP server `oneiddb`.

### 2.2 Ownership dan evidence metadata yang diberikan

| Perkara | Nilai semasa | Status Gate |
|---|---|---|
| Data owner | Firdaus | CONFIRMED |
| DBA | `Firdaus` | RECORDED |
| View version | `MySQL 8.4.7` diberikan, tetapi ini versi DBMS dan bukan versi definisi view | PENDING |
| Refresh/SLA | Live standard view; tiada scheduled refresh. Freshness/consistency source tables dan partial-load behavior belum dibuktikan | PARTIAL |
| Result aggregate | Baseline 49 row mempunyai 1 IC kosong; rerun 23 Julai menunjukkan 52 row dan semua blank metric = 0 | PASS bagi mandatory-field completeness |
| Grant evidence | `USAGE`; `SELECT ON moodle.*` dan `upnm.*`; tiada write/DDL/admin privilege | PASS read-only; broad scope dan `viewer@%` ialah UAT accepted condition |
| TLS evidence | Dari OneID staging `172.16.2.153`: TLS `1.3`, cipher `TLS_AES_256_GCM_SHA384` | PASS |
| Change/access ticket | `N/A`; accepted dalam approval Gate F UAT | ACCEPTED CONDITION |
| Approved by | Firdaus, System Analyst/DBA | APPROVED |

### 2.3 Semakan evidence teknikal — 22 Julai 2026

#### DBMS dan view version

`MySQL 8.4.7` diterima sebagai versi database server. Ia tidak version-kan
definisi `student_basic_info`. View version masih memerlukan salah satu daripada:

- application/schema release version;
- deployment/change reference dan timestamp; atau
- checksum tersanitasi bagi canonical `SHOW CREATE VIEW` output.

#### Refresh dan consistency

`student_basic_info` ialah standard live view dan tidak memerlukan scheduled
refresh. Ini menutup persoalan refresh job, tetapi belum membuktikan bahawa base
tables sentiasa berada dalam state lengkap/consistent ketika OneID membaca
snapshot. Owner perlu menjelaskan sama ada source menggunakan batch import,
transactional publication atau maintenance window, serta bagaimana partial
update dikesan.

Pada 23 Julai 2026, owner mengesahkan row boleh diterbitkan dalam keadaan profil
separa lengkap: data asas pelajar telah lengkap tetapi e-mel mungkin belum ada
dan dikemas kini pada waktu berlainan. Polisi feasibility bagi keadaan ini:

- No. Matrik dan IC ialah mandatory identity fields; blank pada salah satu
  menyebabkan snapshot diblock;
- e-mel ialah late-arriving profile field dan blank e-mel sahaja tidak
  menyebabkan seluruh snapshot diblock;
- apabila e-mel tersedia kemudian, sync merancang `UPDATE` kepada akaun sama;
- e-mel kosong tidak boleh memadam e-mel OneID yang telah ada tanpa polisi
  profile authority yang diluluskan;
- perubahan row count/shrink masih perlu dinilai berasingan kerana penjelasan
  e-mel lewat tidak membuktikan keseluruhan populasi telah diterbitkan.

Owner seterusnya mengesahkan population contract view:

- hanya pelajar postgraduate yang telah diterima sebagai pelajar dan telah
  diberikan No. Matrik dipaparkan;
- pemohon atau individu yang belum diterima tidak dipaparkan walaupun maklumat
  peribadinya telah lengkap;
- ketiadaan individu yang belum diterima ialah expected business filtering,
  bukan incomplete snapshot;
- selepas row layak dipaparkan, e-mel masih boleh tiba kemudian seperti polisi
  late-arriving field di atas.

Dengan pengesahan ini, authority dan in-scope population view ditutup pada
peringkat business contract. Operational anomaly seperti source kosong,
connection failure atau shrink luar biasa kekal di bawah GF-11.

#### Source failure dan shrink policy

Owner menerima polisi fail-closed berikut pada 23 Julai 2026:

| Keadaan | Keputusan |
|---|---|
| Connection atau query gagal | `BLOCK` |
| Source memulangkan zero row | `BLOCK` |
| Blank No. Matrik atau IC melebihi 0 | `BLOCK` |
| Blank e-mel | `ALLOW`; late-arriving profile field |
| Row count turun lebih 20% daripada accepted baseline | `BLOCK` |
| Source failure | Tidak boleh menghasilkan deactivation |

Accepted baseline awal ialah 53 row. Oleh itu 42 row atau kurang melebihi
penurunan 20% dan mesti diblock. Nilai 43 row masih di dalam threshold, tetapi
tetap dipaparkan dalam Preview untuk semakan. Accepted baseline tidak boleh
berubah secara automatik; baseline baharu memerlukan snapshot berjaya,
reconciliation dan approval owner yang direkodkan.

#### Result aggregate

Aggregate read-only diterima pada 22 Julai 2026:

| Metric | Hasil | Penilaian |
|---|---:|---|
| Total rows | 49 | Baseline awal |
| Active (`2`) | 49 | PASS |
| Suspended (`4`) | 0 | PASS; senario belum hadir dalam data |
| Deferred (`5`) | 0 | PASS; senario belum hadir dalam data |
| Invalid status | 0 | PASS |
| Blank Matrik | 0 | PASS |
| Blank IC | 1 | FAIL/BLOCKING |
| Blank e-mel | 0 | PASS |
| Duplicate Matrik groups | 0 | PASS |
| Duplicate Matrik + IC groups | 0 | PASS |
| Maximum Matrik length | 11 | PASS terhadap `u_id varchar(20)` |
| Maximum IC length | 12 | PASS terhadap `data2 varchar(100)` |
| Maximum nama length | 20 | PASS terhadap `data1 varchar(100)` |
| Maximum e-mel length | 30 | PASS terhadap `data5 varchar(100)` |
| Maximum program length | 50 | PASS terhadap `data7 varchar(100)` |
| Maximum fakulti length | 41 | PASS terhadap `data6 varchar(100)` |

Satu row tanpa IC tidak memenuhi matching contract pelajar semasa
`No. Matrik + IC`. Owner menerima finding ini dengan syarat team ODL membetulkan
row tersebut dan memastikan tiada pelajar tanpa IC. Evidence asal tidak diubah:
snapshot feasibility masih mempunyai `blank_ic = 1`. Sebelum Preview data sebenar
atau pilot, aggregate semula wajib menunjukkan `blank_ic = 0`; jika tidak,
proses kekal fail-closed.

Aggregate susulan diterima pada 23 Julai 2026:

| Metric | Hasil | Penilaian |
|---|---:|---|
| Total rows | 52 | Baseline susulan |
| Blank Matrik | 0 | PASS |
| Blank IC | 0 | PASS — blocker data-quality ditutup |
| Blank e-mel | 0 | PASS |

Output hanya mengandungi aggregate tanpa raw PII. Kenaikan daripada 49 kepada 52
row perlu kekal tertakluk kepada completeness/shrink policy, tetapi ia tidak
menghalang penutupan finding IC kosong.

Satu lagi bacaan pada `2026-07-23 04:12:55` menunjukkan 53 row,
`blank_matrik = 0` dan `blank_ic = 0`. Bacaan count pada
`2026-07-23 04:14:42` kekal 53 row. Ini mengesahkan mandatory identity fields
kekal bersih bagi dua bacaan tersebut. Perubahan 52 → 53 menunjukkan view ialah
dataset hidup; dua bacaan stabil dalam selang dua minit belum membuktikan
publication atomic atau menutup risiko partial-load.

#### View definition evidence

Percubaan menggunakan account runtime ditolak seperti berikut:

```text
SQL 1142: SHOW VIEW command denied for student_basic_info
```

Ini tidak menghalang runtime `SELECT` dan account aplikasi tidak perlu diberi
`SHOW VIEW` semata-mata untuk Gate. Definisi/filter view perlu dibekalkan oleh
DBA melalui privileged administrative evidence, migration/source-control
artifact atau signed query specification. Pada 22 Julai 2026, owner permintaan
mengesahkan bahawa `student_basic_info` menapis:

```sql
status_code IN ('2', '4', '5')
```

Pengesahan owner bersama aggregate semasa yang menunjukkan 49 row berkod numeric
`2` diterima sebagai Gate evidence bagi eligibility/filter status. Definisi view
DBA masih berguna sebagai configuration/version evidence, tetapi bukan lagi
blocker untuk status contract.

#### Database grant

Evidence yang diberikan:

```sql
GRANT SELECT ON upnm.* TO 'viewer'@'%';
```

Grant ini tidak mempunyai write privilege, tetapi meliputi semua object dalam
database `upnm` dan account dibenarkan dari mana-mana host. Ia belum memenuhi
least-privilege target Gate F yang menghadkan `SELECT` kepada approved view dari
network identity yang diluluskan. Sebarang pengetatan grant mesti dilakukan oleh
DBA melalui change yang diluluskan, bukan oleh feasibility audit.

Pada 23 Julai 2026, programmer sistem ODL mengesahkan melalui negative mutation
test bahawa account `viewer` menerima MySQL error `1142` bagi kedua-dua operasi:

```text
UPDATE command denied to user viewer for table students
DELETE command denied to user viewer for table students
```

Screenshot asal mengandungi data pelajar dan tidak disimpan dalam repository.
Evidence tersanitasi ini mengesahkan `UPDATE` dan `DELETE` ditolak pada jadual
yang diuji. Ia belum membuktikan `INSERT`, DDL atau privilege pada semua object
lain ditolak. Ia juga tidak mengubah finding bahawa authenticated account masih
`viewer@%` dan `SELECT ON upnm.*` lebih luas daripada approved view.

Output `SHOW GRANTS FOR CURRENT_USER` kemudiannya diterima:

```text
GRANT USAGE ON *.* TO viewer@%
GRANT SELECT ON moodle.* TO viewer@%
GRANT SELECT ON upnm.* TO viewer@%
```

Ini melengkapkan bukti bahawa account tidak mempunyai write, DDL atau
administrative privilege; `USAGE` tidak memberikan akses data. Mutation safety
diterima sebagai read-only. Walau bagaimanapun, account masih boleh membaca dua
database penuh dan boleh authenticate dari wildcard host. Perkara tersebut
direkod sebagai least-privilege exception/accepted condition untuk UAT, bukan
sebagai write-access blocker. Production atau skop yang lebih tinggi masih
memerlukan grant dan host yang dikecilkan atau waiver yang diluluskan.

#### TLS

Dua evidence sesi telah diterima:

```text
Sesi awal:
Ssl_version = ''
Ssl_cipher  = ''

Sesi baharu:
Ssl_version = TLSv1.3
Ssl_cipher  = TLS_AES_256_GCM_SHA384
```

Sesi baharu membuktikan endpoint menyokong TLS dan connection tersebut benar-benar
dienkripsi. Sesi awal pula membuktikan server masih menerima connection tanpa
TLS, atau client awal tidak memaksa TLS. Konfigurasi adapter OneID kelak mesti
memerlukan TLS dan fail closed jika TLS tidak dapat diwujudkan. Untuk melengkapkan
network-path evidence, rekod sama ada sesi TLS diuji dari host OneID UAT atau
client lain; jika client lain, ujian perlu diulang dari OneID UAT pada Fasa 3.

Evidence connection tambahan pada 23 Julai 2026 mengesahkan database `upnm`
dan authenticated account `viewer@%`. Output session ialah:

```text
Ssl_version = ''
Ssl_cipher  = ''
```

Sesi tersebut tidak menggunakan TLS. Execution origin juga belum dinyatakan.
GF-12 gagal bagi sesi ini dan connection tersebut tidak boleh digunakan oleh
adapter OneID. Client/runtime mesti memaksa TLS dan ujian perlu diulang sehingga
kedua-dua nilai tidak kosong; kegagalan TLS tidak boleh fallback kepada
plaintext. Nilai `viewer@%` turut mengesahkan host wildcard bagi account runtime
masih digunakan dan GF-13 belum selesai.

Ujian susulan dibuat terus dari OneID staging `172.16.2.153` kepada ODL DB
`172.16.2.224:3308`. Hasilnya:

```text
USER()         = viewer@172.16.2.153
CURRENT_USER() = viewer@%
Ssl_version    = TLSv1.3
Ssl_cipher     = TLS_AES_256_GCM_SHA384
```

Ini membuktikan network origin sebenar dan encrypted session dari runtime host
OneID. GF-12 ditutup sebagai `PASS`. Adapter masih perlu menggunakan konfigurasi
yang memaksa TLS/fail-closed supaya behaviour ini tidak bergantung kepada
fallback default client. `CURRENT_USER() = viewer@%` kekal sebagai UAT
least-privilege condition di bawah GF-13.

## 3. Contract yang telah disahkan

### 3.1 Eligibility active view

Keputusan owner semasa:

| Kod | Status | Presence active view | Kesan OneID |
|---:|---|---|---|
| `1` | Pending | Tidak masuk | Tiada NEW; pengguna sedia aktif menjadi calon DEACTIVATE apabila hilang |
| `2` | Active | Masuk | NEW/UPDATE/REACTIVATE atau kekal aktif |
| `3` | Inactive | Tidak masuk | Calon DEACTIVATE apabila hilang |
| `4` | Suspended | Masuk | Kekal aktif |
| `5` | Deferred | Masuk | Kekal aktif |
| `6` | Withdrawn | Tidak masuk | Calon DEACTIVATE apabila hilang |

OneID semasa tidak membaca status terperinci. Datasource menentukan populasi
aktif dan setiap row yang hadir dianggap `avail_status = 1`.

### 3.2 Mapping identity/profile

| ODL | Canonical | OneID |
|---|---|---|
| `nama` | `data1` | `user_tbl.data1` |
| `no_kad_pengenalan` | `data2` | `user_tbl.data2` |
| Tiada | `data3 = ''` | `user_tbl.data3` |
| `no_matrik` | `data4` | `user_tbl.data4`, `u_id` |
| `emel_alfateh` | `data5` | `user_tbl.data5` |
| `fakulti` | `data6` | `user_tbl.data6` |
| `program` | `data7` | `user_tbl.data7` |
| Nilai sistem | `Pelajar` | `u_category = 10` |

Matching pelajar aktif semasa menggunakan `No. Matrik + IC`. Reactivation
semasa mengenal pengguna tidak aktif melalui No. Matrik. Matrik dan IC mesti
stabil untuk mengelakkan pasangan DEACTIVATE/NEW yang salah.

Owner permintaan mengesahkan pada 23 Julai 2026 bahawa
`student_basic_info` dijamin 100% postgraduate dan tidak mengandungi pelajar UG.
Polisi conflict dan multi-source provenance masih diperlukan bagi sejarah
pelajar merentas sumber, perubahan skop masa hadapan atau identity yang telah
wujud dalam OneID; tetapi overlap UG aktif bukan expected population view ODL.

### 3.3 Schema live OneID

Read-only `information_schema` evidence pada 22 Julai 2026:

| Field | Type live | Implikasi ODL |
|---|---|---|
| `u_id` | `varchar(20)` | Matrik canonical maksimum 20 aksara |
| `u_category` | `int` | Pelajar menggunakan `10` |
| `avail_status` | `int` | OneID hanya mempunyai active/inactive account state |
| `account_source` | `varchar(16)` | Hanya `manual`/`external`; belum source-specific |
| `sync_protected` | `tinyint(1)` | Manual protected tidak boleh dimutasi |
| `data1` | `varchar(100)` | Nama maksimum 100 aksara |
| `data2` | `varchar(100)` | IC/pasport maksimum 100 aksara |
| `data4` | `varchar(100)` | Matrik profile maksimum 100, tetapi `u_id` menghadkan 20 |
| `data5`–`data7` | `varchar(100)` | E-mel, fakulti dan program maksimum 100 aksara |

Jadual `external_source`, `user_external_identity`,
`external_source_snapshot` dan `user_external_identity_event` belum wujud.

## 4. Baseline code/test evidence

Audit read-only/local fixture pada 22 Julai 2026:

| Evidence | Hasil |
|---|---|
| Pure sync helpers | PASS, 28/28 |
| Legacy sync orchestration | PASS, 18/18 |
| Operational safety | PASS, 24/24 |
| Production adapter functional assertions | PASS sebelum checksum guard |
| Production orchestrator parity assertions | PASS sebelum checksum guard |
| S4D runtime checksum `sync_user_runner.php` | PASS |
| S4D runtime checksum `Database.php` | FAIL — baseline checksum lama tidak sepadan |
| S4D runtime checksum `q_func.php` | FAIL — baseline checksum lama tidak sepadan |

Checksum mismatch ialah evidence gap. Ia mesti dijelaskan melalui approved
change history dan characterization baseline mesti diperbaharui secara sengaja;
checksum tidak boleh ditukar hanya untuk menjadikan test hijau.

## 5. Gate decision register

Owner memberikan jawapan berikut pada 22 Julai 2026 tetapi menyatakan sebahagian
jawapan masih belum pasti dan akan direview semula. Oleh itu keputusan ini
direkod sebagai `PROVISIONAL` dan bukan approval muktamad:

| Decision | Jawapan provisional |
|---|---|
| Profile authority UG vs ODL | Block dan semak manual jika profile berbeza |
| Provenance design | Setuju |
| Database grant | Firdaus akan hadkan kepada `student_basic_info` dan host OneID |
| Firdaus approval scope | Keseluruhan Gate F |
| Active → Pending | Deactivate kerana Pending tidak berada dalam active view |
| Grace period | Tiada |
| Sync actions | Setuju `NEW`, `UPDATE`, `REACTIVATE`, `DEACTIVATE` |
| Live view consistency | Ya |
| Runtime checksum audit | Dibenarkan |

Keputusan Pending kini selari dengan kontrak presence-based OneID. Apabila
pengguna berubah daripada status yang berada dalam active view (`2`, `4`, `5`)
kepada Pending (`1`), row hilang daripada view dan menjadi calon `DEACTIVATE`
pada sync berikutnya. Deactivation masih tertakluk kepada snapshot lengkap,
Preview, approval, threshold, protection dan reconciliation.

| ID | Acceptance item | Evidence/status semasa | Owner diperlukan | Keputusan |
|---|---|---|---|---|
| GF-01 | Business outcome sync dipersetujui | Owner bersetuju NEW/UPDATE/REACTIVATE/DEACTIVATE tetapi jawapan akan direview | Business owner | PROVISIONAL |
| GF-02 | Population active view dipersetujui | Kod `2`, `4`, `5` masuk; `1`, `3`, `6` keluar | Business/data owner | CONFIRMED |
| GF-03 | Pending transition behavior diterima | Active → Pending menjadi calon deactivation kerana Pending tidak berada dalam active view | Business/OneID owner | CONFIRMED |
| GF-04 | Grace period/exception diputuskan | Tiada grace period bagi Pending, Inactive atau Withdrawn | Business owner | CONFIRMED |
| GF-05 | View authoritative dan lengkap | View hanya mengandungi postgraduate yang telah diterima dan diberi No. Matrik; pemohon belum diterima sengaja dikecualikan. E-mel boleh tiba lewat | CTL, ODL | CONFIRMED bagi business population; anomaly teknikal dikawal GF-11 |
| GF-06 | Status code representation konsisten | Owner mengesahkan filter `IN ('2','4','5')`; snapshot 49 row semuanya menggunakan kod numeric `2` | Owner permintaan/ODL data owner | CONFIRMED |
| GF-07 | Identity uniqueness/stability | Rerun 23 Julai berkembang 52 → 53 row; dua bacaan terkini blank Matrik=0 dan blank IC=0; duplicate baseline kekal 0 | ODL data owner | PASS bagi mandatory fields; duplicate perlu kekal dipantau |
| GF-08 | Field length compatible | Semua maximum length snapshot berada dalam had live OneID | ODL data owner | CONFIRMED |
| GF-09 | Profile authority UG vs ODL | View disahkan 100% postgraduate tanpa UG. Blank source e-mel tidak memadam nilai sedia ada; identity/profile conflict diblock untuk semakan manual | Business/data owner | CONFIRMED |
| GF-10 | Multi-source provenance diputuskan | `STUDENT_ODL_PG`; kategori 10; multi-membership; any-active-source activation; source/event history; ODL tidak mengubah password/kategori/ACL | OneID system owner/DBA | CONFIRMED bagi policy; schema migration perlu review berasingan |
| GF-11 | Source failure/completeness policy | Baseline 53; failure/empty/blank identity diblock; shrink melebihi 20% diblock; blank e-mel dibenarkan; source failure tidak boleh deactivate | OneID system owner | CONFIRMED |
| GF-12 | Network dan TLS diluluskan | Ujian dari OneID staging `172.16.2.153` ke ODL `172.16.2.224:3308`: TLSv1.3, `TLS_AES_256_GCM_SHA384`; origin disahkan melalui `USER()` | Infrastructure/security owner | PASS — adapter mesti kekalkan TLS fail-closed |
| GF-13 | Read-only grant dibuktikan | `SHOW GRANTS`: hanya USAGE dan SELECT pada `moodle.*`/`upnm.*`; negative UPDATE/DELETE test ditolak. Tiada write/DDL/admin privilege | Firdaus | PASS bagi read-only; broad SELECT dan `viewer@%` diterima sebagai UAT condition, perlu remediation/waiver sebelum production |
| GF-14 | Secret handling diluluskan | Private runtime secret store; tiada credential dalam Git/source/screenshot/log; akses terhad; rotation semasa incident/perubahan pegawai/polisi | Firdaus, System Analyst/DBA | CONFIRMED |
| GF-15 | Runtime baseline bersih | Rebaseline 23 Julai 2026: 10 suite, 219 checks, 0 failure; SKP quarantine dan approval-bound coordinator disahkan; commit `2e9133d` | OneID code owner | PASS |
| GF-16 | Pilot/rollback strategy diterima | Sync actions dipersetujui secara provisional; draft backup, Deactivate=0 dan correlation rollback tersedia | OneID/business owner | PROVISIONAL |
| GF-17 | Capacity/schedule/operations | Manual Shadow Preview; automatic sync/Apply disabled; failure/empty/shrink block dan log; retry manual; ODL data owner, OneID operations owner, DBA dan escalation: Firdaus | Firdaus | CONFIRMED |
| GF-18 | Named final approver | Firdaus, System Analyst/DBA; 23 Julai 2026; change/access reference `N/A` | Firdaus | APPROVED — PROCEED WITH CONDITIONS |

### 5.1 Audit runtime checksum

Expected baseline dijumpai dalam sejarah Git:

| File | Expected baseline commit | Perubahan selepas baseline |
|---|---|---|
| `lib/Database.php` | `c146e37` | Configuration, session, Admin Step-Up 2FA dan MFA methods |
| `lib/q_func.php` | `ff20b0d` | Operational sync/threshold wiring, session hardening, configuration dan Admin Step-Up 2FA routes |

Functional sync assertions dan operational safety lulus, tetapi checksum guard
masih gagal kerana ia membandingkan whole-file hash lama. Oleh sebab
`q_func.php` memang menerima perubahan operational sync selepas baseline,
expected hash tidak boleh dikemas kini hanya atas alasan perubahan tidak
berkaitan. Code owner perlu menerima current runtime sebagai baseline baharu,
menjalankan regression matrix penuh dan merekod commit/hash yang diluluskan.

### 5.2 Semakan closure lokal — 23 Julai 2026

Atas arahan owner permintaan untuk membersihkan blocker ODL, baseline runtime
semasa telah diaudit semula. Characterization lapuk yang masih menjangka endpoint
SKP telah dikemas kini untuk mengesahkan endpoint tersebut kekal dikuarantin.
Assertion coordinator turut diselaraskan dengan Operational, Full dan Pilot
coordinator semasa tanpa membenarkan legacy writer.

Hasil semakan:

| Perkara | Hasil |
|---|---|
| `lib/Database.php` | `71b51b7a9443bc3b83361be8b80c2ea464694af5454bbb38bfb80ad6ab3a1cce` |
| `lib/q_func.php` | `9f53ef34248c9a8f93f26757d75b4fa1563882b8b4a59a785637c7de8e1d2ee9` |
| Regression sync | 10 suites, 219 checks, 0 failures |
| SKP quarantine contract | PASS |
| Legacy writer exclusion | PASS |

Blocker teknikal lokal GF-15 selesai dan direkod dalam commit `2e9133d`.
Evidence closure dirangkum menggunakan
[`ODL_GATE_F_BLOCKER_CLOSURE_PACK.md`](ODL_GATE_F_BLOCKER_CLOSURE_PACK.md).

### 5.3 Approval akhir — 23 Julai 2026

Firdaus, System Analyst/DBA, merekod keputusan:

```text
Decision: PROCEED WITH CONDITIONS
Environment: UAT
Change/access reference: N/A
```

Dibenarkan:

- implementasi adapter read-only;
- data-quality audit;
- Shadow Preview.

Tidak dibenarkan:

- Pilot Apply atau Full Apply;
- production rollout;
- automatic scheduler;
- sebarang mutation pengguna daripada source ODL.

Operational policy ialah Manual Shadow Preview, automatic sync/Apply disabled,
failure/empty/shrink diblock dan dilog, serta retry manual hanya selepas source
disahkan stabil. `viewer@%` dan broad SELECT diterima sebagai syarat UAT sahaja.

## 6. Baki syarat selepas approval

ODL data owner dan OneID operations owner ialah Firdaus. Baseline dan
characterization lokal direkod dalam commit `2e9133d`.

Sebelum production, grant `viewer@%`, broad database SELECT dan keseluruhan
security/operations design mesti direview semula. Approval Gate F ini bukan
production waiver.

## 7. Syarat menutup Gate F

Gate hanya boleh berubah kepada `PROCEED` atau `PROCEED WITH CONDITIONS` apabila:

- semua item `BLOCKING` ditutup;
- setiap item `PENDING` mempunyai keputusan atau documented accepted condition;
- evidence mempunyai tarikh, owner dan reference;
- checksum mismatch menerima disposition yang sah;
- tiada password atau raw PII dimasukkan dalam evidence; dan
- final approver menandatangani keputusan Gate.

Selepas Gate F lulus, fasa pertama masih Fasa 0 baseline/characterization. Gate
F tidak dengan sendirinya membenarkan Full Apply atau mutation pengguna.
