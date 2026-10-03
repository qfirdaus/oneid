# Fasa 4 — Ujian aplikasi melalui FPM terpencil

Tarikh: 3 Oktober 2026. Skop: OneID UAT sahaja.

## Kaedah

`python3 -B tools/php84_fpm_fixture_suite.py` menjalankan 10 suite fixture yang disemak melalui socket `oneid-web-uat84` dan `oneid-mobile-uat84`. Salinan PHP sementara berada di luar web root, dengan sekatan CLI dibuang pada salinan sahaja. Fail ujian asal tidak diubah. E_ALL dan error handler menjadikan amaran PHP sebagai kegagalan.

Dua mesej error_log yang dijangka daripada senario negatif polisi sesi dibenarkan hanya untuk suite berkenaan; stderr lain tetap menggagalkan ujian. Keputusan berstruktur berada dalam `phase4-fpm-fixtures.json`. Probe runtime berada dalam `phase4-fpm-runtime.json`.

Liputan fixture: asas dan callback MyDigital ID web, sesi dan penolakan/logout; polisi tamat sesi; adapter mobile termasuk MFA dan akaun tidak aktif; MyDigital ID mobile, profil, protokol hosted dan pilihan akaun. Provider dan stor ujian menggunakan fixture. Ini bukan bukti komunikasi dengan provider sebenar.

## Keputusan

10/10 suite lulus melalui FPM 8.4.26. Tiada stderr tidak dijangka dalam suite ini. Kegagalan awal harness akibat dua mesej log negatif yang dijangka telah diperbetulkan; ujian semula penuh lulus.

## Pengasingan

Semakan routing selepas ujian: vhost web masih menggunakan `php8.3-fpm.sock`; snippet mobile masih menggunakan `oneid-mobile-uat.sock`. Default php, phar dan phar.phar masih 8.3. Tiada perubahan trafik, servis PHP 8.3 atau aplikasi lain dibuat oleh harness. Tiada kod aplikasi diubah dalam langkah ini.

## Baki sebelum Fasa 4 ditutup / pertukaran trafik

1. Sediakan laluan ujian browser terhad yang menggunakan pool 8.4 untuk keseluruhan aliran, termasuk callback. Akses URL UAT biasa sekarang masih menguji 8.3. Jangan anggap login pada URL biasa sebagai bukti 8.4.
2. Ulang login web/mobile, MyDigital ID sebenar, sesi, refresh, logout dan lupa kata laluan/SMTP di laluan terpencil; sahkan sambungan database/ODBC sebenar yang digunakan.
3. Ulang sistem downstream dalam matriks baseline termasuk penolakan akses dan token tamat, tanpa perubahan kod downstream. Lengkapkan pengesahan runtime remote.
4. Ukur prestasi authenticated 8.3 berbanding 8.4 dan tetapkan kriteria penerimaan. Masa fixture bukan latency pengguna.

Fasa 4 belum lengkap dengan fixture sahaja. Pertukaran trafik belum dilakukan dan belum dibenarkan oleh keputusan ini.

## Persediaan browser dan smoke HTTP sebenar

Lihat `BROWSER-TEST.md`. Renderer listener loopback dan smoke tanpa root telah diuji menggunakan Nginx sementara, sijil ujian yang dipercayai secara eksplisit, serta pool FPM sebenar. Empat endpoint lulus (`phase4-http-smoke.json`); ini ujian unauthenticated, bukan login sebenar. Listener tetap memerlukan pelaksanaan sudo. Skop listener ialah browser/front-channel; backend Hydra/harness/downstream kekal pada routing sedia ada, bukan dianggap lulus 8.4.


Listener tetap dipasang oleh pengguna pada 3 Oktober 2026 13:01. Agent mengesahkan binding 127.0.0.1:24484, routing awam masih pada socket 8.3, dan empat smoke checks lulus semula dengan sijil UAT sebenar. Lihat `phase4-browser-listener-installed.json`. Ujian authenticated masih belum dilaksanakan.


## Pengesahan manual web diterima — 3 Oktober 2026

Pengguna mengesahkan empat ujian lulus pada laluan browser terpencil: login ID/kata laluan ke dashboard; lanjut sesi dengan + dan kebolehgunaan/scroll; logout/login semula; logout/login MyDigital ID. Status PASS_USER_REPORTED, lihat `phase4-web-manual-validation.json`. Marker runtime untuk GET / telah diberikan sebelum ujian; marker dashboard/callback tidak diberikan secara berasingan. Ini menggantikan status ujian web belum dilaksanakan di atas, tetapi tidak menutup ujian native mobile, backend downstream, SMTP atau prestasi authenticated.

## Rehearsal integrasi mobile FPM dan kontrak API lama

`tools/php84_mobile_integration.py --uat-only` menyediakan proses FPM 8.4.26 dan Nginx sementara, dengan Hydra, PostgreSQL dan MySQL persendirian. Token hook, login hosted, sesi, refresh dan logout semuanya melalui FPM persendirian ini. Identiti, OTP dan pangkalan data adalah sintetik. Guard fixture cli-server dalam entrypoint hanya disesuaikan pada salinan private kepada fpm-fcgi; kod aplikasi aktif tidak diubah. Ini meluaskan bukti daripada unit fixture kepada integrasi provider sebenar secara setempat, bukan native Flutter atau MyDigital ID sebenar.

Keputusan terkini: `phase4-mobile-fpm-integration.json`. Jalankan semula dengan:

```sh
python3 -B tools/php84_mobile_integration.py --uat-only
```

`tools/php84_legacy_negative_parity.py` membandingkan API SSO lama pada route asal 8.3 dan listener 8.4 untuk tiga kes: body tiada, JSON rosak, token sintetik yang tidak wujud. Semua respons ditolak dan kontrak JSON/status sama selepas mengecualikan nilai request_id per permintaan sahaja (kehadiran dan jenis masih disemak). Token tidak wujud mengembalikan HTTP 200 dengan respond=0 mengikut kontrak lama, bukan kejayaan autentikasi. Bukti: `phase4-legacy-api-negative-parity.json`. Ujian ini tidak mengesahkan token sah, akses downstream sebenar atau semua versi PHP remote.

Baki: keputusan launch ODL/SAP/e-BDR daripada pengguna belum diterima; validasi back-channel legacy dengan token sah pada 8.4; inventori remote; ujian mobile peranti sebenar pada route yang disahkan; lupa kata laluan/SMTP; pengukuran authenticated 8.3 lawan 8.4. Tiada pertukaran trafik dibuat.


## Pengesahan launch downstream diterima

Pengguna mengesahkan login OneID melalui browser tunnel PHP 8.4, kemudian klik ODL, SAP dan e-BDR: ketiga-tiganya terus membuka sesi authenticated downstream. Status PASS_USER_REPORTED; lihat `phase4-downstream-launch-manual.json`. Ini menggantikan nota keputusan launch belum diterima di atas. Back-channel downstream masih melalui routing PHP 8.3; validasi token sah pada 8.4, versi runtime remote, SMTP, native mobile dan prestasi authenticated masih belum ditutup.

## Persediaan validasi token sah

Skrip `tools/php84_valid_token_check.py --run` meminta nilai cookie `sso_cre` secara tersembunyi pada terminal interaktif. Jangan masukkan token dalam chat, argv atau fail. Helper PHP membaca input melalui stdin, menghadkan akaun kepada 0530-09, membaca database dalam mod transaksi read-only, menolak token lama/plaintext, token yang hampir tamat (kurang 10 minit), dan token dengan polisi revocation tertunda. Ia menghantar dua POST TLS yang disahkan ke API sebenar 8.3 dan listener 8.4 menggunakan kontrak site_id=IDP. Respons penuh dan data pengguna kekal dalam memori; laporan hanya boolean selamat. Rekod token dibandingkan sebelum/selepas. Audit akses API biasa masih boleh dicatat oleh aplikasi.

Sintaks PHP lulus dan input tidak sah ditolak dalam preflight. Ujian token sah BELUM dijalankan: memerlukan pengguna memasukkan nilai cookie sendiri melalui terminal. Hash token dalam database bukan token asal dan tidak boleh digunakan menggantikannya. Skop ujian ialah penerimaan token sah/kontrak packet; ia tidak membuktikan ACL per aplikasi atau runtime remote.


Pembetulan harness token sah: semakan nombor staf tersilap menggunakan data2; schema aplikasi menggunakan data3. Skrip kini memadankan TRIM(data3)=0530-09 sahaja dan membezakan TOKEN_NOT_FOUND daripada akaun tidak sepadan. Semakan SELECT read-only mengesahkan satu akaun sepadan; sintaks PHP lulus. Keputusan STOP terdahulu bukan bukti kegagalan API 8.4. Ujian token sah perlu diulang oleh pengguna; tiada token screenshot digunakan atau disimpan.


## Validasi token sah selesai — 3 Oktober 2026

Pengguna menjalankan semula harness yang diperbetulkan. Laporan `phase4-valid-token-api.json` disemak: PASS untuk penerimaan token sedia ada pada 8.3 dan 8.4, marker listener 8.4, padanan kunci respons, padanan packet pengguna dan rekod token tidak berubah. Ini menggantikan status ujian token sah belum dijalankan di atas. Skop terhad kepada kontrak site_id=IDP; ACL per aplikasi dan pelaksanaan kod pada runtime remote masih belum disahkan oleh ujian ini. Trafik awam tidak ditukar.

Baki gate Fasa 4: validasi dengan credential/ACL aplikasi sebenar termasuk penolakan dan token tamat pada 8.4; inventori runtime downstream remote; mobile pada peranti sebenar dengan laluan 8.4 yang dibuktikan; lupa kata laluan/SMTP dan sambungan ODBC yang berkaitan; perbandingan prestasi authenticated serta kriteria penerimaan.


## Persediaan ACL aplikasi

Mod `python3 -B tools/php84_valid_token_check.py --run --applications` menggunakan token akaun yang dimasukkan secara tersembunyi untuk membandingkan credential/ACL ODL, SAP dan e-BDR pada API 8.3/8.4. Preflight read-only mendapati ketiga-tiga akses dibenarkan dan satu aplikasi SSO aktif sedia ada ditolak; tiada ACL diubah. Credential legacy/terenkripsi diselesaikan dalam memori sahaja; bahan credential tidak dicetak/disimpan. Kes penolakan mesti menghasilkan respond=0, tanpa packet pengguna, dan sebab Site not allowed; credential tidak sah sahaja tidak dikira sebagai lulus ujian ACL.

Sintaks helper lulus; preflight target/credential/ACL lulus. Pelaksanaan dengan token sebenar masih menunggu input tersembunyi pengguna. Laporan berasingan `phase4-application-acl-api.json` tidak menimpa bukti IDP sebelumnya. Ini ujian langsung API OneID, bukan bukti kod remote dijalankan pada setiap versi PHP downstream.


## Validasi credential/ACL aplikasi selesai — 3 Oktober 2026

Laporan `phase4-application-acl-api.json` disemak selepas pelaksanaan pengguna: keempat-empat kes PASS. ODL, SAP dan e-BDR diterima menggunakan credential berdaftar; satu aplikasi SSO aktif yang memang tidak dibenarkan ditolak pada kedua-dua runtime. Kunci respons dan packet pengguna sepadan; marker 8.4 disahkan; rekod token tidak berubah. Ini menggantikan status menunggu input token dalam seksyen sebelumnya.

Gate penerimaan token aktif dan ACL untuk tiga aplikasi ini kini mempunyai bukti API langsung 8.4. Bukti ini tidak meliputi pelaksanaan kod downstream remote atau semua aplikasi dalam matriks. Baki: token tamat/refresh legacy pada 8.4, runtime remote dan baki matriks integrasi, mobile peranti sebenar, lupa kata laluan/SMTP dan ODBC berkaitan, serta prestasi authenticated dan kriteria penerimaan. Trafik awam kekal 8.3; tiada cutover dibuat oleh langkah dokumentasi ini.


## Dapatan token tamat / legacy refresh — gate belum lulus

Lapan senario sintetik API dijalankan melalui FPM 8.3 dan 8.4: semua mempunyai parity, tetapi tiga gagal jangkaan keselamatan kerana refresh mengeluarkan token baharu untuk revoked token, ACL denied dan due policy revocation. Lima senario lain lulus. Lihat `LEGACY-REFRESH-FINDING.md` dan `phase4-legacy-expiry-fixtures.json`. Ini kelemahan kod sedia ada pada kedua-dua runtime, bukan regresi 8.4. Tiada pembetulan kod aplikasi atau perubahan trafik dibuat. Gate cutover tidak boleh ditutup sebelum remediation dan pengujian semula.


## Pembetulan refresh legacy UAT

Pembetulan status/ACL serta refresh bertransaksi telah dibuat pada api.php dan lib/Database.php, dikongsi oleh kedua-dua route UAT. Semua 8 senario cabang API lulus melalui kedua-dua FPM; 10 ujian database persendirian bagi setiap runtime lulus termasuk rollback dan refresh serentak; 3 kes negatif API sebenar kekal sepadan. Lihat `LEGACY-REFRESH-FINDING.md`, `phase4-legacy-expiry-fixtures.json`, `phase4-legacy-refresh-database.json`. Tiada token sebenar diubah oleh ujian. Pengesahan token aktif/ACL sebenar perlu diulang selepas pembetulan; baki gate Fasa 4 kekal.


## Regresi token aktif/ACL selepas pembetulan lulus

Pengguna mengulang ujian pada 3 Oktober 2026 selepas pembetulan refresh. Keempat-empat kes PASS: ODL/SAP/e-BDR diterima dan aplikasi tanpa akses ditolak; struktur respons/packet sepadan pada 8.3/8.4, marker 8.4 disahkan, rekod token tidak berubah. Bukti tersimpan berasingan dalam `phase4-post-refresh-fix-acl.json` bersama hash kod semasa. Keperluan ulang ujian aktif/ACL selepas pembetulan kini selesai. Baki gate termasuk prestasi authenticated, mobile peranti sebenar pada routing 8.4 yang disahkan, SMTP/ODBC berkaitan dan runtime/skop downstream remote. Tiada cutover dilakukan.


## Persediaan ukuran authenticated API

Mod `python3 -B tools/php84_valid_token_check.py --run --benchmark` meminta token secara tersembunyi, kemudian menjalankan satu warmup dan lima sampel bagi setiap runtime, berselang-seli dengan urutan dibalikkan setiap round. Setiap permintaan menggunakan sambungan cURL baharu melalui loopback TLS yang disahkan. Semakan akaun/status token, kesepadanan respons/packet, marker 8.4 dan rekod token tidak berubah dikekalkan. Laporan berasingan `phase4-authenticated-api-timing.json` hanya mengandungi masa/boolean, bukan token atau packet pengguna.

Sintaks PHP 8.3/8.4 dan Python lulus, input tidak sah ditolak sebelum HTTP. Pelaksanaan authenticated menunggu input pengguna. Status MEASURED bermaksud ukuran berjaya; bukan kelulusan prestasi. Sampel kecil/serial ini tidak meliputi masa login browser, ACL aplikasi, rangkaian pengguna atau kapasiti beban. Pool 8.3 turut melayan trafik biasa manakala pool 8.4 terpencil; tafsir perbezaan dengan had ini. Kriteria penerimaan prestasi masih belum dipersetujui.


## Ukuran authenticated API diterima — 3 Oktober 2026

Laporan `phase4-authenticated-api-timing.json` disemak: semua semakan fungsi lulus dan rekod token tidak berubah. Lima sampel terukur setiap runtime selepas warmup: median 8.3 68.024 ms, 8.4 71.431 ms; delta +3.407 ms (+5.01%). Julat sampel bertindih. Ini pemerhatian sampel kecil, bukan bukti regresi signifikan atau ujian kapasiti. Prestasi kekal NOT_ASSESSED kerana tiada ambang penerimaan dipersetujui; pengukuran ini tidak meliputi keseluruhan login browser/ACL aplikasi.


## Skop ujian dikurangkan atas permintaan pengguna

Reset password/SMTP dilaporkan berjaya. Penjelasan pengguna: ujian mobile melalui /mobile-test/, bukan bukti aplikasi native atau semua backend 8.4. Pengguna meminta kurang ujian dan fasa seterusnya. Persediaan cutover/rollback berada dalam `PHASE5.md`; baki gate tidak ditandakan lulus secara andaian. Ujian integrasi mobile private yang lulus digunakan tanpa membina harness baharu.
