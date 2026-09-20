# Audit Cadangan Google Analytics 4 untuk OneID@UPNM

**Tarikh audit:** 20 September 2026  
**Status:** Dokumentasi dan penilaian sahaja  
**Keputusan pelaksanaan:** Belum diputuskan  
**Persekitaran diperiksa:** Kod aplikasi OneID staging sebagai asas seni bina bersama production

## 1. Tujuan

Audit ini menilai kesesuaian Google Analytics 4 (GA4) untuk mendapatkan statistik
capaian dan penggunaan portal OneID@UPNM. Audit ini tidak memberikan kebenaran
untuk mengaktifkan pengumpulan data dan tidak membuat sebarang perubahan kepada
kod, konfigurasi runtime, Content Security Policy (CSP), Nginx, WAF, staging atau
production.

Sasaran analitik yang dinilai ialah:

- bilangan pengguna, sesi dan paparan halaman;
- waktu capaian paling tinggi;
- jenis peranti, pelayar dan saiz skrin;
- kaedah log masuk yang digunakan;
- aplikasi yang paling kerap dicapai;
- penggunaan kategori aplikasi, favourite, FAQ dan manual pengguna;
- penggunaan bahasa BM dan English;
- kadar mula, selesai atau langkau product tour; dan
- trend kegagalan fungsi pada peringkat kategori umum tanpa mendedahkan identiti.

## 2. Ringkasan keputusan audit

GA4 secara teknikal boleh dipasang dalam OneID. Walau bagaimanapun, pemasangan
snippet GA secara terus pada semua halaman tidak sesuai kerana OneID memproses
maklumat identiti, token SSO, MyDigital ID, MFA, akaun pengguna dan operasi
pentadbir.

Sekiranya pelaksanaan dipertimbangkan kemudian, reka bentuk perlu memenuhi semua
syarat berikut:

1. pengumpulan berdasarkan event yang diluluskan sahaja;
2. tiada data peribadi, credential, token atau input bebas dihantar;
3. URL dan tajuk halaman dibersihkan sebelum dihantar;
4. halaman pentadbir, keselamatan, callback dan API dikecualikan;
5. staging dan production menggunakan konfigurasi analitik berasingan;
6. consent dan notis privasi diputuskan sebelum pengaktifan;
7. CSP aplikasi, Nginx dan WAF dinilai sebagai satu polisi berlapis; dan
8. Analytics gagal secara selamat tanpa mengganggu login atau fungsi OneID.

## 3. Dapatan keadaan semasa

### 3.1 Tiada integrasi Google Analytics ditemui

Carian kod tidak menemui pemasangan Google Analytics, Google tag (`gtag.js`),
Google Tag Manager atau Measurement ID yang sedia ada. OneID pada masa audit
tidak menghantar event penggunaan kepada GA4.

### 3.2 CSP semasa akan menyekat GA4

`lib/config.php` menghantar CSP yang pada asasnya mengehadkan skrip dan sambungan
kepada origin OneID:

```text
script-src 'self' ...
connect-src 'self'
```

Oleh itu, kod GA tidak akan berfungsi hanya dengan menambah snippet. Jika CSP
wujud pada lebih daripada satu lapisan, pelayar menguatkuasakan kesan gabungan
semua polisi. CSP aplikasi, Nginx dan WAF perlu serasi sebelum sebarang ujian.

### 3.3 OneID mempunyai beberapa konteks sensitiviti tinggi

Kod semasa mempunyai aliran berasingan untuk:

- login biasa dan MyDigital ID;
- callback SSO;
- MFA pengguna dan pentadbir;
- admin step-up;
- penyelenggaraan developer;
- carian akaun pengguna;
- audit log dan laporan pentadbiran;
- pengurusan session serta token; dan
- endpoint AJAX/API melalui `q_func`.

Konteks ini tidak sesuai untuk tracking generik kerana URL, input, mesej ralat
atau state halaman boleh mengandungi maklumat sensitif.

### 3.4 Audit keselamatan dalaman sudah tersedia

OneID mempunyai audit log dan rekod operasi dalaman. GA4 tidak patut menggantikan
audit keselamatan, audit pentadbir atau bukti transaksi. GA4 hanya sesuai sebagai
alat statistik penggunaan agregat.

## 4. Cadangan sempadan pengumpulan data

### 4.1 Event yang sesuai dipertimbangkan

| Event cadangan | Tujuan | Parameter yang dibenarkan |
|---|---|---|
| `page_view` | Paparan halaman awam/dashboard | Nama laluan umum, locale, environment |
| `login` | Login berjaya | `method=password` atau `method=mydigitalid` |
| `login_failed` | Trend kegagalan login | Kod kategori umum sahaja |
| `select_content` | Kandungan/aplikasi dipilih | ID aplikasi bukan peribadi, kategori |
| `application_launch` | Aplikasi downstream dilancarkan | ID aplikasi, kategori, jenis SSO |
| `application_search` | Fungsi carian aplikasi digunakan | Bilangan keputusan sahaja |
| `favorite_add` | Aplikasi ditambah ke favourite | ID aplikasi |
| `favorite_remove` | Aplikasi dibuang daripada favourite | ID aplikasi |
| `manual_download` | Manual dimuat turun | Bahasa dokumen |
| `faq_open` | FAQ dibuka | ID/topik terkawal |
| `tutorial_begin` | Product tour dimulakan | Versi tour |
| `tutorial_step` | Langkah tour dipaparkan | Nombor langkah, versi tour |
| `tutorial_complete` | Tour diselesaikan | Versi tour |
| `tutorial_skip` | Tour dilangkau | Langkah terakhir, versi tour |
| `session_renew` | Permintaan renew session | Status berjaya/gagal umum |
| `language_change` | Bahasa antaramuka ditukar | `ms` atau `en` |
| `display_setting_change` | Setting paparan digunakan | Nama setting daripada allowlist |

Nama event standard GA4 seperti `login`, `select_content`, `tutorial_begin` dan
`tutorial_complete` patut digunakan apabila semantiknya sepadan. Custom event
hanya digunakan apabila tiada event standard yang sesuai.

### 4.2 Data yang tidak boleh dihantar

Pelaksanaan masa hadapan mesti menolak data berikut:

- nombor staf atau nombor matrik;
- nombor kad pengenalan atau passport;
- nama, e-mel dan nombor telefon;
- token SSO, PHP session ID, nonce, approval ID atau correlation ID sensitif;
- identifier MyDigital ID;
- OTP, faktor MFA, recovery information atau security answer;
- password atau sebarang credential;
- teks carian yang ditaip pengguna;
- mesej ralat mentah yang mungkin mengandungi identiti;
- URL penuh dengan query string atau fragment;
- kandungan rekod audit, profil atau akaun pengguna; dan
- alamat IP yang dihantar sendiri sebagai parameter/custom dimension.

Hash stabil bagi nombor staf/matrik juga tidak dicadangkan. Walaupun bukan nilai
asal, hash stabil masih membolehkan aktiviti pengguna dipautkan dalam tempoh yang
panjang.

### 4.3 Halaman dan endpoint yang patut dikecualikan

Sekurang-kurangnya laluan berikut patut berada dalam denylist:

```text
/admin/*
/lib/*
/api*
/auth/mydigitalid/*
/page/user-mfa-*
/page/admin-step-up*
/page/admin-totp-qr*
/maintenance/*
```

Callback SSO, endpoint QR/TOTP, AJAX, API dan halaman yang mengandungi token tidak
patut memuatkan tag Analytics.

## 5. Risiko URL, carian dan page title

GA4 boleh mengumpulkan URL dan tajuk halaman secara automatik. Dalam OneID,
query parameter boleh mengandungi tujuan operasi, intent, callback state atau
maklumat lain yang tidak sepatutnya dihantar.

Kawalan yang dicadangkan:

- matikan penghantaran `page_view` automatik;
- hantar `page_view` secara manual selepas sanitasi;
- gunakan origin dan pathname sahaja;
- buang seluruh query string dan fragment;
- tukar tajuk kepada nama umum seperti `login` atau `user_dashboard`;
- jangan hantar nilai input carian; dan
- jangan gunakan DOM scraping untuk menghasilkan parameter event.

Carian akaun pentadbir mesti dikecualikan sepenuhnya. Bagi carian aplikasi
pengguna, hanya event penggunaan carian dan bilangan hasil boleh dipertimbangkan.

## 6. Seni bina teknikal yang dicadangkan jika diluluskan

### 6.1 Gunakan Google tag secara terus untuk fasa pertama

Cadangan awal ialah GA4 melalui `gtag.js`, bukan Google Tag Manager (GTM). Ini
mengurangkan permukaan perubahan kerana tag baharu tidak boleh diterbitkan di
luar proses release OneID. GTM hanya patut dipertimbangkan kemudian jika terdapat
governance container, role, approval dan audit penerbitan yang jelas.

### 6.2 Feature flag fail-closed

Default committed mesti kekal tidak aktif. Contoh konfigurasi:

```php
'ONEID_ANALYTICS_ENABLED' => 'false',
'ONEID_ANALYTICS_MEASUREMENT_ID' => '',
'ONEID_ANALYTICS_ENVIRONMENT' => '',
'ONEID_ANALYTICS_CONSENT_REQUIRED' => 'true',
```

Nilai khusus environment hendaklah berada dalam `.private/runtime.php`. GA tidak
boleh dimuatkan jika flag tidak aktif, Measurement ID tidak sah atau environment
tidak sepadan.

### 6.3 Modul pusat dan allowlist

Semua halaman yang diluluskan perlu menggunakan satu modul pusat, contohnya:

```text
lib/analytics.php
public/dist/js/oneid-analytics.js
```

Modul perlu:

- mempunyai allowlist nama event dan parameter;
- menolak key yang tidak dikenali;
- menghadkan panjang serta format nilai;
- membersihkan URL;
- menyediakan API event yang kecil dan konsisten;
- tidak membaca nilai form secara generik;
- tidak menyebabkan error aplikasi jika GA gagal; dan
- menyediakan logging tempatan tanpa payload sensitif bagi kegagalan integrasi.

### 6.4 Pemisahan staging dan production

Staging dan production tidak boleh menghantar data kepada stream yang sama.
Cadangan ialah property atau sekurang-kurangnya web data stream yang berasingan,
dengan kawalan akses yang jelas. Production report tidak patut dicemari oleh
ujian developer atau UAT.

### 6.5 CSP minimum

Dokumentasi rasmi Google menyatakan GA tanpa fungsi pengiklanan memerlukan akses
sekurang-kurangnya kepada domain Google Tag dan Analytics dalam `script-src`,
`connect-src` dan `img-src`.

Perubahan sebenar mesti menggunakan domain minimum yang diperlukan, bukan
wildcard luas. Polisi perlu dikemas kini pada setiap lapisan yang menghantar CSP.
Jika nonce CSP diperkenalkan, nonce perlu dijana secara rawak bagi setiap response
dan digunakan secara konsisten.

## 7. Consent dan tadbir urus privasi

Cadangan konservatif ialah Basic Consent Mode:

- tag GA tidak dimuatkan sebelum consent;
- tiada request kepada Google jika consent ditolak;
- pilihan pengguna disimpan secara terkawal;
- pengguna boleh mengubah pilihan kemudian; dan
- notis menerangkan kategori data, tujuan, tempoh simpanan dan pihak pemproses.

Keperluan consent dan teks notis perlu diputuskan oleh pemilik sistem serta pihak
privasi/perundangan UPNM. Audit teknikal ini tidak membuat penentuan undang-undang.

Ads personalization, remarketing, Google Signals dan perkongsian dengan Google
Ads dicadangkan kekal tidak aktif kerana objektif OneID ialah statistik operasi,
bukan pengiklanan.

## 8. Tetapan GA4 yang dicadangkan

Jika property diwujudkan kemudian:

- gunakan satu property/data stream production yang dimiliki organisasi;
- asingkan staging;
- hadkan role GA kepada pegawai yang memerlukan akses;
- gunakan tempoh retention minimum yang memenuhi tujuan laporan;
- semak granular location/device collection sebelum diaktifkan;
- jangan aktifkan Ads personalization atau Google Signals secara default;
- konfigurasikan developer/internal traffic filter dengan berhati-hati;
- daftarkan hanya custom dimension yang benar-benar diperlukan; dan
- jangan daftarkan user identifier sebagai custom dimension.

## 9. Batas keupayaan pengukuran

OneID boleh mengukur pengguna menekan butang untuk melancarkan aplikasi
downstream. Selepas pengguna berpindah ke domain aplikasi lain, OneID tidak boleh
melihat aktiviti di dalam aplikasi tersebut.

Pengukuran perjalanan merentas sistem memerlukan setiap sistem downstream
memasang Analytics dan menerima governance/cross-domain measurement yang sama.
Perkara itu berada di luar skop audit ini dan tidak patut diandaikan sebagai
sebahagian daripada pelaksanaan OneID.

## 10. Cadangan fasa jika keputusan pelaksanaan dibuat kemudian

### Fasa A — Foundation staging

- tambah feature flag yang default `false`;
- bina modul pusat dan sanitasi;
- tambah consent asas;
- laksanakan event paling minimum;
- kemas kini CSP staging sahaja; dan
- tambah ujian kontrak untuk denylist PII dan route.

### Fasa B — UAT dan verifikasi privasi

- uji GA Realtime dan DebugView;
- periksa setiap request Analytics melalui browser Network;
- sahkan tiada query string, token atau identiti;
- uji accept, reject dan perubahan consent;
- uji kegagalan/rangkaian Google disekat; dan
- semak prestasi serta compatibility mobile.

### Fasa C — Production terkawal

- dapatkan kelulusan pemilik/privasi;
- sediakan property/stream production;
- aktifkan event minimum;
- pantau CSP report, error dan data quality;
- audit semula selepas tempoh percubaan; dan
- tambah event hanya melalui release yang diluluskan.

## 11. Event minimum yang dicadangkan untuk pilot

Jika pilot diluluskan, mulakan hanya dengan:

1. sanitized `page_view` bagi login dan dashboard;
2. `login` dengan kaedah login sahaja;
3. `application_launch` dengan ID aplikasi bukan peribadi;
4. `tutorial_begin`, `tutorial_complete` dan `tutorial_skip`; dan
5. `language_change`.

Event kegagalan, session, favourite dan search boleh ditambah selepas payload
pilot disahkan bersih.

## 12. Perkara yang masih memerlukan keputusan

- Adakah GA4 dibenarkan oleh polisi UPNM untuk portal identiti?
- Adakah consent wajib dan siapakah pemilik teks notis privasi?
- Siapakah pemilik GA account/property dan pentadbir aksesnya?
- Apakah tempoh retention yang dibenarkan?
- Adakah granular device/location data diperlukan?
- Adakah data penggunaan admin perlu dikecualikan sepenuhnya? Audit ini
  mencadangkan ya.
- Adakah measurement hanya untuk OneID atau akan diperluas kepada sistem
  downstream?
- Apakah KPI rasmi yang hendak dijawab oleh setiap event?
- Siapakah yang meluluskan event atau parameter baharu?

## 13. Rujukan rasmi

- Google Analytics, Recommended events:  
  <https://support.google.com/analytics/answer/9267735>
- Google Analytics, About events:  
  <https://support.google.com/analytics/answer/9322688>
- Google Analytics, Avoid sending Personally Identifiable Information:  
  <https://support.google.com/analytics/answer/6366371>
- Google Analytics, Safeguarding your data:  
  <https://support.google.com/analytics/answer/6004245>
- Google Tag Platform, Consent mode overview:  
  <https://developers.google.com/tag-platform/security/concepts/consent-mode>
- Google Tag Platform, Set up consent mode on websites:  
  <https://developers.google.com/tag-platform/security/guides/consent>
- Google Tag Platform, CSP guidance:  
  <https://developers.google.com/tag-platform/security/guides/csp>
- Google Analytics, Regional data collection:  
  <https://support.google.com/analytics/answer/11598602>
- Google Analytics, Data filters:  
  <https://support.google.com/analytics/answer/13296761>
- Google Analytics, Collect granular location and device data:  
  <https://support.google.com/analytics/answer/12002752>

## 14. Penutup

GA4 boleh memberi nilai kepada OneID bagi statistik penggunaan agregat, tetapi
pelaksanaannya perlu dianggap sebagai integrasi data pihak ketiga dan bukannya
sekadar penambahan skrip. Cadangan semasa ialah kekalkan status **belum aktif**,
gunakan dokumen ini sebagai asas mendapatkan maklumat serta keputusan lanjut,
dan hanya memulakan implementasi staging selepas skop, consent, ownership serta
event taxonomy diluluskan.
