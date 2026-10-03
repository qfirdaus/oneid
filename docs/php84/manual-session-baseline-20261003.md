# Baseline sesi manual — 3 Oktober 2026

Penguji mengesahkan “semua ujian 1-3 dah berjaya” bagi arahan sebelumnya, menggunakan akaun 0530-09 pada OneID UAT PHP 8.3.

Skop sistem: APP-033 Sistem ODL, APP-038 Sistem SAP Web, APP-011 Sistem e-BDR (Pentadbir).

| Senario | Keputusan |
|---|---|
| Buka semula menggunakan sesi OneID sedia ada | PASS_USER_REPORTED |
| Buka ketiga-tiga sistem dalam beberapa tab | PASS_USER_REPORTED |
| Logout OneID, login semula dan buka ketiga-tiga sistem | PASS_USER_REPORTED |

Bukti ialah laporan pengguna, bukan pemerhatian agent atau log terperinci. Masa tepat/peranti tidak diberikan. Pengguna tidak menerangkan sama ada tab downstream lama masih boleh digunakan selepas logout OneID; global logout/invalidation downstream belum disahkan. Kolum logout_83 asal dikekalkan NOT_RUN untuk mengelakkan tafsiran bahawa pembatalan semua sesi downstream telah diuji; keputusan logout/login semula direkod dalam logout_relogin_83.

Tiada bukti tambahan untuk token tamat, akses ditolak, prestasi atau runtime PHP 8.4. Tiada perubahan kod/runtime dibuat.
