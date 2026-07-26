# MyDigital ID Fasa 4 — Dormant Account Matching, Linking dan Audit

> **Status supersession — 26 Julai 2026:** Matching/linking repository telah
> disambung kepada callback staging. Akaun pilot berjaya dilink dan login;
> identiti lain direkod `MYDID_USER_NOT_FOUND` tanpa auto-registration. Lihat
> `MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md`.

Tarikh: 26 Julai 2026
Status historical: siap secara isolated; superseded oleh wiring staging

## 1. Skop

Fasa ini melaksanakan authorization gate selepas identiti MyDigital ID telah
disahkan oleh protocol. Ia merangkumi:

- padanan NRIC kepada akaun OneID;
- gate akaun aktif;
- link pertama antara `issuer + sub` dan `u_id`;
- verifikasi link untuk login seterusnya;
- audit bagi keputusan berjaya atau ditolak; dan
- larangan auto-registration serta profile overwrite.

Pembentukan sesi authenticated OneID, local token issuance dan callback wiring
belum dibuat pada masa dokumen asal kerana migration Fasa 2 belum diaplikasikan.
Keadaan ini telah superseded: migration dan callback wiring kini aktif di
shared development/staging database.

## 2. Peraturan padanan

NRIC MyDigital ID dinormalisasi kepada tepat 12 digit. Padanan OneID menggunakan
peraturan sumber sedia ada:

- staf: `data3` berisi dan NRIC diambil daripada `data4`;
- pelajar: `data3` kosong dan NRIC diambil daripada `data2`.

Keputusan:

| Keadaan | Keputusan |
|---|---|
| Tepat satu akaun aktif | boleh diteruskan |
| Tiada akaun aktif tetapi wujud akaun tidak aktif | `MYDID_USER_INACTIVE` |
| Tiada padanan | `MYDID_USER_NOT_FOUND` |
| Lebih daripada satu akaun aktif | `MYDID_IDENTITY_AMBIGUOUS` |
| `sub` atau NRIC bercanggah dengan link | `MYDID_IDENTITY_MISMATCH` |

Nama daripada MyDigital ID tidak digunakan sebagai matching key dan tidak ditulis
ke `user_tbl`.

Pelajar antarabangsa tanpa NRIC tidak layak melalui aliran ini dan kekal
menggunakan login form dengan nombor matrik.

## 3. Lifecycle identity link

Login pertama:

1. hasilkan HMAC berasingan untuk subject dan NRIC;
2. lock row calon OneID;
3. pastikan tepat satu calon aktif;
4. pastikan `u_id` belum mempunyai link MyDigital ID lain;
5. cipta link aktif;
6. kemas kini statistik login link; dan
7. rekod audit `MYDID_LOGIN_SUCCESS`.

Login berikutnya:

1. cari link aktif menggunakan subject HMAC;
2. semak NRIC HMAC masih sama;
3. lock dan semak akaun OneID masih wujud serta aktif;
4. semak NRIC canonical OneID masih sama;
5. kemas kini statistik login; dan
6. rekod audit berjaya.

Link, login counter dan event dibuat dalam satu transaksi. Keputusan penolakan
yang dijangka turut diaudit dalam transaksi.

## 4. Data minimization

Jadual federated menyimpan digest HMAC sahaja bagi subject, NRIC, alamat IP,
user-agent dan session ID. Ia tidak menyimpan:

- nama MyDigital ID;
- NRIC plain text;
- authorization code;
- access/refresh token;
- ID token; atau
- client secret.

Objek keputusan berjaya hanya membawa medan OneID minimum yang diperlukan oleh
seam pembentukan sesi pada fasa seterusnya. `normalized_nric` dibuang sebelum
keputusan dipulangkan.

## 5. Komponen

- `PdoMyDigitalIdAccountMatcher`
- `MyDigitalIdAccountMatch`
- `MyDigitalIdAccountLinkingService`
- `MyDigitalIdAuthenticationDecision`
- `PdoMyDigitalIdIdentityRepository::findActiveByUser`

## 6. Isolated rehearsal

Rehearsal menggunakan database rawak sementara dan menguji:

- staf melalui `data4`;
- pelajar melalui `data2`;
- login pertama dan login berulang;
- subject kedua cuba mengambil akaun yang telah dilink;
- akaun tidak aktif;
- akaun tidak ditemui;
- padanan aktif berganda;
- event dan login counter;
- tiada raw PII dalam schema; dan
- hash keseluruhan row `user_tbl` kekal sama.

Keputusan: 11/11 lulus, dua link sah, tujuh event, zero auto-registration,
zero profile overwrite dan database rehearsal dipadam.

Jalankan:

```bash
php tools/mydigitalid_f4_contract.php
php tools/mydigitalid_f4_isolated_rehearsal.php
```

## 7. Gate yang masih tertutup

Sebelum wiring callback:

1. migration Fasa 2 mesti diluluskan dan diaplikasikan;
2. HMAC key dan key ID mesti diprovisikan dalam secret store;
3. backup, change window dan retention audit mesti diluluskan;
4. service perlu diintegrasikan dengan protocol callback;
5. kegagalan persistence perlu dipetakan kepada respons generik;
6. pembentukan sesi/local token OneID perlu diuji berasingan; dan
7. UAT end-to-end melalui VPN perlu menggunakan akaun `0530-09`.
