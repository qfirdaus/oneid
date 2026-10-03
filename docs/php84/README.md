# Fasa 1 — Baseline naik taraf OneID UAT ke PHP 8.4.26

Tarikh: 3 Oktober 2026 (Asia/Kuala_Lumpur).
Status: inventori teknikal dan baseline simulasi disediakan; baseline login downstream sebenar BELUM lengkap. Belum layak menukar trafik.

## Skop dan batas

Hanya OneID UAT dirancang untuk dinaik taraf. PHP sistem lain dan production tidak diubah. Kerja fasa ini membaca konfigurasi/kod, SELECT metadata aplikasi dan menjalankan ujian fixture. Tiada login sebenar, token sebenar, emel atau sync dicetuskan. Fail serahan tidak mengandungi key, kata laluan, token atau URL berparameter. Host berdaftar bukan bukti bahawa destinasi itu staging; sahkan sebelum ujian.

## Fail serahan

- `applications-baseline.json`: 44 rekod aplikasi; 34 SSO aktif, 8 bukan SSO aktif, 2 SSO tidak aktif. Nombor APP ialah rujukan snapshot sahaja, bukan ID/API credential.
- `application-test-matrix.csv`: keputusan setiap aplikasi yang perlu diisi sebelum/selepas upgrade. NON_SSO diuji sebagai pautan/log masuk berasingan, bukan dianggap SSO gagal. Rekod tidak aktif kekal tidak aktif.
- `downstream-code-baseline.json`: checksum 38 fail bernama sso*.php yang ditemui dalam projek tempatan. Ini calon integrasi sahaja, termasuk kemungkinan salinan lama; bukan bukti fail tersebut digunakan. Integrasi bernama lain dan server remote belum diliputi.
- `oneid-code-baseline.json`: checksum komponen kontrak OneID dan Composer.
- `test-baseline.json`: keputusan ujian fixture yang dijalankan semula pada PHP 8.3; bukan ujian PHP 8.4.

## Runtime dan pengasingan

- CLI semasa: PHP 8.3.35. FPM aktif: php8.3-fpm.
- Web: /etc/nginx/sites-enabled/oneid-uat → /run/php/php8.3-fpm.sock (dikongsi dengan vhost lain).
- Mobile: snippet /etc/nginx/snippets/oneid-mobile-uat-dormant.conf → /run/php/oneid-mobile-uat.sock; pool /etc/php/8.3/fpm/pool.d/oneid-mobile-uat.conf. Nama fail dormant tidak membuktikan laluan sedang ditutup.
- Cron pengguna iqs: setiap jam, cron/run_conditional_external_sync.php melalui /usr/bin/php dan flock external-sync.lock. Cron root/pengguna lain belum disahkan.
- Servis mobile Hydra/PostgreSQL dan harness Python tidak bertukar versi apabila PHP ditukar.
- PHP 8.4 CLI/FPM belum tersedia dalam semakan audit awal; sebahagian pakej common/intl 8.4.26 sudah ada. Semak semula keadaan pakej sebelum pemasangan.
- Cadangan: dua socket baharu khusus OneID web/mobile 8.4, kekalkan socket 8.3 dan default /usr/bin/php. Pindahkan cron OneID sahaja secara eksplisit selepas uji sumber luar.
- Semak extension curl, PDO MySQL, ODBC/PDO_DBLIB, mbstring, OpenSSL, Sodium, GD, XML, fileinfo dan yang diperlukan oleh job sebenar. Composer hanya menyenaraikan sebahagian keperluan runtime.

## Kontrak yang mesti dikekalkan

1. Dashboard menghantar go_to_service_provider kepada lib/q_func; respons status/domain dan keputusan ACL mesti sama.
2. Aliran legacy menggunakan sso_cre/new_sso_cre dan api.php; kekalkan nama parameter, cookie, redirect serta validation guard/scope sso:validate.
3. Kekalkan respond_flag, respond, respond_description dan respond_user_packet, jenis nilai serta tingkah laku pembaharuan token. Perbandingan dibuat menggunakan data redacted, jangan simpan token mentah.
4. Jangan tukar key, algorithma, domain cookie, TTL atau polisi MFA semasa membandingkan runtime.
5. Mobile OAuth diuji berasingan: authorization/callback PKCE, token, session, refresh, logout dan MyDigital ID. Jangan samakan protokol ini dengan legacy SSO.

## Pelaksanaan baseline pengguna

Untuk setiap aplikasi aktif, tentukan PIC, host staging sebenar, fail integrasi yang aktif dan versi PHP sebenar. Jangan anggap versi CLI sama dengan FPM downstream.

Gunakan akaun ujian staf/pelajar yang dibenarkan, tanpa merekod kata laluan. Isi CSV dengan PASS/FAIL/BLOCKED/NOT_APPLICABLE, tarikh, browser/peranti dan rujukan bukti redacted:

1. Login baharu OneID 8.3 → buka aplikasi → identiti/peranan betul tanpa login kedua bagi SSO.
2. Sesi sedia ada → buka aplikasi lain dan beberapa tab.
3. Token tamat tempoh/tidak sah → tingkah laku penolakan/pembaharuan mengikut kontrak asal.
4. Akaun tanpa ACL/disekat → akses ditolak. Jangan ubah akaun sebenar; gunakan fixture/akaun ujian khusus.
5. Logout/login semula → rekod semantik asal, jangan menganggap semua downstream menyokong global logout.
6. Catat masa login/token validation dan ralat. Prestasi sebenar belum diukur dalam Fasa 1 ini.
7. Ulang set yang sama pada 8.4 nanti, sahkan checksum kod downstream yang benar-benar digunakan tidak berubah.

Baseline tambahan: MFA emel/TOTP, MyDigital ID, forgot-password, admin step-up, renew sesi, mobile refresh/logout, muat naik dan bacaan sumber staf/pelajar. Ujian yang mengubah data atau menghantar emel dijadualkan berasingan dengan akaun ujian.

## Isu persediaan yang diketahui

Jumbojett v1.0.2 mempunyai implicitly-nullable parameters; PHPMailer manual v6.1.8 perlu diuji; bcrypt default 8.4 lebih mahal dan boleh mencetuskan rehash; semak tetapan sesi baharu; installer mobile hardcode php8.3-fpm. Pembetulan ini belum dibuat. Kekalkan PHP 8.3 compatibility untuk rollback.

## Syarat menutup Fasa 1

- PIC/versi runtime/pemetaan kod aktif dikenal pasti bagi semua integrasi dalam skop.
- Baseline E2E 8.3 lengkap, atau pengecualian dinyatakan dan diterima secara jelas.
- Baseline prestasi direkod dan kriteria penerimaan dipersetujui sebelum ujian 8.4.
- Tiada destinasi production diuji tanpa pengesahan skop.

Fasa 1 belum ditanda selesai selagi item di atas tertunggak. Fasa ini tidak memasang pakej, menukar routing Nginx atau mengubah kod aplikasi.

## Sambungan Fasa 1 — pemetaan tempatan

- `vhost-code-map.json`: vhost, document root, socket FPM dan rujukan statik entry point kepada kod SSO. Binary FPM terpasang disahkan 8.3.35; konfigurasi socket menunjukkan PHP 8.3. Ini bukan pengukuran versi dari permintaan PHP pada setiap laman.
- `application-runtime-map.json`: 9 daripada 44 rekod aplikasi sepadan tepat dengan server_name tempatan dan mempunyai rujukan entry point SSO. Dua rekod boleh merujuk host sama; jangan tafsir 9 sebagai 9 deployment unik.
- Baki 35 tidak sepadan: perlukan pengesahan host/akses PIC. Jangan tukar domain aplikasi untuk memaksa ujian.
- Contoh ketidakpadanan: rekod Survey ialah survey.upnm.edu.my, vhost tempatan survey-uat.upnm.edu.my; APEL myapel.upnm.edu.my berbanding apel-uat.upnm.edu.my. Ini belum disahkan sebagai kesilapan konfigurasi.
- `http-baseline.json`: satu GET tanpa login ke root setiap host yang sepadan melalui loopback, tanpa mengikuti redirect dan tanpa menyimpan body/cookie/token. Catatan status dan masa ialah baseline capaian sahaja; bukan bukti SSO atau benchmark prestasi.

### Prosedur baseline E2E dengan pengguna

1. Sahkan akaun ujian yang dibenarkan dan senarai ACL. Jangan berikan kata laluan kepada agent/dokumen.
2. Penguji login melalui browser OneID UAT, lengkapkan MFA jika diperlukan.
3. Buka aplikasi mengikut CSV satu demi satu; catat masa Malaysia, reference APP, PASS/FAIL serta sama ada landing dan identiti betul. Jangan tampal URL penuh yang mengandungi token.
4. Ulang dari sesi sedia ada, tab baharu, logout/login semula. Untuk ujian token tamat/akaun disekat gunakan akaun khusus dan penyelarasan PIC, bukan manipulasi akaun aktif.
5. PIC remote sahkan versi PHP FPM/web sebenar dan checksum fail integrasi aktif. Checksum tempatan sahaja tidak membuktikan salinan remote.
6. Tiada keputusan E2E ditanda PASS sehingga bukti diterima. Penguji memilih akaun sendiri 0530-09; password tidak diminta/disimpan.

Baki penghalang penutupan: pengesahan destinasi remote/PIC, ujian login E2E, prestasi authenticated dan kriteria penerimaan. Persediaan teknikal tempatan tidak menggantikan langkah ini.


## Bukti manual pengguna diterima

12 aplikasi mendapat PASS_USER_REPORTED untuk akses SSO biasa pada OneID UAT PHP 8.3. Lihat `manual-sso-baseline-20261003.md` dan kolum `sso_launch_83` dalam CSV. Senario sesi baharu/lama tidak dibezakan oleh penguji; kolum khusus tidak ditandakan lulus secara automatik. Ujian negatif, logout, prestasi dan PHP 8.4 masih tertunggak. Fasa 1 belum ditutup sepenuhnya.


## Baseline sesi pengguna diterima

Pengguna mengesahkan tiga senario untuk ODL, SAP dan e-BDR: sesi sedia ada, beberapa tab, serta logout OneID/login semula. Keputusan PASS_USER_REPORTED direkod dalam `manual-session-baseline-20261003.md` dan CSV. Semantik tab downstream lama selepas logout tidak dinyatakan; jangan menganggap global logout telah disahkan. Baki Fasa 1: ujian negatif/token tamat dengan akaun khusus, prestasi dan kriteria penerimaan, serta pengesahan integrasi remote dalam skop atau pengecualian yang diterima.


## Baseline ujian negatif diterima

Pengguna mengesahkan akses ditolak dan token tamat berjaya. Kedua-duanya direkod PASS_USER_REPORTED dalam `manual-negative-baseline-20261003.json`. Nama aplikasi, akaun ujian dan hasil terperinci tidak dinyatakan; kolum khusus aplikasi dalam CSV tidak diubah secara andaian. Baki: padankan skop ujian negatif, ukuran prestasi/kriteria penerimaan dan pengesahan integrasi remote dalam skop atau pengecualian yang diterima. Ini bukan keputusan PHP 8.4.


### Penjelasan skop ujian negatif

Pengguna mengenal pasti APP-026 e-Risk (Non-SSO) dan APP-032 IStAD (OneID SSO). Padanan senario kepada setiap sistem masih perlu disahkan: jangan menyimpulkan token OneID disahkan oleh e-Risk. Bukti umum PASS_USER_REPORTED dikekalkan; keputusan per aplikasi belum diisi tanpa padanan yang jelas.

## Kemas kini terkini — e-Risk dikecualikan dan masa respons

Pengguna meminta e-Risk diabaikan. Dua senario negatif yang dilaporkan dipadankan kepada sistem SSO yang tinggal, IStAD (APP-032), sebagai PASS_USER_REPORTED. Ini menggantikan nota padanan tertangguh di atas. e-Risk bukan bukti pengesahan token SSO.

`response-time-baseline.json` merekod 5 permintaan berurutan bagi setiap endpoint melalui loopback dengan TLS disahkan:

- GET /: 5/5 HTTP 200; median 81.7 ms, maksimum 91.0 ms.
- GET /.well-known/openid-configuration: 5/5 HTTP 200; median 63.3 ms, maksimum 85.6 ms.

Ini ukuran ringan tanpa login, bukan masa login sebenar, throughput atau percentile beban. Discovery boleh dilayan oleh Hydra, bukan PHP. Jangan gunakan angka ini sebagai bukti prestasi login PHP 8.4.

### Gate sebelum menukar trafik (belum ditutup)

- Ulang 12 aplikasi baseline positif, 3 aplikasi sesi/multi-tab/logout-login semula, dan IStAD untuk ujian negatif dengan kontrak sama pada 8.4.
- Tiada perubahan kod downstream diperlukan untuk lulus.
- Ujian fixture mesti lulus pada kedua-dua runtime; tiada warning/fatal mencemari respons API.
- Ukur login sebenar, token validation dan cron/source integration pada runtime sasaran. Baseline authenticated masih belum diukur.
- Versi PHP/kod aktif bagi integrasi remote masih belum disahkan; pengesahan login pengguna tidak menggantikan inventori runtime.
- Had regresi prestasi dan skop pengecualian masih perlu dipersetujui. Tiada kelulusan cutover tersirat.

Bahan baseline kini cukup untuk menyusun kerja persediaan Fasa 2, tetapi Fasa 1 tidak didakwa lengkap tanpa baki di atas. Tiada kod aplikasi, dependency, PHP atau konfigurasi diubah pada langkah ini.

## Fasa 2 bermula

Lihat `PHASE2.md` untuk patch nullable Jumbojett yang boleh diulang, kemas kini terhad phpseclib dan pembetulan classmap Composer. Baseline hash Fasa 1 dikekalkan sebagai rekod sebelum perubahan; jangan menimpanya dengan hash baharu. PHP/FPM dan trafik kekal pada 8.3.


Pengesahan manual Fasa 2 diterima: lima senario MyDigital ID web/mobile dan mobile session/refresh/logout lulus menurut pengguna pada PHP 8.3; lihat `phase2-manual-validation.json`. Runtime 8.4 belum dipasang/diuji oleh langkah ini.


## Fasa 4 — ujian FPM terpencil

Lihat `PHASE4.md`, `phase4-fpm-fixtures.json` dan `phase4-fpm-runtime.json`. Harness menguji kod melalui pool 8.4 sebenar dengan provider/stor fixture. Trafik kekal 8.3; ujian browser dan integrasi sebenar serta gate prestasi/downstream masih berbaki.


## Status terkini: cutover UAT selesai

OneID UAT web/mobile menggunakan FPM 8.4.26; default CLI kekal 8.3. Pengesahan selepas cutover melalui URL biasa lulus menurut pengguna (web/satu downstream dan mobile-test login/sesi/refresh/logout). Lihat `PHASE5.md` dan `phase5-post-cutover-manual.json`. Penangguhan native-device, runtime remote/ODBC dan penerimaan prestasi formal telah dipersetujui; bukan pengesahan production.


Audit production read-only tersedia dalam `production/AUDIT.md`. Production masih PHP 8.3.33; tiada perubahan dibuat. Dapatan utama: patch refresh dan dependency UAT belum dibawa, dan konfigurasi/pool production berbeza.
