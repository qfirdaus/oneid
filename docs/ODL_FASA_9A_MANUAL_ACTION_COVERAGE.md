# ODL Fasa 9A — Manual Action Coverage

**Status:** `RUN A PASS / RUN B REACTIVATE DEFERRED`

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

## Run A evidence

Team ODL menyediakan satu perubahan profil bukan identity dan mengeluarkan satu
rekod daripada active view. Exact Preview menghasilkan:

```text
source rows=70
New=0
Update=1
Deactivate=1
Reactivate=0
plan hash=a9bb07fb8ac231cbdf74b64cd4ec2475198f28e6ae6b0c46da32a42891a9b049
```

Apply diluluskan melalui `ONEID-ODL-F9A-20260724-02`, backup
`ONEID-UAT-BACKUP-20260724-03` dan change window 24 Julai 2026,
6:00 PM–6:30 PM MYT. Header 52 merekod tepat satu UPDATE dan satu DEACTIVATE.
Post-Apply Preview kembali zero action. Operational reconciliation, audit
counts dan syslog lulus. Read-only rollback readiness menghasilkan
`rollback_ready=true`, zero blocking code dan zero mutation. Apply dikembalikan
kepada `false`.

## Deferred Run B

Run B memerlukan Team ODL mengaktifkan semula rekod yang sama supaya Preview
menghasilkan satu REACTIVATE. Team ODL tidak tersedia selepas Run A, maka Run B
direkod `DEFERRED` sehingga owner boleh berkomunikasi semula dengan mereka.

Keputusan ini bukan test failure dan bukan blocker teknikal. Jangan mengubah
data OneID secara manual untuk memalsukan precondition REACTIVATE. Apabila Team
ODL tersedia, mereka perlu mengembalikan rekod ke active view menggunakan
Matrik dan IC asal. Fresh Preview, exact-plan authorization, backup dan change
window baharu tetap diperlukan.
