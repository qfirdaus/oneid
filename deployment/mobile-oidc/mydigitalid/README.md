# MyDigital ID mobile — staging sahaja

Pemilik meluluskan tambahan URI mobile dan kemudian melaporkan login MyDigital ID mobile serta web berjaya. Ini mengesahkan callback diterima dalam ujian staging; senarai konfigurasi provider tidak diperiksa secara langsung.

Kekalkan callback web sedia ada:
`https://oneid-uat.upnm.edu.my/auth/mydigitalid/callback.php`

Tambah kepada client yang sama (jangan ganti URI lama):
`https://oneid-uat.upnm.edu.my/mobile/mydigitalid/callback`

Client/secret/issuer dibaca melalui resolver runtime web sedia ada. Konfigurasi mobile menggunakan salinan immutable dengan redirect URI berasingan, terhad kepada staging. Tiada wildcard atau penukaran secret diperlukan.

## Aliran semasa: akaun ditentukan selepas MyDigital ID

1. Halaman awal hanya menunjukkan butang MyDigital ID (BM/EN), tanpa pilihan staf/pelajar.
2. State, nonce, PKCE dan identiti upstream disahkan dalam sesi mobile terasing.
3. Satu akaun/konteks aktif yang layak dipilih secara automatik.
4. Jika staf dan pelajar kedua-duanya layak (dua rekod atau satu rekod dengan dua kategori), pengguna memilih selepas pengesahan. Kod pilihan rawak tidak mendedahkan UID, NRIC atau token upstream.
5. Pemilihan disemak semula dalam transaksi database sebelum penerimaan Hydra. Status akaun, NRIC, polisi, versi keselamatan dan pautan MyDigital ID mesti masih sah. Pilihan sekali guna, terikat browser/transaksi dan luput paling lama lima minit atau apabila transaksi asal luput.

`PdoMobileMyDigitalIdAccounts` melakukan pemadanan mobile berasingan. Pautan web satu-ke-satu tidak dicipta, dipindahkan atau dikemas kini oleh aliran mobile. Pautan dengan subject/NRIC/key yang bercanggah atau berstatus REVOKED ditolak. Ini menggantikan penggunaan `MyDigitalIdAccountLinkingService` dalam aliran mobile; web masih menggunakan servis asalnya. Rekod kejayaan mobile kekal dalam audit adapter, bukan kaunter login pautan web.

Dua akaun bagi kategori sama dianggap ambigu dan ditolak. Akaun tidak aktif, kategori tidak disokong atau akaun di luar allowlist pilot tidak ditawarkan. Had pilot masih `0530-09`; akaun pelajar tidak dibuka secara automatik kepada pilot hanya kerana berkongsi NRIC. Tiada akaun baharu dicipta atau hak admin diberikan.

MFA berskop PASSWORD_ONLY kekal pada aliran kata laluan. MyDigital ID menggunakan AMR `mydigitalid` dan ACR `urn:oneid:mydigitalid`; keperluan tukar kata laluan akaun masih dihormati. Hanya HMAC bukti identiti disimpan dalam sesi sementara, bukan NRIC mentah atau ID token upstream.

Endpoint callback kekal sama dan loopback sahaja dalam pilot. Tiada perubahan skema atau pendaftaran callback diperlukan untuk perubahan pemilihan akaun ini. Mulakan login baharu; transaksi daripada versi lama perlu dimulakan semula.

## Pengaktifan pilot oleh pentadbir

Pastikan provider menambah URI tepat di atas. Hentikan pilot sebelum mengubah konfigurasi:

```sh
cd /var/www/oneid-uat
sudo python3 tools/mobile-oidc-hosted/pilot-control.py stop
sudo php tools/mobile-oidc-hosted/enable-mydigitalid-pilot.php --apply
sudo python3 tools/mobile-oidc-hosted/pilot-control.py retry
```

Skrip enable membuat backup persendirian dan hanya menetapkan flag mobile, dengan hosted kekal OFF sehingga retry. Tidak mengubah konfigurasi web, key, data akaun atau allowlist provider. Flag ini kekal selepas stop, tetapi semua mobile endpoints tetap tertutup semasa hosted OFF.

Gunakan tunnel/browser pilot sedia ada, buka `/mobile-test/`, klik Login dengan OneID, kemudian MyDigital ID. Hanya akaun pilot 0530-09 dibenarkan. Jangan hantar token, password, kod OTP atau screenshot URL callback penuh. Selepas ujian gunakan `pilot-control.py stop`.

## Pengesahan

Pengguna sebelum ini mengesahkan login MyDigital ID mobile, sesi, refresh, logout dan login web berjaya. Itu ialah aliran sebelum perubahan pemilihan akaun automatik; aliran baharu masih memerlukan ujian pengguna staging. Flutter sebenar belum diuji.

Ujian automatik perubahan ini:
- `tests/mobile-oidc/mydigitalid.php`: 36 semakan, termasuk auto staf/pelajar, dua rekod, dua kategori pada satu rekod, pilihan palsu/berulang/luput, NRIC berubah, akaun ditutup, polisi/pautan berubah, padanan ambigu dan allowlist pilot.
- `tests/mobile-oidc-hosted/mydigitalid-selection.php`: 6 semakan render/dispatch halaman sebenar dengan fixture; pilihan selepas callback sahaja, BM/EN, CSRF dan redirect selepas pilihan. Tiada panggilan provider sebenar.
- MySQL fixture: 43/43 termasuk pemadanan dua rekod, transaksi pilihan, konflik/revocation pautan dan pautan/sesi web tidak berubah.
- Adapter: 73/73; protokol: 20/20; regresi MyDigital ID web F1/F3/F4b: 42 semakan.
- Rehearsal HTTP private MySQL/Hydra/PostgreSQL: 123/123, meliputi login kata laluan, MFA, sesi, refresh dan logout.

Butang mobile menggunakan logo dan reka bentuk split-brand seperti login web; pemilihan akaun awal telah dibuang. Untuk menguji, buka login baharu daripada `/mobile-test/`. Jika pilot sudah OFF, jalankan `pilot-control.py retry`. Tiada migrasi database atau perubahan provider diperlukan.
