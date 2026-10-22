# OneID 2.21.4 — UPNM Weather Forecast

Tarikh: 23 Oktober 2026

Panel Cuaca di UPNM kini mempunyai tab Sekarang, Setiap jam dan 7 hari. Data
ramalan menggunakan panggilan Open-Meteo serta cache server sedia ada supaya
browser pengguna tidak membuat sambungan terus kepada pembekal.

Tab Setiap jam memaparkan 12 jam terdekat dalam grid responsif, menandakan jam
semasa dan tidak menggunakan scroll mendatar. Tab 7 hari memaparkan keadaan,
suhu minimum/maksimum dan kebarangkalian hujan dalam baris padat untuk desktop
serta telefon.

Kegagalan data ramalan tidak menutup cuaca semasa atau AQI. Cache 15 minit,
refresh cooldown 60 saat dan fallback enam jam dikekalkan.
