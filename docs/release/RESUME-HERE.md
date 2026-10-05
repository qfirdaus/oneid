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
