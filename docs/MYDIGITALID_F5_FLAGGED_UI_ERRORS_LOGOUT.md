# MyDigital ID Fasa 5 — Flagged UI, Bilingual Errors dan Logout

> **Status supersession — 26 Julai 2026:** UI kedua telah diaktifkan dan
> digunakan di staging, kemudian dikemas kini dengan card profesional. Generic
> rejection UX dan account switching dilanjutkan dalam Fasa 7. Lihat
> `MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md`.

Tarikh: 26 Julai 2026
Status historical: siap secara dormant; superseded oleh activation staging

## UI login kedua

`index.php` membaca `ONEID_MYDID_ENABLED`.

- `false`: preview sedia ada dipaparkan dan tidak boleh diklik;
- `true`: butang “Log masuk dengan MyDigital ID” dipaparkan;
- form username/password kekal tersedia dalam kedua-dua keadaan.

Butang mempunyai accessible label, sasaran dalaman tetap
`/auth/mydigitalid/login.php` dan bantuan menerangkan bahawa pilihan ini untuk
warganegara Malaysia yang mempunyai akaun OneID aktif. Pelajar antarabangsa
terus menggunakan nombor matrik dan kata laluan.

## Mesej BM/English

Locale BM dan English disediakan untuk:

- label/penerangan butang;
- callback tidak sah atau luput;
- akaun tidak layak/tidak dapat diteruskan; dan
- kegagalan provider sementara.

Callback hanya menyimpan flash code allowlisted dalam session dan redirect ke
halaman login. Reason dalaman seperti not-found, inactive, ambiguous dan mismatch
tidak dipaparkan supaya account enumeration tidak berlaku. Transaksi OIDC dan
session key library dibersihkan pada terminal error.

## Logout

Selepas login MyDigital ID berjaya:

- session menyimpan `auth_method=mydigitalid`;
- ID token yang telah disahkan disimpan transient dalam server-side session
  untuk kegunaan `id_token_hint`; dan
- ID token tidak ditulis ke DB atau log.

Logout menjalankan urutan local-first:

1. revoke local SSO token;
2. clear cookie;
3. clear/destroy OneID session;
4. redirect ke MyDigital ID end-session endpoint; dan
5. provider kembali ke URI staging yang telah didaftarkan.

Jika konfigurasi provider atau ID token tidak tersedia, local logout tetap
berjaya dan redirect kembali ke OneID. Password session tidak menjalankan
federated logout dan mengekalkan behavior asal.

## Keadaan dormant

Committed default kekal:

```text
ONEID_MYDID_ENABLED=false
```

Oleh itu WSL/local dan staging selepas deployment awal masih menunjukkan preview,
manakala endpoint login/callback memberi 404 sehingga activation berasingan.

## Verifikasi

```bash
php tests/characterization/mydigitalid_f5_ui_logout.php
php tools/mydigitalid_f5_contract.php
```

Ujian mengesahkan URL logout dipakukan, token hint invalid ditolak, UI dikawal
flag, locale bilingual lengkap, flash message generik dan password logout kekal
serasi.
