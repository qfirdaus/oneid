# OneID 2.14.3 — Kemas Kini Soalan Lazim Pengguna

Tarikh release: 5 Oktober 2026

## Ringkasan

Release ini mengemas kini Soalan Lazim selepas upgrade production kepada PHP
8.4.26. Semua 13 FAQ asal dikekalkan dan lima soalan baharu ditambah berdasarkan
pertanyaan lazim pengguna.

## Penambahbaikan

- akaun OneID tidak aktif dan saluran semakan PTMK;
- aplikasi tidak dipaparkan atau akses ditolak;
- aplikasi downstream dibuka tetapi tidak boleh digunakan;
- paparan tidak lengkap atau butang tidak berfungsi selepas kemas kini; dan
- maklumat yang perlu diberikan apabila meminta bantuan.

Kandungan BM dan English mempunyai identiti serta urutan yang sama, dengan
jumlah 18 FAQ pada halaman login dan dashboard pengguna. Jawapan turut
menegaskan bahawa pengguna tidak perlu dan tidak boleh berkongsi kata laluan,
OTP, token SSO, recovery code atau kunci Authenticator dengan pihak sokongan.

## Skop deployment

Perubahan hanya melibatkan kandungan FAQ, teks pengenalan dan metadata release.
Tiada perubahan database, konfigurasi PHP/Nginx, polisi SSO atau pengaktifan
mobile production.

## English summary

This release retains all 13 existing user FAQs, adds five common support topics,
and provides matching 18-entry Malay and English content on both the public
sign-in page and authenticated user dashboard.
