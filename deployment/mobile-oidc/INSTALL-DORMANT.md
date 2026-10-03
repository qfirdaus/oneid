# Pemasangan awal melalui terminal pentadbir

Skrip ini hanya memasang pool PHP 8.3 khusus dan 12 lokasi Nginx yang semuanya memberi 404. Ia tidak memasang/menghidupkan Hydra, menjalankan migrasi database, mengubah credential atau membuka login mobile. Konfigurasi aplikasi mesti kekal OFF. Endpoint sengaja dihentikan di Nginx supaya provider yang belum dipasang tidak menghasilkan 502.

Semakan tanpa sudo:

```bash
cd /var/www/oneid-uat
python3 tools/mobile-oidc-hosted/install-dormant-staging.py
```

Pemasangan dalam terminal pentadbir pengguna:

```bash
cd /var/www/oneid-uat
sudo python3 tools/mobile-oidc-hosted/install-dormant-staging.py --apply
```

Skrip memastikan virtual host masih sepadan dengan versi yang disemak, membuat backup root-only di `/var/backups/oneid-mobile-uat/`, menguji FPM/Nginx, kemudian graceful reload kedua-dua servis. Reload FPM melibatkan servis PHP 8.3 bersama; pool web sedia ada tidak diubah. Skrip membuat permintaan HTTPS melalui loopback dengan verifikasi sijil sebenar dan memastikan 12 endpoint memberi 404. Tiada password diperlukan di dalam skrip atau chat.

Jika semakan selepas penulisan gagal, skrip cuba memulihkan konfigurasi lama dan reload semula jika reload sudah dicuba. Jika proses dimatikan secara paksa atau pemulihan gagal, pentadbir perlu memulihkan `oneid-uat.conf` daripada path backup yang dicetak, membuang pool `/etc/php/8.3/fpm/pool.d/oneid-mobile-uat.conf` dan snippet `/etc/nginx/snippets/oneid-mobile-uat-dormant.conf` yang baru dicipta, kemudian menguji konfigurasi sebelum reload. Jangan membuang fail lain.

Skrip menolak pemasangan semula jika pool/snippet sudah wujud; semak hasil pemasangan dahulu. Output `SUCCESS` bermakna routing dormant tersedia, bukan integrasi Flutter sudah aktif. Selepas itu masih perlu penyediaan provider kekal, secrets, migrasi staging, polisi MFA dan client pilot.

Semakan pembangunan lulus: preflight baca sahaja pada server semasa, pemeliharaan routing asal, penolakan drift konfigurasi, atomic write pada fail sementara dan `nginx -t` salinan terasing menggunakan sijil ujian/port tinggi. Pemasangan root, reload sebenar dan pemulihan kegagalan pada servis sebenar belum dijalankan oleh agent.
