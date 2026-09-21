# OneID 2.14.0 — Ketahanan Sinkronisasi dan UI Administrator

Tarikh release: 21 September 2026

## Ringkasan

Release ini merangkum perubahan selepas 2.13.4 bagi carian akaun
Administrator, Audit Log, dokumentasi Google Analytics 4 dan ketahanan
sinkronisasi pelajar UG production.

## Perubahan utama

- Carian akaun menyokong padanan separa serta hasil padat yang boleh discroll.
- Status akaun Aktif atau Tidak Aktif dipaparkan secara seragam.
- Audit Log tidak lagi memaparkan toast apabila rekod berjaya dimuatkan.
- Audit GA4 didokumenkan tanpa mengaktifkan tracking production.
- Polisi konflik identiti UG membenarkan kesinambungan daripada akaun external
  legasi yang tidak aktif sahaja; akaun aktif, manual dan dilindungi kekal
  gagal tertutup.
- Preview dan Apply berkongsi polisi yang sama serta menggunakan validasi batch.
- UI memulihkan keputusan sync selepas browser atau WAF timeout tanpa
  menghantar Apply kali kedua.
- Tooltip pecahan UG memaparkan Asasi, Diploma, Ijazah dan NIEED pada preview
  sumber serta Ringkasan Sinkronisasi.

## Pengesahan production

- Sumber UG: 6,190 rekod unik.
- Membership UG aktif: 6,190.
- Staff: 1,072 rekod pada semakan cron terakhir.
- ODL: 3 rekod pada semakan cron terakhir.
- Cron production menjalankan Staff, UG dan ODL setiap jam pada minit ke-10.

## English summary

This release consolidates Administrator account-search improvements, quieter
Audit Log loading, the GA4 implementation audit, safe UG legacy-identity
succession, batched Preview/Apply validation, post-timeout result recovery and
responsive UG student-breakdown tooltips.
