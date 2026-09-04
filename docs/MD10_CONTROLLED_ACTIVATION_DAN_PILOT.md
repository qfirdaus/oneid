# MD10 — Controlled Activation dan Pilot

**Tarikh:** 4 September 2026  
**Fasa:** 10 — activation UAT dan satu akaun pilot  
**Status:** ditutup dengan ujian login sebenar dikecualikan owner; Fasa 11 dibenarkan

**Keputusan owner:** Grant dan UI berjaya diwujudkan, tetapi ujian login sebenar
tidak dilaksanakan kerana owner tiada akses kepada credential akaun pilot.
Keputusan login tidak ditanda PASS; risiko residual diterima sementara dan
sebarang isu penggunaan sebenar akan dilaporkan untuk pembetulan.

## Boundary

Fasa ini mengaktifkan capability pada UAT dan menguji satu akaun pengguna biasa
yang diluluskan. Ia tidak menukar `u_type`, kategori atau ACL aplikasi. Grant
pilot mesti singkat, memerlukan Admin Step-Up dan MFA pengguna ketika login.

## Gate sebelum activation

```bash
php tools/maintenance_developer_phase10_preflight.php
```

Preflight memerlukan schema lengkap, schema apply ditutup, feature masih OFF,
tiada active grant sedia ada, User MFA enforced, approval pilot, change
reference, akaun exact `u_type=0` yang aktif dan window semasa maksimum dua jam.
Output tidak mendedahkan ID pengguna.

## Urutan pilot

1. Preflight mesti `GO` ketika feature masih OFF.
2. Hidupkan feature dalam private runtime.
3. Admin melengkapkan Security Configuration Change Step-Up.
4. Admin memberikan grant yang terikat kepada akaun dan window diluluskan.
5. Hidupkan maintenance dan tester melengkapkan password serta MFA.
6. Sahkan dashboard/ACL kekal level pengguna.
7. Revoke grant; sesi mesti ditamatkan pada request seterusnya.
8. Matikan feature dan tutup approval pilot sebelum tamat window.

Kegagalan MFA, audit, token revocation atau revalidation ialah syarat STOP:
matikan feature dan revoke grant sebelum siasatan diteruskan.
