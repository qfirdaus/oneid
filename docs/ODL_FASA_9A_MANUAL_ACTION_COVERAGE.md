# ODL Fasa 9A — Manual Action Coverage

**Status:** `IMPLEMENTED / PREVIEW TEST DATA PENDING`

**Environment:** UAT

**Change reference:** `ONEID-ODL-F9A-20260724-01`

## Scope

F9A melengkapkan coverage manual ODL untuk satu `UPDATE`, satu `DEACTIVATE`
dan `REACTIVATE` bagi akaun deactivation yang sama. `NEW` telah dibuktikan
melalui F9 header 50.

Implementation dan Preview sahaja dibenarkan. Setiap live Apply memerlukan
exact-plan authorization, backup dan change window berasingan. Scheduler,
unattended Apply, cross-source mutation dan production tidak dibenarkan.

## Reusable exact-plan gate

Private runtime bagi setiap run mesti mengandungi:

- Preview dan Apply flags;
- expected source rows;
- exact `New`, `Update`, `Deactivate`, `Reactivate` counts;
- 64-character plan hash;
- change reference berformat `ONEID-ODL-F9A-YYYYMMDD-NN`;
- backup reference berformat `ONEID-UAT-BACKUP-YYYYMMDD-NN`;
- MYT change window antara 5 hingga 60 minit pada tarikh yang sama.

Apply hanya tersedia apabila fresh Preview sepadan tepat dan waktu server berada
dalam window. Approval Preview adalah one-time dan writer membina semula plan
sebelum transaction.

## Profile dan source safety

- ODL UPDATE tidak boleh mengubah Matrik atau IC;
- blank e-mel tidak memadam nilai OneID sedia ada;
- perubahan profil bukan identity seperti e-mel, fakulti atau program dibenarkan;
- DEACTIVATE menutup membership ODL dahulu dan hanya menutup akaun apabila tiada
  sumber aktif lain;
- REACTIVATE menggunakan Matrik+IC sama dan mengaktifkan semula membership ODL;
- akaun manual dan cross-source identity collision kekal blocked.

## Rollback readiness

`tools/odl_f9a_rollback_readiness.php --header=N` melakukan pemeriksaan
read-only pada action log, old-data evidence dan ODL membership. Ia tidak
melaksanakan rollback dan mengeluarkan `mutation_statements=0`. Live rollback
masih memerlukan authorization khusus.

## Test sequence

1. Run A: satu UPDATE profil bukan identity dan satu DEACTIVATE.
2. Reconcile header, action logs, membership serta account state.
3. Jalankan rollback-readiness check.
4. Team ODL kembalikan rekod deactivated ke active view.
5. Run B: satu REACTIVATE.
6. Reconcile, login/ACL smoke test dan tutup F9A.
