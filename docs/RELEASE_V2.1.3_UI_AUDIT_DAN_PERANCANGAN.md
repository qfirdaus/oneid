# Release OneID v2.1.3 — UI, Audit dan Perancangan

**Versi:** 2.1.3
**Tarikh:** 17 Julai 2026

Release ini mengemas kini ringkasan UI direktori aplikasi serta merekod reka
bentuk keselamatan dan multi-language untuk pelaksanaan berfasa akan datang.

## Perubahan utama

- dashboard pengguna memaparkan pecahan jumlah aplikasi yang boleh diakses
  kepada `Jumlah`, `Full SSO` dan `Non SSO`;
- dashboard Administrator memaparkan pecahan yang sama berdasarkan semua
  aplikasi unik dalam direktori aktif;
- klasifikasi menggunakan kontrak sedia ada: `sp_sso_support = 0` ialah Full
  SSO dan nilai selain `0` ialah Non SSO/Direct Link;
- kad ringkasan mempunyai identiti warna serta layout responsive dan mengurus
  state loading, kosong serta kegagalan data;
- contract user/admin ditambah untuk menghalang regression klasifikasi dan
  kehilangan metric;
- audit Fasa 7 Admin Step-Up 2FA dikembangkan kepada pilihan OTP e-mel atau
  Microsoft Authenticator melalui standard TOTP;
- reka bentuk Fasa 7 merangkumi server-side enforcement, enrollment, secret
  encryption, anti-replay, recovery, rate limit dan audit event;
- audit serta pelan multi-language Bahasa Melayu/English disediakan melalui
  ML0 hingga ML9; dan
- pelan multi-language merangkumi default language dalam Configuration,
  preference pengguna, translation catalogue, API/AJAX, e-mel, accessibility,
  metadata database, dokumentasi, UAT dan rollback.

## Status feature yang dirancang

- Admin Step-Up dual authentication telah diluluskan secara prinsip tetapi
  pelaksanaan kekal `on hold`;
- multi-language masih pada peringkat audit dan perancangan serta belum
  mengubah bahasa runtime sistem; dan
- v2.1.3 tidak menambah schema, OTP TOTP, language switcher atau setting locale.

## Compatibility

- ACL dan senarai aplikasi tidak berubah;
- App ID, URL, token, session dan SSO consumer contract tidak berubah;
- pecahan metric hanya menggunakan data `sp_sso_support` yang telah dihantar
  oleh endpoint sedia ada; dan
- v2.1.2 kekal dalam sejarah Version Releases.
