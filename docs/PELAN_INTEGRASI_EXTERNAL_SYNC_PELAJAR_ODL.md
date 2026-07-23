# Pelan Integrasi External Data Sync Pelajar ODL

**Status:** Gate F `PROCEED WITH CONDITIONS`; Fasa 0–7 `PASS / CLOSED`; Fasa 8 `NOT AUTHORIZED`

**Tarikh asal:** 21 Julai 2026

**Semakan feasibility:** 22 Julai 2026

**Skop:** OneID UAT, external student sync dan source provenance

**Prinsip:** Semua pelajar kekal dalam kategori `Pelajar`; jenis pengajian dibezakan melalui sumber data, bukan kategori pengguna.

> **Notis kawalan:** Dokumen ini merekod pilihan reka bentuk, risiko, soalan dan
> kemungkinan untuk dinilai. Ia bukan implementation specification yang telah
> diluluskan, change request, arahan sambungan datasource, atau kebenaran untuk
> migration, backfill, coding, deployment, Preview terhadap data sebenar atau
> Apply. Semua kandungan boleh berubah selepas keperluan disahkan.

## 1. Tujuan

Dokumen ini menilai kebolehlaksanaan reka bentuk dan kemungkinan pelan
pelaksanaan untuk menambah pangkalan data pelajar Open Distance Learning (ODL)
sebagai candidate external data source OneID. Statusnya sebagai source
authoritative masih perlu disahkan.

Pelaksanaan mesti:

- mengekalkan `u_category = 10` untuk undergraduate, ODL dan postgraduate;
- membezakan asal rekod melalui source provenance yang berstruktur;
- mengekalkan kontrak sync staf dan undergraduate yang telah diuji;
- tidak menggunakan `data1` hingga `data12`, note bebas atau kategori pengguna
  untuk menyimpan metadata sumber;
- tidak menulis ke pangkalan data external;
- tidak menganggap source outage atau query failure sebagai ketiadaan pelajar;
- memperkenalkan perubahan secara additive, dormant dan berfasa;
- menggunakan Preview, approval, transaction, lock, reconciliation dan rollback
  sebelum sebarang Full Apply.

Dokumen ini masih pada peringkat feasibility study. Ia tidak memberikan
kebenaran untuk menjalankan migration, menyimpan credential, menghubungi
datasource, memulakan implementation atau mengaktifkan Preview/Apply. Sebelum
bergerak ke pelaksanaan, owner dokumen mesti mengesahkan bahawa keperluan,
keputusan reka bentuk, risiko, dependency, data ownership dan acceptance gates
telah lengkap serta berada dalam keadaan `in order`.

## 2. Hipotesis dan keputusan reka bentuk untuk pengesahan

Nilai dalam seksyen ini ialah working assumptions untuk feasibility assessment.
Ia belum dianggap keputusan muktamad sehingga direkod sebagai diluluskan oleh
business owner, data owner, system owner dan security/infrastructure owner yang
berkaitan.

Kategori dan sumber ialah dua konsep berasingan:

| Konsep | Nilai |
|---|---|
| Kategori semua pelajar | `user_tbl.u_category = 10` (`Pelajar`) |
| Sumber undergraduate sedia ada | `STUDENT_UG` |
| Sumber ODL baharu | `STUDENT_ODL_PG` |
| Sumber postgraduate masa hadapan | `STUDENT_PG` |
| Sumber staf sedia ada | `STAFF_EHRM` |
| Akaun daripada integrasi | `account_source = external` |
| Akaun manual | `account_source = manual` dan tiada source membership external |

`STUDENT_PG` ialah reka bentuk masa hadapan. Ia tidak perlu diaktifkan atau
diwujudkan dalam production semasa pelaksanaan ODL.

Source code tidak boleh disimpan dalam note atau `data8` hingga `data12` kerana
medan tersebut tidak mempunyai integrity constraint, mudah diubah, dan tidak
boleh mewakili seorang pengguna yang hadir dalam beberapa sumber serentak.

## 3. Baseline external sync semasa

### 3.1 Sumber aktif semasa

Flow semasa mendapatkan satu snapshot gabungan daripada:

1. staf aktif melalui sumber EHRM/ODBC; dan
2. pelajar aktif semasa melalui sumber pelajar/ODBC.

Entry point source semasa ialah `EXTERNAL_DATA_SOURCE_GET_ALL_USER()` dalam
`lib/external_data_source_API.php`. Data kemudian dinormalisasi, dirancang dan
diproses sebagai `NEW`, `UPDATE`, `REACTIVATE` atau `DEACTIVATE`.

### 3.2 Kontrak pelajar semasa yang mesti dikekalkan

| Medan canonical | Makna pelajar |
|---|---|
| `data1` | Nama |
| `data2` | No. Kad Pengenalan/pasport |
| `data3` | Kosong |
| `data4` | No. Matrik |
| `data5` | E-mel |
| `data6` | PTJ/fakulti |
| `data7` | Program |
| `data8`–`data12` | Kosong |
| `ext_data_source_category` | `Pelajar` |
| `u_id` | No. Matrik (`data4`) |
| `u_category` | `10` |

Matching pelajar menggunakan gabungan No. Matrik dan IC:

```text
external.data4 + external.data2
=
user_tbl.u_id + user_tbl.data2
```

`data2` dan `data4` dinormalisasi kepada representasi tanpa ruang dan sengkang.
Perubahan profil dibandingkan pada `data1` hingga `data7`. Hash sync meliputi
`data1` hingga `data12` serta external category.

### 3.3 Perlindungan semasa yang tidak boleh dibuang

- Akaun manual dengan `sync_protected = 1` tidak boleh ditimpa atau dinyahaktif.
- Identity collision dengan akaun manual dilindungi mesti diblock.
- Preview tidak boleh membuat mutation.
- Apply mesti menggunakan single-run lock, transaction dan rollback.
- Approval mesti terikat kepada plan/snapshot dan mempunyai tempoh luput.
- Source completeness, invalid row dan blast-radius thresholds mesti diperiksa.
- Planned, executed dan audited counts mesti direconcile.
- Audit projection tidak boleh membocorkan IC, e-mel atau raw external row.
- Existing exclusion policy dan category mapping mesti kekal serasi.

### 3.4 Batasan baseline

Model semasa hanya membezakan akaun `manual` dan `external`; ia belum menyimpan
datasource asal bagi akaun external. Planner juga menilai active users terhadap
satu snapshot gabungan. Oleh itu, snapshot ODL tidak boleh dihantar secara
berasingan kepada planner semasa kerana pengguna dari sumber lain boleh dianggap
hilang dan dicadangkan untuk deactivation.

## 4. Datasource ODL

### 4.1 Maklumat sambungan

| Perkara | Nilai |
|---|---|
| Host | `172.16.2.224` |
| Port | `3308` |
| DBMS | MySQL `8.4.7` |
| Database | `upnm` |
| Username | Private configuration — nilai sebenar tidak direkod dalam dokumen |
| Password | Tidak direkod dalam dokumen atau repository |
| View dibenarkan | `student_basic_info` |
| Access | Evidence semasa: `SELECT ON upnm.*`; read-only tetapi belum terhad kepada view sahaja |

Credential sebenar mesti disimpan melalui environment variable atau
`.private/runtime.php`. Credential, DSN penuh dan connection error terperinci
tidak boleh direkod ke Git, UI atau application log.

### 4.2 Schema view yang diberikan

| Field | Type | Nullable |
|---|---|---|
| `nama` | `varchar(255)` | Ya |
| `no_kad_pengenalan` | `varchar(255)` | Ya |
| `no_matrik` | `varchar(255)` | Ya |
| `emel_alfateh` | `varchar(255)` | Ya |
| `program` | `varchar(255)` | Ya |
| `fakulti` | `varchar(255)` | Ya |
| `status_code` | `varchar(255)` | Tidak; default `Pending` |
| `status_description` | `varchar(100)` | Ya |

Walaupun `status_code` tidak nullable, default teks `Pending` tidak selari dengan
domain kod angka `1` hingga `6`. Contract sebenar mesti menetapkan bahawa nilai
yang dihantar kepada OneID menggunakan representasi kod yang konsisten. Field
identity dan profile yang nullable juga bermaksud kelulusan schema sahaja tidak
membuktikan kualiti atau keunikan identity.

### 4.2.1 Keputusan populasi active view ODL

Keputusan owner pada 22 Julai 2026 menetapkan hanya status berikut berada dalam
active view yang dibaca oleh OneID:

| Kod | Status external | Makna kepada OneID |
|---:|---|---|
| `2` | `Active` | Rekod hadir; akaun kekal atau menjadi aktif |
| `4` | `Suspended` | Rekod hadir; akaun kekal aktif |
| `5` | `Deferred` | Rekod hadir; akaun kekal aktif |

Status berikut tidak berada dalam active view:

| Kod | Status external | Kesan kepada sync |
|---:|---|---|
| `1` | `Pending` | Tidak diwujudkan; jika sebelumnya aktif, menjadi calon deactivation apabila hilang daripada view |
| `3` | `Inactive` | Menjadi calon deactivation apabila hilang daripada view |
| `6` | `Withdrawn` | Menjadi calon deactivation apabila hilang daripada view |

Polisi ini mengikuti kontrak sync pelajar OneID semasa: datasource menentukan
populasi `AKTIF`, manakala OneID menganggap setiap row yang hadir sebagai aktif.
Sync semasa turut mengekalkan pelajar `GANTUNG PENGAJIAN` dan
`TANGGUH PENGAJIAN` dalam populasi aktif. OneID tidak memetakan enam status
external terus kepada `user_tbl.avail_status`.

Active view ODL mesti melaksanakan eligibility rule yang setara dengan:

```sql
WHERE status_code IN ('2', '4', '5')
```

Jika source menyimpan label dan bukan kod, normalisasi perlu dilakukan oleh
sistem ODL sebelum row dipaparkan kepada OneID. Empty, unknown atau conflicting
status tidak boleh dimasukkan ke active view.

### 4.3 Mapping ODL kepada kontrak OneID

| External ODL | Canonical sync | OneID |
|---|---|---|
| `nama` | `data1` | `user_tbl.data1` |
| `no_kad_pengenalan` | `data2` | `user_tbl.data2` |
| Tiada | `data3 = ''` | `user_tbl.data3` |
| `no_matrik` | `data4` | `user_tbl.data4` dan `u_id` |
| `emel_alfateh` | `data5` | `user_tbl.data5` |
| `fakulti` | `data6` | `user_tbl.data6` |
| `program` | `data7` | `user_tbl.data7` |
| Tiada | `data8`–`data12 = ''` | `user_tbl.data8`–`data12` |
| Nilai sistem | `ext_data_source_category = Pelajar` | `u_category = 10` |
| Nilai sistem | `source_code = STUDENT_ODL_PG` | Source provenance |
| `status_code` | Validasi eligibility active view | Tidak disalin terus ke `user_tbl.avail_status` |
| `status_description` | Tidak diperlukan untuk mutation | Tidak disimpan sebagai profile OneID |

Contoh query tetap read-only:

```sql
SELECT
    COALESCE(`nama`, '') AS data1,
    COALESCE(`no_kad_pengenalan`, '') AS data2,
    '' AS data3,
    COALESCE(`no_matrik`, '') AS data4,
    COALESCE(`emel_alfateh`, '') AS data5,
    COALESCE(`fakulti`, '') AS data6,
    COALESCE(`program`, '') AS data7,
    '' AS data8,
    '' AS data9,
    '' AS data10,
    '' AS data11,
    '' AS data12,
    'Pelajar' AS ext_data_source_category,
    `status_code` AS external_status_code
FROM `student_basic_info`
WHERE `status_code` IN ('2', '4', '5')
```

`source_code` hendaklah ditambah oleh adapter aplikasi, bukan bergantung pada
nilai dalam view. Klausa status dalam contoh ialah defense-in-depth; active view
ODL sendiri tetap bertanggungjawab memulangkan hanya kod `2`, `4` dan `5`.

## 5. Model source provenance sasaran

Model current-state dalam seksyen ini telah dilaksanakan oleh Fasa 1. Migration
canonical ialah `20260723_odl_f1_provenance_up.sql`; type, collation, engine dan
foreign key telah disahkan terhadap schema live. Snapshot/event history kekal
untuk fasa source-aware yang berasingan.

### 5.1 Jadual `external_source`

Registry ini mengelakkan source code bebas dan menjadi parent kepada setiap
source membership.

Cadangan logical schema:

```sql
CREATE TABLE external_source (
    source_code VARCHAR(50) NOT NULL,
    source_name VARCHAR(100) NOT NULL,
    source_family VARCHAR(30) NOT NULL,
    lifecycle_state VARCHAR(16) NOT NULL DEFAULT 'dormant',
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    avail_status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (source_code)
);
```

Proposed initial registry:

| `source_code` | `source_name` | `source_family` |
|---|---|---|
| `STAFF_EHRM` | Staff EHRM | `staff` |
| `STUDENT_UG` | Undergraduate Student | `student` |
| `STUDENT_ODL_PG` | ODL Postgraduate Student | `student` |

Nama `STUDENT_UG` mesti disahkan dengan owner sumber semasa. Jika view semasa
sebenarnya merangkumi lebih daripada undergraduate, source code perlu dinamakan
mengikut sistem/view authoritative dan bukan andaian program.

### 5.2 Jadual `user_external_identity`

Jadual ini membolehkan satu akaun OneID hadir dalam satu atau lebih sumber.

Cadangan logical schema:

```sql
CREATE TABLE user_external_identity (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    u_id VARCHAR(20) NOT NULL,
    source_code VARCHAR(50) NOT NULL,
    external_user_id VARCHAR(20) NOT NULL,
    source_active TINYINT(1) NOT NULL DEFAULT 1,
    source_hash CHAR(64) NULL,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    last_sync_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_source_external_identity (source_code, external_user_id),
    UNIQUE KEY uq_user_source (u_id, source_code),
    KEY idx_user_source_active (u_id, source_active),
    CONSTRAINT fk_external_identity_user
        FOREIGN KEY (u_id) REFERENCES user_tbl(u_id),
    CONSTRAINT fk_external_identity_source
        FOREIGN KEY (source_code) REFERENCES external_source(source_code)
);
```

Jenis dan panjang `u_id` mesti diselaraskan dengan schema sebenar `user_tbl`
sebelum migration ditulis. Foreign key kepada `user_tbl` hanya boleh ditambah
selepas engine, collation dan type compatibility disahkan.

### 5.3 Makna medan

- `external_user_id`: No. Matrik yang digunakan oleh datasource tersebut.
- `source_active`: rekod hadir dalam snapshot source terakhir yang diterima.
- `first_seen_at`: kali pertama hubungan source dikesan.
- `last_seen_at`: kali terakhir rekod hadir dalam snapshot source yang lengkap.
- `last_sync_at`: masa membership terakhir diproses.
- `source_hash`: hash canonical row bagi source tersebut.

Source outage tidak boleh menukar `source_active` kepada `0`. Status hanya boleh
berubah selepas snapshot source yang lengkap dan diterima oleh safety policy.

### 5.4 Mengapa bukan satu kolum pada `user_tbl`

Satu `external_source_code` pada `user_tbl` tidak boleh mewakili pelajar yang
hadir serentak dalam UG dan ODL, atau sejarah UG ke postgraduate. Jadual hubungan
membolehkan satu akaun kekal tunggal sementara semua memberships diaudit.

### 5.5 Snapshot dan sejarah membership

`user_external_identity` hanya mewakili current state. Ia tidak mencukupi dengan
sendirinya untuk membuktikan snapshot mana yang menyebabkan sesuatu membership
ditambah, diaktifkan atau dinyahaktifkan. Feasibility study mesti menilai sama
ada perlu menambah model immutable seperti:

- `external_source_snapshot` untuk source, status fetch, row count, digest,
  generated/fetched time, baseline, correlation ID dan blocking decision; dan
- `user_external_identity_event` untuk before/after state, action, snapshot ID,
  correlation ID, actor/trigger dan timestamp.

Alternatif menggunakan audit framework sedia ada hanya boleh diterima jika ia
boleh menyimpan hubungan `snapshot_id` dan `correlation_id`, memenuhi retention,
dan membolehkan reconciliation tanpa raw PII. Current-state row tidak boleh
digunakan sebagai pengganti immutable event history.

### 5.6 Lifecycle source

Setiap source perlu mempunyai lifecycle yang eksplisit, sekurang-kurangnya:

```text
dormant | shadow | mandatory | optional | disabled | retired
```

`avail_status` sahaja belum menentukan kesan operational. Polisi mesti menjawab:

- source mana wajib hadir bagi setiap mode;
- sama ada kegagalan optional source membenarkan Apply terhad;
- apa berlaku kepada membership terakhir apabila source disabled atau retired;
- siapa boleh mengubah lifecycle dan bagaimana perubahan diaudit; dan
- bila baseline source dianggap luput atau perlu dibina semula.

Mematikan feature gate atau menghentikan source tidak boleh secara automatik
menandakan semua memberships sebagai inactive.

## 6. Polisi identity dan data quality

### 6.1 Medan wajib

Untuk row ODL yang layak dirancang:

- `data4`/No. Matrik mesti ada;
- `data2`/No. Kad Pengenalan atau pasport mesti ada;
- kedua-dua medan mestilah scalar dan dalam panjang yang diterima OneID;
- selepas normalisasi, kedua-duanya masih tidak boleh kosong.

Row tanpa salah satu identity tidak boleh menjadi `NEW`. Ia mesti dikeluarkan
daripada plan, dikira sebagai invalid dan tertakluk kepada invalid threshold.

### 6.2 Normalisasi

No. Matrik dan IC/pasport mesti:

- ditukar kepada string;
- di-trim;
- dibuang ruang dan Unicode dash;
- dibanding menggunakan representasi canonical yang sama seperti pelajar semasa.

Nama, e-mel, fakulti dan program di-trim. Polisi case conversion tidak boleh
menukar data paparan tanpa keputusan owner.

### 6.3 Pemeriksaan snapshot

Preview data-quality mesti mengira sekurang-kurangnya:

- jumlah row;
- No. Matrik kosong;
- IC/pasport kosong;
- No. Matrik duplikat;
- gabungan Matrik + IC duplikat;
- satu Matrik dengan beberapa IC;
- satu IC dengan beberapa Matrik;
- collision dengan akaun manual protected;
- collision dengan sumber pelajar lain;
- e-mel kosong atau format tidak sah;
- nilai melebihi panjang kolum OneID;
- encoding atau nilai bukan scalar yang tidak boleh dinormalisasi.

PII penuh tidak boleh dipaparkan dalam Preview. Sample hendaklah menggunakan
digest atau identity yang dimask.

### 6.4 Evidence aggregate feasibility awal

Aggregate read-only `student_basic_info` pada 22 Julai 2026 menghasilkan:

| Metric | Hasil |
|---|---:|
| Total row | 49 |
| Active (`2`) | 49 |
| Suspended (`4`) | 0 |
| Deferred (`5`) | 0 |
| Invalid status | 0 |
| Matrik kosong | 0 |
| IC kosong | 1 |
| E-mel kosong | 0 |
| Duplicate Matrik groups | 0 |
| Duplicate Matrik + IC groups | 0 |

Semua maximum length yang diukur berada dalam had live OneID. Satu row tanpa IC
tidak memenuhi matching contract Matrik + IC. Owner menerima finding ini sebagai
remediation team ODL dengan syarat tiada pelajar tanpa IC; aggregate semula mesti
menunjukkan `blank_ic = 0` sebelum Preview data sebenar atau pilot. Evidence asal
`blank_ic = 1` dikekalkan untuk integriti audit.

Account runtime tidak mempunyai privilege `SHOW VIEW`; ini tidak diperlukan
untuk operasi `SELECT`. Pada 22 Julai 2026, owner mengesahkan view menapis
`status_code IN ('2','4','5')`; pengesahan ini bersama aggregate numeric semasa
diterima sebagai evidence status contract. Definisi DBA masih diperlukan hanya
untuk configuration/version evidence, bukan sebagai runtime privilege.

## 7. Polisi multi-source dan collision

### 7.1 Kehadiran dalam beberapa sumber

Seorang pengguna boleh mempunyai beberapa memberships:

| `u_id` | Source | Status |
|---|---|---|
| `123456` | `STUDENT_UG` | Tidak aktif |
| `123456` | `STUDENT_ODL_PG` | Aktif |
| `123456` | `STUDENT_PG` | Aktif pada masa hadapan |

Akaun OneID kekal satu dan masih menggunakan kategori Pelajar.

### 7.2 Polisi aktivasi

Untuk source family `student`:

```text
Sekurang-kurangnya satu student membership aktif
=> akaun OneID mesti kekal aktif

Semua student memberships tidak aktif
=> akaun layak dicadangkan untuk deactivation
```

Kelayakan tidak bermaksud deactivation automatik. Ia masih tertakluk kepada
Preview, approval, threshold, protection dan reconciliation.

### 7.2.1 Lifecycle status ODL dalam sync

OneID mengesan lifecycle melalui presence dalam active view, bukan dengan
menulis kod status external terus ke akaun:

| Perubahan external | Presence active view | Tindakan OneID yang layak dirancang |
|---|---|---|
| Tiada rekod → `2`, `4` atau `5` | Muncul | `NEW` jika Matrik belum pernah wujud |
| Kekal `2`, `4` atau `5` | Kekal | Tiada tindakan atau `UPDATE` profile |
| `2`, `4` atau `5` → `1`, `3` atau `6` | Hilang | `DEACTIVATE` selepas semua safety gate |
| `1`, `3` atau `6` → `2`, `4` atau `5` | Muncul semula | `REACTIVATE` bagi Matrik yang tidak aktif |

Deactivation tidak memadam pengguna; ia menukar `user_tbl.avail_status` kepada
`0`. Reactivation memerlukan No. Matrik yang stabil dan mengembalikan status
akaun kepada `1`. Pertukaran pengguna aktif kepada `Pending` mempunyai kesan
yang sama seperti keluar daripada active view dan oleh itu menjadi calon
deactivation. Keputusan ini mesti kekal kelihatan dalam Preview sebelum Apply.

### 7.3 Identity conflict

- Matrik dan IC sama dalam beberapa sumber: boleh menjadi satu akaun dengan
  beberapa memberships.
- Matrik sama tetapi IC berbeza: block dan perlukan semakan owner.
- IC sama tetapi Matrik berbeza: block kecuali terdapat polisi rasmi multiple
  matric identities.
- Collision dengan akaun manual protected: block seperti baseline semasa.

### 7.4 Authoritative profile

Source membership dan pemilihan data profil ialah keputusan berasingan. Default
selamat ialah:

1. jika hanya satu student source aktif, source itu menjadi calon profil;
2. jika beberapa source aktif dan canonical profile sama, teruskan;
3. jika beberapa source aktif tetapi identity atau profile bercanggah, jangan
   memilih pemenang secara senyap; Preview mesti melaporkan collision;
4. precedence tetap, jika diperlukan, mesti diluluskan oleh business/data owner.

Tiada precedence `PG > ODL > UG` ditetapkan oleh dokumen ini.

Sebelum implementation, conflict mesti diklasifikasikan sekurang-kurangnya
kepada:

- **identity conflict** — Matrik/IC tidak konsisten dan mesti block;
- **profile conflict** — nama, e-mel, fakulti atau program berbeza dan boleh
  menjadi block atau warning mengikut keputusan owner;
- **empty versus populated** — polisi sama ada nilai populated boleh melengkapkan
  nilai kosong daripada source lain; dan
- **display-only difference** — beza case, spacing atau Unicode yang tidak patut
  mencipta false collision.

Owner perlu menetapkan authority pada per-field, bukan semata-mata satu source
untuk keseluruhan profil. Tiada pemenang atau merge rule boleh diinfer oleh code.

## 8. Source-aware completeness dan deactivation safety

### 8.1 Snapshot status per source

Aggregator mesti menerima hasil terstruktur, bukan hanya satu array tanpa asal:

```text
STAFF_EHRM  : success | failed, row count, snapshot digest
STUDENT_UG  : success | failed, row count, snapshot digest
STUDENT_ODL_PG : success | failed, row count, snapshot digest
```

Jika mana-mana source wajib gagal, Apply keseluruhan mesti block. Source failure
tidak boleh ditukar menjadi empty array dan tidak boleh menandakan memberships
sebagai inactive.

Istilah `authoritative` bagi ODL masih provisional. Sebelum ia boleh digunakan
untuk activation atau deactivation, data owner mesti mengesahkan bahawa view
tersebut mempunyai skop pelajar aktif yang lengkap, freshness yang diketahui,
cut-off yang konsisten dan proses pembetulan data yang dimiliki. Sehingga itu,
ODL hanya dianggap candidate external source untuk kajian read-only.

### 8.1.1 Fingerprint snapshot deterministik

Fingerprint yang mengikat Preview dan approval mesti mempunyai spesifikasi yang
boleh diulang, termasuk:

- versi canonicalization dan senarai medan;
- normalisasi null, string, whitespace, dash dan Unicode;
- stable row identity dan susunan row sebelum hashing;
- pengendalian duplicate dan invalid row;
- algoritma digest dan version identifier; dan
- source code, query/view version, row count serta snapshot time yang termasuk
  dalam fingerprint.

Query yang mengembalikan row sama dalam susunan berbeza tidak boleh membatalkan
approval secara palsu. Sebaliknya, perubahan data material mesti sentiasa
menghasilkan fingerprint berbeza.

### 8.2 Metrics

Safety metrics sasaran:

```text
staff_rows
student_ug_rows
student_odl_rows
student_pg_rows
student_total_rows
previous_rows_by_source
source_shrink_percent_by_source
invalid_percent_by_source
duplicate_count_by_source
collision_count_by_source
```

Dashboard boleh memaparkan hanya source yang telah didaftarkan dan aktif.

### 8.3 Blocking conditions

Apply mesti block apabila berlaku sekurang-kurangnya satu keadaan berikut:

- connection atau authentication source gagal;
- query gagal atau view tidak boleh dibaca;
- source wajib kosong tanpa baseline/approval khas;
- source shrink melebihi threshold;
- invalid identity melebihi threshold;
- duplicate atau identity conflict belum diselesaikan;
- protected manual collision;
- source/category code tidak dikenali;
- deactivation melebihi threshold;
- authoritative baseline tidak tersedia;
- Preview luput atau snapshot/plan berubah selepas approval;
- planned, executed dan audited reconciliation tidak sepadan.

Threshold awal hendaklah fail-closed dan dikalibrasi menggunakan beberapa shadow
snapshot, bukan diteka sebelum data sebenar diaudit.

## 9. Adapter MySQL ODL read-only

ODL mesti menggunakan adapter khusus, contohnya `OdlStudentSource`, yang memenuhi
kontrak external source tanpa memasukkan PDO MySQL secara terus ke helper ODBC
legacy.

Tanggungjawab adapter:

- membaca konfigurasi private;
- menggunakan MySQL PDO dengan `utf8mb4`;
- menetapkan connection timeout yang munasabah;
- tidak menggunakan persistent connection;
- menggunakan exception mode;
- menjalankan hanya query tetap `SELECT` kepada `student_basic_info`;
- memetakan dan menormalisasi row kepada kontrak canonical;
- menambah `source_code = STUDENT_ODL_PG` dalam metadata aplikasi;
- menutup reference connection selepas fetch;
- memulangkan diagnostic code tersanitasi;
- tidak memulangkan empty snapshot apabila query sebenarnya gagal.

Cadangan konfigurasi:

```text
ONEID_ODL_MYSQL_HOST
ONEID_ODL_MYSQL_PORT
ONEID_ODL_MYSQL_DATABASE
ONEID_ODL_MYSQL_USERNAME
ONEID_ODL_MYSQL_PASSWORD
ONEID_ODL_MYSQL_CONNECT_TIMEOUT
```

Host, port dan database boleh mempunyai committed non-secret defaults hanya
selepas deployment owner bersetuju. Username dan password mesti private. Nama
view lebih selamat dikekalkan sebagai constant/allowlisted identifier dalam
adapter.

Defense in depth aplikasi tidak menggantikan database grant. Database owner
mesti mengesahkan user `viewer` hanya mempunyai `SELECT` kepada view yang
dibenarkan. Bukti `SHOW GRANTS FOR CURRENT_USER()` hendaklah direkod tanpa
credential.

## 10. Preview, approval dan audit

### 10.1 Preview

Preview mesti kekal read-only dan menunjukkan:

- status setiap datasource;
- row count mengikut source;
- `NEW`, `UPDATE`, `REACTIVATE`, `DEACTIVATE`;
- invalid, duplicate dan collision counts;
- source transitions atau multiple memberships;
- baseline dan shrink percentage;
- plan hash, generated time dan expiry;
- blocking codes dan warnings.

Sample mestilah digest/masked sahaja.

### 10.2 Approval

Approval sekali guna mesti terikat kepada:

- admin yang meluluskan;
- plan fingerprint;
- action counts;
- source counts dan source snapshot digests;
- accepted baseline per source;
- issued/expiry time;
- correlation ID.

Sebarang perubahan source selepas Preview membatalkan approval.

### 10.3 Audit dan reconciliation

Audit perlu dapat menerangkan:

- source yang melihat pengguna;
- membership yang ditambah, diaktifkan atau dinyahaktifkan;
- tindakan terhadap akaun OneID;
- profile fields yang berubah;
- actor, trigger, timestamp dan correlation ID.

Audit tidak boleh menyimpan password, DSN penuh, raw IC atau raw snapshot.
Reconciliation mesti membandingkan planned, executed, audited dan membership
state sebelum commit dianggap berjaya.

Mutation kepada `user_tbl`, source membership dan audit sebaiknya berada dalam
transaction boundary database yang sama. Jika audit atau event history berada
dalam datastore berlainan, design mesti mempunyai idempotency key, durable
outbox/recovery mechanism dan prosedur reconciliation bagi partial failure.
Correlation ID sahaja tidak menjamin atomicity.

## 11. Specific-user resync

Specific-user resync semasa memahami keluarga `staff` dan `student`. ODL tidak
boleh ditambah dengan cubaan rawak ke semua datasource.

Reka bentuk sasaran:

1. baca source memberships pengguna;
2. route lookup kepada source berkaitan;
3. lookup ODL menggunakan prepared statement pada No. Matrik;
4. gabungkan hasil mengikut polisi collision/authoritative profile;
5. Preview perubahan dalam bentuk masked;
6. Apply hanya selepas fingerprint dan row lock disahkan.

Contoh lookup:

```sql
SELECT ...
FROM `student_basic_info`
WHERE `No. Matrik` = ?
```

## 12. Strategi compatibility dan non-regression

Penambahan ODL ialah additive extension. Ia tidak boleh menggantikan mapping
atau behavior sedia ada sebelum parity dibuktikan.

### 12.1 Compatibility requirements

- Staf dan undergraduate menghasilkan plan yang sama apabila ODL disabled.
- `u_category = 10` kekal untuk semua pelajar.
- Existing hash behavior tidak berubah tanpa migration/rebaseline eksplisit.
- Manual protected accounts kekal di luar external mutation scope.
- Existing Preview, approval, pilot/full controls dan reconciliation kekal.
- ODL boleh dimatikan melalui feature/configuration gate tanpa menutup sync lama.
- Tiada perubahan kepada login, password lifecycle, SSO token atau ACL pelajar.
- Legacy runner dan production safe planner mesti diaudit; tiada satu path boleh
  menerima source metadata baharu sementara path lain salah mentafsirkannya.

### 12.2 Minimum regression matrix

| Senario | Expected |
|---|---|
| Staf matched/updated | Behavior baseline kekal |
| UG matched/updated | Behavior baseline kekal |
| UG deactivation dengan snapshot lengkap | Behavior baseline kekal |
| ODL baharu | Pelajar kategori 10 dan membership ODL |
| ODL update | Profil/membership direconcile |
| ODL reactivation | Akaun dan membership aktif semula |
| UG inactive, ODL active | Akaun kekal aktif |
| UG dan ODL aktif | Satu akaun, dua memberships |
| Matrik sama, IC berbeza | Block |
| IC sama, Matrik berbeza | Block |
| Manual protected collision | Block |
| ODL connection/query gagal | Apply block, tiada deactivation |
| ODL snapshot kosong | Apply block secara default |
| UG source gagal | Apply block, ODL/UG tidak disentuh |
| Preview | Zero mutation |
| Approval luput | Apply ditolak |
| Snapshot berubah | Apply ditolak |
| Reconciliation mismatch | Rollback |
| ODL feature disabled | Plan sama seperti baseline |

## 13. Fasa pelaksanaan

### Gate F — Penutupan feasibility study dan authorization to proceed

Gate F telah diluluskan sebagai `PROCEED WITH CONDITIONS` pada 23 Julai 2026.
Fasa 0 telah ditutup melalui baseline/characterization read-only. Fasa selepas
itu hanya boleh dimulakan mengikut exit gate dan authorization masing-masing.

Gate F telah dibuka pada 22 Julai 2026. Evidence, acceptance item dan keputusan
semasa direkod dalam
[`ODL_GATE_F_FEASIBILITY_REGISTER.md`](ODL_GATE_F_FEASIBILITY_REGISTER.md).
Keputusan semasa ialah `PROCEED WITH CONDITIONS`. Rujuk Gate register bagi skop
yang dibenarkan, accepted UAT conditions dan larangan Apply/production.

Minimum evidence Gate F:

- use case, in-scope population dan business outcome ODL disahkan;
- status `student_basic_info` sebagai active/complete source disahkan;
- data dictionary, row eligibility, identity dan profile authority diluluskan;
- pilihan source provenance, snapshot history dan retention diputuskan;
- source lifecycle, failure mode dan deactivation semantics diputuskan;
- security, privacy, network, TLS dan read-only access review selesai;
- impact kepada schema, runtime, capacity, operasi dan support dinilai;
- test, pilot, rollback, backup/restore dan observation strategy diterima;
- semua blocking question mempunyai jawapan atau documented disposition;
- named owner dan approver memberikan authorization to proceed melalui change
  record yang boleh diaudit.

Output feasibility hendaklah salah satu daripada:

```text
PROCEED
PROCEED WITH CONDITIONS
REWORK / MORE EVIDENCE REQUIRED
DO NOT PROCEED
```

Ketiadaan bantahan, kewujudan dokumen ini atau ketersediaan credential bukan
kelulusan tersirat. Jika hasil belum direkod sebagai `PROCEED` atau
`PROCEED WITH CONDITIONS`, semua kerja kekal pada analisis read-only tanpa
connection ke datasource sebenar.

Setiap fasa memerlukan change record, ujian, bukti exit gate dan rollback sendiri.
Fasa seterusnya tidak boleh dimulakan hanya kerana code telah ditulis; exit gate
mesti benar-benar lulus.

### Fasa 0 — Baseline dan characterization — `PASS / CLOSED`

Aktiviti:

- inventori kedua-dua production sync paths dan wiring sebenar;
- bekukan kontrak mapping, matching, safety dan persistence semasa;
- jalankan test suite/characterization semasa;
- rekod baseline Preview dan expected counts menggunakan fixture;
- pastikan quarantine SKP/IDMS tidak dianggap sebagai dependency sync;
- tiada external connection atau schema change.

Exit gate:

- semua characterization sync sedia ada lulus;
- source dan apply path sebenar dikenal pasti;
- baseline artifact boleh diulang.

Rollback: tiada runtime change.

Closure evidence:
[`ODL_FASA_0_BASELINE_DAN_CHARACTERIZATION_CLOSURE.md`](ODL_FASA_0_BASELINE_DAN_CHARACTERIZATION_CLOSURE.md).

### Fasa 1 — Schema provenance additive dan dormant — `PASS / CLOSED`

Aktiviti:

- sahkan schema sebenar `user_tbl`;
- sediakan migration up/down `external_source` dan `user_external_identity`;
- tambah indexes dan constraints yang serasi;
- register source secara dormant;
- jangan wire schema kepada planner/writer.

Exit gate:

- migration up/down lulus dalam isolated rehearsal;
- schema live contract lulus;
- sync baseline masih menghasilkan output sama;
- tiada membership atau user mutation.

Rollback: jalankan down migration hanya jika jadual masih dormant dan kosong.

Closure evidence:
[`ODL_FASA_1_SCHEMA_PROVENANCE_DORMANT_CLOSURE.md`](ODL_FASA_1_SCHEMA_PROVENANCE_DORMANT_CLOSURE.md).

### Fasa 2 — Backfill provenance sumber sedia ada — `PASS / CLOSED`

Aktiviti:

- sahkan makna sebenar sumber undergraduate semasa;
- Preview calon mapping `STAFF_EHRM` dan `STUDENT_UG`;
- exclude manual/protected accounts;
- laporkan ambiguous identities;
- backfill secara transaction dan batch terkawal;
- jangan ubah `u_category`, password, ACL atau `avail_status`.

Backfill tidak boleh menganggap semua pengguna kategori `Pelajar` berasal
daripada `STUDENT_UG`. Bukti sumber yang boleh diterima mesti ditetapkan terlebih
dahulu, contohnya match terhadap snapshot authoritative yang lengkap serta
identity yang tidak ambiguous. Inference berdasarkan kategori, format Matrik,
e-mel atau tarikh akaun sahaja tidak mencukupi. Calon tanpa bukti kekal
unclassified dan tidak ditulis.

Exit gate:

- setiap membership yang ditulis mempunyai bukti sumber;
- ambiguous rows tidak ditulis;
- counts direconcile;
- zero unintended user/profile/status mutation.

Rollback: padam hanya membership daripada correlation/change set backfill;
jangan padam atau deactivate pengguna.

Preview evidence:
[`ODL_FASA_2_PROVENANCE_BACKFILL_PREVIEW.md`](ODL_FASA_2_PROVENANCE_BACKFILL_PREVIEW.md).
Owner mengesahkan `STUDENT_UG` dan meluluskan exact Preview. Backfill
transactional selesai dengan 5,423 memberships, zero blocking identity finding,
29 profile-variant review groups yang tidak ditulis, dan zero user mutation.

### Fasa 3 — Adapter ODL read-only — `CLOSED`

Aktiviti:

- tambah private configuration contract;
- implement adapter MySQL dan fixed query;
- sahkan TLS/network requirement dengan infrastructure owner;
- sahkan `SHOW GRANTS` dan SELECT-only access;
- tambah unit/contract tests bagi mapping dan failure codes;
- jangan gabungkan adapter dengan Apply.

Fasa ini tidak boleh bermula hanya berdasarkan host/view yang diberikan.
Kebenaran connection test, network route, credential issue, TLS policy, data
classification dan named technical owner mesti diperoleh melalui change/access
process yang diluluskan.

Exit gate:

- connection dan fixed view read berjaya dalam environment diluluskan;
- grant read-only disahkan;
- credential tidak muncul dalam Git/log;
- failure tidak dipulangkan sebagai empty success;
- tiada mutation pada OneID atau external DB.

Rollback: disable/remove private configuration dan adapter wiring dormant.

Implementation evidence:
[`ODL_FASA_3_ADAPTER_READ_ONLY.md`](ODL_FASA_3_ADAPTER_READ_ONLY.md).
Adapter, private configuration contract dan unit/contract tests telah tersedia
secara dormant. Ia belum disambungkan kepada Preview, Apply atau scheduler.
Preflight WSL dan staging lulus dengan 53 row, zero blank matrik/IC, zero wrong
category/source dan zero mutation.

### Fasa 4 — ODL data-quality audit — `CLOSED`

Aktiviti:

- jalankan read-only snapshot audit;
- kira invalid, duplicate, collision dan length/encoding findings;
- bandingkan ODL dengan UG dan akaun manual protected;
- tentukan baseline row count awal;
- dapatkan keputusan owner bagi setiap conflict class.

Exit gate:

- semua identity conflicts mempunyai disposition;
- threshold invalid/shrink diluluskan;
- tiada raw PII dalam report;
- data owner mengesahkan view ialah active/authoritative scope yang betul.

Rollback: tiada mutation; hapus artifact sensitif mengikut retention policy.

Implementation evidence:
[`ODL_FASA_4_DATA_QUALITY_AUDIT.md`](ODL_FASA_4_DATA_QUALITY_AUDIT.md).
Baseline WSL dan staging ialah 53 row status aktif `2`, digest sepadan, zero
blocking/review findings, zero raw PII dan zero mutation.

### Fasa 5 — Source-aware planner dan safety — `CLOSED`

Aktiviti:

- bawa `source_code` dalam internal row envelope tanpa mengubah category;
- tambah source snapshot status dan baseline per source;
- implement membership planning;
- implement all-student-sources activation rule;
- block source outage/empty/anomaly;
- tambah ODL metrics dan blocking codes;
- pastikan legacy runner tidak salah menganggap ODL sebagai staf.

Exit gate:

- ODL outage tidak menghasilkan deactivation;
- UG outage tidak menghasilkan deactivation silang;
- multi-source fixtures lulus;
- full existing regression matrix lulus;
- planner kekal pure dan Preview zero-mutation.

Rollback: feature gate kembali kepada baseline aggregator/planner; schema kekal
dormant untuk audit.

Implementation evidence:
[`ODL_FASA_5_SOURCE_AWARE_PLANNER.md`](ODL_FASA_5_SOURCE_AWARE_PLANNER.md).
Planner kekal pure, dormant dan hanya menghasilkan safe Preview projection.
Semua outage/anomaly menghasilkan zero membership/account action.

### Fasa 6 — Shadow Preview ODL — `PASS / CLOSED`

Aktiviti:

- expose ODL dalam Preview sahaja;
- Apply ODL kekal disabled;
- jalankan beberapa snapshot pada waktu operasi berbeza;
- bandingkan counts, digests, shrink dan collision stability;
- review hasil bersama system/data owner.

Exit gate:

- minimum observation window dan bilangan snapshot ditetapkan owner;
- snapshots stabil atau variasi dapat dijelaskan;
- tiada blocking collision tertunggak;
- plan counts diluluskan untuk pilot.

Rollback: disable ODL Preview gate; tiada data pengguna perlu dipulihkan.

Implementation evidence:
[`ODL_FASA_6_SHADOW_PREVIEW.md`](ODL_FASA_6_SHADOW_PREVIEW.md).
Shadow Preview mempunyai endpoint/UI berasingan, aggregate-only response,
feature gate private dan tiada approval atau Apply capability. Tiga snapshot
staging dalam observation window UAT pendek adalah identik: Staff 1061, UG
5452, ODL 53, ODL candidate new 53, risk normal, zero blocking code dan zero
mutation. Firdaus, System Analyst/DBA meluluskan closure pada 23 Julai 2026
melalui evidence `ONEID-ODL-F6-20260723-01`.

### Fasa 7 — Controlled Pilot Apply — `PASS / CLOSED`

Aktiviti:

- backup OneID dan rehearsal restore;
- allowlist beberapa ODL identities yang diluluskan;
- tetapkan `Deactivate = 0`;
- guna fresh Preview, one-time approval dan transaction;
- sahkan membership, kategori 10, profil, login dan ACL;
- pantau audit/reconciliation selepas commit.

Exit gate:

- planned = executed = audited;
- semua pilot users boleh digunakan seperti Pelajar biasa;
- staf, UG, manual accounts dan unrelated users tidak berubah;
- rollback rehearsal terbukti.

Rollback: revert pilot change set berdasarkan correlation ID; jangan membuat
bulk delete atau broad deactivation.

Implementation-only authorization diterima pada 23 Julai 2026 untuk tepat tiga
pelajar ODL dan tindakan `NEW` sahaja. Exact-three private digest config,
NEW-only planner, transactional writer, provenance event migration, one-time
approval boundary, reconciliation dan targeted rollback telah dibina serta
lulus isolated rehearsal.

Pilot Preview dan one-shot Apply UAT kemudiannya diluluskan secara berasingan.
Plan `New=3` dan tindakan lain sifar telah menghasilkan tepat tiga users, tiga
membership `STUDENT_ODL_PG` dan tiga event di bawah correlation
`0763c8dd60a3cc21`. Independent reconciliation mengesahkan ketiga-tiganya
kategori 10 dan external account. Login/ACL smoke test turut disahkan `PASS`.
Firdaus, System Analyst/DBA meluluskan closure pada 23 Julai 2026 melalui
evidence `ONEID-ODL-F7-20260723-01`. Pilot Preview dan Apply dikembalikan kepada
disabled dan private allowlist dikosongkan; scheduler, Full Apply dan production
kekal tidak dibenarkan. Rujuk
[`ODL_FASA_7_CONTROLLED_PILOT_IMPLEMENTATION.md`](ODL_FASA_7_CONTROLLED_PILOT_IMPLEMENTATION.md).

### Fasa 8 — Controlled Full Apply — `NOT AUTHORIZED`

Cross-source isolation hardening mesti ditutup sebelum Fasa 8 diberi
authorization. Implementation dan aggregate-only Preview telah disediakan di
bawah `ONEID-SOURCE-ISOLATION-20260723-01`. UG kini source-bound pada read,
fresh-plan dan persistence; ODL kekal fixed-source; manual account kekal
protected. Staff Preview menghasilkan 1061 exact candidate memberships dengan
zero blocking finding dan zero mutation. Live registry/backfill `STAFF_HR`
masih belum dibenarkan, maka Staff provenance gate kekal `false`. Rujuk
[`CROSS_SOURCE_ISOLATION_HARDENING.md`](CROSS_SOURCE_ISOLATION_HARDENING.md).

Aktiviti:

- gunakan approved expected counts dan plan hash;
- ambil backup/restore point;
- jalankan full Apply dalam change window;
- reconcile user, membership, audit dan source totals;
- mulakan observation window.

Exit gate:

- output tepat dengan approved Preview;
- tiada unexplained collision/deactivation;
- non-regression checks lulus;
- owner menandatangani hasil observation.

Rollback: disable ODL source gate, restore targeted change set atau restore point
mengikut severity dan runbook yang telah diuji.

### Fasa 9 — Operational rollout — `NOT AUTHORIZED`

Aktiviti:

- tetapkan jadual sync dan ownership;
- simpan accepted baseline per source;
- alert untuk connection failure, empty source, shrink dan collision;
- dokumentasikan retry, incident, rollback dan credential rotation;
- tetapkan log retention dan PII policy;
- handoff dashboard/runbook kepada operator.

Exit gate:

- monitoring dan alert diuji;
- owner on-call dan escalation path tersedia;
- scheduled run kekal fail-closed.

### Fasa 10 — Template postgraduate — `NOT STARTED`

Aktiviti masa hadapan:

- daftar `STUDENT_PG` selepas datasource disahkan;
- reuse adapter contract, provenance, planner dan safety;
- lakukan data-quality audit dan shadow Preview baharu;
- kekalkan `u_category = 10`.

Penambahan PG tidak boleh dibuat hanya dengan menukar source code ODL; ia mesti
melalui gate yang sama mengikut risiko datasource baharu.

## 14. Rollback dan recovery principles

- Setiap perubahan mesti mempunyai migration/rollback yang terhad kepada change
  set dan correlation ID.
- Feature gate ODL mesti boleh dimatikan tanpa mematikan sync staf/UG.
- Source failure tidak boleh mencipta inactive memberships.
- Rollback ODL tidak boleh memadam pengguna yang masih aktif dalam UG/PG.
- Jangan gunakan broad `DELETE`, truncate atau recursive cleanup untuk rollback.
- Schema hanya dibuang selepas dependency dan retention audit.
- Backup dianggap sah hanya selepas restore rehearsal berjaya.
- Quarantined legacy SKP/IDMS endpoints tidak boleh dipulihkan sebagai jalan
  pintas untuk ODL.

## 15. Observability dan operasi

Minimum operational signals:

- connection/query success per source;
- duration dan row count per source;
- snapshot digest dan accepted baseline;
- invalid, duplicate dan collision counts;
- planned/executed/audited action counts;
- membership transitions;
- blocked reason code;
- correlation ID dan triggered-by identity.

Log mesti menggunakan error code tersanitasi, contohnya:

```text
ODL_CONNECTION_FAILED
ODL_QUERY_FAILED
ODL_EMPTY_SOURCE
ODL_SOURCE_SHRINK_EXCEEDED
ODL_INVALID_IDENTITY_THRESHOLD_EXCEEDED
ODL_IDENTITY_COLLISION
ODL_SNAPSHOT_CHANGED_AFTER_APPROVAL
```

## 16. Security acceptance criteria

- Database user ODL mempunyai `SELECT` sahaja kepada view yang diluluskan.
- OneID tidak menerima arbitrary SQL, database atau view name daripada request.
- Password tidak berada dalam repository, document, HTML, JavaScript atau log.
- Connection menggunakan network path dan encryption policy yang diluluskan.
- Preview dan audit mask identity sensitif.
- Apply memerlukan authorization, CSRF/session controls dan sync approval sedia
  ada; penambahan datasource tidak mewujudkan endpoint Apply baharu yang lemah.
- Error kepada UI tidak memaparkan host internals, SQL atau credential.
- Dependency/driver MySQL yang digunakan disahkan tersedia dan disokong.

## 17. Keputusan owner dan perkara yang masih diperlukan

Keputusan feasibility bagi population, identity, status, TLS, read-only access,
secret handling, provenance, failure threshold, ownership dan approval telah
direkod dalam Gate F register pada 23 Julai 2026. Senarai asal di bawah kekal
sebagai traceability; perkara implementasi/schema/pilot masih memerlukan exit
gate fasa masing-masing dan tidak dianggap diluluskan oleh Gate F.

Sebelum implementation melepasi fasa berkaitan, owner mesti mengesahkan:

1. Adakah sumber pelajar semasa benar-benar undergraduate sahaja dan sesuai
   dinamakan `STUDENT_UG`?
2. Adakah `student_basic_info` hanya mengandungi ODL aktif, atau turut mengandungi
   alumni/inactive/pending students?
3. Apakah source authoritative bagi nama, e-mel, fakulti dan program apabila UG
   dan ODL aktif serentak?
4. Adakah satu IC dibenarkan mempunyai beberapa No. Matrik?
5. Apakah threshold kosong/invalid/shrink ODL selepas baseline diperoleh?
6. Berapa lama shadow Preview perlu diperhatikan sebelum pilot?
7. Siapa data owner, system owner dan approver bagi ODL Full Apply?
8. Apakah jadual sync, maintenance window dan escalation path?
9. Adakah TLS wajib/disokong oleh MySQL endpoint dalaman tersebut?
10. Apakah retention period untuk membership history dan audit metadata?
11. Apakah business outcome sync: provisioning sahaja, profile refresh,
    activation/deactivation, atau kombinasi tertentu?
12. Baki definisi lifecycle di luar keputusan active view: adakah terdapat
    exception atau grace period bagi pertukaran kepada Pending, Inactive atau
    Withdrawn sebelum deactivation?
13. Apakah freshness/SLA view, waktu refresh dan cara mengenal snapshot separa?
14. Adakah `No. Matrik` kekal sepanjang hayat dan unik merentas UG/ODL/PG?
15. Field profil manakah authoritative bagi setiap source dan apakah polisi
    empty-versus-populated serta display-only difference?
16. Adakah profile conflict perlu block Apply atau hanya warning mengikut field?
17. Apakah lifecycle setiap source dan semantik bagi optional, disabled serta
    retired source?
18. Apakah event/snapshot history model dan retention yang memenuhi audit serta
    reconciliation?
19. Apakah spesifikasi canonical snapshot fingerprint yang diluluskan?
20. Adakah user, membership dan audit boleh committed dalam satu transaction?
    Jika tidak, apakah outbox/recovery design yang diterima?
21. Apakah bukti yang sah untuk backfill source sedia ada dan bagaimana
    unclassified/ambiguous account dikendalikan?
22. Siapa yang mempunyai kuasa akhir untuk merekod keputusan Gate F sebagai
    `PROCEED`, `PROCEED WITH CONDITIONS`, `REWORK` atau `DO NOT PROCEED`?

## 18. Dijangka terlibat semasa implementasi

Senarai ini ialah impact map awal, bukan arahan untuk mengubah semua fail:

- external source adapter dan aggregator;
- canonical external row normalizer/envelope;
- `SyncPlanner` dan source-aware membership planner;
- `SyncSafetyPolicy` dan Preview projection;
- sync policy/category mapper — kategori kekal `Pelajar`/10;
- safe orchestrator dan persistence contracts;
- database migrations dan provenance repository;
- specific-user resync routing;
- admin Preview/dashboard metrics;
- configuration/secrets example tanpa nilai credential;
- unit, characterization, integration, failure dan reconciliation tests;
- operational runbook dan rollback evidence.

Wiring production sebenar mesti ditentukan selepas Fasa 0 mengesahkan engine
yang aktif. Implementasi tidak boleh menganggap semua class dormant telah wired.

## 19. Definition of done keseluruhan

Integrasi ODL hanya dianggap lengkap apabila:

- ODL dibaca melalui connection read-only yang disahkan;
- semua ODL users kekal kategori Pelajar/10;
- source provenance boleh menjawab dari mana pengguna datang;
- satu pengguna boleh mempunyai beberapa student memberships;
- source outage tidak menyebabkan deactivation;
- all-student-sources activation rule dilaksanakan;
- collision tidak diselesaikan secara senyap;
- Preview, approval, Apply dan reconciliation lulus;
- existing staf/UG/manual/login/ACL behavior lulus regression;
- pilot, Full Apply, observation dan operational handoff selesai;
- rollback telah direhearsal dan mempunyai bukti.

Sehingga semua gate berkaitan lulus, ODL hendaklah kekal dalam mode dormant atau
read-only Preview dan tidak dibenarkan membuat mutation kepada akaun OneID.

## 20. Status feasibility dan change control

Pada semakan 23 Julai 2026:

| Perkara | Status |
|---|---|
| Business/data requirements | Disahkan bagi skop UAT read-only |
| Status eligibility active view | Diputuskan: hanya `2 Active`, `4 Suspended`, `5 Deferred` |
| Status dikecualikan daripada active view | Diputuskan: `1 Pending`, `3 Inactive`, `6 Withdrawn` |
| Active → Pending | Diputuskan: menjadi calon deactivation kerana Pending tidak berada dalam active view |
| Authority dan completeness view ODL | Disahkan: postgraduate diterima dan mempunyai No. Matrik; e-mel boleh tiba lewat |
| Identity/profile conflict policy | Disahkan: block dan semak manual; blank e-mel tidak memadam nilai sedia ada |
| Aggregate data-quality awal | Baseline diterima 53 row; blank Matrik=0 dan blank IC=0 |
| Field length compatibility | Disahkan bagi snapshot awal |
| Provenance dan immutable history design | Polisi `STUDENT_ODL_PG` dipersetujui; schema Fasa 1 dipasang dormant dengan zero membership |
| Source lifecycle/deactivation semantics | Status `1`, `3`, `6` keluar daripada view dan menjadi calon deactivation; tiada grace period |
| Security/network/TLS/read-only evidence | PASS UAT: origin `172.16.2.153`, TLSv1.3/AES-256-GCM, read-only; broad SELECT/`viewer@%` accepted UAT condition |
| Migration atau implementation approval | Fasa 1 selesai; setiap fasa seterusnya masih memerlukan exit gate |
| Connection kepada ODL datasource | Connection test read-only dibenarkan dan lulus |
| Preview menggunakan data ODL sebenar | PASS — tiga snapshot staging stabil, digest identik, zero blocking/mutation |
| Pilot atau Full Apply | Tidak dibenarkan |
| Gate F | `PROCEED WITH CONDITIONS` — diluluskan Firdaus, System Analyst/DBA |
| Fasa 0 | `PASS / CLOSED` |
| Fasa 1 | `PASS / CLOSED` — schema live dormant, zero membership |
| Fasa 2 | `PASS / CLOSED` — `STUDENT_UG` dormant, 5,423 memberships, zero user mutation |
| Fasa 3 | `PASS / CLOSED` — adapter read-only dan TLS preflight WSL/staging lulus |
| Fasa 4 | `PASS / CLOSED` — data-quality audit WSL/staging lulus |
| Fasa 5 | `PASS / CLOSED` — source-aware planner dan safety lulus |
| Fasa 6 | `PASS / CLOSED` — tiga snapshot staging stabil; `ONEID-ODL-F6-20260723-01` |
| Fasa seterusnya | Fasa 8 Controlled Full Apply — authorization baharu diperlukan |

Dokumen hendaklah dikemas kini apabila hasil siasatan atau keputusan owner
diterima. Setiap keputusan baru perlu merekod tarikh, owner/approver, evidence
reference, keputusan dan kesan kepada reka bentuk. Perubahan daripada
feasibility kepada implementation hanya berlaku selepas Gate F ditutup secara
eksplisit; menukar label status dokumen sahaja tidak mencukupi.
