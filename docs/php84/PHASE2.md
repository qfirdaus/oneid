# Fasa 2 — Persediaan dependency MyDigital ID

3 Oktober 2026. Skop: OneID UAT sahaja. PHP/FPM, Nginx, cron, key, callback, polisi MFA dan kontrak downstream tidak ditukar.

## Perubahan

1. Jumbojett kekal v1.0.2 (versi stabil terkini yang dilaporkan Composer semasa semakan). Tidak menggunakan dev-master.
2. `tools/patch_openid_php84.php` menukar 12 parameter `string $x = null` kepada `?string $x = null`. Hanya menerima SHA-256 sumber asal yang diketahui atau sumber patched; gagal jika dependency berubah tanpa semakan. Penulisan menggunakan fail sementara dan rename. Boleh dijalankan semula tanpa perubahan tambahan.
3. Composer post-install/post-update menjalankan patch tersebut. Jika deployment menggunakan `--no-scripts`, WAJIB jalankan `php tools/patch_openid_php84.php --apply`, kemudian `php tools/patch_openid_php84.php`. Jangan deploy hanya composer.lock tanpa patcher.
4. phpseclib dikemas kini 3.0.55 → 3.0.57 untuk advisory PKSA-12sz-bcny-pk4m / CVE-2026-84308 yang dikesan Composer. Advisory bukan bukti bahawa integrasi MyDigital ID menggunakan laluan rentan atau telah dieksploitasi.
5. Classmap eksplisit ditambah untuk `app/Auth/MobileOidc/Contracts.php` kerana fail itu mengandungi beberapa interface. Ini menghapuskan warning PSR-4 ketika autoloader dioptimumkan.

## Pengesahan

- Composer validate --strict dan dump-autoload --optimize --strict-psr.
- Composer audit --locked selepas kemas kini.
- Ujian baharu `tests/characterization/php84_openid_compatibility.php`: load dependency dengan error handler, periksa 12 kontrak null/string dan construction tanpa request provider.
- 12 suite regresi MyDigital ID web/mobile, profile dan protokol; keputusan akhir dalam `phase2-test-results.json`.
- Semua ujian runtime setakat ini PHP 8.3.35. Belum ada PHP 8.4 CLI/FPM. Lulus 8.3 bukan pengesahan 8.4.
- Ujian login provider sebenar, SMTP, remote downstream dan prestasi authenticated belum dijalankan oleh agent.

## Pelaksanaan dan rollback

Patch ialah perubahan vendor yang dihasilkan semula daripada script tracked, bukan perubahan manual yang hilang ketika install. Patch checksum perlu dikaji semula apabila Jumbojett mengeluarkan versi lain. Jangan bypass kegagalan checksum.

Rollback persediaan ini memerlukan pemulihan composer.json/lock sebelum Fasa 2 dan pemasangan dependency asal, termasuk fail Jumbojett asal daripada archive versi terkunci. Jangan gunakan git reset keseluruhan projek: workspace mengandungi perubahan UI/kerja lain yang sedia ada. Backup release penuh diperlukan sebelum pertukaran trafik kelak.

## Baki sebelum cutover

- Jalankan suite yang sama menggunakan PHP 8.4.26 sebenar dengan E_ALL.
- Login MyDigital ID web/mobile serta mobile session, refresh dan logout telah dilaporkan lulus oleh pengguna selepas perubahan dependency pada PHP 8.3. Ulang pada runtime 8.4 nanti.
- Sahkan PHPMailer manual/SMTP, tetapan sesi dan prestasi bcrypt pada runtime baharu.
- Installer mobile masih hardcode PHP 8.3; penyediaan pool/version akan dikendalikan sebelum Fasa 3/pertukaran routing, bukan dijalankan sekarang.
- Kekalkan penghalang remote runtime dan prestasi authenticated daripada Fasa 1.


## Pengesahan manual selepas perubahan dependency

Pada 3 Oktober 2026 pengguna mengesahkan login MyDigital ID web dan mobile, semak sesi mobile, refresh serta logout semuanya berjaya. Lima senario direkod PASS_USER_REPORTED dalam `phase2-manual-validation.json`. Ini melengkapkan pengesahan manual yang diminta untuk perubahan dependency Fasa 2 pada runtime sedia ada; bukan kelulusan keserasian PHP 8.4 atau cutover. Baki runtime remote, prestasi authenticated, SMTP dan konfigurasi runtime masih seperti di atas.
