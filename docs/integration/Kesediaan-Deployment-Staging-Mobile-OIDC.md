# Semakan deployment staging mobile OIDC

Tarikh: 29 September 2026.

## Kemas kini provider dan migrasi — 16:53 MYT

Pemasangan provider berjaya selepas pembetulan permission direktori (umask 0077 memerlukan chmod eksplisit untuk direktori binary/config). Pentadbir menggunakan `--resume-before-initdb`; tiada cluster terdahulu dipadam.

Agent mengesahkan servis `oneid-mobile-postgres-uat` dan `oneid-mobile-hydra-uat` active/enabled serta readiness Hydra 200. Listener public/admin provider hanya 127.0.0.1:24144/24145; PostgreSQL hanya 127.0.0.1:24146.

Selepas itu agent menjalankan migrasi OneID yang telah dibenarkan. Keputusan: empat jadual mobile dan 28 trigger dipasang, observer disahkan OFF, tiada rekod identiti sumber diubah. Metadata skema sebelum migrasi, pelan dan progress disimpan di `.private/mobile-schema-20260929-085324-70a18996` (nama direktori menggunakan UTC); ini bukan full backup data.

Pengesahan selepas migrasi: Nginx, PHP-FPM dan kedua-dua servis khusus active; hosted enabled=false; kesemua 12 endpoint mobile melalui HTTPS loopback masih 404 dengan verifikasi sijil. Tiada push Git atau perubahan production. Status ini menggantikan catatan belum dipasang/belum dimigrasi di bawah. Ujian login browser sebenar, wiring adapter/SMTP/keyring dan pengaktifan pilot masih belum dilakukan.

## Kemas kini pemasangan — 16:40 MYT

Pentadbir menjalankan `install-dormant-staging.py --apply` dan menerima SUCCESS. Backup: `/var/backups/oneid-mobile-uat/20260929-164049-uwnhtr41`. Semakan selepas pemasangan oleh agent mengesahkan Nginx dan PHP 8.3 FPM active, socket `/run/php/oneid-mobile-uat.sock` tersedia, 12 exact locations terpasang dengan `return 404`, dan konfigurasi hosted kekal `enabled=false`. Output pemasangan mengesahkan ujian HTTPS 12 route semuanya 404 serta semakan konfigurasi sebelum reload lulus.

Ini menggantikan status belum dipasang bagi routing dormant/pool FPM dalam catatan awal di bawah. Provider Hydra kekal, database provider, migrasi OneID dan pengaktifan pilot masih belum dilaksanakan. Kesihatan servis ini bukan pengesahan regresi browser login web/SSO penuh. Tiada push Git.

## Keputusan semakan

- Pengguna mengesahkan `https://oneid-uat.upnm.edu.my` ialah staging dan database staging khusus, berasingan daripada production. Pengasingan berdasarkan pengesahan pemilik; konfigurasi production tidak dibaca.
- Semakan PDO terus dalam transaksi READ ONLY menunjukkan environment staging, server `mysql8-DEV`, database `oneiddb`, MySQL 8.0.41. Tiada rekod identiti/password dicetak. Jadual `mobile_oidc_*` belum wujud.
- Sembilan jadual sumber yang dirujuk observer wujud; kolum yang digunakan trigger tersedia. Ini semakan metadata, bukan bukti migrasi atau load test pada MySQL staging. Fixture Fasa C menggunakan MySQL 8.0.46.
- Nginx staging menggunakan document root `public`, HTTPS sedia ada dan pool PHP 8.3 bersama. Konfigurasi aktif tidak diubah.
- Akaun terminal tidak mempunyai sudo tanpa kata laluan. Fail Nginx dan pool FPM dimiliki root. Tiada percubaan menukar permission atau memintas kawalan tersebut.

## Konfigurasi yang disediakan

Semua fail berikut berada dalam `deployment/mobile-oidc/` dan belum dipasang:

| Fail | Tujuan |
| --- | --- |
| `hydra.staging.yaml.example` | Issuer dan hosted login menggunakan hostname staging yang disahkan |
| `hosted-config.staging.php.example` | Origin/issuer ditetapkan; feature OFF, client kosong, credential placeholder |
| `nginx.staging-locations.conf.example` | Include tambahan di dalam HTTPS server staging sedia ada; tiada server_name pendua |

Pilihan ini menggunakan issuer `https://oneid-uat.upnm.edu.my/`. Route hosted ialah `/login`, `/consent`, `/login.css`, `/mobile/session`, `/mobile/logout` dan `/token-hook`. Route provider ialah discovery, JWKS, `/oauth2/auth`, `/oauth2/token`, `/oauth2/revoke` dan `/userinfo`. Exact locations memastikan discovery tidak disekat regex hidden-file sedia ada. Semakan fail public tidak menemui halaman pada route baharu tersebut; `/admin/login.php` dan login MyDigitalID kekal pada laluan sendiri.

Semua location baharu dalam contoh hanya membenarkan loopback IPv4/IPv6. Ini belum boleh diakses telefon developer. Apabila pilot dibuka, kawalan rangkaian mesti membenarkan laluan Flutter yang diperlukan secara konsisten; `/token-hook` kekal dalaman. Jangan buka public sebelum servis/config/client siap.

## Pengesahan konfigurasi

`php -l` pada hosted config staging lulus. `nginx -t` pada salinan gabungan virtual host semasa dan snippet baharu lulus. Ujian menggunakan port tinggi 28080/28443, sijil self-signed sementara dan log persendirian untuk mengelakkan keperluan root. Tiada Nginx tambahan dimulakan; tiada reload. Ujian ini tidak mengesahkan private key TLS sebenar, reachability FPM/provider atau callback Flutter.

## Urutan pemasangan yang tinggal

1. Pentadbir dengan akses sudo menyediakan akaun/directories servis, provider PostgreSQL dan pool FPM khusus mengikut runbook. Jangan gunakan cluster fixture sebagai database provider kekal.
2. Lengkapkan secrets, SMTP, keyring, polisi MFA dan client/redirect yang diluluskan. Host hook mesti resolve ke loopback dari provider sambil mengekalkan verifikasi TLS. Feature kekal OFF.
3. Backup skema/data staging dan uji prosedur pemulihan; kemudian pasang skema B dan C dengan observer OFF. Semak privileges definer trigger, keserasian collation dan kesan lock sebelum observer ON. Pengesahan database oleh pengguna telah diterima; gate pengasingan tidak perlu ditanya semula.
4. Tambah include snippet di HTTPS server sedia ada sebelum routing umum. Semak konfigurasi sebenar dengan `nginx -t` dan pemeriksaan FPM sebelum reload. Keadaan semasa tidak memadai untuk reload kerana servis/socket belum dipasang.
5. Uji servis secara loopback, kemudian benarkan pilot rangkaian terkawal dan daftarkan callback Flutter. Laksanakan regresi login lama sebelum membuka akses lebih luas.

Migrasi ditangguhkan bersama pemasangan kerana laluan servis belum boleh dipasang dalam sesi ini. Tiada jadual, trigger atau data staging diubah. Fungsi kekal OFF; tiada perubahan production, commit atau push Git.

Rujukan: [runbook](../../deployment/mobile-oidc/README.md), [hasil Fasa C](Hasil-Fasa-C-OneID-Mobile-OIDC.md).
