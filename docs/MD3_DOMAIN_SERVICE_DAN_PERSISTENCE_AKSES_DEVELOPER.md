# MD3 — Domain Service dan Persistence Akses Developer Maintenance

**Tarikh:** 4 September 2026  
**Rujukan:** `ONEID-MAINTENANCE-DEVELOPER-20260904-01`  
**Fasa:** 3 — domain service dan persistence  
**Status:** implemented dormant; menunggu verifikasi owner

## 1. Skop siap

Fasa ini menambah domain policy, repository contract, PDO adapter, service dan
typed exception untuk:

- mencipta grant bagi akaun aktif `u_type=0`;
- menolak akaun admin atau inactive;
- memerlukan pengesahan Admin Step-Up daripada caller;
- mengawal satu active grant dan window maksimum 30 hari;
- revoke menggunakan optimistic configuration version;
- menukar grant tamat kepada `EXPIRED` sebelum grant pengganti;
- menulis lifecycle history dalam transaksi yang sama; dan
- revalidation read-only yang fail closed.

Fasa ini tidak menambah endpoint, UI, login flow, session flag atau bypass pada
`MaintenanceGate`.

## 2. Komponen

- `MaintenanceDeveloperAccessPolicy`: keputusan pure berdasarkan account,
  grant dan waktu UTC.
- `MaintenanceDeveloperAccessRepositoryInterface`: port persistence yang boleh
  diuji tanpa bergantung kepada HTTP.
- `PdoMaintenanceDeveloperAccessRepository`: SQL parameterized, row locking,
  optimistic update dan transaction rollback.
- `MaintenanceDeveloperAccessService`: validation dan orchestration atomik.
- `MaintenanceDeveloperAccessException`: reason code dan correlation ID.

## 3. Boundary keselamatan

Feature flag diperiksa ketika revalidation dan default kekal OFF. Grant/revoke
boleh disediakan secara terkawal sebelum activation apabila schema tersedia,
tetapi grant tidak berkuat kuasa pada runtime selagi flag OFF. Boolean Admin
Step-Up dalam service bukan bukti cryptographic sendiri; Fasa endpoint wajib
mendapatkan keputusan itu daripada guard Admin Step-Up server-side dan tidak
boleh menerimanya daripada POST.

Grant/revoke mengunci row admin dan subject dalam transaction. History failure
menyebabkan keseluruhan perubahan rollback. Revalidation tidak mempercayai
session payload dan membaca semula account serta active grant.

## 4. Time contract

Semua timestamp domain menggunakan format UTC tepat:

```text
Y-m-d H:i:s.u
```

Window mesti berakhir selepas waktu mula, tidak melebihi 30 hari dan belum
tamat ketika grant dicipta. Perbandingan interval menggunakan UTC.

## 5. Deferred ke fasa berikutnya

- admin search/list endpoint dan UI;
- wiring kepada exact Admin Step-Up purpose;
- login maintenance developer dan pending MFA;
- session/token finalization;
- maintenance gate revalidation dan forced logout;
- runtime syslog bagi login/session events; dan
- migration UAT serta feature activation.

## 6. Verifikasi

```bash
php tools/maintenance_developer_phase3_integration.php
php tools/maintenance_developer_phase2_isolated_rehearsal.php
php tools/maintenance_mode_contract.php
```

Ujian integration menggunakan database sementara, kemudian membuangnya. Tiada
schema atau data UAT dimutasi.

## 7. Gate Fasa 3

- [x] Policy hanya menerima akaun aktif `u_type=0`.
- [x] Mutation memerlukan signal Admin Step-Up server-side.
- [x] Grant dan history atomic.
- [x] Duplicate active grant ditolak.
- [x] Stale configuration version ditolak dan rollback.
- [x] Revoke menghilangkan akses pada revalidation berikutnya.
- [x] Feature flag OFF kekal default committed.
- [x] Tiada wiring runtime atau live schema mutation.

Fasa 4 hanya boleh bermula selepas owner mengesahkan Fasa 3.
