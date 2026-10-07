# Semakan akhir OneID staging untuk integrasi UPNM Mobile

Tarikh: 30 September 2026
Skop: semakan baca sahaja deployment staging, ujian fixture terasing dan pembetulan skrip operasi. Tiada login menggunakan credentials pengguna, emel ujian, perubahan production atau push Git.

## Keputusan

OneID staging bersedia menerima ujian integrasi Android. Client dan callback berdaftar, authorization request Android sampai ke borang OneID, endpoint tersedia melalui alamat LAN server dan ujian regresi selesai seperti senarai di bawah. Ini bukan pengesahan kejayaan aplikasi Android sebenar atau kelulusan production.

## Bukti deployment semasa

| Semakan | Keputusan |
| --- | --- |
| Nginx, PHP 8.3 FPM, Hydra, PostgreSQL provider, browser harness | Semua active |
| Hydra, PostgreSQL dan harness selepas reboot | Semua enabled |
| Had masa servis harness | RuntimeMaxUSec=infinity; Restart=on-failure |
| Android client | upnm-mobile-android-staging, public client tanpa secret |
| iOS client | upnm-mobile-ios-staging, public client tanpa secret; berdaftar 7 Oktober 2026 |
| iOS acceptance target | Bundle ID com.upnmmobile1.app; minimum iOS 15.0; iPhone 15/iOS 18.1.1 |
| Callback berdaftar | com.upnmmobile1.app://oneid/callback |
| Grants / scope / audience | authorization_code + refresh_token; openid profile offline_access mobile:session; oneid-mobile-session |
| HTTPS discovery dan JWKS | HTTP 200; sijil disahkan curl tanpa mengabaikan TLS |
| Authorization Android dengan PKCE S256 | HTTP 200 borang password; butang MyDigital ID tersedia |
| Callback tidak berdaftar | Provider memulangkan invalid_request ke halaman ralat provider, bukan callback aplikasi |
| /mobile/session tanpa bearer | HTTP 401 seperti dijangka |
| /mobile-test/ dan halaman web utama | HTTP 200 |
| Discovery dan halaman ujian melalui 172.16.2.153 (Host/TLS staging) | HTTP 200; tidak menggunakan tunnel |
| Token hook melalui alamat LAN | HTTP 403 |
| Listener provider/DB/harness | Port 24144/24145/24146/24147 terikat 127.0.0.1 sahaja |
| Konfigurasi adapter persendirian | root:www-data, permission 0640; kandungan tidak dicetak/dibaca oleh agent |
| DB sasaran | oneiddb pada mysql8-DEV; disahkan sebelum pertanyaan metadata baca sahaja |
| Observer dan lifecycle | Observer 1, tiada trigger diperlukan yang hilang; LifecycleSchema::ready=true |
| Kolum profil | data1 hingga data7 wujud |

Paparan metadata provider menyenaraikan plain dan S256. Pemeriksaan awal authorization tanpa PKCE/plain boleh sampai ke halaman login; ia bukan bukti token boleh diterbitkan. Penguatkuasaan aliran provider diuji dalam fixture berasingan, bukan dengan mengesahkan pengguna sebenar pada staging. Flutter mesti menggunakan S256.

## Ujian automatik

- Provider OIDC fixture (rotation grace 5 saat): 52/52, termasuk PKCE tiada/plain ditolak, verifier salah, code replay dan refresh rotation. Laporan: .private/mobile-oidc-poc/run-20260930-113447-9e8b1f/report.json.
- Adapter: 73/73.
- Profil staf/pelajar: 6 senario lulus; nombor staf mengekalkan sifar depan, medan pelajar tidak membocorkan NRIC, medan kosong menjadi null.
- Protokol sesi/logout: 20/20.
- MyDigital ID/pemilihan akaun: 36/36.
- Rendering dan dispatch pemilihan akaun hosted: 6 semakan lulus.
- Harness: 8 semakan ID token dan 5 senario pemulihan/logout lulus.
- Regresi MyDigital ID web F1/F3/F4b: 42 semakan lulus.
- Rehearsal HTTP dengan MySQL/Hydra/PostgreSQL persendirian: 123/123. Laporan: .private/mobile-oidc-poc/run-20260930-113225-cc0d47/phase-c-report.json.
- MySQL/PDO fixture: 43/43. Laporan: .private/mobile-oidc-poc/sql-078a44006c58/report.json.
- Simulasi mod staging terbuka dan rollback: lulus. Simulasi tidak mengaktifkan/menghentikan servis host sebenar.

## Isu yang dijumpai dan dibetulkan

Skrip pilot-state.php sebelum ini mengosongkan config clients semasa stop dan sesetengah pengaktifan pilot. Ini boleh membuang allowlist Android pada adapter walaupun client masih berdaftar di Hydra. Skrip kini mengekalkan senarai client; stop menetapkan enabled=false dan observer OFF, yang tetap menyekat semua route operasi. Pengaktifan browser menambah client browser tanpa memadam Android. Tiada stop/restart sebenar dilakukan semasa audit ini.

## Perkara yang hanya boleh ditutup dengan build Flutter

1. Akses telefon/VPN dan DNS staging; semakan LAN server tidak membuktikan laluan dari telefon.
2. Android menerima custom-scheme callback ketika app aktif, cold start dan selepas app-switch MyDigital ID.
3. Pustaka OIDC menyemak state/nonce/signature/issuer/audience, menyimpan token dengan selamat dan mengendalikan refresh serentak.
4. Login, profil, tutup/buka app, token luput, offline, refresh rotation dan logout dalam build sebenar.
5. Pengesahan backend perniagaan jika aplikasi mempunyai backend; audience oneid-mobile-session bukan kebenaran universal kepada API lain.

Client configuration, dokumen dan callback telah sepadan. Baki di atas ialah ujian penerimaan bersama, bukan alasan untuk menangguhkan penulisan kod Flutter. Rate limit sedia ada kekal: permulaan login 30 cubaan/IP dalam 15 minit; pasukan yang berkongsi IP VPN perlu mengambil kira had ini semasa ujian berulang.
