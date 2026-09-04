# MD6 — Runtime Gate dan Continuous Revalidation Developer Maintenance

**Tarikh:** 4 September 2026  
**Rujukan:** `ONEID-MAINTENANCE-DEVELOPER-20260904-01`  
**Fasa:** 6 — authenticated runtime enforcement  
**Status:** diluluskan owner pada 4 September 2026; Fasa 7 dibenarkan

## 1. Hasil

Fasa ini melengkapkan authorization runtime bagi sesi developer ketika
maintenance aktif. Session marker bukan authorization sendiri. Pada setiap
request PHP yang melalui konfigurasi pusat, gate membaca semula:

- authenticated user dan `u_type=0`;
- SSO cookie dan status token dalam database;
- status akaun melalui domain revalidation;
- active grant daripada database;
- grant ID; dan
- configuration version.

Hanya padanan tepat semua nilai dibenarkan meneruskan request.

## 2. Forced termination

Jika token, akaun, feature atau grant tidak lagi sah, atau ID/version tidak
sepadan, gate akan cuba:

1. revoke token semasa dengan reason code;
2. merekod event termination tanpa raw token;
3. membersihkan cookie dan keseluruhan authenticated session; dan
4. mengembalikan halaman maintenance atau JSON `503` mengikut request.

Pembersihan sesi tetap berlaku jika audit atau compensating token update gagal.
Ini memastikan authorization fail closed.

## 3. Route isolation

Revalidation developer berlaku sebelum pengecualian `/api`, jadi session
developer bertanda tidak boleh menggunakan route itu untuk mengelakkan
continuous validation. Route admin masih menggunakan `oneid_require_admin_page`
dan exact `u_type=1`; capability developer tidak mengubah sempadan tersebut.

Static asset tidak memerlukan revalidation kerana ia tidak membawa data atau
mutation terautentikasi.

## 4. Maintenance tamat

Apabila maintenance tidak aktif, central gate kembali sebelum memproses
capability developer. Sesi yang token dan akaunnya masih sah terus menjadi sesi
pengguna biasa seperti keputusan MD1. Marker capability dibersihkan supaya sesi
lama tidak boleh masuk secara automatik pada maintenance berikutnya; login dan
MFA baharu diperlukan. Semua authorization admin kekal bergantung kepada
`u_type=1`.

## 5. Keadaan deployment

Feature committed masih OFF dan schema UAT belum dipasang. Oleh itu kod runtime
ini dormant dan tidak mengubah tingkah laku pengguna semasa. Migration dan
activation memerlukan fasa rollout serta approval berasingan.

## 6. Verifikasi

```bash
php tools/maintenance_developer_phase6_contract.php
php tools/maintenance_developer_phase5_contract.php
php tools/maintenance_developer_phase3_integration.php
php tools/maintenance_mode_contract.php
```

## 7. Gate Fasa 6

- [x] Exact user type, grant ID dan version diperlukan.
- [x] Active SSO token disahkan di server.
- [x] Akaun dan grant dibaca semula pada setiap request PHP bertanda.
- [x] Revoke/expiry/version change menolak request berikutnya.
- [x] Invalid session menyebabkan token compensation dan session cleanup.
- [x] Termination diaudit tanpa raw token.
- [x] Developer tidak mendapat akses admin.
- [x] Maintenance tamat mengekalkan sesi biasa tetapi membuang capability marker.
- [x] Feature kekal OFF dan UAT tidak dimutasi.

Fasa seterusnya ialah hardening, end-to-end regression, migration rehearsal dan
UAT readiness. Ia hanya boleh bermula selepas owner mengesahkan Fasa 6.

**Keputusan owner:** Fasa 6 diluluskan melalui arahan memulakan Fasa 7 pada
4 September 2026. Migration dan activation UAT masih belum dibenarkan.
