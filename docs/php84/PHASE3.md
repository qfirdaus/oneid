# Fasa 3 — Penyediaan PHP 8.4 secara selari

Status: pengguna menjalankan pemasangan root pada 3 Oktober 2026; PHP 8.4.26 CLI/FPM aktif secara selari. Routing web/mobile OneID kekal pada pool 8.3. Semakan lanjut sebelum trafik masih diperlukan.

## Semak / pasang

Dari /var/www/oneid-uat:

```sh
python3 tools/prepare_php84_parallel.py
sudo python3 tools/prepare_php84_parallel.py --apply
```

Skrip memasang versi tepat 8.4.26 daripada repositori sedia ada tanpa apt upgrade. Ia menolak plan yang membuang pakej atau mengubah pakej selain php8.4-*. Default alternatif `php` dipin kepada 8.3 SEBELUM pemasangan; ini menukar mod alternatives kepada manual untuk mengelakkan pemasangan CLI 8.4 mengalih default server. Keadaan asal disimpan di /var/backups/oneid-php84-phase3/before.json.

Pool baharu: oneid-web-uat84.sock dan oneid-mobile-uat84.sock. Pool mobile menyalin tetapan pool 8.3 sedia ada dengan nama/socket berbeza. Pool web ialah pool ujian konservatif (ondemand, max_children 4), BUKAN pengesahan kapasiti produksi. Servis PHP 8.4 dimulakan; pakej FPM juga mungkin menyediakan pool www defaultnya yang tidak dirujuk trafik. Nginx, cron dan konfigurasi PHP 8.3 tidak diedit/reload oleh skrip. Paket boleh menjalankan maintainer hooks biasa; plan mesti disemak sebelum apply.

## Selepas pemasangan

Hantar output akhir kepada agent. Semak PHP 8.3 masih default dan aktif, binary 8.4.26, kedua-dua socket pool baharu, extension CLI/FPM dan log startup. Kemudian jalankan ujian terpencil PHP 8.4. Jangan tukar fastcgi_pass atau cron lagi.

Jika gagal, skrip berhenti. Pemasangan pakej bukan transaksi rollback; jangan autoremove pakej atau reset konfigurasi secara rambang. Config pool baharu yang gagal syntax test dibuang jika baru dicipta. Snapshot konfigurasi asal tersedia untuk semakan; PHP 8.3 dan routing asal mesti dikekalkan. Jika perlu hentikan PHP 8.4 sahaja selepas memastikan tiada trafik dirujuk kepadanya.

Baki gate: runtime downstream remote, prestasi authenticated, perbandingan php.ini/session/extensions, SMTP/ODBC dan suite 8.4. Fasa 3 belum selesai sebelum output pemasangan serta pengesahan diterima.


## Pengesahan selepas pemasangan

CLI default 8.3.35; CLI 8.4.26 tersedia; kedua-dua servis FPM aktif; socket oneid-web-uat84 dan oneid-mobile-uat84 wujud. 1,251 fail lulus lint PHP 8.4 dengan E_ALL tanpa warning/deprecation. Lihat phase3-runtime-check.json dan phase3-php84-tests.json untuk keputusan ujian selepas selesai.

Pemasang pakej menukar alternatives phar/phar.phar kepada 8.4. Default php kekal 8.3. Untuk pengasingan CLI lengkap, pentadbir boleh memulihkan phar kepada 8.3:

```sh
sudo update-alternatives --set phar /usr/bin/phar8.3
sudo update-alternatives --set phar.phar /usr/bin/phar.phar8.3
```

Perbezaan php.ini FPM baharu yang mesti diselaraskan pada pool OneID sebelum trafik: memory_limit 512M→128M, execution 120→30s, upload 100M→2M, post 100M→8M, session gc 28800→1440s, secure/httponly global lama tidak dibawa ke default baharu. Override aplikasi/pool mungkin mengubah nilai efektif; jangan anggap nilai ini semuanya efektif pada mobile. Session ID defaults berubah; jangan salin sid_length/sid_bits lama yang deprecated. Belum membuat perubahan tetapan ini.


Alternatives dipulihkan oleh pengguna dan disahkan semula: php resolves to php8.3; phar dan phar.phar resolves to phar8.3.phar. Kedua-dua servis FPM aktif. Penyelarasan pool dan ujian FPM terpencil masih belum selesai; routing tidak diubah.

## Penyelarasan pool khusus

Ujian FastCGI terus (fail sementara di luar web root, tanpa bootstrap aplikasi/akses database) mengesahkan kedua-dua pool 8.4.26 dan pool lama 8.3.35; nilai sebenar direkod dalam fpm-before-alignment.json.

```sh
cd /var/www/oneid-uat
python3 -B tools/align_php84_pools.py
sudo python3 -B tools/align_php84_pools.py --apply
```

Skrip membaca nilai efektif pool 8.3 melalui socket dan memadankan 8 tetapan sahaja pada pool 8.4. Tetapan session menggunakan php_value supaya aplikasi masih boleh menetapkan GC/session policy sendiri. Tidak menyalin session.sid_length/bits yang deprecated. Backup root /var/backups/oneid-php84-alignment-*. Syntax test, reload 8.4 sahaja, probe nilai selepas reload dan rollback pool jika gagal. Nginx/PHP8.3 tidak diedit. Menunggu pelaksanaan root; semakan awal sahaja telah dijalankan oleh agent.


## Penyelarasan disahkan selesai

Pengguna melaksanakan apply pada 3 Oktober 2026 12:44 waktu Malaysia. Agent mengesahkan semula melalui FastCGI: kedua-dua pool 8.4.26 sepadan dengan lapan tetapan baseline masing-masing. Bukti: `fpm-after-alignment.json`. Backup: /var/backups/oneid-php84-alignment-20261003-124453. Default PHP dan routing OneID masih 8.3. Penyediaan selari Fasa 3 selesai; ujian aplikasi/FPM hujung ke hujung dan gate cutover belum selesai.
