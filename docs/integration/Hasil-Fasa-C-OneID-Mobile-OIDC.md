# Hasil Fasa C — aliran login mobile OneID

Tarikh: 29 September 2026. Skop: UAT sahaja. Tiada commit atau push Git.

## Status

Kod aliran lengkap telah disediakan dan diuji menggunakan HTTP loopback, akaun sintetik, MySQL persendirian, Hydra v26.2.0 dan PostgreSQL persendirian. Ini belum merupakan pengaktifan pada hostname HTTPS UAT sebenar atau pengesahan aplikasi Flutter sebenar.

Konfigurasi `.private/mobile-oidc-hosted.php` kekal `enabled=false`. Endpoint memberi 404 sebelum membuka database atau sesi apabila dimatikan. Tiada konfigurasi Nginx/FPM sedia ada ditukar, tiada servis kekal dipasang dan tiada migrasi dilaksanakan pada database aplikasi. Semua servis ujian telah dihentikan.

## Fungsi disediakan

- Halaman login khas di `mobile-public/`, di luar document root web sedia ada: nombor staf atau matrik, password, MFA email/TOTP, batal dan pertukaran password wajib selepas pengesahan yang diperlukan.
- Authorization Code + PKCE melalui provider; consent automatik untuk client first-party yang didaftarkan, tanpa pendaftaran akaun tambahan atau padanan MyCampus.
- Sesi browser mobile berasingan, CSRF dan semakan Origin, cookie HttpOnly/SameSite, respons no-store, dan sekatan framing.
- Refresh token disemak semula terhadap status akaun dan MFA. Sesi kekal selepas restart provider/adapter; logout membatalkan pemasangan berkenaan sahaja.
- Pemerhatian perubahan akaun/polisi melalui 28 trigger MySQL dan versi keselamatan. Disable kemudian enable semula, reset password/faktor dan perubahan polisi tidak menghidupkan semula sesi lama. Akaun dipadam dan dicipta semula mendapat sub baharu.
- Kegagalan kebergantungan menolak akses; gangguan sementara tidak dianggap sebagai arahan memadam credential Flutter.

## Apa yang OneID sediakan kepada Flutter

Hostname dan client sebenar belum ditetapkan. Nilai dalam template bukan endpoint yang boleh digunakan sekarang.

| Komponen | Kegunaan |
| --- | --- |
| Discovery OIDC dan JWKS pada issuer | SDK mendapatkan endpoint dan kunci pengesahan token |
| Authorization dan token endpoint provider | Login browser sistem, PKCE S256, pertukaran code dan refresh |
| ID token | Identiti `sub`, `account_type`, `name` apabila scope profile diminta, serta assurance/nonce standard |
| `GET /mobile/session` pada host login | Bearer access token; respons `session_status`, `sub`, `account_type`, `display_name` |
| `POST /mobile/logout` pada host login | Bearer access token; membatalkan sesi pemasangan dan meminta revocation provider; 204 berjaya |
| `/token-hook` | Endpoint dalaman provider sahaja; bukan API Flutter |

Scope yang dibenarkan: `openid profile offline_access mobile:session`. Audience: `oneid-mobile-session`. Client public berasingan mengikut platform/persekitaran, tanpa client secret dalam aplikasi; redirect URI mesti tepat dan didaftarkan. Password/hash password tidak diberikan kepada Flutter.

Flutter perlu menggunakan browser sistem dan SDK OIDC yang mengesahkan issuer, signature, audience, nonce dan state; menyimpan token dalam secure storage; menyelaraskan refresh serentak; serta memanggil semakan sesi apabila kembali online. Respons 401 memerlukan pengesahan semula; 503 memerlukan retry tanpa memadam refresh token. ID token dan refresh token bukan bearer untuk API sesi.

Template menetapkan access token 5 minit dan refresh TTL `-1` (tidak tamat secara rutin). Ujian menggunakan access token 120 saat. Ini bukan bukti ujian berbulan-bulan atau pengesahan secure storage Flutter. Logout atau perubahan keselamatan tetap boleh memerlukan login semula. Provider userinfo bukan semakan akses akaun semasa; API aplikasi perlu pengesahan access token dan semakan sesi yang sesuai.

## Kesan kepada sistem sedia ada

Satu fail lama diubah: `lib/Database.php`. Rehash algoritma selepas password disahkan diberi penanda connection-local, dibersihkan dalam finally, supaya tidak dianggap sebagai reset password. Update menggunakan perbandingan hash asal untuk mengelakkan rehash lama menimpa reset serentak. Fungsi sebenar diuji pada database sintetik.

Login mobile biasa tidak membatalkan token web. Pertukaran password wajib membatalkan sesi pengguna berkenaan, menyimpan sejarah password dan membatalkan OTP reset. Cookie dan aliran login web tidak digunakan oleh halaman mobile. Namun ujian browser penuh web/SSO/MyDigitalID pada deployment sebenar masih perlu; tiada dakwaan risiko gangguan sifar.

Trigger masih belum dipasang pada database aplikasi. Selepas dipasang, ia menjadi sebahagian transaksi penulis data lama; ujian beban, lock/deadlock dan rollback mesti dibuat sebelum pilot. Penanda rehash ialah kontrak untuk penulis database dipercayai, bukan input pengguna.

## Bukti ujian

| Suite | Keputusan |
| --- | --- |
| Aliran HTTP penuh dengan provider dan SQL sebenar dalam fixture | 110/110 |
| Domain adapter | 73/73 |
| Kontrak protokol | 20/20 |
| Regresi primitif/pending login/TOTP MFA lama | 33/33 |

Jumlah assertion fungsi: **236 lulus**. Report hosted turut mempunyai dua pemeriksaan kebersihan: fail runtime tidak berubah sepanjang ujian dan servis sementara dihentikan. Perbandingan fail sepanjang ujian bukan perbandingan dengan Git HEAD; perubahan `lib/Database.php` di atas memang wujud.

Bukti: [hosted.json](bukti-fasa-c/hosted.json), [regression.json](bukti-fasa-c/regression.json). Tiada credential sebenar atau token disalin ke bukti ini.

## Perkara yang mesti diselesaikan sebelum pilot HTTPS

1. Sahkan hostname issuer/login, sijil TLS, client ID dan redirect URI Flutter. Lengkapkan konfigurasi sebenar tanpa menggunakan `.invalid` dalam template.
2. Buktikan database sasaran UAT berasingan daripada produksi; sahkan skema/versi MySQL, backup dan keizinan migrasi. Tiada migrasi aplikasi sehingga sempadan ini jelas.
3. Semak polisi MFA efektif, termasuk layanan konservatif akaun administrator, keyring TOTP sedia ada dan ujian penghantaran SMTP sebenar. Notifikasi perubahan password melalui outbox lama belum disambungkan dalam laluan baharu.
4. Laksanakan rehearsal migrasi, prestasi/lock, backup/restore dan rollback pada salinan UAT. Trigger tidak menangkap TRUNCATE/DROP/restore: matikan feature sebelum operasi tersebut, batalkan sesi/refresh terdahulu, reseed generasi dan naikkan epoch sebelum pengaktifan semula. Akaun runtime tidak patut mempunyai kuasa DDL.
5. Tetapkan retention/housekeeping bagi transaksi tamat, rate records, audit dan data provider. Jangan padam subject atau sesi aktif secara automatik. Refresh tidak tamat meningkatkan keperluan kapasiti dan backup.
6. Uji HTTPS melalui proxy, deep link Android/iOS, signature verification SDK, restart telefon, refresh serentak, offline/resume dan logout. Jalankan regresi browser web/SSO/MyDigitalID yang masih aktif.

## Pemasangan dan rollback

Rujuk [runbook](../../deployment/mobile-oidc/README.md). Semua konfigurasi ialah contoh untuk semakan dan belum dipasang. Pengaktifan UAT sebenar masih bergantung pada hostname, pemisahan database dan semakan di atas; fungsi kekal tidak aktif untuk pengguna umum.
