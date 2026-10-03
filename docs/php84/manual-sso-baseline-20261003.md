# Baseline manual SSO — 3 Oktober 2026

Persekitaran OneID: UAT, baseline PHP 8.3. Penguji: pengguna melalui akaun 0530-09.
Sumber bukti: laporan pengguna bahawa semua aplikasi yang disenaraikan berjaya diakses, diikuti dua screenshot senarai aplikasi dalam perbualan. Screenshot menunjukkan pilihan aplikasi, bukan rakaman landing downstream. Keputusan ialah PASS_USER_REPORTED, bukan ujian automatik/agent.
Masa tepat, browser/peranti dan versi runtime downstream tidak diberikan.

| Rujukan | Nama inventori | Akses SSO |
|---|---|---|
| APP-001 | Celik Madani (ASNB) | PASS_USER_REPORTED |
| APP-004 | MyTrainingHub | PASS_USER_REPORTED |
| APP-010 | Sistem Attendance | PASS_USER_REPORTED |
| APP-011 | Sistem e-BDR (Pentadbir) | PASS_USER_REPORTED |
| APP-014 | Sistem E-Hepa | PASS_USER_REPORTED |
| APP-015 | Sistem E-HRM | PASS_USER_REPORTED |
| APP-020 | Sistem E-LPPT | PASS_USER_REPORTED |
| APP-021 | Sistem E-LPPT (Pentadbir) | PASS_USER_REPORTED |
| APP-024 | Sistem e-Prestasi (Pentadbir) | PASS_USER_REPORTED |
| APP-031 | Sistem i-MAP | PASS_USER_REPORTED |
| APP-033 | Sistem ODL | PASS_USER_REPORTED |
| APP-038 | Sistem SAP Web | PASS_USER_REPORTED |

Padanan label English: E-Student Affairs System → Sistem E-Hepa; Work From Home System (Administrator) → Sistem e-BDR (Pentadbir); e-Performance System (Administrator) → Sistem e-Prestasi (Pentadbir).

Skop: login OneID UAT dan membuka aplikasi melalui dashboard berjaya menurut penguji. Tidak membuat andaian bahawa fresh login, sesi lama, ketepatan semua profil/peranan, logout, token tamat, akses ditolak atau prestasi telah diuji berasingan. Kolum ujian tersebut kekal NOT_RUN. Tiada keputusan PHP 8.4 lagi.

Ulang 12 aplikasi yang sama selepas naik taraf, tanpa mengubah kod downstream. Laporan ini tidak mengesahkan bahawa host remote menggunakan PHP 8.3; versi 8.3 merujuk OneID UAT.
