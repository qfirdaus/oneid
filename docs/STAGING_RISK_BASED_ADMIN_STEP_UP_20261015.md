# Staging — Risk-based Admin Step-up

Tarikh: 15 Oktober 2026  
Status: staging sahaja, belum diluluskan untuk Git atau production

## Polisi

- `ADMIN_ACCESS` melindungi kemasukan dashboard, semua bacaan dan kemas kini
  pentadbiran rutin.
- MFA `ADMIN_ACCESS` yang disahkan dalam lima minit terakhir boleh digunakan
  untuk tindakan kritikal tanpa meminta kod kedua.
- Sambungan tempoh sesi admin mengekalkan masa MFA asal. Renewal tidak dianggap
  sebagai MFA baharu.
- Selepas tempoh fresh lima minit, tindakan kritikal memerlukan purpose
  `SECURITY_CONFIGURATION_CHANGE` atau `ACTIVE_SESSION_REVOCATION` yang tepat.

## Kemas kini rutin

Carian, laporan, preview, metadata translation, default locale, Login Banner,
profil pengguna, reset password pengguna, kategori, ACL dan assignment aplikasi
menggunakan `ADMIN_ACCESS`.

## Tindakan kritikal

- polisi MFA, exemption dan recovery;
- password recovery dan konfigurasi SSO;
- Maintenance Mode dan maintenance developer access;
- rotation Site API Code;
- full dan operational sync;
- permanent application purge dan pembuangan kategori pengguna;
- Apply active-session revocation.

Preview pembatalan sesi menggunakan `ADMIN_ACCESS`. Hanya Apply memerlukan
`ACTIVE_SESSION_REVOCATION` selepas recent-MFA window tamat.

## Kawalan kekal

Semua request kekal memerlukan sesi sah, token SSO aktif, role admin, CSRF dan
password-change gate. Service berisiko tinggi terus menggunakan transaction,
confirmation, audit dan correlation ID masing-masing.
