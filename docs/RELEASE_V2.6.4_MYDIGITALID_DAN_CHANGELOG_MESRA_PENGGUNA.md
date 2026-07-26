# OneID v2.6.4 — MyDigital ID dan Changelog Mesra Pengguna

**Versi:** 2.6.4
**Tarikh:** 26 Julai 2026
**Skop:** staging dan kod sumber; production belum diluluskan

## Bahasa Melayu

### Apa yang baharu

- MyDigital ID tersedia sebagai pilihan log masuk kedua. Log masuk OneID
  menggunakan ID pengguna dan kata laluan kekal tersedia.
- Hanya pengguna yang mempunyai akaun OneID aktif boleh masuk melalui
  MyDigital ID. Sistem tidak mencipta akaun baharu dan tidak menggantikan nama
  atau nombor pengenalan yang telah disimpan dalam OneID.
- Pengguna yang tidak mempunyai akses menerima mesej yang jelas dan boleh
  memilih untuk mencuba akaun MyDigital ID yang lain.
- Log keluar OneID selepas log masuk MyDigital ID turut menggunakan proses log
  keluar rasmi penyedia.
- Paparan log masuk, mesej pengguna, rekod aktiviti dan perlindungan maklumat
  callback telah ditambah baik.

### Penambahbaikan sejarah versi

Paparan Version Releases kini menggunakan bahasa mudah dalam BM dan English.
Semua 39 versi masih disimpan mengikut tarikh dan turutan asal. Butiran
pelaksanaan dalaman diringkaskan daripada 229 catatan kepada 43 penerangan yang
lebih sesuai untuk pengguna. Dokumen audit teknikal kekal tersedia untuk
pentadbir dan pasukan operasi.

### Status

Ujian automatik, login positif dan penolakan pengguna tanpa akses telah lulus
di staging. Senarai penerimaan manual dan semua gate production dalam
`MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md` masih terpakai.

## English

### What is new

- MyDigital ID is available as a second sign-in option. The standard OneID
  username and password login remains available.
- Only users with an active OneID account may sign in through MyDigital ID.
  The system does not create a new account or replace the name or identity
  number already held by OneID.
- Users without access receive a clear message and may choose to try another
  MyDigital ID account.
- Signing out of OneID after a MyDigital ID login also uses the provider's
  official logout process.
- The login screen, user messages, activity records and callback information
  protection have been improved.

### Release-history improvement

Version Releases now uses plain language in BM and English. All 39 versions
remain in their original date order. Internal implementation details were
condensed from 229 entries into 43 user-friendly descriptions. Detailed
technical audit documents remain available to administrators and operations
teams.

### Status

Automated tests, a successful login and rejection of a user without access have
passed in staging. The manual acceptance list and all production gates in
`MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md` still apply.
