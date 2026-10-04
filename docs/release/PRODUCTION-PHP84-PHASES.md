# Pelan upgrade production OneID ke PHP **8.4.26**

Server: `iqs@172.16.2.109` (`APPSSSOPRODv1`), root `/var/www/oneid`.
Skop: production OneID sahaja. Server ini tidak berkongsi aplikasi lain yang perlu kekal pada PHP 8.3.
Mobile Android production belum mempunyai package ID/redirect URI, maka mobile OIDC kekal OFF sehingga input itu tersedia.

## Fasa 1 — Audit kesiapsiagaan production (read-only)

**Tindakan:** inventori PHP/extension/FPM pool/socket/INI, Composer dan vendor, CLI/phar, semua cron dan system timers OneID, ODBC/connectors, Nginx route, source Git, permissions, backup/restore evidence, disk/RAM dan konflik port provider.

**Hasil diperlukan:** senarai dependency, script yang mesti diuji pada 8.4, risiko/halangan, backup yang belum lengkap. Tiada package dipasang dan tiada service/config/database diubah.

## Fasa 2 — Runtime PHP 8.4 secara selari

**Tindakan:** pasang PHP **8.4.26** dan extension, sediakan FPM web/mobile khusus OneID, semak INI/pool dan socket, bina PHP 8.4 CLI/phar. PHP 8.3 masih tersedia sebagai rollback.

**Hasil diperlukan:** FPM/CLI probes lulus dan rollback package/socket tersedia. Trafik belum ditukar.

## Fasa 3 — Code/dependency release

**Tindakan:** deploy source daripada branch release yang diluluskan, bina vendor daripada `composer.lock`, jalankan compatibility patch, uji full lint/test suite. Setiap cron/timer OneID menggunakan PHP 8.4 secara eksplisit selepas lint.

**Hasil diperlukan:** source/vendor hash, PHP 8.4 platform check, script/cron smoke lulus. Mobile config masih disabled.

## Fasa 4 — Cutover web OneID

**Tindakan:** tukar hanya vhost web OneID ke FPM 8.4; `nginx -t`, reload, smoke login/logout/reset/session dan downstream SSO. Mobile route kekal `enabled=false`, `production_ready=false`.

**Hasil diperlukan:** tiada HTTP 5xx, login web/downstream stabil dan rollback socket 8.3 disahkan.

## Fasa 5 — Mobile dormant dan database state

**Tindakan:** jika mobile state diperlukan, sediakan provider/database private dan jalankan migration additive dengan observer OFF. Jangan daftar Android client atau callback MyDigital ID mobile.

**Hasil diperlukan:** migration evidence, 4 jadual/28 trigger disahkan, observer OFF; tiada route mobile awam.

## Fasa 6 — Operasi/monitoring PHP 8.4

**Tindakan:** pantau FPM, Nginx, cron/timer, error log, HTTP 5xx, memory/queue dan downstream. Semak default CLI/phar/cron benar-benar 8.4 kerana production hanya OneID.

**Hasil diperlukan:** tempoh pemerhatian dan acceptance owner direkodkan.

## Fasa 7 — Rollback

**Tindakan:** pulihkan Nginx/FPM web ke 8.3, CLI/phar/cron ke 8.3 jika diperlukan, reload dan smoke. Mobile kekal OFF. Jangan DROP mobile tables atau restore DB penuh tanpa penilaian kehilangan data.

**Hasil diperlukan:** masa rollback, backup ID, punca dan smoke result.

## Fasa 8 — Aktifkan Android mobile kemudian

**Prasyarat:** package/application ID, exact redirect URI, client/provider registration dan callback MyDigital ID production diluluskan.

**Tindakan:** daftar public Android client PKCE S256, isi allowlist, daftar callback MyDigital ID, set `production_ready=true`, jalankan native login/session/refresh/logout dan reject-account E2E, kemudian enable secara terkawal.
