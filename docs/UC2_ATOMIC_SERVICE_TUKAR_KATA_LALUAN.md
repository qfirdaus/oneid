# UC2 — Atomic Service Tukar Kata Laluan

**Tarikh:** 16 Julai 2026  
**Status:** IMPLEMENTED — CONTRACT PASS

Workflow perubahan password dipindahkan daripada endpoint kepada
`UserPasswordChangeService`. Dalam satu transaction, service mengunci user,
mengesahkan status/current password, menolak password yang sama, menyimpan hash
baharu, membersihkan forced-change flag, merevoke semua token, menginvalidasi
OTP aktif, mencipta replacement token dan menulis audit success.

Sebarang kegagalan termasuk replacement token atau audit menyebabkan rollback.
Cookie dan PHP session hanya dikemas kini oleh endpoint selepas commit berjaya.
Rejected attempt diaudit selepas rollback menggunakan reason dan correlation
yang selamat.

UC2 belum menguatkuasakan forced-change pada semua endpoint, belum menambah rate
limit dan belum merotasi PHP session/CSRF; itu skop UC3–UC4.
