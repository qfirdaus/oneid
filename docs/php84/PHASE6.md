# Fasa 6 — pemerhatian UAT selepas cutover

Status: snapshot awal selesai; belum bukti kestabilan jangka panjang.

`phase6-initial-monitor.json` merekod semakan selepas cutover semula 14:10:22 pada 3 Oktober 2026. Nginx/FPM 8.3/FPM 8.4 aktif; NRestarts=0. Tiada HTTP 5xx dalam access logs semasa yang dibaca. Enam rekod error log web ialah audit oneid_integration api_access dalam mode observe; bukan PHP fatal/warning. Log FPM utama root-only belum dapat dibaca oleh agent, jadi ketiadaan error worker/pool belum disahkan sepenuhnya.

## Satu arahan pemantauan tanpa mengubah sistem

```sh
cd /var/www/oneid-uat
sudo python3 -B tools/php84_uat_monitor.py
```

Output hanya kiraan/status, tanpa baris log mentah, credential, cookie atau identiti. Tarikh mula default ialah cutover semula; gunakan --since ISO_LOCAL jika diperlukan. Skrip tidak memasang daemon/timer dan bukan pemantauan berterusan. Ia hanya membaca log semasa, bukan log rotated. Perhatikan HTTP 5xx, fatal/deprecation, timeout, pool max_children, worker signal dan restart. Jika simptom baharu muncul, siasat sebelum meneruskan production; backup rollback terakhir berada dalam PHASE5.md.

## Batas runtime

Web/API/mobile UAT telah dipindahkan ke FPM 8.4.26. Default CLI dan cron pengguna iqs untuk cron/run_conditional_external_sync.php masih memanggil /usr/bin/php (8.3). Ini disengajakan untuk skop cutover semasa; bukan dakwaan semua kerja OneID telah berpindah ke 8.4. Jangan tukar alternatives global atau cron sistem lain. Cron/ODBC perlu pengesahan khusus sebelum migrasi CLI berasingan.

Tiada ujian pengguna tambahan diminta untuk snapshot ini. Gunakan UAT seperti biasa sepanjang tempoh pemerhatian yang dipersetujui; belum ada SLA/tempoh minimum dipersetujui. Production, inventori remote, native-device dan prestasi formal kekal di luar pengesahan lengkap semasa.


## Root snapshot diterima — 14:18:19

Pengguna memberikan output sudo: semua servis aktif, NRestarts=0, tiada HTTP 5xx; enam rekod web ialah audit integrasi. Log FPM 8.4 boleh dibaca dan mengandungi SATU pool_capacity_warning sejak cutover. Pool dan masa tepat belum dikenal pasti daripada ringkasan. Kedua-dua pool khusus OneID max_children=4; pool www juga wujud, maka jangan andaikan pool tertentu. Semakan server menunjukkan kira-kira 13.4 GiB RAM available dan swap tidak digunakan pada waktu semakan, bukan ukuran peak. Tiada tetapan kapasiti diubah; perlu baris warning max_children yang khusus. Bukti pengguna diringkaskan dalam `phase6-root-monitor-summary.json`.


## Pool kapasiti dikenal pasti

Output pengguna mengesahkan oneid-web-uat84 mencapai max_children=4 pada 13:28, 13:36, 13:52, 13:57 dan 14:12:24; hanya kejadian terakhir selepas cutover semula. Ini menunjukkan had concurrency dicapai, bukan bukti PHP crash atau semua permintaan gagal. Tiada amaran mobile dalam lima baris yang diberi.

Disediakan `tools/php84_web_capacity.py`: default preflight, --apply root meningkatkan web sahaja daripada 4 kepada 8. RAM available semasa kira-kira 13.4 GiB, swap kosong; ini snapshot bukan worst-case keseluruhan server. Pool kekal ondemand, tiada perubahan memory_limit atau mobile/8.3/Nginx. Skrip backup, syntax test, graceful reload FPM 8.4, probe dan auto-rollback jika gagal. Reload menyentuh master FPM 8.4 termasuk worker pool lain, tetapi konfigurasi pool lain tidak diubah. Belum diaplikasi; perlu pelaksanaan sudo pengguna. Amaran lama kekal dalam log; selepas pelarasan tapis berdasarkan masa baru untuk menilai kejadian baharu.


## Pelarasan kapasiti dipasang

Pengguna melaksanakan --apply pada 14:22:21; backup `/var/backups/oneid-php84-web-capacity-20261003-142220`. Semakan agent: web max_children=8, mobile=4, FPM web 8.4.26, default CLI 8.3, enam endpoint HTTP lulus. Bukti `phase6-web-capacity-applied.json`. Pemerhatian amaran baharu hendaklah bermula selepas 14:22:21, bukan mengira semula amaran lama. Ini belum pengesahan kapasiti bawah beban.
