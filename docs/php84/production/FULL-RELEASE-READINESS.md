# Pelepasan penuh OneID production: web + mobile + PHP 8.4

## Keputusan audit

Boleh dirancang sebagai satu release bersepadu, tetapi snapshot staging sekarang BELUM selamat untuk direct copy/git pull dan terus aktif. Tiada push, commit atau perubahan production dibuat semasa audit ini. Skop pengguna kini merangkumi keseluruhan perubahan produk staging termasuk mobile, UI, dependency dan pembetulan refresh, bukan patch PHP sahaja. Persediaan runtime/DB/credential di luar Git tetap diperlukan.

## Bukti blocker production

- mobile-public/index.php memerlukan root /var/www/oneid-uat dan environment staging.
- UatAdapterFactory memerlukan path dan environment staging serta memaksa environment staging ke adapter.
- MobileIdentityAdapter::ready/assert readiness menolak environment selain staging.
- PdoIdentitySource membina PdoUserMfaPolicyReader dengan environment staging secara tetap; wajib guna polisi production sebenar apabila dipindahkan.
- MobileMyDigitalId menggunakan forMobileStaging; MyDigitalIdConfig memerlukan callback UAT tepat. Callback production perlu konfigurasi terasing serta pendaftaran/allowlist pihak MyDigital ID.
- Installer provider, migration, Nginx/FPM templates, pendaftaran client dan kawalan pilot sedia ada khusus UAT. Jangan jalankan pada production atau menghapuskan guard secara global.
- Production tiada mobile-public entrypoint/config, tiada mobile_oidc tables/trigger. Kesemua sembilan jadual sumber yang diperlukan trigger wujud menurut pemeriksaan metadata, tetapi ini belum semakan semua kolum/index/privilege atau tempoh DDL.
- Schema-Fasa-B-Mobile-OIDC.sql diabaikan oleh aturan *.sql dalam .gitignore dan belum tracked. Migration mesti masuk pakej release secara eksplisit (lokasi migrations yang dibenarkan atau exception sempit); jangan unignore semua SQL.
- PHP 8.4 belum dipasang. Routing web production menggunakan pool oneid khusus 8.3, berbeza daripada UAT.

## Komponen release

1. Semua perubahan produk yang dimaksudkan: UI web/dashboard/mobile, terjemahan, polisi sesi, reset password/MFA, mobile OIDC, patch refresh dan dependency. Sediakan manifest daripada tracked diff + untracked; review artifak ujian/laporan dan assets yang ignored secara berasingan. Jangan git add seluruh .private/vendor/logs/dumps/token.
2. Kod mobile production-aware: environment/path/origin/issuer/client allowlist dipadankan melalui konfigurasi yang disahkan; unknown environment fail closed. UAT kekal berfungsi dengan config asal. Test fixtures loopback tidak dibenarkan dalam production.
3. Migration additive untuk empat jadual mobile + 28 trigger lifecycle: observer OFF semasa pemasangan, metadata preflight penuh dan backup DB yang boleh dipulihkan. DDL MySQL auto-commit, bukan rollback transaksi biasa. Jangan salin data/sesi/token UAT.
4. Hydra + PostgreSQL private khusus production, binary/artifact pin yang disemak, service user, backup/restore/retention dan pemilik patching. Admin API dan database tidak dibuka ke luar. Secrets binding/hook/provider baru, permission ketat; jangan salin secrets UAT.
5. Pool FPM 8.4 production khusus web/mobile, Nginx endpoint/OIDC discovery, hook loopback, TLS/log selamat. Kekalkan default CLI serta tiga cron dikenal pasti pada 8.3 dahulu; bukan upgrade semua PHP sistem lain.
6. Client Android production dan iOS jika dalam skop release: client ID, exact redirect URI, package/bundle ID, PKCE S256, scopes/audience. Token issuer production mesti diasingkan daripada staging. Tetapan Flutter release perlu menunjuk production; bukan menukar server sahaja.
7. MyDigital ID production: semak client/issuer/secrets/callback web sedia ada, tambah callback mobile yang diluluskan provider. Jangan menganggap callback UAT sah pada hostname production.

## Data yang diperlukan sebelum pakej boleh diaktifkan

- Pengesahan issuer/origin production, cadangan hostname sedia ada https://oneid.upnm.edu.my/.
- Package/bundle ID dan exact callback build production daripada team mobile; Android sahaja atau Android+iOS.
- Status kelulusan callback mobile MyDigital ID production.
- Tetingkap deployment, backup DB/provider, pemilik pemantauan dan kaedah rollback.

## Urutan satu release, pengaktifan terkawal

A. Sediakan perubahan production-aware dan migration/runbook pada workspace/branch terasing, kekalkan UAT aktif; review manifest tanpa secrets, uji targeted regression sahaja.
B. Commit/push atau PR selepas pakej reviewable dan arahan push yang jelas. Commit kod tidak memasang servis, packages, DB atau config rahsia.
C. Pasang PHP 8.4/provider/pool/config production secara selari; mobile disabled dan observer OFF. Preflight DB, backup, migration dan client registration yang tepat.
D. Pengesahan minimum pada laluan terhad: web/SSO sedia ada; mobile password/MyDigital ID + sesi/refresh/logout; reset/SMTP; satu akses ditolak. Gunakan bukti UAT untuk mengelakkan pengulangan besar, tetapi guard production baru perlu diuji.
E. Switch web ke 8.4 dan aktifkan mobile dalam tetingkap sama selepas gate lulus. Pemeriksaan ringkas dan pemantauan. Peranti/SDK production tetap memerlukan build config betul.

## Rollback

Asingkan web/PHP rollback daripada mobile kill switch. Web boleh kembali ke socket 8.3 yang masih tersedia; mobile boleh dinyahaktifkan tanpa merosakkan login web. Kekalkan jadual identity/session/audit, jangan drop state secara automatik. Selepas gap observer atau restore DB, sesi/refresh perlu dibatalkan dengan epoch yang betul sebelum re-enable. Skrip UAT php84_uat_cutover.py tidak sesuai digunakan pada production kerana path/socket berlainan.

## Status

Audit kebolehlaksanaan release penuh selesai; pelaksanaan production-aware, migration production, manifest Git dan provider registration masih perlu disiapkan. Tiada dakwaan production sedia diaktifkan sekarang. Production kekal PHP 8.3.33 tanpa mobile runtime; staging kekal seperti sebelumnya.
