# OneID v2.6.2 — ODL Manual Action Coverage dan Sync User UI

**Versi:** 2.6.2
**Tarikh release:** 24 Julai 2026
**Environment sasaran:** UAT
**Status:** Fasa 0–9A `PASS / CLOSED`

## Ringkasan

Release ini menutup Fasa 9 dan F9A selepas Manual Operational Sync ODL
membuktikan tindakan `NEW`, `UPDATE`, `DEACTIVATE` dan `REACTIVATE`. Release ini
juga menyusun semula keseluruhan pengalaman Admin untuk Sync User supaya status,
tindakan dan kawalan keselamatan lebih mudah difahami tanpa mengubah logic
planner, transaction, source isolation atau exact confirmation.

## Manual Operational Sync ODL

- F9 exact-plan Apply menambah 18 akaun `NEW` melalui reconciliation header 50;
- jumlah active membership `STUDENT_ODL_PG` meningkat daripada 53 kepada 71;
- F9A Run A merekod satu `UPDATE` dan satu `DEACTIVATE` melalui header 52;
- F9A Run B mengaktifkan semula rekod yang sama melalui satu `REACTIVATE` pada
  header 53;
- setiap run lulus reconciliation, audit marker dan rollback-readiness check;
- post-Apply Preview kembali kepada zero action;
- login dan ACL smoke test lulus dengan kategori `Pelajar/10` serta membership
  ODL aktif; dan
- private Apply flag dikembalikan kepada `false` selepas setiap execution.

Evidence:

- F9: `ONEID-ODL-F9-20260724-02`;
- F9A Run A: `ONEID-ODL-F9A-20260724-02`;
- F9A Run B: `ONEID-ODL-F9A-20260724-03`.

## Source-aware notification

- badge hanya mengira tindakan `NEW`, `UPDATE`, `DEACTIVATE`, `REACTIVATE`,
  calon tindakan dan membership yang perlu ditambah;
- rekod `KEEP_ACCOUNT_ACTIVE` tidak lagi menghasilkan loceng palsu;
- Summary menggabungkan jumlah tindakan sebenar tanpa mencampurkan Apply; dan
- status Staff, Prasiswazah serta ODL kekal source-specific.

## Sync User UI

- tindakan Admin dinamakan semula daripada Add User kepada Sync User;
- parent modal dibahagikan kepada Ringkasan, sinkronisasi mengikut sumber dan
  tindakan manual;
- Summary menggunakan identiti warna tersendiri, ketiga-tiga sumber sync
  menggunakan warna yang sama dan Manual Add User menggunakan warna berasingan;
- child modal Summary, Preview/Apply dan Manual Add User menggunakan header,
  curve, spacing, footer dan hierarchy visual yang konsisten;
- lebar child modal dihadkan supaya kandungan kekal mudah dibaca;
- modal responsif menyusun kandungan teknikal secara menegak pada skrin kecil;
- label `Akaun manual yang dilindungi` dan `Konflik identiti` dipisahkan;
- status utama menjelaskan sama ada data terkini, memerlukan tindakan atau
  disekat; dan
- plan hash, digest, baseline serta blocking code dipindahkan ke bahagian
  Maklumat teknikal dan rujukan audit yang tertutup secara default.

## Kawalan keselamatan yang kekal

- Summary kekal read-only dan tidak mempunyai Apply;
- Preview serta Apply wajib menerima source scope;
- ODL source kekal `STUDENT_ODL_PG`;
- akaun manual dan membership sumber lain kekal dilindungi;
- exact-plan authorization dan typed confirmation kekal diwajibkan;
- fresh plan disemak semula sebelum transaction;
- automatic scheduler dan unattended mutation kekal disabled;
- production rollout belum dibenarkan; dan
- credential serta `.private/runtime.php` tidak dimasukkan ke Git.

## Validasi release

```bash
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
php tools/odl_f6_shadow_contract.php
php tools/s4g_operational_sync_contract.php
php tools/odl_f9_manual_operational_contract.php
php tools/source_scoped_sync_apply_contract.php
php tools/r52_dashboard_characterization.php
```
