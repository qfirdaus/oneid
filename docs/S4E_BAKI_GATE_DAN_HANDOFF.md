# S4E — Baki Gate dan Handoff Sebelum Controlled Apply

Tarikh handoff: 14 Julai 2026
Change owner: Pemilik sistem OneID
Rollback owner: Pemilik sistem OneID
Baseline sebelum handoff: `ea51e52`
Status: **NO-GO — APPLY KEKAL DISABLED**

## 1. Tujuan

Dokumen ini ialah titik sambung kerja sebelum S4E. Ia menerangkan evidence yang
masih diperlukan sebelum satu controlled Apply boleh dipertimbangkan. Dokumen
ini tidak memberi kebenaran untuk mengaktifkan feature flag, menambah UI Apply,
menjalankan scheduler atau menjalankan live sync.

## 2. Evidence Yang Sudah Lulus

| Item | Evidence |
| --- | --- |
| Release S4D | `68f10ff` |
| Pembetulan verifier CSRF | `ea51e52` |
| Regression | S1 39/39, S2 29/29, S3 26/26, S4A 16/16, S4B 19/19, S4C 15/15, S4D 21/21 |
| Admin preview/logout | 16/16 |
| Fresh preview | 6,485 source; 71 new; 7 update; 0 deactivate; 0 reactivate; protected 1; collision 0 |
| Plan | Hash prefix `6f0010cb7e6a`; approval ready; tiada Apply dibuat |
| SSO | `iqs-framework.local` end-to-end dan logout lulus |
| Public root | Smoke 10/10 |
| Backup | 73,881,422 bait; SHA-256 direkod di private evidence |
| Restore rehearsal | 15 jadual; exact row-count digest sama; rehearsal DB dibuang |
| External read | Staf 1,062 + pelajar 5,423 melalui SELECT sahaja |

## 3. Baki Gate Yang Menyekat S4E

| Gate | Owner | Status | Syarat lulus |
| --- | --- | --- | --- |
| DBA external credential | DBA | Pending | Bukti kedua-dua runtime login hanya mempunyai hak bacaan yang diperlukan |
| Scheduler inventory | Operations | Pending | Root, user, system cron, timer dan queue tiada job OneID sync aktif |
| Monitoring dan privasi log | Operations + Security reviewer | Pending | Log boleh dicapai, observation owner ditetapkan dan tiada raw PII/token/credential |
| Maintenance window | Change owner | Pending | Window, pilot admin, owners, freeze, komunikasi dan abort criteria direkod |

Walaupun empat gate utama di atas selesai, arahan **GO S4E** yang baharu masih
wajib. Tiada persetujuan tersirat daripada preview `approval_ready=yes`.

## 4. Gate DBA — External Credential Benar-benar Read-only

### Skop

- runtime staff membaca `ehrmdb.dbo.SSO_Staf_Aktif`;
- runtime student membaca `asisdb..v210_sso_student_aktif`;
- endpoint production ialah external source yang telah disahkan sebelum ini;
- password, token dan raw grant yang mengandungi identiti sensitif tidak boleh
  dimasukkan ke Git atau dokumen ini.

### Evidence yang diterima

DBA perlu memberikan salah satu evidence tersanitasi:

1. output grant/role bagi kedua-dua login yang menunjukkan hanya CONNECT dan
   SELECT pada objek yang diperlukan; atau
2. pengesahan bertulis DBA bahawa login tersebut tiada INSERT, UPDATE, DELETE,
   EXECUTE writer, CREATE, ALTER, DROP atau hak pentadbiran.

Jangan menguji read-only dengan menghantar INSERT/UPDATE/DELETE walaupun diikuti
ROLLBACK. Production external DB kekal untuk bacaan sahaja.

Application defense-in-depth berada di `lib/readonly_odbc.php`. Semua caller
staff/student aktif mesti melalui wrapper yang menerima tepat satu statement
bermula dengan `SELECT` dan menolak DML, DDL, privilege changes, stored
procedure execution, comment serta multi-statement sebelum ODBC dipanggil.
Semak polisi dengan:

```bash
php tools/s4e_external_readonly_policy_contract.php
php tools/s4d_external_readonly_evidence.php
```

Guard aplikasi tidak menggantikan database grant. Jika code regression atau
credential digunakan oleh proses lain, hanya privilege SELECT-only pada DB
yang menjadi sempadan terakhir. Oleh itu evidence DBA masih wajib.

### Rekod penutupan

```text
DBA owner:
Tarikh semakan:
Staff login grant evidence: PASS|FAIL
Student login grant evidence: PASS|FAIL
Write/DDL/admin privileges absent: YES|NO
Evidence location/ticket:
DBA decision: PASS|FAIL
```

Rekod semasa 15 Julai 2026: DBA mengesahkan kedua-dua runtime credential Staff
dan Student mempunyai CONNECT serta SELECT pada view diperlukan sahaja.
INSERT, UPDATE, DELETE, MERGE, CREATE, ALTER, DROP, TRUNCATE, writer EXECUTE,
GRANT/REVOKE, database-owner dan administrative/server role semuanya absent.
Keputusan gate ialah PASS; raw grant/credential evidence kekal di luar Git.

Gate berkaitan: `S4D-09` dan `S4-G19`.

## 5. Gate Scheduler — Sahkan Semua Sync Kekal Retired

Semakan ini read-only dan tidak mengaktifkan cron. Jalankan:

```bash
crontab -l 2>&1
sudo crontab -l 2>&1

sudo rg -n -i \
  'oneid|oneid-uat|run_sync|sync_user|admin_add_sync_user' \
  /etc/crontab /etc/cron.d /etc/systemd/system /lib/systemd/system

systemctl list-timers --all --no-pager
atq 2>&1
sudo atq 2>&1
```

Jika `rg` menemui unit atau job unrelated, rekod sebab ia bukan caller OneID.
Jangan delete atau disable job lain sebagai sebahagian audit ini.

### Syarat lulus

- tiada root/user/system cron menjalankan OneID sync;
- tiada systemd timer atau queued `at` job menjalankan OneID sync;
- tiada scheduler vendor/external yang diketahui memanggil endpoint Apply;
- evidence command, timestamp dan operations owner direkod.

### Rekod penutupan

```text
Operations owner:
Tarikh semakan:
User crontab: CLEAN|FINDING
Root crontab: CLEAN|FINDING
System cron/timers: CLEAN|FINDING
Queued jobs: CLEAN|FINDING
External/vendor scheduler confirmation:
Evidence location:
Decision: PASS|FAIL
```

Gate berkaitan: `S4D-08` dan `S4-G20`.

## 6. Gate Monitoring dan Privasi Log

### Log minimum

- `/var/log/nginx/oneid-r4.access.log`;
- `/var/log/nginx/oneid-r4.error.log`;
- PHP-FPM journal atau error log sebenar;
- application/sync marker yang digunakan semasa pilot.

Semakan awal yang selamat:

```bash
sudo test -r /var/log/nginx/oneid-r4.access.log && echo NGINX_ACCESS_READABLE
sudo test -r /var/log/nginx/oneid-r4.error.log && echo NGINX_ERROR_READABLE

sudo systemctl status php8.3-fpm --no-pager
sudo journalctl -u php8.3-fpm --since "30 minutes ago" --no-pager

sudo tail -n 100 /var/log/nginx/oneid-r4.error.log
```

Jangan salin raw log yang mempunyai PII atau token ke Git. Evidence hendaklah
berbentuk counts, timestamp, correlation ID, header ID atau keputusan redacted.

### Syarat lulus

- operations owner boleh membaca semua log semasa window;
- security reviewer mengesahkan response/log tidak mendedahkan password, OTP,
  cookie, `new_sso_cre`, approval ID, credential atau raw user payload;
- correlation ID, plan-hash prefix dan numeric header ID dibenarkan;
- observation window minimum 60 minit selepas satu pilot diterima;
- abort owner tahu cara mematikan flag dahulu jika error meningkat.

### Rekod penutupan

```text
Operations/observation owner:
Security reviewer:
Nginx access/error readable: YES|NO
PHP-FPM/application log readable: YES|NO
PII/token/credential review: PASS|FAIL
Observation duration accepted:
Alert/abort channel:
Evidence location:
Decision: PASS|FAIL
```

Gate berkaitan: `S4D-15`, `S4-G11` dan precondition `S4-G29`.

## 7. Gate Maintenance Window dan Ownership

Change owner perlu mengisi semua medan sebelum GO:

```text
Change ID:
Tarikh:
Window mula:
Window tamat:
Observation tamat:
Pilot admin:
Change owner: Pemilik sistem OneID
Rollback owner: Pemilik sistem OneID
DBA/on-call:
Operations/monitoring owner:
Security reviewer:
Communication/incident channel:
User/admin change freeze mula dan tamat:
Approved release commit:
Backup evidence:
Expected preview: source=6485 new=71 update=7 deactivate=0 reactivate=0
Maximum Apply requests: 1
```

Counts di atas hanyalah baseline 14 Julai 2026. Preview baharu wajib dijana
dalam maintenance window. Jika counts/hash/warning berubah, owner mesti menilai
semula dan tidak boleh menggunakan acceptance lama secara automatik.

### Abort/NO-GO criteria

- mana-mana gate masih pending atau gagal;
- preview warning, collision atau blocking code;
- deactivate/reactivate tidak dijangka;
- external source total berubah secara luar biasa;
- backup checksum tidak sah;
- lock/run lain aktif;
- log tidak boleh dibaca;
- working tree/deployed commit tidak sepadan;
- lebih daripada seorang admin atau lebih daripada satu Apply dirancang.

Gate berkaitan: `S4D-16` dan `S4-G23`.

## 8. Urutan Menyambung Kerja

### Investigation calon deactivation

Sebelum menerima sebarang count `Deactivate` bukan sifar, operator boleh
menjalankan tool CLI read-only berikut. Output default hanya membawa digest:

```bash
php tools/s4e_deactivation_investigation.php
```

Jika identiti perlu dilihat untuk semakan authorized, jalankan terus pada
terminal private dan ikut confirmation interaktif:

```bash
php tools/s4e_deactivation_investigation.php --reveal
```

Jangan salin output revealed ke Git, ticket, log atau chat. Tool tidak membuka
transaction, tidak menulis header/audit/user dan tidak memanggil Apply. Semak
contract dengan `php tools/s4e_deactivation_investigation_contract.php`.

1. Dapatkan evidence DBA tanpa menjalankan write test.
2. Lengkapkan inventory scheduler read-only.
3. Sahkan log path, privacy review, monitoring owner dan observation duration.
4. Tetapkan maintenance window, pilot admin dan semua owner.
5. Kemas kini `docs/S4D_PRE_PILOT_READINESS_REGISTER.tsv` dan
   `docs/S4_PILOT_GATE_REGISTER.tsv` dengan evidence sebenar.
6. Buat checkpoint Git khusus untuk penutupan gate.
7. Semak effective flags masih `false/disabled`.
8. Minta arahan **GO S4E** yang baharu daripada change owner.
9. Selepas GO sahaja, sediakan implementation/runbook satu controlled Apply.

Jangan set `ONEID_SYNC_APPLY_ENABLED=true` atau `ONEID_SYNC_ENGINE=safe` semasa
menutup gate 1–7.

## 9. Definition of Ready untuk S4E

S4E hanya ready apabila semuanya benar:

- `S4D-08`, `S4D-09`, `S4D-15` dan `S4D-16` ialah `pass`;
- `S4-G11`, `S4-G19`, `S4-G20` dan `S4-G23` ialah `pass`;
- release/working tree bersih dan commit diluluskan;
- backup masih tersedia dan checksum sah;
- fresh preview dalam window normal serta diterima;
- monitoring dan rollback boleh dilaksanakan segera;
- arahan GO S4E direkod secara eksplisit.

Jika satu sahaja syarat tidak dipenuhi, keputusan kekal **NO-GO** dan Apply
kekal disabled.

## 10. Rujukan

- `docs/S4_CONTROLLED_FEATURE_FLAG_WIRING_DAN_PILOT_RUNBOOK.md`
- `docs/S4D_DORMANT_DEPLOYMENT_DAN_PRE_PILOT_READINESS.md`
- `docs/S4D_VERIFIKASI_OPERASI_SEBELUM_S4E.md`
- `docs/S4D_PRE_PILOT_READINESS_REGISTER.tsv`
- `docs/S4_PILOT_GATE_REGISTER.tsv`
