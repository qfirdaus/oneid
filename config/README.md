# Configuration

Direktori sasaran bagi konfigurasi bukan secret.

Secret kekal melalui environment atau `ONEID_SECRETS_FILE`; credential tidak
boleh disimpan dalam direktori ini. R5.2A belum menukar bootstrap/config runtime.

`application.php` ialah source of truth untuk metadata awam aplikasi. Versi,
tahun copyright dan pemilik dalam footer login, dashboard pengguna serta
dashboard admin dibaca daripada fail ini.
