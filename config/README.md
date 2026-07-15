# Configuration

Direktori source-of-truth bagi konfigurasi bukan secret yang masuk Git.

`runtime.php` menyediakan default staging-safe. Environment variable mempunyai
keutamaan tertinggi, diikuti `.private/runtime.php`, kemudian default committed.
Credential tidak boleh disimpan dalam direktori ini.

`application.php` ialah source of truth untuk metadata awam aplikasi. Versi,
tahun copyright dan pemilik dalam footer login, dashboard pengguna serta
dashboard admin dibaca daripada fail ini.

Salin `docs/examples/oneid-secrets.example.php` ke `.private/runtime.php`, isi
nilai local melalui saluran secrets yang diluluskan dan kekalkan permission
minimum yang masih boleh dibaca oleh PHP-FPM.
