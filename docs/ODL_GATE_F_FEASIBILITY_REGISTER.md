# Gate F — Feasibility Register External Sync Pelajar ODL

**Status:** `REWORK / MORE EVIDENCE REQUIRED`

**Tarikh dibuka:** 22 Julai 2026

**Skop:** Penilaian kebolehlaksanaan sebelum sebarang connection ke datasource
ODL, migration, implementation, Preview data sebenar atau Apply.

## 1. Keputusan semasa

Gate F belum lulus. Kontrak status active view dan baseline teknikal OneID telah
disahkan, tetapi authority/completeness view, ownership, security/network,
read-only grant, lifecycle exception, provenance dan beberapa acceptance
decision masih belum mempunyai evidence atau approval.

Keputusan Gate semasa:

```text
REWORK / MORE EVIDENCE REQUIRED
```

Keputusan ini tidak membenarkan penggunaan password, connection test ke
`172.16.2.224:3308`, migration, coding adapter atau mutation pengguna.

## 2. Datasource candidate

Maklumat asas berikut telah diberikan oleh owner permintaan pada 22 Julai 2026
untuk environment UAT:

| Perkara | Nilai/evidence | Status Gate |
|---|---|---|
| Evidence date | 22 Julai 2026 | RECORDED |
| Environment | UAT | CONFIRMED |
| Host | `172.16.2.224` | RECORDED; belum diuji |
| Port | `3308` | RECORDED; belum diuji |
| DBMS | MySQL `8.4.7` | CONFIRMED; driver OneID `pdo_mysql` tersedia |
| Database | `upnm` | RECORDED; belum diuji |
| View | `student_basic_info` | RECORDED; authority/completeness belum disahkan |
| Username | Private configuration | DIBERIKAN; grant belum disahkan |
| Password | Belum dibekalkan dan tidak boleh direkod dalam Git/dokumen | PENDING |
| Network/TLS | Belum ada evidence route, firewall atau TLS mode | PENDING |
| Database grant | Perlu bukti `SELECT` sahaja kepada view diluluskan | PENDING |

### 2.1 Ownership dan evidence metadata yang diberikan

| Perkara | Nilai semasa | Status Gate |
|---|---|---|
| Data owner | `CTL, ODL` | RECORDED; nama/jawatan pegawai pengesah belum diberikan |
| DBA | `Firdaus` | RECORDED |
| View version | `MySQL 8.4.7` diberikan, tetapi ini versi DBMS dan bukan versi definisi view | PENDING |
| Refresh/SLA | Live standard view; tiada scheduled refresh. Freshness/consistency source tables dan partial-load behavior belum dibuktikan | PARTIAL |
| Result aggregate | 49 row; status/identity/length/duplicate aggregate diterima | ACCEPTED WITH CONDITION; 1 IC kosong akan diselesaikan team ODL |
| Grant evidence | `SELECT ON upnm.*` kepada account viewer | PARTIAL; read-only tetapi lebih luas daripada approved view |
| TLS evidence | Sesi baharu: TLS `1.3`, cipher `TLS_AES_256_GCM_SHA384` | PASS bagi capability/session; origin OneID UAT masih perlu direkod |
| Change/access ticket | `N/A` diberikan; waiver/acceptance daripada owner belum direkod | PENDING |
| Approved by | `Firdaus` | RECORDED; skop/authority approval perlu disahkan |

### 2.2 Semakan evidence teknikal — 22 Julai 2026

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
| GF-05 | View authoritative dan lengkap | Owner menyatakan live view sentiasa konsisten; jawapan akan direview. Partial-load mechanism belum mempunyai evidence | CTL, ODL | PROVISIONAL |
| GF-06 | Status code representation konsisten | Owner mengesahkan filter `IN ('2','4','5')`; snapshot 49 row semuanya menggunakan kod numeric `2` | Owner permintaan/ODL data owner | CONFIRMED |
| GF-07 | Identity uniqueness/stability | Duplicate=0 dan Matrik kosong=0; 1 IC kosong diterima sebagai remediation team ODL | ODL data owner | ACCEPTED CONDITION — `blank_ic=0` sebelum Preview/pilot |
| GF-08 | Field length compatible | Semua maximum length snapshot berada dalam had live OneID | ODL data owner | CONFIRMED |
| GF-09 | Profile authority UG vs ODL | Provisional: block dan semak manual jika profile berbeza | Business/data owner | PROVISIONAL ACCEPT |
| GF-10 | Multi-source provenance diputuskan | Provisional: owner setuju logical design; migration/schema masih perlu DBA review | OneID system owner/DBA | PROVISIONAL ACCEPT |
| GF-11 | Source failure/completeness policy | Fail-closed design tersedia; baseline/threshold per source belum ditetapkan | OneID system owner | PENDING |
| GF-12 | Network dan TLS diluluskan | TLSv1.3/AES-256-GCM berjaya pada sesi baharu; sesi awal tanpa TLS menunjukkan adapter mesti memaksa TLS. Client origin belum direkod | Infrastructure/security owner | CONDITIONAL PASS — sahkan dari OneID UAT pada Fasa 3 |
| GF-13 | Read-only grant dibuktikan | Provisional: Firdaus akan hadkan kepada view dan host OneID; perubahan belum dibuktikan | Firdaus | ACCEPTED CONDITION |
| GF-14 | Secret handling diluluskan | Password belum diberi; private-store requirement ditetapkan | Deployment/security owner | PENDING |
| GF-15 | Runtime baseline bersih | Audit dibenarkan; mismatch berasal daripada approved feature history termasuk operational sync dan Admin 2FA. Rebaseline belum diluluskan | OneID code owner | PENDING DISPOSITION |
| GF-16 | Pilot/rollback strategy diterima | Sync actions dipersetujui secara provisional; draft backup, Deactivate=0 dan correlation rollback tersedia | OneID/business owner | PROVISIONAL |
| GF-17 | Capacity/schedule/operations | Live view tiada refresh schedule; row baseline, source consistency, maintenance dan escalation belum ada | ODL/OneID operations | PENDING |
| GF-18 | Named final approver | Provisional: Firdaus diberi skop keseluruhan Gate F; jawatan dan approval muktamad masih perlu direkod | Project owner/Firdaus | PROVISIONAL |

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

## 6. Evidence yang perlu diminta tanpa password

Daripada team ODL/data owner:

1. DDL atau `DESCRIBE student_basic_info` yang diluluskan.
2. Definisi view/query yang membuktikan hanya kod `2`, `4`, `5` dipulangkan.
3. Penjelasan nilai sebenar `status_code`: kod angka, label atau kedua-duanya.
4. Row count mengikut status sebelum filter dan jumlah active view selepas filter.
5. Aggregate sahaja bagi null/blank, duplicate Matrik, duplicate Matrik+IC,
   Matrik dengan beberapa IC dan IC dengan beberapa Matrik.
6. Maximum length bagi setiap field tanpa menghantar raw PII.
7. Freshness/SLA, waktu refresh dan signal snapshot complete/partial.
8. Named data owner, DBA dan escalation contact.
9. Bukti grant read-only yang disanitasi.
10. Keputusan TLS dan network path.

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
