# Sambung kerja dari sini — checkpoint 3 Oktober 2026 (Asia/Kuala_Lumpur)

## Permintaan pemilik dan titik hentian

Pemilik membenarkan **commit/push ke branch release sahaja**, kemudian berhenti dan sambung kemudian. Skop production yang dirancang ialah semua perubahan web + login mobile **Android sahaja** + PHP 8.4.26. Tiada kebenaran deployment production atau merge main diberikan dalam checkpoint ini.

Branch serahan: `release/production-php84-mobile`
Remote: `git@github.com:qfirdaus/oneid.git`
Worktree persediaan: `/var/www/oneid-uat/.private/release-workspaces/full-production`
Base asal (juga production/main ketika disemak): `06f364d5d2fbd936d8ed496d7e68095aa640cdcb`.

Dokumen ini termasuk dalam commit serahan. Gunakan HEAD branch remote untuk mengenal pasti commit checkpoint tepat; jangan menggunakan hash base sebagai hash release. Sebelum menutup sesi, bandingkan `git rev-parse HEAD` dengan `git ls-remote origin refs/heads/release/production-php84-mobile`. Jika tidak sama, sambung pengesahan/push sahaja dahulu.

## Apa yang telah siap

1. Upgrade **UAT sahaja**: trafik web/mobile menggunakan PHP 8.4.26. Default CLI/phar, cron dan sistem lain kekal 8.3. Web pool UAT dinaikkan daripada 4 kepada 8 workers selepas warning kapasiti. Tiada pensijilan load production dibuat.
2. Ujian UAT dilaporkan berjaya: login web, downstream ODL/SAP/e-BDR, parity token/API/ACL (allow dan deny), MyDigital ID, sesi/refresh/logout. Selepas cutover URL biasa, web/downstream dan `/mobile-test/` berjaya. `/mobile-test/` ialah browser harness, **bukan native Android production E2E**.
3. Audit production read-only: PHP 8.3.33, web socket `/run/php/php8.3-fpm-oneid.sock`, mobile belum dipasang. Production branch main/working tree bersih ketika disemak; tiada perubahan dibuat.
4. Candidate mengandungi semua perubahan source staging yang belum di-commit, dependency patch MyDigital ID untuk PHP 8.4, pembetulan legacy token refresh/status/ACL dan UI/mobile. Perbandingan fail sebelum commit: **tiada source staging tertinggal**. Secrets, vendor, runtime data, uploads dan Python bytecode tidak dipush.
5. Candidate menambah sokongan environment production yang tidak dipasang ke UAT aktif: root/origin/issuer guard, polisi MFA mengikut environment, callback mobile MyDigital ID production, allowlist Android dan konfigurasi dormant. Guard tidak dibuang secara global.
6. Pakej persediaan: manifest, tool build/verify archive, template Nginx/FPM/Hydra/PostgreSQL, migration tambahan 4 tables/28 triggers (observer OFF), prosedur deployment dan rollback berlapis. Rujuk [README](README.md), [RUNBOOK](RUNBOOK.md), [ROLLBACK](ROLLBACK.md), [INPUTS](INPUTS.md).
7. Candidate diuji pada PHP 8.3/8.4: syntax 860 fail, enam suite komponen setiap runtime, 14 semakan migration MySQL sintetik setiap runtime, fresh Composer install/patch/platform dan syntax template Nginx/FPM. Rujuk [VALIDATION](VALIDATION.md) dan fail JSON. Bukan ujian deployment production sebenar.

## Keadaan yang sengaja dikekalkan

- Live UAT root `/var/www/oneid-uat` masih branch **main dengan working tree asal yang dirty**. Perubahan tersebut kini disalin/disimpan pada branch release bersama adaptasi production. Ini disengajakan; jangan `reset`, `clean`, `stash`, checkout branch atau pull ke root live untuk menghilangkan status dirty.
- Worktree release berasingan ialah tempat sambung persediaan. Ia tidak menghidangkan trafik UAT.
- Tiada merge ke main atau deploy production. Semakan repository tidak menemui workflow CI/CD atau hook deploy; automasi luar repository tidak dapat dipastikan hanya melalui Git. Push branch bukan arahan memasang production.
- Template production mempunyai `enabled=false`, `production_ready=false`, client kosong. Pendaftaran client/provider production belum dilakukan.
- Secrets runtime tidak berada dalam Git. Jika sambung dari clone lain, bina vendor daripada composer.lock dan gunakan konfigurasi sintetik untuk ujian, bukan menyalin secrets UAT ke production.
- Artifact awal `/var/www/oneid-uat/.private/release-packages/production-candidate-20261003/` ialah snapshot **sebelum commit/checkpoint dokumentasi**. Manifest awal menyebut belum push; itu rekod pada masa build. Jangan anggap tar awal sebagai artifact final yang sama tepat dengan HEAD selepas push. Bina semula daripada commit yang telah direview sebelum deployment.

## Runtime UAT dan rollback terdahulu

- Web 8.4: `/run/php/oneid-web-uat84.sock`, max_children 8.
- Mobile 8.4: `/run/php/oneid-mobile-uat84.sock`, max_children 4.
- Nginx UAT: `/etc/nginx/sites-enabled/oneid-uat`; mobile snippet `/etc/nginx/snippets/oneid-mobile-uat-dormant.conf` (nama dormant, tetapi route UAT sudah aktif).
- Cutover terakhir: backup `/var/backups/oneid-php84-cutover-20261003-141022`.
- Capacity backup: `/var/backups/oneid-php84-web-capacity-20261003-142220`.
- Loopback listener ujian 24484 pernah dipasang; keperluan cleanup perlu disemak kemudian, jangan padam sewaktu checkpoint.
- Pemilik pernah menjalankan rollback secara tersilap kemudian apply semula dengan berjaya; ini bukan kegagalan upgrade.
- Jangan jalankan arahan rollback UAT hanya kerana ia tertera dalam dokumen. Production mempunyai runbook berbeza.

## Langkah pertama bila sambung nanti

```sh
cd /var/www/oneid-uat/.private/release-workspaces/full-production
git status --short
git log -1 --format='%H %s'
git ls-remote origin refs/heads/release/production-php84-mobile
```

Baca dokumen ini dan `INPUTS.md` dahulu. Jika ada perubahan baharu pada UAT/production sejak checkpoint, bandingkan secara read-only dengan manifest/commit sebelum memasukkannya ke release. Jangan mengulang semua ujian yang sudah lulus tanpa perubahan atau isu baharu.

Tugas seterusnya ialah **menutup input production dan menyemak release**, bukan terus execute cutover:

1. Dapatkan package/application ID, client ID dan exact redirect URI **build Android production** daripada team mobile. Android sahaja telah disahkan; nilai production belum disahkan. Jangan andaikan nilai staging boleh digunakan terus.
2. Sahkan callback `https://oneid.upnm.edu.my/mobile/mydigitalid/callback` didaftarkan pada client MyDigital ID production; kekalkan callback web asal. Jangan kongsi credential/token dalam chat/Git.
3. Sahkan physical DB hostname/name, columns/indexes/privileges/trigger coexistence dan backup restore production; bina private Hydra/PostgreSQL serta secrets baru. Tentukan window dan pemilik monitoring/patching.
4. Review commit/diff, deployment templates dan rollback. Bina artifact baru daripada commit yang dipersetujui, sahkan hash. Commit/push sekarang **bukan approval merge/deploy**.
5. Hanya selepas pengguna memberi arahan deployment yang jelas dan syarat selesai, ikuti RUNBOOK secara berurutan. Kekalkan sistem lain/default PHP 8.3. Buat smoke terhad: web+downstream, Android sebenar login/sesi/refresh/logout, MyDigital ID production dan reject akaun tidak dibenarkan.

Baki had: native production Android, remote downstream PHP/ODBC inventory, formal performance acceptance dan production backup/provider readiness belum ditutup. Mobile production akan kekal OFF sehingga package ID dan redirect URI Android disahkan. Perbezaan median API +5.01% berdasarkan lima sampel UAT setiap runtime bukan pensijilan prestasi.

## Checkpoint berhenti selepas Fasa 1 — 4 Oktober 2026

Pemilik meminta tugasan dihentikan selepas **Fasa 1 audit production**. **Fasa 2 belum dimulakan**: tiada PHP package dipasang, tiada alternatives ditukar, tiada FPM pool/socket dibuat, tiada cron diubah dan tiada production traffic disentuh.

Sasaran versi yang dimuktamadkan ialah **PHP 8.4.26** untuk semua runtime OneID production: CLI, FPM web, FPM mobile dormant, `phar`, `phar.phar` dan cron/timer OneID. Partial package 8.4.25 yang terlihat semasa audit bukan target akhir dan mesti disejajarkan ke 8.4.26.

Fasa 1 report: [PHASE-1-PRODUCTION-AUDIT-20261004.md](PHASE-1-PRODUCTION-AUDIT-20261004.md)
Pelan penuh: [PRODUCTION-PHP84-PHASES.md](PRODUCTION-PHP84-PHASES.md)
Commit dokumentasi terakhir: `99a8ff6` (branch `release/production-php84-mobile`, sudah dipush).

### Dapatan yang perlu dibawa ke Fasa 2

- Production `172.16.2.109` / `APPSSSOPRODv1` hanya menjalankan OneID; root `/var/www/oneid`.
- Aktif sekarang PHP 8.3.33, FPM web socket `/run/php/php8.3-fpm-oneid.sock`, pool max 10.
- PHP 8.4.26 tersedia sebagai apt candidate; PHP 8.4.25 partial packages wujud tetapi CLI/FPM/ODBC lengkap belum tersedia.
- Extension 8.3 yang perlu dipadankan termasuk PDO MySQL, ODBC, PDO ODBC, PDO DBLIB, mysqli, curl, mbstring, intl, XML, ZIP, GD, sodium dan OPcache.
- Tiga cron menggunakan `/usr/bin/php`: external sync, session housekeeping dan user MFA lifecycle. Semak system timers penuh sebelum tukar.
- Backup package/alternatives wujud, tetapi backup restore code/vendor/private config/database belum dibuktikan. Ini syarat sebelum cutover.
- Mobile Android production kekal OFF; package ID, redirect URI dan callback MyDigital ID mobile belum tersedia.

### Cara sambung

```sh
cd /var/www/oneid-uat/.private/release-workspaces/full-production
git status --short
git log -1 --format='%H %s'
git ls-remote origin refs/heads/release/production-php84-mobile
```

Kemudian baca dua report Fasa 1 di atas. Langkah pertama sambungan ialah **Fasa 2: semak `apt-get --simulate` dan sediakan PHP 8.4.26 secara selari**, bukan terus cutover. Kekalkan PHP 8.3 sebagai rollback sehingga Fasa 4 selesai. Mobile config mesti kekal `enabled=false` dan `production_ready=false`.

Ayat sambungan:

> Sambung dari checkpoint selepas Fasa 1 dalam `docs/release/RESUME-HERE.md`. Mulakan Fasa 2 dengan persediaan PHP 8.4.26 secara selari; jangan ubah trafik production sebelum runtime, extension dan rollback disahkan.

## Arahan terakhir pemilik sebelum berhenti — 3 Oktober 2026

- **Berhenti sekarang; sambung kemudian menggunakan dokumen ini.** Tiada deployment atau penyelarasan folder live dibuat pada masa berhenti.
- Production OneID **mesti dinaik taraf ke PHP 8.4.26** dalam release yang dirancang. Server yang diaudit ialah `iqs@172.16.2.109`, hostname `APPSSSOPRODv1`, root `/var/www/oneid`, dan hanya menjalankan OneID. Oleh itu web, mobile pool (dormant dahulu), CLI dan cron OneID production dirancang menggunakan PHP 8.4.x. Pengekalan PHP 8.3 untuk sistem lain/CLI hanya terpakai pada staging, bukan server production ini.
- **Selepas production selesai dinaik taraf dan disahkan**, pemilik mahu fail source untracked dalam `/var/www/oneid-uat` dimasukkan/diselaraskan ke Git supaya IDE tidak lagi menunjukkan fail source tersebut sebagai untracked. Ini tugas susulan yang dipersetujui, belum dilaksanakan. Jangan buat lebih awal sewaktu kerja sedang dihentikan.
- Commit release `35d353dabefa85b7b18008e3973dd9455ab9d8f3` sudah dipush dan hash remote disahkan. Live UAT masih branch main dengan perubahan asal kerana push datang daripada worktree berasingan. Push branch lain sahaja tidak membersihkan working tree live.

### Penyelarasan Git UAT selepas production berjaya

1. Ambil snapshot/diff status UAT ketika itu, termasuk semua perubahan baharu selepas checkpoint; bandingkan dengan commit release yang benar-benar digunakan pada production.
2. Rekod fail source yang belum tersimpan dalam commit. Selepas menentukan branch sasaran/merge yang sesuai, selaraskan sejarah Git dan working tree live secara terkawal tanpa menimpa perubahan pengguna atau menukar kod aktif secara tidak sengaja. Menukar branch live boleh menukar fail aplikasi; jangan anggap ia sekadar mengubah paparan IDE.
3. Secrets, `.private`, vendor hasil Composer, uploads, logs, caches dan data runtime kekal di luar Git mengikut polisi ignore. Jangan commit semuanya secara membuta tuli atau menyembunyikan source dengan `.gitignore` semata-mata untuk mendapatkan status bersih.
4. Verify kandungan live dan ujian ringkas yang relevan; commit/push perubahan source yang diperlukan. Sahkan tiada fail source yang dimaksudkan masih untracked dan terangkan jika ada local configuration/data yang sengaja tidak dijejak.
5. Jangan gunakan `git clean -fdx`, `reset --hard`, atau overwrite folder live untuk membersihkan status. Kemas kini checkpoint sekali lagi setelah penyelarasan benar-benar selesai.

## Ayat untuk sambung sesi

> Sambung dari `docs/release/RESUME-HERE.md` pada branch `release/production-php84-mobile`. Semak checkpoint dan perubahan sejak berhenti; lengkapkan input Android/MyDigital ID/production sebelum cadangkan deployment. Jangan ubah production atau main dahulu.

## Checkpoint Fasa 2 — dry-run sahaja (4 Oktober 2026)

Fasa 2 sudah bermula tetapi **pemasangan sebenar belum dilakukan**. Dry-run package PHP 8.4.26 di production lulus: 12 package baharu + 2 upgrade, 0 removal. Punca berhenti: pemasangan memerlukan kata laluan `sudo` operator. Tiada production runtime berubah.

Skrip: `tools/release/prepare-production-php84.py`.

Untuk sambung, semak dahulu backup/approval kemudian jalankan di production:

```sh
scp tools/release/prepare-production-php84.py iqs@172.16.2.109:/home/iqs/
ssh iqs@172.16.2.109
sudo python3 -B /home/iqs/prepare-production-php84.py --apply
```

Skrip mesti melaporkan PHP 8.4.26, FPM 8.4 aktif, default `php`/`phar`/`phar.phar` masih 8.3 dan routing belum berubah. Jika berhenti separuh jalan, jangan ulang secara membuta tuli; periksa backup yang dicetak dan status dpkg/FPM dahulu.

## Checkpoint Fasa 2 — PHP 8.4.26 dipasang selari (4 Oktober 2026)

Operator menjalankan `sudo python3 -B /home/iqs/prepare-production-php84.py --apply` pada production.

Keputusan: **INSTALLED_PENDING_FASTCGI_PROBE**.

- PHP 8.4.26 berjaya dipasang.
- FPM 8.4 berjaya diaktifkan dan config test lulus.
- Semua extension yang ada pada PHP 8.3 dipadankan pada PHP 8.4, termasuk ODBC, PDO ODBC dan PDO DBLIB.
- Default `php`, `phar` dan `phar.phar` masih menunjuk PHP 8.3.
- Nginx routing belum berubah; web traffic masih PHP 8.3.
- Mobile routes belum ditambah dan mobile login kekal OFF.
- Backup pemasangan: `/var/backups/oneid-prod-php84-phase2-20261004-183102`.
- Host melaporkan pending kernel upgrade/reboot (`7.0.0-31` berbanding `7.0.0-34`). Jangan reboot dalam langkah seterusnya tanpa maintenance approval; ia bukan sebahagian daripada cutover PHP.

**Langkah sambungan wajib sebelum Fasa 3:** jalankan probe FastCGI/INI untuk pool 8.4 secara terpencil, semak socket dan logs, kemudian deploy/test code dependency release. Jangan tukar Nginx, alternatives, cron atau mobile feature sehingga probe dan Fasa 3 diluluskan.

## Checkpoint Fasa 2 selesai — 4 Oktober 2026

Pemilik menghentikan kerja selepas **Fasa 2: PHP 8.4.26 dipasang secara selari dan diprobe**. **Fasa 3 belum bermula**: code release/vendor production belum ditukar dan Nginx production masih menggunakan PHP 8.3.

### Pencapaian production

- PHP 8.4.26 dipasang dengan package lengkap dan extension parity.
- PHP-FPM 8.4 aktif; `php-fpm8.4 -t` lulus.
- Socket pool disediakan:
  - `/run/php/oneid-web-prod84.sock`
  - `/run/php/oneid-mobile-prod84.sock`
- Kedua-dua socket mode `0660`, owner/group `www-data`.
- FPM service aktif dan Nginx `-t` lulus.
- PHP 8.4 loaded extensions sepadan dengan PHP 8.3, termasuk `odbc`, `PDO_ODBC`, `pdo_dblib`, `pdo_mysql`, curl, mbstring, intl, XML, GD, ZIP, sodium dan OPcache.
- Default CLI masih PHP 8.3.33.
- `php`, `phar`, `phar.phar` masih menunjuk kepada 8.3; `readlink` Phar menunjukkan executable dalaman `phar8.3.phar`, yang normal.
- Nginx traffic masih socket `/run/php/php8.3-fpm-oneid.sock`; tiada cutover.
- Cron masih menggunakan `/usr/bin/php` 8.3.
- Mobile route/client/provider masih OFF; tiada package ID/redirect URI Android production.
- Backup pemasangan: `/var/backups/oneid-prod-php84-phase2-20261004-183102`.

### False positive probe yang telah dikenal pasti

Probe pertama menghasilkan `defaults_unchanged=false` kerana skrip membandingkan target Phar literal `/usr/bin/phar8.3`, sedangkan `readlink -f` menyelesaikan symlink kepada `/usr/bin/phar8.3.phar`. Semakan `update-alternatives --display` dan `readlink` operator mengesahkan `phar`/`phar.phar` masih 8.3. Ini isu probe, bukan runtime drift. Jangan menukar alternatives lagi berdasarkan keputusan false positive itu.

### Keadaan sistem yang perlu dikekalkan

- Jangan reboot server untuk pending kernel tanpa maintenance approval; kernel update bukan sebahagian daripada PHP cutover.
- Jangan tukar Nginx, alternatives, cron, code root, vendor, database atau mobile config sebelum Fasa 3 dirancang.
- PHP 8.3 kekal rollback runtime dan production web aktif.
- Provider/mobile migration tidak dijalankan.

### Cara sambung ke Fasa 3

1. Baca checkpoint ini dan `docs/release/PRODUCTION-PHP84-PHASES.md`.
2. Sahkan backup directory masih ada dan simpan salinan evidence probe output.
3. Betulkan/gunakan probe alternatives berasaskan `readlink -f /usr/bin/phar` berbanding target `phar8.3.phar`, supaya false positive tidak berulang.
4. Sahkan commit release yang akan dipasang: `release/production-php84-mobile`, remote HEAD semasa boleh disemak dengan `git ls-remote`.
5. Backup source production/vendor/private config/uploads dan semak Composer lock sebelum menyentuh `/var/www/oneid`.
6. Jalankan Fasa 3 secara berasingan: deploy code candidate, build vendor dengan lock, patch OIDC PHP 8.4, lint/compatibility/platform check melalui `/usr/bin/php8.4`, dan uji script/cron OneID tanpa menukar cron production dahulu.
7. Pastikan mobile config kekal `enabled=false`, `production_ready=false`; jangan aktifkan client Android.
8. Hanya selepas Fasa 3 lulus, rancang Fasa 4 cutover Nginx web.

Arahan sambungan:

> Sambung dari checkpoint **Fasa 2 selesai** dalam `docs/release/RESUME-HERE.md`. Mulakan Fasa 3 dengan code/dependency release secara staged; jangan cutover trafik atau aktifkan mobile.

## Checkpoint Fasa 3 — candidate code/dependency lulus, live belum ditukar (5 Oktober 2026)

Candidate release `1b27cab15a6e5c89eafc5210de230a58ab8dfa9f` telah diextract ke production pada `/home/iqs/oneid-release-candidates/1b27cab` dan vendor dibina daripada `composer.lock`. `/var/www/oneid` live kekal pada commit `06f364d5d2fbd936d8ed496d7e68095aa640cdcb`, bersih dan masih dilayan PHP 8.3.

Validation pada candidate menggunakan PHP 8.4.26:

- 1,221 fail PHP lint: tiada syntax error.
- Production environment suite: 28 checks lulus.
- Mobile adapter: 73/73 lulus.
- Mobile MyDigital ID: 36 checks synthetic lulus.
- Hosted protocol: 20/20 lulus.
- OIDC nullable compatibility: lulus.
- Session housekeeping policy: 6/6 lulus.
- Idle heartbeat policy: 11/11 lulus.
- Composer install daripada lock berjaya; PHP 8.4 platform requirements `ext-curl`, `ext-json`, PHP `8.4.26` lulus. Composer 2.7.1 mengeluarkan deprecation notices pada PHP 8.4; bukan aplikasi, tetapi Composer baharu patut dipertimbangkan sebelum production build final.

Had yang direkodkan: ujian PHP CLI candidate belum menjadi FastCGI request melalui socket pool 8.4; bacaan `php -c ... fpm/php.ini` tidak merangkumi semua pool override. Probe effective FPM/worker dan smoke HTTP loopback masih diperlukan sebelum Fasa 4. Tiada code, vendor, database, cron, alternatives atau Nginx live ditukar.

Mobile production kekal OFF: `.private/mobile-oidc-hosted.php` tiada pada live root, tiada Android client/callback dan Nginx live tiada route mobile. `phase3-source.tar` checksum production: `a56e53f727bfcf506f2d8cb8809a85fa2d0eb8c105cbd6e2f80faa40e33904fa`.

Langkah seterusnya: jalankan effective FPM probe/loopback FastCGI smoke menggunakan candidate atau disposable docroot, kemudian review backup dan rancang deploy code/vendor ke `/var/www/oneid` dalam maintenance window. Jangan tukar Nginx traffic atau cron dahulu.

## Checkpoint FastCGI smoke lulus — 5 Oktober 2026

Probe privileged read-only `/home/iqs/probe-production-fpm-fastcgi.py` lulus untuk kedua-dua pool:

- `oneid-web-prod84.sock`: PHP 8.4.26, `fpm-fcgi`, memory 512M, timeout 120s, post/upload 100M, timezone Asia/Kuala_Lumpur, session 28800, secure/httponly cookies.
- `oneid-mobile-prod84.sock`: PHP 8.4.26, `fpm-fcgi`, memory 512M, timeout 30s, post/upload 64K, timezone Asia/Kuala_Lumpur, session 28800, secure/httponly cookies.
- `display_errors=0` untuk kedua-dua pool.

Probe ini mengesahkan INI efektif melalui FastCGI; ia tidak menukar Nginx, code, database, cron, alternatives atau mobile feature. Live web masih `/run/php/php8.3-fpm-oneid.sock`. Fasa 3 candidate validation lulus; langkah berikutnya ialah review/backup dan deploy code/vendor ke live sebelum merancang Fasa 4 web cutover.

## Checkpoint staged application smoke lulus — 5 Oktober 2026

Candidate code/vendor yang telah staged di `/var/www/oneid` berjaya diuji terus melalui FastCGI web 8.4 menggunakan `/home/iqs/probe-production-app-fpm.py`.

- Socket: `oneid-web-prod84.sock`
- PHP SAPI: FPM 8.4.26
- Response: HTML login 92,274 bytes
- Login marker: ditemui
- Fatal/Parse error: tiada
- Secure `PHPSESSID` (`Secure`, `HttpOnly`, `SameSite=Lax`) dan security headers hadir.
- Nginx live masih menggunakan `/run/php/php8.3-fpm-oneid.sock`; tiada cutover.
- Mobile route/config kekal OFF.

Deploy backup kekal di `/var/backups/oneid-code-20261005-081341`. Legacy directories yang rsync tidak padam (`vendors`, `public.bak`, `public/vendors`) dikekalkan dan perlu direview kemudian; ia tidak menghalang smoke login.

Status Fasa 3: **STAGED + APPLICATION FPM SMOKE PASS**. Langkah seterusnya ialah Fasa 4: review backup/rollback, semak Nginx diff dan health baseline, kemudian cutover web OneID ke `/run/php/oneid-web-prod84.sock` dalam maintenance window. Jangan tukar cron/CLI atau enable mobile dalam langkah cutover web pertama.

## Checkpoint Fasa 4 — web cutover PHP 8.4 berjaya (5 Oktober 2026)

Production web OneID telah dipindahkan daripada `/run/php/php8.3-fpm-oneid.sock` kepada `/run/php/oneid-web-prod84.sock` menggunakan skrip guarded `cutover-production-web84.py`.

- Backup Nginx: `/var/backups/oneid-nginx-cutover-20261005-081739`.
- Root `nginx -t`: successful.
- Public `https://oneid.upnm.edu.my/`: HTTP/2 200.
- Login HTML marker hadir, response 92,274 bytes.
- Secure/HttpOnly/SameSite session cookie dan security headers hadir.
- Tiada Fatal error, Parse error, Warning atau Deprecated pada response.
- Nginx, PHP 8.3-FPM dan PHP 8.4-FPM aktif.
- Default CLI masih PHP 8.3.33; cron belum ditukar.
- Mobile route/client/provider kekal OFF.
- Database tidak diubah.

Fasa 4 web cutover berstatus **PASS**. Langkah operasi seterusnya ialah pemantauan selepas cutover dan smoke authenticated/downstream dengan akaun ujian yang diluluskan. Jangan tukar CLI/cron atau aktifkan mobile sebagai sebahagian daripada smoke web ini.

## Checkpoint post-cutover monitoring — dependency restore (5 Oktober 2026)

Selepas web cutover, public smoke mendedahkan legacy dependency `vendors/spyc-master` dan `vendors/device-detector-master` hilang/terbaca sebagai permission error. HTTP 200 masih returned tetapi log PHP menunjukkan `q_func.php` gagal load dependency. Punca: staging rsync mengecualikan vendor Composer tetapi juga cuba membersihkan direktori legacy `vendors`; direktori non-empty dikekalkan dalam keadaan tidak lengkap.

Pemulihan dibuat daripada backup production yang disahkan:

- Archive: `/home/iqs/oneid-backups/oneid-app-pre-2.12.0-20260908-000757.tar.gz`
- Paths dipulihkan: `vendors/spyc-master`, `vendors/device-detector-master`
- Backup sebelum restore: `/var/backups/oneid-legacy-vendors-20261005-082053`
- Owner/group selepas restore: `iqs:www-data`, file mode 644, directories 755.

Post-restore smoke:

- Public GET `/`: HTTP 200, 92,274 bytes, tiga request sekitar 0.013s.
- `Spyc.php` dan `device-detector-master/autoload.php`: hadir.
- Request baharu selepas restore tidak menambah error vendor pada `php-error.log`; error tail yang dilihat ialah rekod lama sebelum restore.
- Service nginx/php8.3-fpm/php8.4-fpm aktif, web route kekal `oneid-web-prod84.sock`.
- Log integration menunjukkan authenticated `oneid-internal-production` requests; ini bukti trafik authenticated sedia ada, bukan pengganti login browser/downstream E2E.

Status Fasa 4: **CUTOVER + DEPENDENCY RESTORE PASS**, dengan pemantauan authenticated/downstream sebenar masih perlu dibuat menggunakan akaun ujian yang diluluskan. Jangan aktifkan mobile atau tukar cron/CLI semasa pengesahan ini. Deployment script perlu dikemas kini supaya legacy `vendors/` tidak disentuh pada deployment akan datang.

## Checkpoint stop — 2026-10-05: post-cutover web validation complete

### Current phase

**Fasa 4 — Web production cutover to PHP 8.4.26: COMPLETE and validated.**

The production web route now uses `/run/php/oneid-web-prod84.sock`. Browser login, authenticated dashboard, session countdown, session renewal control, logout, and downstream SSO access were tested successfully.

### Post-upgrade audit result

- PHP-FPM 8.4.26 is active and serving production web traffic.
- Nginx configuration tested successfully and remains active.
- Public HTTPS smoke returned HTTP 200.
- PHP-FPM and Nginx services remained active.
- No new PHP-FPM application errors were found in the supplied post-cutover log review.
- Browser initially reported corrupted/missing vendor assets. Root cause was incomplete vendor deployment under the document root.
- Legacy vendor directories and frontend assets were restored from the production backup.
- Final asset checks returned HTTP 200 with correct JavaScript/CSS content types for jQuery, Bootstrap and vectormap.
- Session countdown became visible after the vendor restoration.
- Browser console was reported clear of the previous asset corruption errors.
- Downstream SSO access was reported successful.

### Production state intentionally unchanged

- Mobile production login remains disabled; Android package ID and production redirect URI are not registered.
- PHP CLI/default remains 8.3.
- `phar` and `phar.phar` remain 8.3.
- Cron/timers remain on the existing PHP 8.3 path.
- Database was not changed.
- Other production services were not changed.

### Backups and rollback references

- Web cutover: `/var/backups/oneid-nginx-cutover-20261005-081739`
- Staged code: `/var/backups/oneid-code-20261005-081341`
- Legacy vendors: `/var/backups/oneid-legacy-vendors-20261005-082053`
- Frontend vendor pre-restore: `/var/backups/oneid-bower-components-before-restore-20261005-*`
- PHP 8.4 preparation: `/var/backups/oneid-prod-php84-phase2-20261004-183102`

### Monitoring window

Recommended observation period before the next migration is **24 hours minimum**; **48–72 hours** is preferred when normal production traffic allows it. During the window record Nginx 5xx responses, PHP-FPM errors, application errors, login failures, downstream SSO failures and session-renewal issues.

### Resume point

After the monitoring window is accepted, start the next staged phase: review and migrate production CLI, `phar`, `phar.phar`, cron and timers to PHP 8.4.26. Keep mobile disabled until the Android production package ID and redirect URI are approved. Do not start those changes from this checkpoint without a new backup and rollback plan.

## Fasa 5 prepared — CLI/cron monitoring gate (2026-10-05)

Fasa 4 web production kekal validated, tetapi audit admin selepas cutover menemui asset frontend yang tidak lengkap. Asset legacy dalam `public/vendors`, termasuk `typeahead.js`, dipulihkan daripada backup production. Session countdown kembali berfungsi; admin dynamic pages perlu disahkan semula selepas `typeahead.js` restore.

Fasa 5 (CLI, `phar`, `phar.phar`, cron dan timer ke PHP 8.4.26) berstatus **PREPARED, NOT SWITCHED**. Pada 15:33 +08, hanya kira-kira tujuh jam berlalu sejak cutover web 08:17; minimum monitoring 24 jam belum dipenuhi. Ketiga-tiga cron entrypoint lulus PHP 8.4 lint. Session housekeeping dan MFA lifecycle turut lulus read-only `--check` pada PHP 8.4 tanpa mutasi. External sync tidak dijalankan manual kerana berpotensi menulis data.

Pelan: `docs/release/PHASE-5-CLI-CRON-PHP84.md`. Skrip backup-only disediakan di `tools/release/prepare-production-cli-cron84.py` dan disalin ke production sebagai `/home/iqs/prepare-production-cli-cron84.py`. Jalankan dengan `sudo ... --apply` untuk membuat snapshot sahaja; skrip tidak menukar runtime. Jangan buat CLI/cron switch sehingga monitoring gate diterima. Mobile kekal OFF dan database tidak diubah.

### Fasa 5 backup checkpoint — 2026-10-05 15:37 +08

Production backup-only preparation completed successfully using `/home/iqs/prepare-production-cli-cron84.py --apply`.

- Backup: `/var/backups/oneid-cli-cron-pre84-20261005-153705`
- PHP versions confirmed: 8.3.33 and 8.4.26.
- Existing alternatives confirmed on PHP 8.3.
- All three cron entrypoints passed PHP 8.4 lint.
- PHP 8.4 housekeeping `--check`: PASS, mutation statements 0.
- PHP 8.4 MFA lifecycle `--check`: PASS, mutation 0.
- Runtime switched: false.
- Cron/timers changed: false.
- Mobile changed: false.
- Database changed: false.

Fasa 5 remains **PREPARED + BACKUP COMPLETE, CUTOVER NOT STARTED**. Earliest minimum 24-hour monitoring gate is approximately 2026-10-06 08:17 +08. Prefer 48–72 hours if operationally practical. Before any switch, review monitoring logs and confirm admin dynamic pages after the final `typeahead.js` vendor restoration.

### Production mobile avatar correction — 2026-10-05

Production and staging dashboard source initially matched, but computed CSS showed a 108px border-box produced only a 92px visible photo after border/padding. Mobile dashboard avatar was increased to a 124px wrapper (approximately 108px visible image) with `margin-top:-74px` so it overlaps the banner edge as intended. Only `page/dashboard.php` changed; PHP 8.4 lint passed. Production pre-change backup: `/home/iqs/oneid-backups/mobile-avatar-20261005/dashboard.php.before`. No Nginx, runtime, database, mobile login or cron change.

### Fasa 5 monitoring gate accepted early — 2026-10-05 20:40 +08

The operator explicitly accepted an approximately 12.5-hour observation window instead of the previously recommended 24-hour minimum because OneID had been used throughout the working day without a reported critical issue. Latest snapshot: Nginx/PHP 8.3-FPM/PHP 8.4-FPM active; public web HTTP 200 (~0.013s); external sync reported normal `SKIP_NO_CHANGES`; session housekeeping applied with reconciliation pass; MFA lifecycle reported normal zero-work executions; application log showed ongoing authenticated SSO validations without fatal/parse errors. Admin dynamic pages, session countdown and mobile avatar asset/code issues found earlier were corrected and user-validated.

Guarded cutover script prepared and copied to `/home/iqs/cutover-production-cli-cron84.py`. It snapshots alternatives/crontabs/hashes, switches only `php`, `phar`, and `phar.phar`, performs PHP 8.4 lint/read-only checks, verifies required extensions and rolls alternatives back automatically on failure. Cron contents and system timers are not rewritten; existing `/usr/bin/php` cron commands inherit PHP 8.4. `phpsessionclean.timer` remains version-aware system infrastructure. PHP 8.3 remains installed. Mobile, Nginx web routing and database are excluded.

### Fasa 5 cutover — PHP CLI/cron 8.4.26 (2026-10-05 20:43 +08)

Guarded cutover completed successfully. Backup: `/var/backups/oneid-cli-cron-cutover-20261005-204309`. Default `php`, `phar`, and `phar.phar` now resolve to PHP 8.4.26. Crontab and timer content did not change; PHP 8.3 remains installed; web routing, mobile and database were unchanged. Required CLI extensions and Asia/Kuala_Lumpur timezone passed. PHP 8.4 lint plus housekeeping/MFA read-only checks passed.

Scheduled execution evidence after cutover:

- User MFA lifecycle log mtime `20:44:01 +08`; result normal (`processed=0 warnings=0`).
- Session housekeeping ran at `20:50:01 +08`; selected/updated 2 and reconciliation passed.
- External sync first post-cutover scheduled run remains pending at `21:10 +08`; do not invoke it manually because it may write synchronized data.

Rollback command: `sudo python3 -B /home/iqs/cutover-production-cli-cron84.py --rollback /var/backups/oneid-cli-cron-cutover-20261005-204309`.

### Fasa 5 complete — production CLI/cron PHP 8.4.26 (2026-10-05 21:11 +08)

All scheduled post-cutover jobs are validated:

- MFA lifecycle: repeated normal executions after cutover.
- Session housekeeping: repeated successful executions with reconciliation pass.
- External sync first post-cutover run at `21:10:03 +08`: `STAFF_HR`, `STUDENT_UG`, and `STUDENT_ODL_PG` all returned `SKIP_NO_CHANGES` with `code=NONE`.

Final snapshot: default `php`, `phar`, and `phar.phar` resolve to PHP 8.4.26; Nginx/PHP 8.4-FPM/PHP 8.3-FPM active; public web HTTP 200 (~0.011s). PHP 8.3 remains installed for rollback. Crontab/timer contents, Nginx web routing, mobile production and database were not changed.

Fasa 5 status: **COMPLETE**. Retain `/var/backups/oneid-cli-cron-cutover-20261005-204309` and the rollback script during the observation period. Mobile production remains OFF pending approved Android production package ID and redirect URI. Do not remove PHP 8.3 until a separately approved cleanup phase.

## Fasa 6 complete — production monitoring and validation (2026-10-05 21:26 +08)

Operator-confirmed random downstream tests, authenticated login/session behaviour, admin dynamic pages and normal user workflows passed. The final read-only monitoring snapshot used the post-recovery cutoff `2026-10-05T08:21:12+08:00`, excluding the known vendor deployment incident that was fixed immediately after cutover.

Final evidence:

- 52,406 HTTP 200 responses in the observed post-recovery window.
- HTTP 5xx: 0.
- Nginx critical/upstream error matches: 0.
- PHP 8.4-FPM warning/crash/max_children matches: 0.
- Application fatal/parse/permission/required-file matches: 0.
- Nginx, PHP 8.4-FPM and retained PHP 8.3-FPM active; `NRestarts=0` for all.
- Default CLI remains PHP 8.4.26.
- External sync: normal `SKIP_NO_CHANGES`, `code=NONE` for all three sources.
- Session housekeeping: repeated reconciliation pass.
- MFA lifecycle: repeated normal executions.
- Mobile remained OFF and database was unchanged.

Fasa 6 status: **PASS / COMPLETE**. Fasa 7 rollback is not required and must not be run without an actual runtime incident. Fasa 8 mobile activation remains deferred until approved Android production package ID and redirect URI are available.

## Release 2.14.2 dan penyelarasan Git/production (2026-10-05)

Keseluruhan kerja upgrade PHP 8.4.26, integrasi mobile dormant, bukti audit,
skrip operasi dan dokumentasi telah digabungkan secara fast-forward daripada
branch `release/production-php84-mobile` ke `main`.

- Commit release: `dbd61cb` (`release: publish OneID 2.14.2 PHP 8.4 upgrade`).
- Branch `main` dan branch release telah dipush ke `origin`.
- Fail yang sebelum ini kelihatan untracked di `/var/www/oneid-uat` kini
  direkodkan dalam Git; working tree staging bersih.
- Laporan Word rasmi disimpan di
  `docs/release/reports/OneID_Laporan_Upgrade_PHP_8.4.26_Production_2026-10-05.docx`.
- Metadata aplikasi, footer dan Version Releases dinaikkan daripada 2.14.1
  kepada **2.14.2** dengan changelog BM/English serta approval digest baharu.
- Production `/var/www/oneid` telah fast-forward ke commit release yang sama.
- Keadaan production sebelum penyelarasan Git disimpan sebagai stash
  `pre-origin-main-sync-2.14.2-20261005` untuk rujukan pemulihan tambahan.
- Kontrak metadata release dan polisi versi lulus pada PHP 8.4.26.
- Public login mengembalikan HTTP 200 dan memaparkan `Version 2.14.2`.
- Nginx kekal menggunakan `/run/php/oneid-web-prod84.sock`; Nginx dan
  PHP 8.4-FPM aktif.
- Mobile production kekal OFF: tiada location mobile khusus dalam Nginx dan
  service mobile production tidak aktif/tidak dipasang.
- Tiada migration atau perubahan database dibuat dalam penyelarasan ini.

Status akhir: **RELEASE 2.14.2 DEPLOYED / GIT MAIN SYNCHRONIZED**. Fasa 7
rollback kekal sebagai contingency sahaja. Fasa 8 mobile masih ditangguhkan.

## Release 2.14.3 — FAQ pengguna (2026-10-05)

Audit semula Soalan Lazim selesai dan semua 13 FAQ asal dikekalkan. Lima FAQ
baharu ditambah untuk akaun tidak aktif, aplikasi hilang/akses ditolak, masalah
downstream, paparan browser selepas kemas kini dan saluran bantuan PTMK.

- Parity kandungan: 18/18 BM dan English.
- Commit release: `e399da4` (`feat: update user FAQ for OneID 2.14.3`).
- Backup production:
  `/home/iqs/oneid-backups/oneid-faq-pre-2.14.3-20261005-230810.tar.gz`.
- Git staging dan production berada pada commit yang sama.
- Kontrak FAQ dan metadata release: PASS.
- Semakan live: HTTP 200, 18 FAQ BM, 18 FAQ English dan footer Version 2.14.3.
- Nginx/PHP 8.4-FPM kekal aktif.
- Mobile production kekal OFF dan database tidak diubah.

Status: **ONEID 2.14.3 FAQ DEPLOYED / VERIFIED**.

## Release 2.14.4 — pembetulan Panduan Sistem mobile (2026-10-06)

Isu arahan dan spotlight Panduan Sistem yang tidak sepadan pada telefon telah
dibetulkan. Puncanya ialah senarai langkah dikira semula ketika kandungan
aplikasi dimuatkan secara asynchronous; kemunculan butang Pilihan di tengah tour
boleh mengalihkan indeks kepada sasaran lain.

- Senarai langkah dan pasangan sasaran dibekukan ketika tour bermula.
- Pemilih sasaran menggunakan elemen sepadan yang benar-benar kelihatan.
- Langkah Keselamatan Akaun boleh membuka menu mobile yang tertutup.
- Spotlight dikemas kini semasa scroll, resize dan perubahan orientasi.
- Ruang paparan sasaran dikira di atas kad arahan mobile; tinggi kad maksimum
  dilaraskan kepada 44dvh.
- Versi aplikasi dinaikkan kepada **2.14.4**.
- Commit kod release: `2502746` (`fix: align mobile product tour targets`).
- Backup production:
  `/home/iqs/oneid-backups/oneid-product-tour-pre-2.14.4-20261006-095951.tar.gz`.
- Kontrak product tour 16/16, metadata release 18/18 dan dokumentasi 4/4 lulus.
- Aset JavaScript dan CSS live mengembalikan HTTP 200 dengan MIME yang betul.
- Git staging dan production berada pada commit release yang sama ketika
  pengesahan deployment; Nginx dan PHP 8.4-FPM aktif.
- Mobile production kekal OFF dan database tidak diubah.

Status: **ONEID 2.14.4 MOBILE PRODUCT TOUR DEPLOYED / VERIFIED**.

## Release 2.15.0 — peluasan laporan pentadbiran (2026-10-06)

Modul Reports dikembangkan daripada 18 kepada 25 laporan sambil mengekalkan
enam kategori tab sedia ada. Tujuh laporan baharu ialah Ringkasan Eksekutif
MyDigital ID, Akaun Dipautkan MyDigital ID, Penggunaan SSO Downstream, Laporan
Pengesahan MyDigital ID, Kesihatan & Kesegaran Penyelarasan, Aktiviti Pentadbir
dan Sejarah Perubahan Polisi MFA.

- Commit release: `5a8f702`; pembetulan jumlah pautan: `135b0ae`.
- Backup production:
  `/home/iqs/oneid-backups/oneid-admin-reports-pre-2.15.0-20261006-144427.tar.gz`.
- Production mempunyai semua enam jadual sumber dan 2,944 event MyDigital ID
  semasa preflight.
- Ketujuh-tujuh query production lulus; masa semakan antara 1.5 ms dan 1.46 s.
- Pautan MyDigital ID production: 1,112 keseluruhan/aktif; senarai dipagarkan
  kepada 500 rekod terkini dan ringkasan memaparkan jumlah penuh.
- Kontrak Reports 48/48, metadata release 18/18 dan ML8C 6/6 lulus.
- Public login HTTP 200 dan footer Version 2.15.0 disahkan.
- Nginx dan PHP 8.4-FPM aktif; mobile production kekal OFF.
- Tiada migration atau perubahan database dibuat.

Status: **ONEID 2.15.0 ADMIN REPORTS DEPLOYED / VERIFIED**. Pengesahan visual
oleh pentadbir bagi setiap preview laporan masih merupakan semakan operasi yang
disyorkan; query, authorization contract dan sumber production telah disahkan.
