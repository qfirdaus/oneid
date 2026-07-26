# MyDigital ID Fasa 4B — Dormant Callback, Local Token dan Session

> **Status supersession — 26 Julai 2026:** Callback, local token dan session
> OneID telah digunakan dalam successful staging login. Feature kekal
> runtime-controlled dan schema apply `false`. Lihat
> `MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md`.

Tarikh: 26 Julai 2026
Status historical: siap secara dormant; superseded oleh activation staging

## Skop

Fasa 4B menyambungkan seam berikut dalam kod:

```text
callback request
  -> one-use state transaction
  -> OIDC protocol verification
  -> Fasa 4A account authorization/link/audit
  -> local SSO token
  -> authenticated OneID session
  -> dashboard
```

Wiring produksi tersedia dalam callback, tetapi kod itu tidak boleh dicapai
kerana committed default `ONEID_MYDID_ENABLED=false`. Tiada tindakan MyDigital ID
dipaparkan pada halaman login.

## Urutan dan fail-closed behavior

1. Feature flag disemak sebelum DB, HMAC key atau protocol client dibina.
2. Callback GET dan parameter allowlist disahkan.
3. Authorization transaction dipadam dan state disahkan sebelum token exchange.
4. Signature, issuer, audience, nonce, masa dan UserInfo subject disahkan.
5. Fasa 4A memutuskan akaun dibenarkan atau ditolak.
6. Hanya keputusan `allowed` boleh mencipta local token dan sesi.
7. Return path kekal `/page/dashboard`.

Keputusan reject tidak memanggil local login finalizer.

## Local token dan sesi

Finalizer menggunakan primitive keselamatan password login yang sama:

- token raw 32 random bytes/64 lowercase hex;
- DB menyimpan SHA-256 melalui `add_new_token`;
- `sys_config.multi_session=0` merevoke token lama;
- cookie `sso_cre` menggunakan helper `HttpOnly`, `SameSite=Lax`;
- `oneid_establish_authenticated_session` meregenerasi session ID; dan
- session ditanda `auth_method=mydigitalid`.

Jika cookie atau pembentukan sesi gagal selepas token dimasukkan, token baru
direvoke secara compensating dan partial local session dibersihkan.

## Batas transaksi

Fasa 4A merekod keputusan identity authorization dalam transaksi repository.
Local token issuance berlaku selepas keputusan itu. Jika finalization gagal,
tiada authenticated session dibentuk dan token baru direvoke. Operational error
mapping/log correlation tambahan akan dilengkapkan semasa activation hardening.

## Verifikasi

```bash
php tests/characterization/mydigitalid_f4b_callback_session.php
php tools/mydigitalid_f4b_contract.php
```

Ujian mock meliputi success, account rejection, protocol failure, single-session
policy, `auth_method`, serta compensating token revocation. Ia membuat zero
network call dan zero live schema mutation.

## Deployment

WSL kekal `ONEID_ENVIRONMENT=local`. Selepas Git push/pull ke staging:

1. provision konfigurasi dan secret private staging;
2. backup DB staging;
3. apply migration Fasa 2 melalui gated runner;
4. jalankan reconciliation;
5. uji endpoint masih 404 ketika flag false; dan
6. hanya aktifkan flag dalam change window ujian yang berasingan.
