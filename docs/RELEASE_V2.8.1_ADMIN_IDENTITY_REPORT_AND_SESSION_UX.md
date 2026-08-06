# OneID v2.8.1 — Admin Identity, Print Report and Session UX

**Versi:** 2.8.1  
**Tarikh release:** 6 Ogos 2026  
**Environment sasaran:** Staging/UAT  
**Status:** IMPLEMENTED / READY FOR STAGING

## Ringkasan

Release ini menyatukan pembetulan ketepatan kategori Admin SSO, naik taraf
laporan cetak User List dan lifecycle sesi Administrator yang mempunyai amaran
dua minit serta pembaharuan grant secara selamat.

## Admin SSO

- Jumlah dan listing Admin SSO menggunakan peranan Administrator aktif.
- Rekod ujian bukan Administrator tidak lagi dikira sebagai Admin SSO.
- Tiga Administrator sah dikekalkan tanpa mengubah status akaun staf biasa.

## Laporan User List

- Layout print-ready dengan branding UPNM/OneID, metadata, jumlah dan rujukan.
- Staf menggunakan `data3`; pelajar menggunakan `data4` sebagai ID paparan.
- `data7` dipaparkan sebagai baris sekunder di bawah nama.
- Kolum diseimbangkan untuk A4 landskap dan header diulang pada halaman baharu.
- Nombor kad pengenalan tidak lagi digunakan sebagai ID laporan.

## Sesi Administrator

- SweetAlert profesional muncul apabila baki grant mencapai dua minit.
- Countdown datang daripada baki grant server dan diselaraskan antara tab.
- `Stay Connected` menggantikan grant secara atomik mengikut konfigurasi semasa.
- Pembaharuan menggunakan CSRF, session/browser binding, transaksi dan audit 67.
- Tanpa respons, akses admin tamat dan kembali ke dashboard pengguna.
- Mesej pembaharuan berjaya memerlukan pengesahan `OK` secara manual.
- Asset UI dilayan daripada `public/dist` dengan cache version dan contract.

## Validasi Release

- Contract Admin SSO count/listing.
- Contract laporan kategori dan pemilihan Staff/Student ID.
- Contract renewal, expiry, rollback, multi-tab dan technical heartbeat.
- Contract BM/English, metadata pusat dan polisi versioning.

## English summary

OneID v2.8.1 corrects Admin SSO role totals and listings, delivers a polished
print-ready category User List with staff or student identifiers, and introduces
a professional two-minute Administrator session warning. Stay Connected renews
the server-side grant atomically using the configured lifetime, while expiry
returns the Administrator to the active user account. Successful renewal now
remains visible until the Administrator confirms OK.
