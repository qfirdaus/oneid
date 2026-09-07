# MR5 — Workflow Mutation dan Approval User MFA

**Tarikh:** 7 September 2026
**Status:** IMPLEMENTED / AWAITING CONTROLLED UAT

Fasa ini menyediakan UI dan boundary server untuk memilih semua mode User MFA,
strategi grace lima minit atau immediate revoke, tempoh Emergency Bypass antara
30 minit hingga lapan jam, typed confirmation, change reference dan approval.

Staging memproses controlled approval dalam transaksi yang sama. Production
mencipta `PENDING_APPROVAL`; requester tidak boleh menjadi approver. Approval
terikat kepada digest payload dan expected configuration version.

Immediate revoke menamatkan transaksi/challenge tertangguh dan membuang hash
OTP e-mel. Grace mengekalkan challenge sedia ada maksimum lima minit. Faktor
TOTP dan sesi yang telah authenticated tidak dipadam.

Tiada perubahan mode dilakukan semasa pembangunan ini. Controlled UAT perlu
dijalankan berasingan dengan satu mode bukan bypass dahulu.

## Controlled UAT ENROLLMENT

Pada 7 September 2026, perubahan `ENFORCED` version 8 kepada `ENROLLMENT`
version 9 menggunakan strategi `GRACE` berjaya. Request `#1`, history `#9` dan
transition run `#1` mempunyai correlation
`2629610d87c9eb7bf937a98e45b2b3ab`; run berstatus `SUCCEEDED` dan tiada
pending transaction/challenge terlibat.

UAT ini menemui dua isu paparan yang dibetulkan sebelum ujian diteruskan:

- resume selepas Admin Step-Up kini one-shot supaya dua respons SweetAlert
  tidak dihasilkan oleh dua callback load; dan
- masa mula, grace dan expiry kini datang daripada `NOW(6)` database agar
  tidak berlaku perbezaan UTC dan `Asia/Kuala_Lumpur`.

Percubaan restore pertama kemudian menemui `PDOException` MySQL 1064 kerana
alias `current_time` ialah keyword MySQL. Alias ditukar kepada
`db_now_value`, `grace_time_value` dan `expiry_time_value`. Restore terkawal
`ENROLLMENT` version 9 kepada `ENFORCED` version 10 seterusnya berjaya sebagai
request `#3` dan run `#2`, correlation
`8c04d5497ac3e8861b6572b860187b79`. Request enrollment `#1` ditutup sebagai
`RESTORED`; 10 faktor TOTP dan polisi Maintenance MFA `ENFORCED` dipelihara.
