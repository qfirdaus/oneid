# OneID Production Trial Handover

**Tarikh rekod:** 9 Ogos 2026  
**Server:** `APPSSSOPRODv1` (`172.16.2.109`)  
**Path:** `/var/www/oneid`  
**Domain akhir:** `oneid.upnm.edu.my`  
**Release trial:** `b3b0155` — OneID `v2.8.4`  
**Status:** Trial production terhad berjaya; **belum mendapat clearance go-live**

## 1. Tujuan dan batas semasa

Environment production telah disediakan untuk trial oleh pemilik sahaja. Ia
belum menjadi production live penuh. DNS rasmi masih menunjuk sistem lama,
akses server baharu dihadkan melalui allowlist, dan deployment baharu masih
menggunakan database UAT yang sama dengan staging.

Sepanjang trial, perubahan data/schema automatik, External Sync, cron, ODL Apply
dan activation gate lain dikekalkan dalam keadaan ditutup. Dokumen ini tidak
memberi authorization untuk mengaktifkan mana-mana gate tersebut.

## 2. Keadaan akhir yang telah disahkan

| Komponen | Status | Keadaan disahkan |
| --- | --- | --- |
| Source | PASS | Detached HEAD pada commit tepat `b3b0155`, release `v2.8.4` |
| Web root | PASS | Nginx menggunakan `/var/www/oneid/public` |
| PHP | PASS | PHP 8.3 dan dedicated pool `oneid` |
| FPM socket | PASS | `/run/php/php8.3-fpm-oneid.sock` |
| TLS/domain | PASS untuk trial | HTTPS `oneid.upnm.edu.my` berfungsi melalui hosts override |
| Access control | PASS untuk trial | Hanya IP pemilik, localhost dan server sendiri dibenarkan |
| Database | TEMPORARY | Production masih menggunakan shared UAT database |
| Login manual | PASS | Login, dashboard, profile photo dan logout berjaya |
| User MFA | PASS untuk shared UAT | Runtime diselaraskan dengan polisi DB `ENFORCED/PASSWORD_ONLY` |
| MyDigital ID | PASS | Login, callback, dashboard dan logout berjaya |
| Admin step-up | PASS | Step-up dan dashboard Administrator berjaya |
| App icons | PASS | Tiga ikon production dipromote dan HTTP 200 |
| Login banner | DISABLED | Tiada mapping banner environment production diluluskan |
| Sync/schema gates | SAFE/OFF | Apply, cron, ODL dan schema mutation ditutup |
| Sensitive access logging | PASS | HTTP/HTTPS menggunakan `oneid_safe`, query callback tidak direkodkan |

## 3. Kerja yang telah selesai

### 3.1 Source dan dependency

- DNS resolver server dibaiki sementara sehingga GitHub boleh di-resolve dan
  SSH GitHub berjaya authenticate.
- Release staging asal `ec6be0e` dipasang untuk baseline production.
- Kecacatan MyDigital ID yang mengunci callback kepada host UAT dibaiki dalam
  repository, diuji di staging dan diterbitkan sebagai `v2.8.4`.
- Release akhir dipush ke branch
  `agent/close-odl-mydigitalid-audits` dan production dipasang pada commit tepat
  `b3b0155`.
- `composer install --no-dev --classmap-authoritative` berjaya; dependency OIDC
  tersedia dan Composer audit terdahulu tidak menemukan advisory.
- Semua contract MyDigital ID, characterization berkaitan dan release metadata
  lulus dengan sifar kegagalan.

### 3.2 Runtime private production

- `.private/runtime.php` diformat, syntax sah, owner/group `iqs:www-data`, mode
  `0640`, dan mempunyai backup bertimestamp.
- Nilai asas production disahkan:
  - `ONEID_ENVIRONMENT=production`;
  - `ONEID_APP_URL=https://oneid.upnm.edu.my`;
  - debug ditutup;
  - SSO/callback/post-logout menggunakan domain production;
  - credential database, SMTP dan MyDigital ID tersedia tanpa direkodkan dalam
    output atau Git.
- Gate mutation ditutup, termasuk Sync Apply, Operational/Pilot/Full Sync, cron,
  ODL Apply, ML1 schema, MyDigital ID schema dan User MFA schema.
- Login banner production ditutup kerana shared DB hanya mempunyai mapping
  staging bagi aset semasa.

Backup penting yang direkodkan semasa kerja termasuk:

- `.private/runtime.php.backup-before-production-20260809-105629`;
- `.private/runtime.php.backup-before-production-trial-*`;
- `.private/runtime.php.backup-before-mfa-parity-*`;
- `.private/runtime.php.backup-before-totp-keyring-*`;
- `.private/runtime.php.backup-before-mydid-*`;
- `.private/runtime.php.backup-before-enable-mydid-v284-20260809-125043`;
- backup formatter `runtime.php.backup-before-format-*`.

Nama bertanda `*` perlu disenaraikan semula pada server sebelum rollback; jangan
anggap semua timestamp sama antara environment.

### 3.3 PHP-FPM dan permission

- Dedicated pool `/etc/php/8.3/fpm/pool.d/oneid.conf` diwujudkan dengan user
  `iqs`, group `www-data` dan runtime file production.
- Default pool `www` tidak dibuang supaya servis lain tidak terjejas.
- `storage/runtime` diselaraskan kepada akses `iqs:www-data`; directory `0770`
  dan file `0660`.
- PHP-FPM configuration test dan reload berjaya.
- TOTP keyring dipasang di luar repository:
  `/etc/oneid/keys/admin-totp-keyring.php`, owner `root:www-data`, mode `0640`.
- Keyring boleh dibaca oleh identity pool OneID dan checksum sepadan dengan
  staging. Fail pemindahan sementara di `/tmp` telah dibuang.
- Semakan semula sebagai identity efektif `iqs:www-data` pada 9 Ogos 2026
  mengesahkan keyring `READABLE=yes` dengan SHA-256
  `7cc9e21f8f967230f3f5f0511f1b22821c1f2ffd5776922ca9d938ae0c18d75c`.

### 3.4 Nginx, TLS dan trial access

- Site `/etc/nginx/sites-available/oneid` diaktifkan dengan domain akhir
  `oneid.upnm.edu.my`, public web root dan dedicated FPM socket.
- Backup Nginx diwujudkan:
  - `/etc/nginx/sites-available/oneid.backup-before-production`;
  - `/etc/nginx/sites-available/oneid.backup-before-safe-log`.
- Allowlist trial mengandungi IP pemilik `2.0.1.7`, localhost, IPv6 localhost
  dan IP server `172.16.2.109`; selainnya ditolak.
- Private/project paths seperti README, package, docs, config, `.private`,
  storage dan tools telah diuji sebagai `403/404`.
- HTTP dan HTTPS access log kini menggunakan format `oneid_safe`, yang
  merekodkan `$uri` tanpa query string dan tidak merekodkan referrer.
- Nginx syntax test dan reload selepas perubahan log berjaya.
- Temporary cookie-debug configuration tidak lagi wujud dalam konfigurasi
  aktif. Fail lama 3,957 byte telah dipindahkan secara recoverable kepada
  `/var/log/nginx/oneid-cookie-debug.log.retired-20260809`, owner `root:root`,
  mode `0600`; health request tidak mencipta semula log debug tersebut.
- Nginx log telah diliputi polisi harian sedia ada `/etc/logrotate.d/nginx`
  dengan 14 rotation.
- Application log `/var/www/oneid/storage/logs/php-error.log` kini diliputi
  `/etc/logrotate.d/oneid-app`: harian, 14 rotation, `maxsize 10M`, compression,
  `copytruncate` dan `su iqs www-data`. Debug validation lulus tanpa rotation.
- Application log diketatkan daripada `0644` kepada `0640`; identity pool
  `iqs:www-data` kekal boleh menulis.

### 3.4.1 Pre-clearance host health evidence

- Root filesystem menggunakan 22% daripada 118 GiB; inode menggunakan 4%.
- Memory tersedia kira-kira 14 GiB daripada 15 GiB; swap 8 GiB tidak digunakan.
- Load average semasa audit rendah (`0.29, 0.15, 0.10`).
- Timezone `Asia/Kuala_Lumpur`, system clock synchronized dan NTP aktif.
- Nginx serta PHP-FPM kedua-duanya `enabled` dan `active`; dedicated OneID socket
  wujud dengan owner `www-data:www-data`, mode `0660`.
- Wildcard TLS `*.upnm.edu.my` dikeluarkan oleh Sectigo dan sah sehingga
  16 Januari 2027. Tiada Certbot/ACME timer tempatan ditemui; renewal ownership
  masih perlu disahkan secara operasi sebelum expiry.
- Audit project mendapati sifar symlink aktif dan sifar world-writable path.

### 3.5 DNS/hosts trial routing

- DNS rasmi `oneid.upnm.edu.my` masih menunjuk server lama `172.16.4.23` ketika
  rekod ini dibuat.
- Windows pemilik menggunakan hosts override ke `172.16.2.109`.
- Production server menggunakan `/etc/hosts` override ke `172.16.2.109` supaya
  internal SSO/API request tidak kembali ke server lama.
- Backup `/etc/hosts.backup-before-oneid-trial` tersedia.
- Internal request dan browser request telah disahkan sampai ke server baharu.

### 3.6 MyDigital ID

- Production credential yang telah diuji di staging dipasang melalui private
  runtime tanpa mendedahkan secret.
- Callback production:
  `https://oneid.upnm.edu.my/auth/mydigitalid/callback.php`.
- Issuer, confidential client, TLS verification, `openid`, authorization-code
  flow dan PKCE `S256` berjaya divalidasi.
- Source `v2.8.4` kini fail-closed mengikut environment:
  - staging hanya menerima `oneid-uat.upnm.edu.my`;
  - production hanya menerima `oneid.upnm.edu.my`;
  - callback silang dan environment tidak dikenali ditolak.
- End-to-end production berjaya:
  `login 303 -> callback 303 -> dashboard 200 -> logout 303`.
- Access log production pada awal trial pernah merekodkan satu authorization
  code sebelum `oneid_safe` dipasang. Code tersebut telah digunakan/single-use.
  Log sejarah tidak diubah bagi mengekalkan integriti audit.

### 3.7 App icons dan banner

- Tiga ikon aplikasi sebenar dipromote daripada staging ke production:

| Aplikasi | App ID | Production filename |
| --- | --- | --- |
| Sistem E-Hepa | `PEYYRREE2B` | `app_icon_a66ff37d06dcb122c4da64fb2d829d77.png` |
| Sistem E-PR | `2RARD39ZMP` | `app_icon_4d9b3fec1e39f4e2c0c8f90e13ab13cc.png` |
| Sistem ODL | `EJEN8QNV9N` | `app_icon_0c02065a8efc2e6c96209bfe9ae07686.png` |

- Ketiga-tiga fail mempunyai SHA-256
  `9c48c20e50a44fb42e01fe4e32b0c7aeafb0878c2cc1c8611523ec53d9ef2a94`
  dan memberikan `200 image/png`.
- Tiga row `sp_app_asset` environment `production` diwujudkan secara transaction
  dengan `updated_by=deploy-v284`.
- Fixture WA2/WA5 tidak dipromote. Satu missing reference berbaki untuk fixture
  tidak aktif `2WJ4USYRS9`; ia bukan blocker aplikasi aktif.
- Empat login-banner file staging telah disalin untuk preservation tetapi tidak
  dipetakan/diaktifkan dalam production. Jangan delete sebelum reconciliation
  dan retention gate yang diluluskan.
- Empat directory upload production telah disahkan dengan identity pool
  `iqs:www-data`:
  - private `storage/runtime/app-icon-staging` mode `0700`;
  - private `storage/runtime/login-banner-staging` mode `0700`;
  - public `public/public_img` mode setgid `2750`;
  - public `public/login_banners` mode setgid `2750`.
- Write probe dan atomic rename daripada private staging ke public directory
  lulus untuk app icon dan login banner; semua probe dibuang selepas ujian.
- GD, Fileinfo dan WebP runtime tersedia. WA2/WA3/WA5 serta LB2/LB3/LB4/LB6
  upload, atomicity, normalization, guard dan public-reader contracts semuanya
  lulus tanpa mutation production sebenar.

### 3.8 Pre-clearance security surface audit

- External listeners terhad kepada SSH `22`, HTTP `80` dan HTTPS `443`; resolver
  serta guest/VM agent listeners lain hanya bind pada loopback.
- UFW aktif dengan default incoming deny, SSH rate limit dan HTTP/HTTPS allow.
  Trial audience masih dihadkan lagi pada lapisan Nginx allowlist.
- Composer manifest sah dan `composer audit --no-dev` melaporkan tiada security
  vulnerability advisory.
- TLS 1.2 berjaya dengan `ECDHE-RSA-AES256-GCM-SHA384`; TLS 1.0 dan 1.1 tidak
  dapat merundingkan protocol/cipher dan dianggap ditolak.
- Secure session cookie, CSP, nosniff, SAMEORIGIN, referrer policy dan permissions
  policy hadir. HSTS belum dihantar dan sengaja tidak diaktifkan ketika DNS rasmi
  masih menunjuk sistem lama.
- OS mempunyai pending security updates termasuk libc, OpenSSL, Samba libraries,
  NetworkManager, timezone data dan kernel userspace headers. Tiada package
  dipasang semasa audit read-only ini.
- Private checksum baseline bagi Nginx site/safe-log, dedicated FPM pool,
  logrotate, runtime, TOTP keyring dan `/etc/hosts` disimpan pada production di
  `.private/production-config-manifest-20260809.sha256`, owner `root:www-data`,
  mode `0640`. Nilai digest tidak diterbitkan ke Git kerana manifest mengikat
  fail konfigurasi yang mengandungi secret.

## 4. Perkara sementara yang mesti kekal sepanjang trial

1. Kekalkan Nginx IP allowlist; jangan buka kepada umum.
2. Kekalkan Windows hosts override dan production `/etc/hosts` override selagi
   DNS rasmi masih menunjuk server lama.
3. Kekalkan shared UAT DB sehingga database production diluluskan dan siap.
4. Kekalkan semua sync, cron, ODL Apply dan schema Apply dalam keadaan off.
5. Kekalkan `ONEID_LOGIN_BANNER_ENABLED=false` sehingga mapping production
   dipromote melalui gate banner yang sesuai.
6. Jangan delete runtime backup, keyring, copied banner atau orphan candidate
   ketika observation trial belum ditutup.

## 5. Checklist belum selesai sebelum clearance go-live

### 5.1 Keputusan dan authorization

Semua item dalam Seksyen 5 berstatus **DEFERRED — DO NOT EXECUTE BEFORE OWNER
CLEARANCE**. Selepas clearance diterima, buka semula hanya item yang termasuk
dalam skop change reference tersebut; clearance satu item tidak mengaktifkan
item lain secara automatik.

- [ ] Dapatkan clearance bertulis untuk production go-live dan maintenance
  window.
- [ ] Tetapkan change reference, owner, approver, rollback owner dan saluran
  komunikasi insiden.
- [ ] Tetapkan commit/tag canonical yang diluluskan. Production kini detached
  pada `b3b0155`; tentukan sama ada release perlu merge/tag daripada branch
  rasmi sebelum cutover.
- [ ] Bekukan perubahan staging/production sepanjang cutover window.

### 5.2 Database production

- [ ] Provision database production berasingan.
- [ ] Tetapkan DSN, least-privilege DB user, credential rotation dan network
  allowlist production.
- [ ] Tentukan kaedah baseline data: clone terkawal, migration atau clean
  initialization; jangan terus menyalin UAT tanpa keputusan data owner.
- [ ] Ambil backup sumber dan sasaran serta sahkan checksum/restore rehearsal.
- [ ] Jalankan schema inventory dan migration readiness secara read-only.
- [ ] Dapatkan approval berasingan sebelum mana-mana schema Apply.
- [ ] Rekonsiliasi akaun, role Admin, MFA policy, TOTP factors, MyDigital ID
  identity links, app catalogue, categories, translations, banner dan audit
  retention pada DB production.
- [ ] Tukar runtime DB production, reload FPM dan buktikan aplikasi tidak lagi
  tersambung ke UAT sebelum membuka akses.
- [ ] Pastikan staging kekal pada DB UAT selepas pemisahan.

### 5.3 Asset promotion

- [ ] Hasilkan manifest final semua environment-specific app icons yang perlu
  berada di production dan jalankan WA6 reconciliation.
- [ ] Selesaikan atau rekod acceptance bagi fixture tidak aktif
  `2WJ4USYRS9`; jangan promote fixture tanpa keperluan.
- [ ] Laksanakan gate promotion login banner production jika banner dinamik
  diperlukan; cipta mapping DB production dan sahkan checksum.
- [ ] Audit runtime uploads lain seperti profile photos/manual/public documents
  berdasarkan keperluan DB production baharu.
- [ ] Tetapkan backup/replication bagi aset runtime yang tidak berada dalam Git.

### 5.4 DNS, TLS dan networking

- [ ] Sahkan A/AAAA record akhir, TTL semasa dan jadual pengurangan TTL dengan
  pasukan DNS.
- [ ] Sahkan sijil production, chain, expiry, renewal automation dan private-key
  permission.
- [ ] Selepas DNS/TLS cutover disahkan stabil, tentukan polisi HSTS dan rollout
  berperingkat. Jangan tambah `includeSubDomains` tanpa semakan semua subdomain.
- [ ] Bersihkan konflik Netplan/NetworkManager dan pastikan DNS resolver
  `172.16.2.10` serta default route kekal selepas reboot. Perubahan ini berisiko
  memutuskan SSH dan perlu window/console akses sendiri.
- [ ] Uji outbound DNS/HTTPS ke GitHub, MyDigital ID, SMTP dan dependency lain
  selepas reboot atau network remediation.
- [ ] Selepas DNS cutover, buang Windows hosts override dan baris production
  `/etc/hosts`, kemudian sahkan `getent hosts oneid.upnm.edu.my` menunjuk
  `172.16.2.109` melalui DNS sebenar.
- [ ] Uji dari sekurang-kurangnya satu klien tanpa hosts override.
- [ ] Tentukan allowlist go-live atau buka akses secara terkawal mengikut polisi
  firewall/WAF; jangan sekadar membuang `deny all` tanpa keputusan.

### 5.5 Authentication dan integration

- [ ] Sahkan MyDigital ID registered redirect/post-logout URI dalam provider
  untuk keadaan DNS sebenar dan lakukan smoke test selepas cutover.
- [ ] Sahkan SMTP delivery, sender policy, throttling dan mailbox monitoring
  untuk OTP/MFA production.
- [ ] Tentukan sama ada keyring production kekal sama atau perlu rotation;
  rotation mesti mengambil kira faktor TOTP sedia ada.
- [ ] Jalankan login manual, MFA email, MFA TOTP, forgot-password, MyDigital ID,
  account-switch guard, logout dan session timeout regression menggunakan DB
  production.
- [ ] Sahkan internal SSO API tidak lagi bergantung pada hosts override.
- [ ] Daftarkan/validasi setiap service provider yang akan menerima token
  production dan uji tanpa mengganggu sistem lama.

### 5.6 Sync, cron dan mutation gates

- [ ] Putuskan sama ada External Sync diperlukan pada hari pertama go-live.
- [ ] Jika diperlukan, lakukan preview/read-only reconciliation dan dapatkan
  approval berasingan untuk Operational/Pilot/Full Apply.
- [ ] Provision lock/log/backup, exact counts dan plan hash sebelum Apply.
- [ ] Pasang cron/systemd timer hanya selepas dry-run dan owner approval.
- [ ] Pastikan satu scheduler sahaja aktif untuk mengelakkan duplicate run.
- [ ] Kekalkan ODL, ML1, MyDigital ID dan User MFA schema flags `false` sehingga
  gate masing-masing lengkap.

### 5.7 Operations, monitoring dan security

- [x] Logrotate Nginx dan `storage/logs/php-error.log` dikonfigurasi serta lulus
  debug validation; application log mode `0640` dan writable oleh pool.
- [ ] Sahkan permission dan kelakuan sebenar selepas rotation automatik pertama;
  force rotation tidak dilakukan semasa trial.
- [x] Rekod health check awal dan dua recheck disimpan secara private dengan
  owner `root:www-data` dan mode `0640`; semua semakan mempunyai 0 failures,
  manakala rotation automatik pertama masih berstatus observation/pending.
- [x] Manual smoke test 09 Ogos 2026 oleh Norfirdaus Harun pada commit `f706008`
  merekodkan PASS untuk manual login, MFA, dashboard, profile photo,
  MyDigital ID, logout dan safe callback logging.
- [ ] Tetapkan monitoring HTTP/TLS/FPM/disk/database, alert owner dan retention.
- [x] Read-only production trial health-check dan manual authentication
  observation runbook tersedia di
  `tools/production_trial_health_check.php` dan
  `docs/PRODUCTION_TRIAL_OBSERVATION_RUNBOOK.md`.
- [x] Temporary cookie-debug config disahkan tiada; log lama telah diretire
  secara recoverable dengan mode `0600` dan tidak dicipta semula.
- [ ] Tetapkan owner/proses renewal wildcard Sectigo sebelum Januari 2027;
  tiada Certbot/ACME timer tempatan ditemui. Owner semasa direkodkan sebagai
  `PENDING CONFIRMATION BY SYSTEM OWNER`, bukan dianggap selesai.
- [x] Audit open ports/UFW, Composer advisory, TLS protocol, security headers dan
  upload-directory atomic write telah selesai tanpa blocker trial.
- [x] Private checksum baseline konfigurasi production telah diwujudkan dengan
  permission `0640`; gunakan untuk change detection sebelum cutover.
- [x] Login-page guest locale parity diselaraskan dengan staging melalui
  `ONEID_LOCALE_INFRASTRUCTURE_ENABLED=true` dan `ONEID_DEFAULT_LOCALE=ms`.
  Pertukaran EN/BM, cookie `oneid_locale`, redirect dan active-state telah
  disahkan; tiada perubahan source code diperlukan. Manifest private dijana
  semula dan kesemua tujuh target lulus checksum.
- [ ] Jadualkan OS security update dalam maintenance window dengan snapshot,
  console access dan regression selepas reboot; khususnya OpenSSL, libc dan
  NetworkManager tidak dikemas kini semasa trial aktif.
- [ ] Semak log terdahulu yang mengandungi query callback mengikut polisi
  retention/access; jangan edit log audit secara ad hoc.
- [ ] Jalankan vulnerability/dependency scan dan production configuration review
  terkini sebelum clearance.
- [ ] Uji reboot terkawal: network, DNS, Nginx, FPM sockets, keyring permission,
  runtime read, scheduler state dan application health.
- [ ] Ambil snapshot/backup server sebelum cutover dan rehearse rollback ke
  sistem lama/DNS lama.
- [ ] Selepas observation berjaya, semak dan kurangkan backup runtime lama secara
  terkawal; jangan guna wildcard deletion.

## 6. Minimum go-live validation selepas clearance

Jalankan sekurang-kurangnya bukti berikut selepas DB dan DNS production sebenar
siap:

```bash
cd /var/www/oneid

git status --short --branch
git log -1 --oneline --decorate
php tools/format_private_runtime.php --check
php tools/release_metadata_contract.php
php tools/wa6_web_app_asset_reconciliation.php
sudo php-fpm8.3 -tt
sudo nginx -t
getent hosts oneid.upnm.edu.my
curl -I https://oneid.upnm.edu.my/
```

Kemudian lakukan smoke test browser:

1. login manual dan MFA;
2. MyDigital ID login/callback;
3. dashboard dan profile photo;
4. Administrator step-up;
5. app catalogue/icon/link;
6. logout dan session expiry;
7. endpoint private kekal `403/404`;
8. access log tidak mengandungi query OIDC.

## 7. Rollback ringkas trial

Jika trial perlu ditutup sebelum go-live:

1. set `ONEID_MYDID_ENABLED=false`, format runtime dan reload PHP-FPM;
2. kekalkan `deny all`/allowlist dan jangan tukar DNS;
3. rollback source hanya kepada commit yang telah direkodkan dan diuji;
4. gunakan backup runtime bertimestamp yang tepat, bukan fail yang diteka;
5. jangan delete row asset production atau fail ikon tanpa manifest dan rollback
   authorization;
6. sahkan login manual, HTTP health dan log selepas rollback.

Rollback DNS selepas cutover kelak mesti diselaraskan dengan pasukan DNS dan
sistem lama; ia bukan sebahagian daripada authorization trial ini.

## 8. Definisi siap untuk production live

Production hanya boleh dianggap live apabila semua perkara berikut benar:

- clearance dan change window direkodkan;
- release/tag canonical diluluskan;
- DB production berasingan, dibackup dan diuji restore;
- DNS sebenar menunjuk server baharu tanpa hosts override;
- authentication/integration smoke test lulus;
- asset reconciliation tiada missing reference aktif;
- scheduler/mutation state sepadan keputusan owner;
- monitoring, logrotate, backup dan rollback tersedia;
- access control dibuka hanya kepada audience yang diluluskan;
- observation awal selesai tanpa insiden blocker.
