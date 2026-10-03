# Panduan Integrasi OneID — UPNM Mobile Android

Versi 2.1 • 30 September 2026 • STAGING SAHAJA
Untuk developer Flutter. Mulakan terus dengan Langkah 1; maklumat aplikasi di bawah telah diterima dan tidak perlu dihantar semula.

## Status yang sudah disahkan

| Perkara | Status semasa |
| --- | --- |
| Aplikasi / platform | UPNM Mobile / Android dahulu |
| Android package ID | com.upnmmobile1.app |
| Callback aplikasi | com.upnmmobile1.app://oneid/callback |
| Client ID OneID | upnm-mobile-android-staging — sudah berdaftar pada provider |
| Ujian konfigurasi Android dari server | Authorization request menggunakan client/callback di atas berjaya sampai ke borang login OneID |
| Mod ujian staging | Route mobile dibuka melalui hostname staging; servis ujian di-enable untuk bermula selepas reboot |
| Kelayakan pengguna | Semua akaun OneID yang layak mengikut polisi; bukan lagi allowlist 0530-09 |
| Ujian yang masih diperlukan | Akses dari telefon, callback kembali ke Flutter dan keseluruhan login/sesi/logout dalam build Android |

Semakan server ini tidak bermaksud build Android sudah lulus ujian. Team Flutter boleh mula konfigurasi sekarang. Telefon perlu mempunyai akses rangkaian ke staging.

## Langkah 1 — Masukkan konfigurasi berikut dalam Flutter

Gunakan fail OneID-UPNM-Mobile-Android-Staging.config.json yang disertakan. Ia ialah konfigurasi integrasi untuk dipetakan kepada pustaka OIDC yang digunakan, bukan fail yang semua pustaka import secara automatik.

| Tetapan | Nilai yang perlu digunakan |
| --- | --- |
| client_id | upnm-mobile-android-staging |
| redirect_uri | com.upnmmobile1.app://oneid/callback |
| Issuer | https://oneid-uat.upnm.edu.my/ |
| Discovery URL | https://oneid-uat.upnm.edu.my/.well-known/openid-configuration |
| Scopes | openid profile offline_access mobile:session |
| Audience (parameter authorization tambahan) | oneid-mobile-session |
| Flow / response_type | Authorization Code / code |
| PKCE | S256 |
| token_endpoint_auth_method | none |
| Client secret | Tidak diperlukan |

Gunakan pustaka OIDC yang menyokong PKCE dan browser sistem. Aktifkan validasi signature ID token, issuer, audience, expiry dan nonce. Pustaka juga perlu menyemak state. Jangan hanya decode JWT atau menganggap access token ialah JWT.

Hasil langkah ini: aplikasi mempunyai konfigurasi staging dengan client_id dan redirect_uri tepat seperti jadual.

## Langkah 2 — Konfigurasi penerimaan callback Android

Callback telah disahkan oleh team mobile. Gunakan nilai yang sama dalam konfigurasi Android dan pustaka OIDC:

| Bahagian callback | Nilai |
| --- | --- |
| URI penuh | com.upnmmobile1.app://oneid/callback |
| Jenis | Custom URI scheme |
| Scheme | com.upnmmobile1.app |
| Host | oneid |
| Path | /callback |

Pastikan callback boleh membuka aplikasi ketika aplikasi sedang aktif dan ketika belum dibuka. Ikut mekanisme penerimaan callback pustaka OIDC yang digunakan; semak URI dan transaksi asal sebelum menerimanya.

Tidak perlu domain HTTPS, fail association atau certificate fingerprint App Link untuk callback custom scheme ini. Maklumat iOS belum diperlukan bagi fasa Android.

Hasil langkah ini: selepas login, browser boleh kembali ke UPNM Mobile. Callback ini bukan callback MyDigital ID ke OneID dan bukan URL halaman ujian browser.

## Langkah 3 — Sambungkan butang “Login dengan OneID”

1. Pengguna menekan butang dalam UPNM Mobile.
2. Pustaka OIDC menjana state, nonce dan pasangan PKCE bagi cubaan itu, kemudian membuka authorization endpoint dalam browser sistem.
3. Pengguna login pada halaman OneID menggunakan password/MFA atau MyDigital ID.
4. OneID menghantar authorization code melalui callback aplikasi.
5. Pustaka menyemak callback dan menukar code kepada token menggunakan code_verifier yang sama.
6. Simpan token dalam secure storage, kemudian teruskan ke Langkah 4.

Team Flutter tidak perlu membina borang password/OTP OneID atau integrasi MyDigital ID berasingan. Jangan buka /login secara terus, gunakan WebView terbenam atau membaca database OneID. Tiada integrasi MyCampus diperlukan.

OneID mengurus pemilihan staf/pelajar selepas pengesahan MyDigital ID jika pengguna mempunyai dua konteks akaun yang layak. Admin OneID tetap pengguna biasa dalam aplikasi.

## Langkah 4 — Semak sesi dan baca profil pengguna

Panggil endpoint ini dengan access token yang baru diterima:

```http
GET https://oneid-uat.upnm.edu.my/mobile/session
Authorization: Bearer <access_token daripada respons token>
```

Hanya buka halaman utama selepas menerima HTTP 200 dengan session_status bernilai active. Contoh data staf:

```json
{
  "session_status": "active",
  "sub": "oneid_contoh",
  "account_type": "staff",
  "display_name": "Pengguna Contoh",
  "full_name": "Pengguna Contoh",
  "staff_number": "0530-09",
  "staff_number_short": "0530",
  "student_matric_number": null,
  "email": "contoh@example.invalid",
  "department": "Jabatan Contoh",
  "job_title": "Jawatan Contoh"
}
```

| Medan | Cara penggunaan |
| --- | --- |
| sub | ID pengguna opaque; gunakan bersama issuer untuk pemetaan identiti |
| account_type | staff atau student; bukan hak admin |
| full_name / display_name | Nama penuh daripada OneID; display_name dikekalkan untuk keserasian. Gelaran boleh termasuk dalam nama sumber. |
| staff_number / staff_number_short | Contoh 0530-09 / 0530; kedua-duanya string supaya sifar depan kekal |
| student_matric_number | Nombor matrik dalam konteks pelajar; null bagi staf |
| email | Emel sumber OneID; jangan andaikan email_verified |
| department | Jabatan staf atau PTJ/fakulti pelajar |
| job_title | Jawatan bagi staf; nama program pengajian bagi pelajar. Kedua-duanya daripada kolum sumber data7. |

Medan profil yang kosong atau tidak berkenaan bernilai null. Bagi pelajar, kedua-dua nombor staf bernilai null; job_title mengandungi nama program jika tersedia. Gunakan account_type untuk memaparkan label “Jawatan” bagi staf atau “Program pengajian” bagi pelajar. Nombor staf yang tidak menepati format xxxx-xx tidak direka semula. NRIC, password dan OTP tidak diberikan.

Profil ini diperoleh melalui /mobile/session, bukan semuanya di dalam ID token. Data mengikut akaun/konteks yang digunakan; jangan gabungkan akaun berdasarkan nama. Dua rekod staf/pelajar mempunyai sub berasingan; satu rekod dengan dua konteks boleh berkongsi sub.

## Langkah 5 — Kekalkan login apabila aplikasi dibuka semula

1. Baca token daripada secure storage OS.
2. Jika tiada token, paparkan butang login.
3. Jika token ada, semak sesi. Jika access token luput atau mendapat 401, lakukan satu percubaan refresh terselaras.
4. Simpan token baharu secara atomik, termasuk refresh token baharu jika dipulangkan.
5. Semak sesi semula; jika aktif, buka aplikasi tanpa meminta login semula.

Hanya satu refresh boleh berjalan pada satu masa. Gunakan expires_in sebenar, bukan tempoh yang di-hardcode. Jika refresh memberi invalid_grant, padam token dan minta login semula. Offline/timeout/503 ialah gangguan sementara; jangan terus padam token.

Simpan token menggunakan secure storage yang dilindungi OS, bukan plain preferences, log atau analytics. Sesi boleh dibatalkan jika pengguna logout atau keadaan keselamatan berubah; “kekal login” bukan sesi tanpa pembatalan.

## Langkah 6 — Sambungkan logout

```http
POST https://oneid-uat.upnm.edu.my/mobile/logout
Authorization: Bearer <access_token semasa>
```

Badan boleh kosong. Jika HTTP 204, padam token/profil tempatan dan kembali ke skrin login. Sesi mobile tersebut dibatalkan; refresh lama tidak boleh digunakan lagi.

Jika access token luput, refresh dahulu dan cuba logout semula. Jika refresh invalid_grant, tamatkan sesi tempatan. Jika offline atau server gagal, bezakan logout tempatan daripada pembatalan server yang belum disahkan. Jangan mendakwa logout server berjaya jika permintaan gagal.

Logout ini tidak melogout semua peranti, browser OneID atau sesi upstream MyDigital ID.

## Rujukan endpoint dan token

| Endpoint | URL staging |
| --- | --- |
| Authorization | https://oneid-uat.upnm.edu.my/oauth2/auth |
| Token dan refresh | https://oneid-uat.upnm.edu.my/oauth2/token |
| JWKS | https://oneid-uat.upnm.edu.my/.well-known/jwks.json |
| Profil/sesi | https://oneid-uat.upnm.edu.my/mobile/session |
| Logout aplikasi | https://oneid-uat.upnm.edu.my/mobile/logout |
| Revocation standard | https://oneid-uat.upnm.edu.my/oauth2/revoke |
| UserInfo standard | https://oneid-uat.upnm.edu.my/userinfo |

Gunakan discovery untuk metadata. Gunakan /mobile/session bagi profil penuh dan /mobile/logout bagi logout aplikasi; revocation standard sahaja bukan pengganti logout sesi OneID.

OneID memberikan access_token, refresh_token dan id_token selepas pertukaran authorization code. Nilai token berbeza setiap sesi, jadi ia tidak diisi sebagai konfigurasi tetap dalam dokumen. ID token ialah bukti identiti, access token digunakan untuk API sesi, refresh token digunakan untuk mendapatkan token baharu.

Pustaka OIDC biasanya mengurus pertukaran code. Jika perlu menyemak permintaan, body berikut dihantar sebagai satu form-encoded body dengan Content-Type: application/x-www-form-urlencoded:

```text
grant_type=authorization_code
client_id=upnm-mobile-android-staging
redirect_uri=com.upnmmobile1.app://oneid/callback
code=<code diterima melalui callback>
code_verifier=<verifier daripada cubaan login yang sama>
```

Untuk refresh, gunakan endpoint token yang sama:

```text
grant_type=refresh_token
client_id=upnm-mobile-android-staging
refresh_token=<refresh token terkini dalam secure storage>
```

Tiada client_secret. Scope offline_access diminta untuk refresh token. Gunakan token_type dan expires_in daripada respons sebenar; jangan log token atau authorization code.

## Jika berlaku ralat

| Keadaan | Tindakan Flutter |
| --- | --- |
| Pengguna membatalkan login | Kembali ke skrin login tanpa membuka browser berulang |
| Sesi mendapat 401 | Refresh sekali secara terselaras; jika tetap ditolak, login semula |
| Refresh invalid_grant | Padam token; minta login semula |
| Offline / timeout / 503 | Papar gangguan sementara dan pilihan cuba semula |
| 404 / sekatan 403 | Semak akses rangkaian dan konfigurasi staging bersama team OneID |
| Callback, state, nonce atau signature tidak sah | Tolak login |

Apabila melaporkan isu, beri versi build/OS, masa, status HTTP dan mesej ralat tanpa token, password, OTP atau URL callback penuh.

## Ujian penerimaan Android

| Ujian | Hasil yang diperlukan |
| --- | --- |
| Telefon mencapai discovery | Metadata boleh dibaca melalui rangkaian staging |
| Login password/MFA dan MyDigital ID | Kembali ke aplikasi, /mobile/session aktif |
| Callback ketika app aktif / belum dibuka | Transaksi betul diteruskan; callback tidak sah ditolak |
| Staf/pelajar dan profil | Medan betul, null dikendalikan, sifar depan nombor staf kekal |
| Tutup dan buka app / token luput | Sesi sah dipulihkan atau refresh berjaya |
| Permintaan serentak | Tidak berlaku refresh serentak yang merosakkan rotation |
| Logout | Token tempatan dipadam; refresh lama ditolak |
| Offline / akaun dibatalkan | Ralat jelas dan akses tidak diberi tanpa sesi sah |

## Maklumat yang masih perlu dilengkapkan

Isi hanya ruangan belum lengkap dalam borang: PIC teknikal, versi Flutter/pustaka OIDC, seni bina backend, cara menyimpan token dan jadual/peranti ujian. Nama aplikasi, package ID, callback, jenis custom scheme serta client_id sudah diisi.

Jika ada backend perniagaan, sahkan cara backend mengesahkan identiti bersama team OneID. Audience oneid-mobile-session bukan kebenaran automatik untuk semua API perniagaan; backend tidak boleh mempercayai profil yang dihantar klien tanpa bukti yang disahkan.

Keutamaan sekarang: laksanakan Langkah 1–6 dan sediakan build Android untuk ujian bersama. Konfigurasi iOS dan production adalah fasa berasingan.
