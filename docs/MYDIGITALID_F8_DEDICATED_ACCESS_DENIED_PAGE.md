# MyDigital ID Fasa 8 — Dedicated Access Denied Page

**Tarikh:** 26 Julai 2026
**Status:** implemented dalam kod; staging browser acceptance diperlukan

Status canonical dan baki acceptance dirujuk dalam
`MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md`.

## Tujuan

Fasa 8 menambah halaman khas selepas pengguna berjaya mengesahkan identiti di
MyDigital ID tetapi tidak mendapat authorization OneID. Kes yang diliputi
termasuk akaun OneID tiada, tidak aktif, padanan berganda atau identity link
tidak sepadan.

## Boundary keselamatan

- Semua sebab dalaman dipaparkan sebagai satu mesej generik.
- Reason code, NRIC, subject, nama, authorization code dan token tidak
  dimasukkan ke URL atau HTML.
- Halaman hanya tersedia apabila server-side verified rejection state masih
  sah dalam tempoh lima minit.
- Akses terus tanpa state kembali ke login dengan mesej generik.
- Response menggunakan no-store, no-referrer, frame denial dan CSP.
- Account switching kekal POST + CSRF dan menggunakan provider logout rasmi.
- Tiada auto-registration atau profile overwrite.

## UX

Halaman menyediakan:

- identiti visual OneID dan MyDigital ID;
- mesej BM/English bahawa akses OneID tidak dapat diberikan;
- penjelasan bahawa tiada akaun baharu dicipta dan profil tidak diubah;
- butang `Cuba akaun MyDigital ID lain`; dan
- pautan kembali ke login OneID biasa.

## Acceptance staging

1. Gunakan akaun MyDigital ID yang tiada akses OneID melalui VPN.
2. Selepas provider authentication, pastikan browser menuju ke
   `/auth/mydigitalid/access-denied.php`.
3. Pastikan tiada reason/token/PII dalam address bar atau source halaman.
4. Tukar BM/English dan semak mesej.
5. Klik `Cuba akaun MyDigital ID lain` dan pastikan provider logout serta QR
   baharu dipaparkan.
6. Ulang login pilot yang sah dan pastikan dashboard masih boleh dicapai.
7. Buka URL denial secara terus dalam private browser; ia mesti kembali ke
   login dan tidak memaparkan halaman penolakan.

Automated contract:

```bash
php tests/characterization/mydigitalid_f8_access_denied_page.php
php tools/mydigitalid_f6_security_suite.php
```
