# Runbook Fasa C — dormant UAT

Template dalam folder ini belum dipasang. Jangan salin nilai placeholder terus ke servis aktif. Tiada perubahan produksi atau push Git dibenarkan dalam skop semasa.

Hostname staging telah disahkan sebagai `oneid-uat.upnm.edu.my`, dan pemilik mengesahkan database khusus staging. Gunakan varian `hydra.staging.yaml.example`, `hosted-config.staging.php.example` dan `nginx.staging-locations.conf.example` untuk deployment pada hostname yang sama. Snippet lokasi dimasukkan ke server HTTPS sedia ada; jangan memasang template dua virtual host asal bersama varian ini. Lihat [semakan deployment staging](../../docs/integration/Kesediaan-Deployment-Staging-Mobile-OIDC.md) untuk keputusan dan batas ujian terkini.

## Susunan pemasangan pada UAT terasing

1. Sahkan database fizikal UAT, backup, skema sumber OneID, hostname HTTPS issuer/login dan TLS. Review MFA runtime serta keyring bersama pemilik OneID.
2. Sediakan provider Hydra versi tepat dalam `tools/mobile-oidc-poc/provider-lock.json`, database PostgreSQL persendirian dan secrets persisten. Admin API hanya loopback; jangan expose melalui proxy. Jalankan migrasi provider yang sepadan sebelum servis.
3. Sediakan skema state Fasa B, kemudian review SQL yang dicetak oleh `php tools/mobile-oidc-hosted/schema-plan.php --up`. Arahan ini mencetak SQL sahaja. Observer default OFF. Uji migrasi dahulu pada salinan UAT; jangan jalankan terhadap database yang mungkin dikongsi produksi.
4. Render `hosted-config.php.example` ke `.private/mobile-oidc-hosted.php`, kekalkan `enabled=false`. Isi DSN, clients/redirect tepat, secrets berasingan, SMTP, keyring dan session_path persendirian. Owner sahaja boleh menulis; tiada world permission. `database_scope_confirmed` hanya boleh true setelah bukti pengasingan disahkan.
5. Render pool FPM, servis Hydra dan dua virtual host daripada template. Gunakan user/service directory yang disediakan khusus. Semak konfigurasi FPM/Nginx sebelum reload. Document root mobile ialah `mobile-public`, bukan `public` web lama. Log tidak patut merekod query authorization, password atau token.
6. Hook HTTPS mesti resolve ke loopback dari proses Hydra (contohnya DNS setempat) kerana lokasi Nginx hanya menerima 127.0.0.1. SNI dan sijil mesti kekal sah; jangan matikan verifikasi TLS. Padankan hook secret di kedua-dua pihak.
7. Daftarkan hanya client public pilot dengan authorization_code/refresh_token, PKCE S256, scope dan audience yang didokumenkan serta redirect URI tepat. Dynamic registration kekal OFF. Lindungi akses host pilot melalui kawalan rangkaian sehingga pengaktifan umum diluluskan.
8. Setelah rehearsal trigger/locking dan regresi lama lulus, aktifkan observer pada DB UAT yang disahkan, kemudian enable hosted feature untuk pilot terkawal. Pastikan semua trigger lengkap; aplikasi menolak akses jika observer OFF atau trigger hilang. Jangan menggunakan `--dev` Hydra pada host HTTPS sebenar.

## Ujian berulang tanpa aplikasi sebenar

```sh
php tests/mobile-oidc/adapter.php
php tests/mobile-oidc-hosted/protocol.php
PYTHONDONTWRITEBYTECODE=1 python3 tools/mobile-oidc-hosted/rehearsal.py --uat-only
```

Rehearsal memerlukan artifak persendirian Fasa A/B yang telah dipin/dimuat turun. Ia membuat DB sintetik, sink OTP persendirian dan proses sementara; tidak memuatkan credential DB aplikasi atau menghantar email sebenar. Laporan terhasil di `.private/mobile-oidc-poc/run-*/phase-c-report.json`. Bukti yang dikongsi mestilah tanpa token/secrets.

## Rollback

1. Set `enabled=false`; entrypoint memberi 404 sebelum DB/session. Tutup akses host mobile dan hentikan provider khusus jika perlu. Ini tidak memerlukan penutupan web OneID lama.
2. Cetak `php tools/mobile-oidc-hosted/schema-plan.php --rollback` dan review. Pelan mematikan observer serta membuang trigger sahaja; state, audit dan subject dikekalkan. Jangan padam jadual sumber OneID.
3. Sebelum menghidupkan semula selepas gap pemerhatian/restore, batalkan sesi/refresh terdahulu dan pastikan epoch/generasi mencerminkan data semasa. Backup lama tidak boleh menghidupkan semula token yang telah dibatalkan.
4. Jika perlu undurkan perubahan rehash `lib/Database.php`, gunakan patch khusus perubahan ini selepas observer dimatikan; jangan reset keseluruhan working tree yang mengandungi kerja lain.

Rujuk [hasil dan batas ujian](../../docs/integration/Hasil-Fasa-C-OneID-Mobile-OIDC.md). Template bukan bukti deployment TLS atau pengesahan database aplikasi sebenar.

## MyDigital ID mobile (pilot staging)

Implementasi callback berasingan dan arahan pengaktifan: [mydigitalid/README.md](mydigitalid/README.md). Login MyDigital ID mobile dan web telah disahkan pengguna. Aliran pemilihan akaun selepas pengesahan kini siap diuji secara automatik; ujian pengguna bagi perubahan terkini masih diperlukan. Konfigurasi web tidak diganti.

## Serahan UPNM Mobile — Android dan iOS staging

[Panduan Android](../../docs/integration/Serahan-Integrasi-OneID-Flutter-Staging.md), [panduan iOS](../../docs/integration/Serahan-Integrasi-OneID-iOS-Staging.md) dan [borang maklumat aplikasi](../../docs/integration/Borang-Maklumat-Aplikasi-Flutter-Staging.md) merekodkan kontrak staging. Client iOS `upnm-mobile-ios-staging` telah didaftarkan pada provider dan allowlist adapter staging pada 7 Oktober 2026. Ia menggunakan Bundle ID `com.upnmmobile1.app`, callback `com.upnmmobile1.app://oneid/callback`, minimum iOS 15.0 dan peranti penerimaan iPhone 15/iOS 18.1.1. Skrip `register-ios-staging.php --check` boleh digunakan untuk semakan read-only; tiada perubahan production dibuat.

## Mod ujian staging terbuka — 30 September 2026

Pemilik meluluskan semua akaun yang layak dan URL ujian sentiasa tersedia. Kod serta ujian simulasi siap; pengaktifan host masih perlu dilakukan pentadbir:

```sh
cd /var/www/oneid-uat
sudo python3 tools/mobile-oidc-hosted/pilot-control.py open
```

Boleh dijalankan dari keadaan pilot ON atau OFF. Jangan perlu jalankan stop/retry dahulu. Skrip menyemak route dikenali, client browser sedia ada dan target DB staging sahaja. Ia membuang `pilot_identifiers` dan `pilot_until`, mengekalkan pengesahan akaun aktif/polisi/MFA, membuka route OIDC/mobile melalui virtual host staging, dan memasang servis ujian berterusan yang di-enable pada boot. Hydra/PostgreSQL tetap loopback; token hook tetap loopback dan menggunakan kunci. Konfigurasi login web tidak diubah. Client Flutter masih memerlukan pendaftaran berasingan.

URL mula login: https://oneid-uat.upnm.edu.my/mobile-test/ — browser biasa, tanpa SSH tunnel atau host-resolver override. Penguji masih memerlukan laluan rangkaian ke hostname staging. Jangan buka `/login` secara terus tanpa authorization request.

Observer dihidupkan secara berterusan. Jika sebelumnya OFF, identity epoch diselaraskan sekali dan sesi lama mungkin perlu login semula; jika sudah ON, epoch tidak di-reset. Pengaktifan menjalankan semula proses harness, jadi sesi ujian dalam memorinya perlu login semula. Harness kekal alat ujian: sesi browser satu jam dan token hanya dalam memori; had kapasiti 500 sesi serentak bukan allowlist pengguna. Ketersediaan URL tidak bermaksud setiap sesi login kekal tanpa tamat tempoh. Selepas restart server, URL hidup semula tetapi sesi harness perlu login semula.

Jika pemasangan gagal, skrip cuba menutup hosted/observer, memulihkan route dormant dan menghentikan servis ujian. Untuk menutup secara sengaja:

```sh
sudo python3 tools/mobile-oidc-hosted/pilot-control.py stop
```

Untuk membuka kembali mod ini gunakan `open`, bukan `retry`. Tidak perlu stop selepas setiap ujian jika mahu URL sentiasa tersedia.

Pengesahan: `tests/mobile-oidc-hosted/open-staging.py` menguji route/pengasingan endpoint, unit berterusan, padanan snippet host semasa, simulasi pengaktifan dan rollback tanpa perubahan pada host. Ujian harness juga lulus. Ujian Nginx/FPM sebenar dan capaian melalui VPN perlu disahkan selepas arahan sudo berjaya. Tiada perubahan host root dilaksanakan oleh sesi agent ini.
