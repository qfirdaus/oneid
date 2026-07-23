# ODL Gate F — Blocker Closure Pack

**Tarikh:** 23 Julai 2026
**Tujuan:** Mengumpul bukti minimum untuk menutup baki blocker Gate F tanpa
menyimpan password atau data peribadi mentah dalam Git.

Semua output yang dilampirkan mesti mempunyai tarikh, environment, sumber
pelaksanaan dan nama/jawatan pengesah. Padam username, host dalaman atau metadata
lain jika polisi organisasi mengklasifikasikannya sebagai rahsia.

## 1. Data quality — IC kosong

Team ODL membetulkan rekod pada sistem sumber. Selepas pembetulan, jalankan:

```sql
SELECT
    COUNT(*) AS total_rows,
    SUM(no_matrik IS NULL OR TRIM(no_matrik) = '') AS blank_matrik,
    SUM(no_kad_pengenalan IS NULL OR TRIM(no_kad_pengenalan) = '') AS blank_ic,
    SUM(emel_alfateh IS NULL OR TRIM(emel_alfateh) = '') AS blank_email
FROM student_basic_info;
```

Acceptance:

```text
blank_matrik = 0
blank_ic = 0
```

Jangan lampirkan row pelajar atau nilai IC. Aggregate sahaja mencukupi.

## 2. Least-privilege database grant

DBA hendaklah menggantikan `<oneid_uat_source_ip>` dengan IP sumber sebenar
server OneID UAT. Nama account di bawah ialah contoh dan bukan arahan untuk
mencipta account baharu jika account runtime sedia ada telah diluluskan.

```sql
REVOKE SELECT ON upnm.* FROM 'viewer'@'%';
GRANT SELECT ON upnm.student_basic_info
TO 'viewer'@'<oneid_uat_source_ip>';
```

Jika account `%` tidak lagi diperlukan oleh consumer lain, DBA boleh
menyahaktifkan atau membuangnya melalui change berasingan yang diluluskan.
Jangan lakukan tindakan tersebut hanya berdasarkan dokumen ini.

Evidence selepas perubahan:

```sql
SHOW GRANTS FOR 'viewer'@'<oneid_uat_source_ip>';
```

Acceptance:

- hanya `SELECT` kepada `upnm.student_basic_info`;
- tiada `INSERT`, `UPDATE`, `DELETE`, DDL atau privilege pentadbiran;
- host bukan `%`;
- change/access reference dan DBA approver direkodkan.

## 3. TLS dari OneID UAT

Jalankan dari host aplikasi OneID UAT menggunakan client dan credential runtime
yang diluluskan. Jangan masukkan password pada command line atau output.

Selepas connection diwujudkan:

```sql
SELECT
    @@hostname AS database_host,
    DATABASE() AS selected_database,
    CURRENT_USER() AS authenticated_account;

SHOW SESSION STATUS
WHERE Variable_name IN ('Ssl_version', 'Ssl_cipher');
```

Acceptance:

- execution origin disahkan sebagai host OneID UAT;
- `Ssl_version` sekurang-kurangnya `TLSv1.2`;
- `Ssl_cipher` tidak kosong;
- kegagalan TLS menyebabkan connection gagal, bukan fallback plaintext;
- timestamp dan infrastructure/security verifier direkodkan.

## 4. Completeness dan partial-load declaration

Data owner/DBA perlu menjawab dan mengesahkan perkara berikut:

```text
Publication model:
Adakah base table dikemas kini secara transaction/atomic publication?

Partial state:
Bolehkah student_basic_info dibaca ketika import/batch belum lengkap?

Completion signal:
Apakah signal yang membuktikan snapshot lengkap?

Safe window:
Apakah waktu yang diluluskan untuk OneID membaca snapshot?

Failure behaviour:
Apakah keadaan yang boleh menghasilkan zero row atau shrink luar biasa?

Escalation:
Siapa data owner dan operational contact apabila anomaly berlaku?
```

Acceptance:

- mekanisme lengkap/partial dinyatakan dengan jelas;
- baseline row count dan shrink threshold dipersetujui;
- empty, unavailable atau incomplete snapshot mesti block Apply;
- source outage tidak boleh ditafsirkan sebagai deactivation.

## 5. Polisi konflik profil dan provenance

Keputusan untuk ditandatangani oleh business/data owner dan OneID system owner:

```text
Identity:
No. Matrik + IC ialah identity match. Blank atau ambiguous identity diblock.

UG + ODL:
Satu akaun OneID boleh mempunyai lebih daripada satu source membership.

Profile conflict:
Jika UG dan ODL mempunyai nilai profil berbeza, tiada source precedence automatik.
Rekod diblock dan dihantar untuk semakan manual.

Activation:
Akaun kekal aktif jika sekurang-kurangnya satu student source authoritative
masih aktif.

Provenance:
Membership dan event history menyimpan source code, first_seen_at, last_seen_at,
status, correlation ID dan sebab perubahan. Password dan raw snapshot PII tidak
disimpan dalam provenance event.
```

Acceptance:

- keputusan bukan lagi bertanda provisional;
- retention period dan owner data provenance ditetapkan;
- schema migration masih memerlukan review/approval berasingan.

## 6. Gate F dan change/access approval

Rekod approval minimum:

```text
Change/access reference:
Environment: UAT
Scope: ODL read-only feasibility / connection / adapter phase
Business owner:
Data owner:
OneID system owner:
DBA:
Infrastructure/security owner:
Final approver:
Decision: PROCEED | PROCEED WITH CONDITIONS | REWORK | DO NOT PROCEED
Conditions:
Approved at:
Evidence references:
```

Kelulusan Gate F hanya membenarkan fasa yang dinyatakan secara eksplisit. Ia
tidak membenarkan Pilot Apply atau Full Apply secara automatik.

## 7. Local code baseline

Pada 23 Julai 2026, characterization yang lapuk telah diselaraskan dengan
quarantine SKP dan runtime semasa:

```text
lib/Database.php
sha256 71b51b7a9443bc3b83361be8b80c2ea464694af5454bbb38bfb80ad6ab3a1cce

lib/q_func.php
sha256 9f53ef34248c9a8f93f26757d75b4fa1563882b8b4a59a785637c7de8e1d2ee9
```

Hasil regression berkaitan sync:

```text
10 suites
219 checks
0 failures
```

Commit/reference perubahan perlu direkod selepas perubahan ini diluluskan dan
diterbitkan. Sebarang perubahan seterusnya kepada dua fail runtime tersebut
memerlukan re-audit; hash tidak boleh dikemas kini secara mekanikal semata-mata.
