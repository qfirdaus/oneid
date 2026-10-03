# Fasa 5 — cutover terkawal OneID UAT

Status: skrip disediakan dan preflight read-only lulus; trafik belum ditukar. Pengguna meminta mengurangkan ujian dan meneruskan fasa seterusnya. Ini tidak menandakan semua gate lama lulus.

## Skop perubahan konkrit

`tools/php84_uat_cutover.py` menggantikan satu handler web daripada php8.3-fpm.sock kepada oneid-web-uat84.sock, serta tujuh handler mobile daripada oneid-mobile-uat.sock kepada oneid-mobile-uat84.sock. Semua perubahan hanya pada vhost/snippet OneID UAT. Hook Hydra serta panggilan backend mobile-test ke hostname UAT kemudian melalui 8.4 juga. Hydra sendiri bukan PHP.

Default CLI PHP/phar, cron, pool 8.3, database, hostname, credential dan aplikasi lain tidak ditukar. Tiada deployment production. Pool web 8.4 mempunyai max_children=4: sesuai untuk pilot UAT terkawal, belum pengesahan kapasiti production.

## Bukti dan batas skop dikurangkan

- Web/password/MyDigital ID/sesi/logout dan reset password/SMTP: PASS_USER_REPORTED melalui laluan browser ujian. Bukti header khusus bagi setiap langkah tidak dikumpul.
- ODL/SAP/e-BDR launch dan API credential/ACL/penolakan: lulus. Refresh diperbetulkan dengan ujian FPM dan private MySQL.
- 126 semakan integrasi mobile FPM: lulus menggunakan provider/database private. Tidak membina harness tambahan.
- Ujian pengguna mobile-test sebelum cutover bukan native-device E2E 8.4. Semakan ringkas mobile-test selepas cutover akan meliputi backend UAT sebenar 8.4.
- Median authenticated API +3.407 ms (+5.01%); hanya lima sampel. Tiada kriteria prestasi dipersetujui; belum lulus gate prestasi formal.
- Inventori runtime remote dan ODBC sebenar masih belum lengkap. Jangan nyatakan semua sistem atau production terbukti serasi.

Arahan terdahulu pengguna mengekalkan remote/runtime dan prestasi authenticated sebagai syarat sebelum pertukaran trafik. Persediaan boleh diteruskan; penerimaan eksplisit baki batas UAT diperlukan sebelum menggugurkan syarat tersebut untuk cutover terkawal.

## Pelaksanaan selepas penerimaan skop baki

```sh
cd /var/www/oneid-uat
python3 -B tools/php84_uat_cutover.py
sudo python3 -B tools/php84_uat_cutover.py --apply
```

Skrip menyimpan backup/hashes, menguji sintaks Nginx, reload, dan memeriksa enam endpoint tanpa login. Kegagalan pelaksanaan mengembalikan dua fail asal dan reload semula. Hash konfigurasi PHP dan Nginx lain dibandingkan. Preflight semasa lulus; apply/rollback privileged belum dijalankan atau direhearsal sebagai root.

## Satu semakan pengguna selepas cutover

Buang entry hosts pada komputer penguji, hentikan tunnel dan buka profil browser baharu supaya ujian menuju route awam sebenar.

1. Login OneID dan buka satu downstream (ODL/SAP/e-BDR).
2. Buka /mobile-test/, login, semak sesi, uji refresh, logout.

Jika gagal, rollback menggunakan arahan yang dicetak oleh pemasang:

```sh
sudo python3 -B tools/php84_uat_cutover.py --rollback /var/backups/oneid-php84-cutover-YYYYMMDD-HHMMSS
```

Rollback memulihkan routing PHP 8.3 sahaja, bukan data/token/perubahan kod. Skrip menolak rollback jika konfigurasi sasaran berubah di luar snapshot. Listener loopback 24484 boleh dikekalkan untuk diagnosis; ia bukan laluan ujian selepas cutover. Tiada kelulusan production tersirat.


## Skop diterima pengguna

Pengguna menjawab setuju untuk cutover UAT dengan penangguhan pengesahan remote/ODBC, aplikasi native dan prestasi formal seperti yang diterangkan. Lihat `phase5-uat-scope-acceptance.json`. Preflight diulang dan lulus; routing masih 8.3. Pelaksanaan --apply memerlukan kata laluan sudo dalam terminal pengguna. Tiada pengesahan tambahan diperlukan untuk skop ini.


## Cutover dilaksanakan dan disahkan

Pengguna melaksanakan --apply pada 3 Oktober 2026 14:06. Backup: `/var/backups/oneid-php84-cutover-20261003-140643`. Semakan agent: routing web/mobile menunjuk kepada socket 8.4, kedua-dua pool melaporkan 8.4.26, default php/phar kekal 8.3; enam semakan HTTP diulang dan lulus. Lihat `phase5-cutover-verification.json`. Semakan pengguna selepas cutover masih menunggu.


## Status terkini: rollback ke PHP 8.3

Pengguna menjalankan rollback menggunakan backup cutover-20261003-140643. Agent mengesahkan routing web/mobile kembali ke socket 8.3 dan enam semakan HTTP lulus. Sebab rollback belum diberikan; jangan anggap kegagalan aplikasi. Kod/database tidak dirollback. PHP 8.4 dan listener terpencil masih disediakan. Tiada pertukaran semula tanpa arahan pengguna. Bukti: `phase5-rollback-verification.json`.


## Status terkini: cutover semula ke 8.4 disahkan

Pengguna menjelaskan rollback terdahulu tersilap dijalankan, bukan kegagalan aplikasi. --apply dijalankan semula pada 3 Oktober 2026 14:10. Backup terkini `/var/backups/oneid-php84-cutover-20261003-141022`. Agent mengesahkan 1 handler web dan 7 handler mobile ke pool 8.4.26, CLI masih 8.3 serta enam pemeriksaan HTTP lulus. Semakan pengguna selepas cutover masih menunggu. Bukti: `phase5-cutover-reapplied.json`.


## Fasa 5 selesai dalam skop UAT terkawal

Pengguna mengesahkan entry hosts ujian dibuang/tunnel dihentikan, login web dan satu downstream melalui URL biasa berjaya, serta mobile-test login/sesi/refresh/logout berjaya. Status PASS_USER_REPORTED; bukti `phase5-post-cutover-manual.json`. Digabungkan dengan pengesahan routing/pool sebelumnya, cutover terkawal OneID UAT ke 8.4.26 selesai. Baki skop yang dipersetujui ditangguhkan kekal terbuka, bukan dianggap lulus. Production tidak diubah; rollback backup terkini kekal tersedia.
