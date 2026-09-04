# MD5 — Login dan MFA Developer Semasa Maintenance

**Tarikh:** 4 September 2026  
**Rujukan:** `ONEID-MAINTENANCE-DEVELOPER-20260904-01`  
**Fasa:** 5 — login developer dan MFA boundary  
**Status:** diluluskan owner pada 4 September 2026; Fasa 6 dibenarkan

## 1. Hasil

Fasa ini menyediakan laluan login developer khusus dalam kedua-dua document
root. Laluan hanya boleh dirender apabila maintenance aktif dan feature flag
aktif. Butang login developer pada halaman maintenance juga hanya kelihatan
apabila flag aktif.

Selepas password sah, server menyemak akaun `u_type=0` dan exact active grant.
Kegagalan menggunakan mesej umum supaya kewujudan grant tidak terdedah.

## 2. MFA boundary

Login developer maintenance memaksa polisi transaksi `ENFORCED` walaupun akaun
tidak berada dalam pilot/category normal. User MFA infrastructure tetap mesti
tersedia, runtime policy tidak boleh `OFF`, activation mesti authorized dan
email factor mesti enabled. Jika prasyarat gagal, login fail closed sebelum
token atau authenticated session diwujudkan.

Developer menggunakan faktor User MFA, bukan faktor atau grant Admin Step-Up.
MyDigital ID tidak disediakan pada login khusus ini.

## 3. Grant binding dan finalization

Pending session menyimpan grant ID dan configuration version yang diperoleh
daripada server. Sebelum token finalizer berjalan, sistem mengesahkan semula:

- maintenance masih aktif;
- feature masih aktif;
- account dan grant masih layak; dan
- grant ID/version sama dengan pending transaction.

Selepas token dan sesi diwujudkan, semakan sama dijalankan sekali lagi untuk
menutup race window. Kegagalan akan revoke token baharu dan membersihkan sesi.
Kejayaan menyimpan grant ID/version pada authenticated session dan menghasilkan
audit runtime tanpa token atau OTP.

Redirect akhir ialah `/page/dashboard`. Parameter service provider yang diwarisi
daripada login form dibuang supaya maintenance grant tidak membuka aplikasi SSO
luar.

## 4. Boundary Fasa 5

Fasa ini hanya membenarkan route login dan route MFA sementara melepasi gate.
`MaintenanceGate` belum membenarkan authenticated developer membuka portal
selepas login. Authenticated bypass dan continuous revalidation ialah Fasa 6.

Feature committed kekal OFF dan migration UAT belum dilakukan, maka flow tidak
boleh diaktifkan pada keadaan semasa.

## 5. Verifikasi

```bash
php tools/maintenance_developer_phase5_contract.php
php tools/maintenance_developer_phase4_contract.php
php tools/maintenance_developer_phase3_integration.php
php tools/maintenance_mode_contract.php
```

## 6. Gate Fasa 5

- [x] Route tersedia untuk kedua-dua document root.
- [x] Route dan butang disembunyikan apabila feature OFF.
- [x] Password diterima sebelum eligibility diperiksa.
- [x] Penolakan eligibility tidak mendedahkan grant state.
- [x] MFA diwajibkan sebelum token/session.
- [x] Pending MFA terikat grant ID dan version.
- [x] Maintenance dan grant disemak sebelum serta selepas finalization.
- [x] Kegagalan selepas token creation membuat compensating revocation.
- [x] Developer kekal `u_type=0`.
- [x] Redirect hanya ke user dashboard, bukan admin atau external SP.
- [x] Cancel/failure membersihkan pending marker.
- [x] Runtime authenticated bypass masih belum diaktifkan.

Fasa 6 hanya boleh bermula selepas owner mengesahkan Fasa 5.

**Keputusan owner:** Fasa 5 diluluskan melalui arahan memulakan Fasa 6 pada
4 September 2026. Migration dan activation UAT masih belum dibenarkan.
