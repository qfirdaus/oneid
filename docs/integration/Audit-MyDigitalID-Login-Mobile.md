# Audit awal MyDigital ID untuk login mobile

Skop: semakan kod staging; belum menambah atau mengaktifkan MyDigital ID pada mobile.

## Dapatan kod

- `public/auth/mydigitalid/login.php` memanggil endpoint MyDigital ID web sedia ada.
- `MyDigitalIdCallbackOrchestrator` mengesahkan protokol, memadankan akaun OneID dan memanggil finalizer sesi tempatan.
- `MyDigitalIdLocalLoginFinalizer` mencipta token web/cookie/sesi. Jika multi_session=0, ia membatalkan token pengguna terdahulu. Ia tidak sesuai dipanggil terus untuk menyelesaikan transaksi mobile.
- `MyDigitalIdAuthorizationTransaction` mengehadkan returnPath kepada `/page/dashboard`. Ia belum mengikat callback kepada challenge/client/sesi mobile.
- `MyDigitalIdAccountLinkingService` memadankan subject/identiti kepada akaun tempatan dan menolak padanan tidak layak. Penggunaan semula perlu mengekalkan aturan pemadanan, audit dan semakan akaun aktif; bukan sekadar menerima parameter identiti daripada browser.

## Reka bentuk dicadangkan

Flutter masih berhubung dengan issuer OIDC OneID melalui Authorization Code + PKCE. Halaman hosted OneID menyediakan dua pilihan: credential OneID atau MyDigital ID. Bagi pilihan kedua, OneID menjalankan pengesahan upstream, mengesahkan hasil callback, memadankan akaun aktif dan menyambung transaksi mobile asal. Token yang diterima Flutter tetap token OneID, bukan token mentah MyDigital ID.

Perubahan diperlukan:

1. Transaksi upstream mobile terikat kepada browser, client, challenge OneID, state, nonce, PKCE dan tempoh sah; callback sekali guna. Jangan gunakan return URL bebas daripada input pengguna.
2. Callback/finalizer khusus mobile yang tidak mencipta cookie web, mengalih ke dashboard atau membatalkan sesi web sebagai kesan sampingan.
3. Adapter menerima bukti MyDigital ID yang telah disahkan server-side; rekod auth_method/amr/acr berdasarkan bukti sebenar, bukan menandakan `pwd` atau mendakwa MFA tanpa bukti.
4. Semak polisi MFA mengikut kaedah login. Polisi sedia ada mempunyai scope PASSWORD_ONLY; jangan mengenakan atau mengecualikan MFA semata-mata berdasarkan label MyDigital ID. Pastikan tingkah laku sepadan dengan keputusan polisi web yang diluluskan.
5. Selesaikan pemilihan akaun staf/pelajar bagi identiti yang mempunyai lebih satu akaun. Jangan menggabungkan subject atau memberikan role admin kepada apps.
6. Sahkan pendaftaran redirect URI/client staging di pihak MyDigital ID. Callback baharu tidak boleh dianggap telah dibenarkan oleh provider.
7. Uji pembatalan, callback palsu/ulang, timeout, akaun tidak aktif, padanan berganda, browser kembali daripada aplikasi MyDigital ID, refresh/logout OneID serta regresi aliran web lama.

## Batas kesimpulan

Kod sedia ada memberi asas untuk integrasi, tetapi penggunaan pada telefon yang sama, deep link/app-switch serta keperluan onboarding provider masih perlu disahkan dalam persekitaran MyDigital ID sebenar. Audit kod ini tidak membuktikan kelulusan redirect URI atau sokongan aliran telefon tertentu. Tiada perubahan callback, akaun, polisi MFA atau konfigurasi provider dilakukan semasa audit.
