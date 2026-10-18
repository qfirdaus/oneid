# OneID 2.21.1 — UPNM Weather and Air Quality

Tarikh: 18 Oktober 2026

Dashboard pengguna kini menyediakan panel cuaca bagi lokasi tetap UPNM yang
memaparkan suhu, keadaan semasa, suhu terasa, kelembapan, hujan dan angin.
Panel turut memaparkan anggaran US AQI, PM2.5 dan PM10 daripada Open-Meteo dan
CAMS dengan warna tahap standard serta penjelasan bahawa data tersebut bukan
bacaan IPU rasmi JAS Malaysia.

Integrasi berjalan melalui backend OneID tanpa mengumpul geolokasi browser.
Cache bersama selama 15 minit, cooldown refresh 60 saat, file lock dan fallback
bacaan selama enam jam mengawal beban pembekal serta mengekalkan paparan ketika
perkhidmatan luar terganggu. Masa pembekal dinormalisasi kepada Asia/Kuala_Lumpur.

Paparan BM dan English menyokong desktop serta telefon, menggunakan ikon refresh
sebagai petunjuk loading dan menyusun masa Bacaan serta Disemak dalam dua baris.
