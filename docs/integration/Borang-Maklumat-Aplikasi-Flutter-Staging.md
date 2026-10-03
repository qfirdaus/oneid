# Borang Maklumat Aplikasi Flutter — Integrasi OneID Staging

Versi 2.0 • 30 September 2026
Maklumat yang diketahui telah diisi. Team Flutter hanya perlu melengkapkan ruangan [ISI] dan memulangkan borang kepada pemilik projek. Isi BELUM ADA jika belum diputuskan. Jangan masukkan password, OTP, private key, signing keystore atau token.

## A. Identiti projek dan PIC

| Soalan | Jawapan team Flutter |
| --- | --- |
| Nama aplikasi dan organisasi/pasukan | UPNM Mobile; pasukan/PIC: [ISI] |
| Nama PIC teknikal, email kerja dan saluran koordinasi | [ISI] |
| Platform diuji dahulu: Android / iOS / kedua-duanya | Android dahulu (disahkan pemilik); iOS belum dijadualkan |
| Versi Flutter/Dart dan minimum Android/iOS | [ISI] |
| Sasaran tarikh build staging dan sesi ujian | [ISI] |

## B. Identiti aplikasi dan callback

| Soalan | Jawapan team Flutter |
| --- | --- |
| Android applicationId/package ID untuk staging | com.upnmmobile1.app |
| iOS bundle ID untuk staging | [LENGKAPKAN APABILA FASA iOS DIMULAKAN] |
| Adakah staging berbeza daripada production? | [ISI] |
| Exact redirect URI Android selepas login | com.upnmmobile1.app://oneid/callback (disahkan team mobile) |
| Exact redirect URI iOS selepas login | [FASA iOS; BELUM DIPERLUKAN UNTUK UJIAN ANDROID] |
| Callback menggunakan App Link/Universal Link atau custom scheme? | Custom scheme; scheme com.upnmmobile1.app, host oneid, path /callback |
| Domain callback / fail association | Tidak berkenaan untuk callback Android custom scheme semasa |
| SHA-256 fingerprint untuk App Link | Tidak diperlukan untuk callback custom scheme semasa |
| Apple Team ID / App ID | Fasa iOS kemudian; tidak diperlukan untuk ujian Android ini |
| Pakej/library OIDC Flutter dan versinya yang digunakan/dicadang | [ISI] |
| Kaedah browser sistem dan pengendalian callback semasa app ditutup/aktif | [ISI] |

Client Android upnm-mobile-android-staging telah disahkan berdaftar pada provider, menggunakan PKCE S256 tanpa client secret. Callback tepat sudah disahkan. Client iOS akan disediakan secara berasingan.

## C. Seni bina aplikasi dan backend

| Soalan | Jawapan team Flutter |
| --- | --- |
| Adakah terdapat backend/API aplikasi? Teknologi dan URL staging | [ISI] |
| Di mana profil aplikasi dan transaksi disimpan? | [ISI] |
| Bagaimana backend mengesahkan pengguna sekarang? | [ISI] |
| Adakah backend mahu menerima token OneID atau mencipta sesi backend sendiri? | [ISI cadangan untuk semakan] |
| Medan tambahan selain sub, account_type, display_name/full_name, staff_number, staff_number_short, student_matric_number, email, department dan job_title, serta tujuan setiap medan | [ISI / TIADA] |
| Bagaimana data aplikasi diasingkan bagi pengguna yang mempunyai akaun staf dan pelajar? | [ISI] |

Keputusan telah dipersetujui: OneID sahaja mengesahkan identiti; tiada semakan/padanan MyCampus. Admin OneID ialah pengguna biasa dalam mobile. Token dengan audience oneid-mobile-session belum merupakan kebenaran untuk backend perniagaan.

## D. Sesi dan logout

| Soalan | Jawapan team Flutter |
| --- | --- |
| Mekanisme secure storage Android/iOS | [ISI] |
| Kaedah serialize refresh dan simpan token rotation secara atomik | [ISI] |
| Tingkah laku ketika offline, timeout dan server 503 | [ISI] |
| Tingkah laku logout offline dan pembatalan server belum disahkan | [ISI] |
| Perlu paparan/data offline? Nyatakan skop | [ISI / TIADA] |
| Reinstall/clear data/pertukaran telefon: aliran login semula | [ISI] |

Keperluan pengguna: aplikasi dibuka semula tanpa login berulang selagi sesi sah; logout/pembatalan keselamatan tetap dihormati. Jangan menjanjikan refresh token tidak boleh dibatalkan.

## E. Akses dan ujian staging

| Soalan | Jawapan team Flutter |
| --- | --- |
| Peranti fizikal/emulator, model dan versi OS | [ISI] |
| Bolehkah telefon mengakses rangkaian staging melalui VPN yang diluluskan? | [ISI] |
| IP/rangkaian penguji seperti dilihat server; jika belum diketahui, nyatakan perlu semakan bersama | [ISI] |
| Akaun staf/pelajar yang akan digunakan untuk ujian (identifier sahaja) | [ISI untuk koordinasi; semua akaun yang layak dibenarkan mengikut polisi] |
| Peranti mempunyai MyDigital ID untuk ujian app-switch? | [ISI] |
| Kaedah edaran build: APK/internal testing/TestFlight/lain-lain | [ISI] |
| PIC dan masa untuk sesi ujian bersama | [ISI] |

Arahan pemilik ialah ujian terbuka kepada semua akaun yang layak. Semakan server mendapati route mobile terbuka melalui hostname staging dan servis ujian di-enable pada boot. Tidak lagi terhad kepada akaun 0530-09 atau SSH tunnel. Akses sebenar dari telefon/VPN masih perlu diuji.

## F. Diisi team OneID selepas semakan

| Item | Nilai akhir / keputusan |
| --- | --- |
| client_id Android | upnm-mobile-android-staging — disahkan pada provider; authorization request sampai ke borang login |
| client_id iOS | [BELUM DIDAFTARKAN] |
| Exact redirect URI diluluskan | com.upnmmobile1.app://oneid/callback |
| Issuer/discovery disahkan dari telefon | [BELUM DIUJI] |
| Akses staging dan akaun | Semua akaun layak mengikut polisi; laluan telefon ke staging perlu diuji |
| Audience/validasi backend perniagaan | [BELUM DIPUTUSKAN / TIDAK BERKENAAN] |
| TTL runtime dan polisi refresh disahkan | [BELUM DISAHKAN UNTUK CLIENT FLUTTER] |
| Tarikh / PIC / isu yang masih terbuka | [ISI] |

## Cara pulangkan

Isi kolum jawapan dalam fail DOCX dan pulangkan fail itu kepada pemilik projek. Maklumat belum diketahui boleh ditandakan BELUM ADA dengan PIC dan tarikh sasaran. Tiada keperluan menghantar credentials. Fail jawapan boleh diletakkan semula di /var/www/ dan beritahu nama fail untuk semakan seterusnya.
