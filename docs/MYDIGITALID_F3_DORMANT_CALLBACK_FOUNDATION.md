# MyDigital ID Fasa 3 — Dormant Authorization dan Callback Foundation

> **Status supersession — 26 Julai 2026:** Callback foundation telah diaktifkan
> di staging dan dibuktikan dengan provider sebenar. Secure-session bootstrap
> hotfix berada pada commit `081087a`; login endpoint kini menghasilkan redirect
> `303`. Lihat `MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md`.

Tarikh: 26 Julai 2026
Status historical: siap secara dormant; superseded oleh activation staging

## 1. Objektif dan sempadan

Fasa 3 menyediakan sempadan HTTP dan transaksi OIDC Authorization Code + PKCE
tanpa menggunakan schema Fasa 2 secara live. Pelaksanaan ini sengaja tidak:

- memaparkan tindakan login MyDigital ID pada halaman login;
- membaca atau menulis `user_federated_identity` atau `federated_auth_event`;
- mencari atau menghubungkan akaun dalam `user_tbl`;
- membentuk sesi authenticated OneID;
- mengubah data nama atau NRIC OneID; atau
- mengaplikasikan migration Fasa 2.

`ONEID_MYDID_ENABLED` kekal `false`. Dalam keadaan ini endpoint login dan callback
menjawab `404 Not Found` tanpa memerlukan client secret.

## 2. Komponen yang disediakan

Endpoint:

- `/auth/mydigitalid/login.php`
- `/auth/mydigitalid/callback.php`

Foundation:

- `MyDigitalIdAuthorizationTransaction` menyimpan state, nonce, PKCE verifier,
  masa ciptaan dan return path setempat.
- `MyDigitalIdAuthorizationTransactionStore` menyediakan transaksi sekali guna
  dengan TTL 300 saat.
- `MyDigitalIdAuthorizationRequest` membina URL authorization yang dipakukan
  kepada issuer UPNM, code flow, callback berdaftar dan PKCE `S256`.
- `MyDigitalIdCallbackRequest` menerima GET sahaja, menggunakan allowlist
  parameter dan menolak provider error, issuer asing serta input cacat.
- `MyDigitalIdProtocolGateway` menyediakan adapter dormant kepada library OIDC.
  Ia menyemak persamaan `sub` ID token/UserInfo, mewajibkan nonce yang sepadan,
  dan mewajibkan claim masa token.

## 3. Urutan keselamatan callback

Urutan yang diwajibkan ialah:

1. Semak feature flag dan kaedah/parameter HTTP.
2. Semak format callback dan issuer jika `iss` dihantar.
3. Ambil dan terus padam transaksi daripada sesi.
4. Banding `state` menggunakan `hash_equals`.
5. Tolak transaksi luput, replay atau tidak sepadan.
6. Hanya selepas semua langkah itu, token exchange boleh dipanggil.

Langkah 3–5 berada di sempadan aplikasi kerana library dependency melakukan
token exchange sebelum perbandingan state dalamannya. Adapter protocol tetap
menyemai state, nonce dan verifier ke sesi library sebagai pertahanan tambahan.

## 4. Return path dan data sensitif

Return path tidak diterima sebagai URL bebas. Nilai tunggal yang dibenarkan ialah
`/page/dashboard`; nilai lain ditukar kepada nilai itu. State, nonce dan verifier
tidak dilog atau dipersist ke pangkalan data.

Nama, NRIC dan token daripada MyDigital ID hanya wujud sebagai objek transient
selepas pengesahan protocol. Fasa 3 tidak memanggil adapter tersebut dari callback
live dan tidak menyimpan data itu.

## 5. Gate sebelum sambungan seterusnya

Callback kekal memberi `503` selepas transaksi tempatan yang sah jika flag
tersilap dihidupkan pada tahap ini. Penyambungan kepada protocol, repository dan
sesi OneID memerlukan semua gate berikut:

1. migration Fasa 2 diluluskan dan diaplikasikan;
2. HMAC identity key serta key ID tersedia melalui secret store;
3. polisi retention audit, backup dan change window diluluskan;
4. Fasa 4 account matching menetapkan hanya akaun OneID aktif boleh masuk;
5. keputusan ambiguous/no-match mesti fail closed dan tidak auto-register; dan
6. ujian end-to-end UAT melalui VPN menggunakan akaun rujukan `0530-09`.

## 6. Verifikasi

Jalankan:

```bash
php tests/characterization/mydigitalid_f3_callback_foundation.php
php tools/mydigitalid_f3_contract.php
```

Ujian meliputi pembinaan URL, PKCE, TTL, state mismatch, replay, provider error,
issuer asing, parameter luar allowlist dan return URL luaran. Ujian Fasa 3 tidak
membuat network call, repository call, schema mutation atau authenticated session.
