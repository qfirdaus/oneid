# Admin Web Apps — Carian Semua Aplikasi

**Tarikh:** 17 Julai 2026
**Status:** IMPLEMENTED — AUTOMATED CONTRACT PASS; MANUAL UAT PENDING

## Skop

Carian ditambah pada `Admin > Web Apps` dengan pola interaksi yang sama seperti
direktori aplikasi pengguna. Ia menapis data aplikasi yang telah dimuatkan dan
tidak menambah endpoint, query database, mutation atau perubahan ACL.

## Behavior

- carian merentas semua kategori;
- padanan case-insensitive pada nama, penerangan/fungsi, URL/domain dan App ID;
- kiraan setiap kategori menunjukkan bilangan padanan;
- kategori padanan pertama dipilih apabila kategori semasa tiada hasil;
- status jumlah padanan diumumkan kepada screen reader tanpa menambah teks
  visual di sebelah textbox;
- textbox menggunakan lebar penuh ruang carian;
- butang clear memulihkan semua aplikasi dan fokus input;
- carian dikekalkan apabila direktori di-refresh;
- input dan output aplikasi menggunakan escaping sedia ada.

## Manual UAT

1. Cari sebahagian nama aplikasi dan sahkan kategori padanan dipilih.
2. Cari perkataan dalam description/fungsi.
3. Cari hostname/domain.
4. Cari App ID tepat.
5. Cari teks tanpa padanan; semua kategori menunjukkan 0 dan empty state.
6. Tekan clear; jumlah dan kategori kembali ke baseline.
7. Refresh direktori ketika carian aktif; filter kekal digunakan.
8. Sahkan View/Edit pada hasil carian masih membuka app yang tepat.
