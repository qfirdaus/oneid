# F7.5 — UI, Controlled Bootstrap dan Operational Recovery

**Change ID:** `ONEID-F7-2FA-20260720-01`  
**Owner/pilot:** `0530-09`  
**Status:** PILOT ACTIVATED / FUNCTIONAL UAT HANDED OFF TO F7.6 OBSERVATION

F7.5 menyediakan halaman `page/admin-step-up`, API ber-CSRF untuk status,
request/verify OTP e-mel, verify TOTP, enrollment/confirmation/revoke TOTP,
preference per-admin dan controlled bootstrap. Pautan Administrator kini masuk
melalui halaman challenge ini.

QR TOTP dirender secara lokal menggunakan salinan Debian `phpqrcode` 1.1.4
(lesen/copyright disimpan bersama dependency). Endpoint QR hanya membaca faktor
PENDING milik admin, session dan browser yang tepat, serta menghantar
`Cache-Control: no-store`. Secret tidak dihantar kepada servis QR luar.

Selepas verification berjaya, PHP session ID dan CSRF token dirotasi. Grant yang
baru dikeluarkan dipindahkan secara server-side daripada hash session lama ke
hash baharu; kegagalan rebind gagal tertutup.

Pilihan `EMAIL_OTP` atau `TOTP` adalah preference setiap admin, bukan grant.
TOTP hanya boleh dipilih selepas confirmed; e-mel hanya apabila sah. Ketika
feature OFF perubahan memerlukan current password, manakala ketika ON ia
memerlukan `SECURITY_CONFIGURATION_CHANGE` grant.

Bootstrap enable memerlukan current password, e-mel sah, TOTP confirmed,
configuration version tepat, sebab minimum 10 aksara, Change ID tepat dan typed
confirmation `ENABLE ADMIN 2FA`. Ia merekod configuration history dan audit
secara transaksi.

## Bukti 20 Julai 2026

- UI/local QR/rotation/preference/bootstrap-negative contract: 9/9 lulus.
- QR menghasilkan PNG tempatan tanpa cache secret pada filesystem.
- Audit dictionary event 50–53 dipasang dengan feature OFF.
- F7.1–F7.4 regression kekal lulus.
- Controlled bootstrap berjaya pada 20 Julai 2026 19:11:29 MYT;
  configuration version meningkat kepada 4 dan feature menjadi 1.
- TOTP `ADMIN_ACCESS` verification berjaya dan grant 15 minit dikeluarkan.

Automated test tidak menghantar e-mel atau mengaktifkan feature. Owner `0530-09`
telah menjalankan enrollment dan bootstrap secara interaktif. Functional UAT
ditutup dalam F7.6; observation 24 jam dan enhanced monitoring masih perlu
dilengkapkan sebelum keputusan akhir `ACCEPT`.
