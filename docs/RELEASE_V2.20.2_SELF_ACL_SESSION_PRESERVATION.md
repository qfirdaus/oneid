# OneID 2.20.2 — Self ACL Session Preservation

Tarikh: 16 Oktober 2026

Release ini membetulkan logout serta-merta apabila administrator mengemas kini
akses aplikasi untuk akaunnya sendiri. Proses batch ACL sebelum ini membatalkan
semua token pengguna sasaran, termasuk token browser yang sedang menjalankan
dashboard admin.

Untuk kemas kini akaun sendiri, token semasa kini dikecualikan dan semua sesi
lain masih dibatalkan. Kemas kini pengguna lain terus membatalkan semua sesi
pengguna sasaran. Semakan ACL pada server, transaksi, audit dan correlation ID
kekal digunakan.
