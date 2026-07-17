# Release OneID v2.0.7 — Security dan Web Apps

**Versi:** 2.0.7
**Tarikh:** 17 Julai 2026

Release ini menggabungkan penambahbaikan selepas v2.0.6 bagi konfigurasi SSO,
Password Recovery, tukar kata laluan pengguna dan pengurusan Admin Web Apps.

## Perubahan utama

- konfigurasi SSO mempunyai validation, operational feedback, audit correlation,
  integriti database dan token lifecycle yang lebih jelas;
- Password Recovery dipisahkan daripada polisi SSO serta penghantaran test email
  dan OTP telah disahkan sehingga mailbox;
- tukar kata laluan mengesahkan current password dan polisi password, merotasi
  session semasa serta membatalkan session/token lain;
- MyDigital ID dipaparkan sebagai logo preview pada login tanpa fungsi aktif;
- Add/Edit Web Apps menggunakan validation, App ID kriptografi, atomic
  persistence, rollback dan mandatory audit;
- icon aplikasi disimpan berasingan bagi environment local dan staging;
- icon baharu dinormalisasi kepada static PNG 256×256 dengan kawalan jenis,
  dimensi, pixel, animation dan metadata;
- WA6 menyediakan laporan reconciliation read-only serta manifest SHA-256.
- login dan Password Recovery mempunyai bounded network timeout, perlindungan
  double-submit, pelepasan session lock semasa SMTP, audit correlation dan
  operational feedback apabila request tergendala.
- ID atau password kosong ditolak sebelum authentication; mesej login kekal
  menggunakan inline alert asal dengan validation dan timeout baharu.
- halaman login memuatkan runtime SweetAlert sebelum notification wrapper supaya
  feedback login dan Password Recovery benar-benar boleh dipaparkan.

## Batas release

- WA6 tidak memindah, mengkuarantin atau memadam sebarang fail;
- orphan candidate memerlukan semakan dan kelulusan owner per environment;
- MyDigital ID belum menyediakan authentication atau integration;
- shared database kekal digunakan oleh WSL dan staging, tetapi filesystem aset
  tidak dikongsi.
