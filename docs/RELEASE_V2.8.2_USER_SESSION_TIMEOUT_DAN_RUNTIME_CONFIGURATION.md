# OneID v2.8.2 — User Session Timeout dan Runtime Configuration

**Versi:** 2.8.2  
**Tarikh release:** 7 Ogos 2026  
**Environment sasaran:** Staging/UAT  
**Status:** IMPLEMENTED / READY FOR STAGING

## Ringkasan

Release ini menyelaraskan idle timeout portal pengguna dengan tetapan
Administrator, memperkenalkan amaran dan pembaharuan sesi pengguna yang tidak
mengubah token SSO, serta menyediakan pengurusan fail konfigurasi private yang
lebih tersusun dan selamat.

## Sesi Portal Pengguna

- Idle timeout PHP mengikuti tetapan Administrator dengan fallback 30 minit.
- Had mutlak sesi kekal lapan jam walaupun terdapat aktiviti berterusan.
- SweetAlert muncul dua minit sebelum deadline efektif server.
- `Stay Connected` memperbaharui sesi portal sahaja tanpa rotate atau revoke token.
- Technical heartbeat dan status polling tidak memanjangkan idle session.
- Tamat sesi portal tidak menutup aplikasi SSO lain yang masih aktif.
- Multi-tab, offline, ralat server, CSRF, akaun dan status token dikendalikan
  menggunakan kod keadaan yang stabil.

## Administrator dan Integrasi

- Deadline asas pengguna dan grant Administrator dinilai secara konsisten.
- Pembaharuan grant Administrator tidak menghidupkan semula sesi pengguna tamat.
- Kontrak token validation dan service-provider API sedia ada tidak berubah.
- Event audit khusus meliputi expiry, renewal dan explicit portal end.

## Runtime Configuration

- Tool formatter menyusun key private mengikut kategori dengan komen konsisten.
- Semua key dan value disahkan kekal sebelum fail diaktifkan.
- Backup bertimestamp dibuat sebelum perubahan.
- Permission asal dikekalkan supaya akses PHP-FPM tidak terputus.

## Validasi Release

- Characterization Fasa 0 hingga Fasa 4 dan readiness staging.
- Polisi idle, absolute timeout, heartbeat dan multi-tab.
- Sesi Administrator, SSO, multilingual dan kontrak keselamatan.
- Syntax, metadata release dan polisi versioning.

## English summary

OneID v2.8.2 aligns the user portal idle timeout with the Administrator setting,
adds a two-minute user session warning and safe local renewal without changing
SSO tokens, and introduces a documented private-runtime formatter that preserves
all values and the original file permissions required by PHP-FPM.
