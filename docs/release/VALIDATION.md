# Candidate validation — 3 Oktober 2026

Semua pemeriksaan di bawah dilakukan pada worktree release atau proses sintetik persendirian. Tiada migration, restart, config write atau perubahan source production dijalankan.

- **860 fail PHP** syntax clean pada PHP 8.3 dan 8.4; Python source syntax clean. Guard production terakhir turut dilint semula selepas tightening client/redirect. Lihat `syntax-validation.json`.
- Enam suite PHP pada setiap runtime lulus: production environment (28), adapter (73), MyDigital ID (36), profile, hosted protocol (20), nullable OIDC compatibility. Lihat `validation.json`. Ini synthetic identity/provider, bukan real provider/network E2E.
- **14 semakan migration bagi setiap runtime** pada MySQL 8.0.46 private lulus: additive schema, 28 triggers, observer OFF, seed, revocation bump, transaction rollback, disable policy epoch, retention dan MFA environment propagation. Proses database dihentikan. Lihat `migration-validation.json`. Root-guarded apply wrapper/privileges production belum dijalankan.
- Fresh Composer install daripada lock dalam direktori private berasingan lulus, termasuk post-install patch dan platform check 8.4. Composer OS sendiri mengeluarkan 55 deprecation notices ketika dijalankan dengan 8.4; build menggunakan CLI 8.3 tanpa warning. Lihat `dependency-validation.json`.
- Template Nginx dan FPM 8.4 lulus syntax test dengan log paths redirected ke private directory. Tiada service dimulakan/reload. Bukan ujian gabungan penuh vhost production. Lihat `template-validation.json`.
- Pemeriksaan pola private key, credential URL, literal bearer dan JWT pada source serta XML DOCX tidak menemui credential literal daripada pola tersebut. Dua padanan DSN ialah Python f-string dengan pembolehubah password yang dijana, bukan secret sebenar. Ini bukan audit secrets komprehensif.
- Package verifier menyemak checksum, manifest, senarai tar tepat dan path selamat; keputusan disimpan di folder artifact selepas build.

Production masih memerlukan input Android/MyDigital ID, backup/restore, schema privilege/index/trigger review, private provider dan pengesahan konfigurasi sebenar. Native Android production, real MyDigital ID callback, load capacity dan downstream runtime/ODBC belum diperakui oleh ujian candidate ini.

Nota harness: percubaan pertama private MySQL menunggu lokasi socket yang salah; ia diperbetulkan ke datadir dan ujian seterusnya lulus. Nginx isolated test pertama menemui default access-log permission; top-level logging diarahkan OFF untuk private test, kemudian test lulus. Ini tidak melibatkan perubahan runtime live.
