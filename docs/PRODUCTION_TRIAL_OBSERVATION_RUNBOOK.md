# OneID Production Trial Observation Runbook

**Environment:** production trial  
**Server:** `APPSSSOPRODv1`  
**Release baseline selepas sync-preview patch:** OneID `v2.9.0`
**Mutation authorization:** none

## Automated read-only health check

Jalankan secara manual sekurang-kurangnya sekali sehari sepanjang trial:

```bash
cd /var/www/oneid
sudo php tools/production_trial_health_check.php
```

Tool ini menyemak runtime/version, Nginx, PHP-FPM, disk, memory, private checksum
manifest, TLS expiry, HTTPS root, MyDigital ID production configuration dan
status pemerhatian rotation pertama. Ia tidak login, menulis database,
mengaktifkan scheduler atau mengubah konfigurasi.

`OBSERVE first automatic application-log rotation is still pending` bukan
kegagalan sebelum fail rotation pertama wujud. Selepas rotation automatik,
pastikan status bertukar `PASS` dan semak:

```bash
sudo stat -c '%U:%G %a %s %y %n' \
  /var/www/oneid/storage/logs/php-error.log \
  /var/www/oneid/storage/logs/php-error.log.1
```

Fail aktif mesti kekal writable oleh pool `iqs:www-data` dan tidak boleh menjadi
world-readable.

## Rekod pemerhatian 09 Ogos 2026

Pemerhatian awal production trial telah direkodkan secara private pada server
production di bawah `/var/www/oneid/.private/observations/`. Direktori evidence
dimiliki `root:www-data` dengan mode `0750`; semua fail evidence dimiliki
`root:www-data` dengan mode `0640`.

Evidence yang tersedia:

- `production-health-20260809-initial.log` — health check awal;
- `production-health-recheck-pending-rotation.log` — semakan semula semasa
  rotation pertama masih pending;
- `production-health-recheck2-pending-rotation.log` — semakan kedua semasa
  rotation pertama masih pending;
- `manual-smoke-20260809.md` — keputusan smoke test manual oleh Norfirdaus
  Harun terhadap commit `f706008`.

Keputusan health check: 13 checks, 0 failures, 1 observation. Runtime/version,
Nginx, PHP-FPM, disk, memory, private checksum manifest, TLS, HTTPS root,
MyDigital ID configuration dan konfigurasi logrotate semuanya lulus. Satu-satunya
observation ialah rotation automatik pertama `php-error.log` masih belum berlaku.

Keputusan manual smoke test: manual login, MFA, dashboard, profile photo,
MyDigital ID, logout dan safe callback logging semuanya `PASS`. Trial menggunakan
hosts override ke `172.16.2.109`, akses tester `2.0.1.7`, dan database shared UAT.

Pada semakan terakhir, hanya fail aktif `storage/logs/php-error.log` wujud dan
belum ada rekod path tersebut dalam `/var/lib/logrotate/status`. Oleh itu, jangan
cipta evidence bernama `production-health-after-first-rotation.log` sehingga
fail rotation atau rekod status automatik benar-benar wujud. Force rotation
tidak dilakukan.

### Pembetulan parity locale production

Language switch pada halaman login pada mulanya tidak berfungsi kerana
`ONEID_LOCALE_INFRASTRUCTURE_ENABLED` dan `ONEID_DEFAULT_LOCALE` tidak terdapat
dalam private runtime production. Default aplikasi mematikan locale
infrastructure dan kembali kepada Bahasa Melayu. Staging disahkan menggunakan
`ONEID_LOCALE_INFRASTRUCTURE_ENABLED=true` dan `ONEID_DEFAULT_LOCALE=ms`.

Production telah diselaraskan kepada nilai staging tersebut tanpa perubahan
source code. Ujian guest session baharu mengesahkan:

- `?locale=en` memberikan HTTP 303, halaman seterusnya `lang="en"`, dan EN aktif;
- `?locale=ms` memberikan HTTP 303, halaman seterusnya `lang="ms"`, dan BM aktif;
- cookie jar mengandungi hanya nama yang dijangka untuk ujian ini:
  `oneid_locale` dan `PHPSESSID`;
- semua fail ujian sementara di `/tmp` telah dibuang.

Private production configuration manifest dijana semula selepas perubahan
runtime. Kesemua tujuh target manifest disahkan `OK`; nilai digest kekal private
dan tidak direkodkan dalam Git.

### Deployment v2.9.0 dan read-only Sync Preview

Release `v2.9.0` pada commit `48a759b` telah diuji di staging dan dideploy ke
production. Release metadata, Shadow Preview dan zero-mutation Sync Preview
contracts masing-masing lulus `18/18`, `10/10` dan `28/28`.

Production trial dibenarkan membuat sambungan baca sahaja kepada sumber HR,
Undergraduate dan ODL. Runtime diselaraskan dengan baseline staging dan hanya
gate berikut diaktifkan:

- `ONEID_ODL_SHADOW_PREVIEW_ENABLED=true`;
- `ONEID_ODL_OPERATIONAL_PREVIEW_ENABLED=true`.

Semua gate Apply, Pilot, Full, Operational Apply, on-demand dan cron kekal
disabled; Sync Engine production kekal `disabled`. ODL runtime preflight membaca
179 rekod dengan sifar medan canonical tidak sah dan
`mutation_statements=0`.

Ujian browser production mengesahkan Summary, Staff, Undergraduate dan ODL
Review dapat menyediakan preview. Semua kiraan tindakan sync adalah sifar,
menunjukkan sumber dan shared-UAT target semasa adalah up to date. Access log
menunjukkan HTTP 200 dan tiada error preview baharu, Apply atau cron direkodkan
semasa observation window.

Evidence `production-health-v290-readonly-sync-20260809.log` disimpan secara
private dengan owner `root:www-data` dan mode `0640`. Health check melaporkan
13 checks, 0 failures, 1 observation, 0 mutation statements dan 0 authentication
attempts. Observation tunggal kekal rotation aplikasi automatik pertama yang
belum berlaku. Selepas runtime diselaraskan, kesemua tujuh target private
configuration manifest dibaseline semula dan disahkan `OK`.

### Status penutupan pre-clearance

Semua kerja minor yang boleh dilaksanakan dengan selamat sebelum clearance telah
ditutup. Production trial kini berada dalam keadaan observation-only. Aktiviti
rutin yang dibenarkan hanyalah health check read-only, semakan checksum manifest,
smoke test terkawal dan pemerhatian rotation/log/TLS tanpa perubahan konfigurasi.

Baki pelaksanaan ialah perubahan major: database production berasingan, schema
dan data migration, DNS/public-access cutover, network/reboot, production asset
mapping, authentication/service-provider integration, mutation sync/cron,
security-update window, HSTS dan cleanup yang memerlukan keputusan owner.
Kesemuanya kekal deferred sehingga clearance bertulis dan change reference
diterima.

## Manual smoke test

Jalankan selepas perubahan yang diluluskan dan sekurang-kurangnya sekali bagi
setiap observation window. Jangan rekod password, OTP, authorization code,
cookie atau token dalam evidence.

### Login manual

- [ ] Buka `https://oneid.upnm.edu.my/` melalui hosts override trial.
- [ ] Login password berjaya.
- [ ] MFA email atau TOTP yang dipilih berjaya.
- [ ] `/page/dashboard` memberikan HTTP 200.
- [ ] Profile photo dan app catalogue dipaparkan.
- [ ] Logout memberikan redirect dan session tidak boleh digunakan semula.

### MyDigital ID

- [ ] Klik MyDigital ID dari login page.
- [ ] Provider rasmi `sso.digital-id.my` dipaparkan.
- [ ] Authentication dan callback berjaya.
- [ ] `/page/dashboard` memberikan HTTP 200.
- [ ] Logout kembali ke root OneID.
- [ ] Access log hanya merekod path callback tanpa query string.

### Evidence minimum

Rekod tarikh/masa, tester, release commit, PASS/FAIL dan correlation ID generik
jika ada. Jangan salin URL callback penuh atau secret.

## Certificate renewal ownership

- Certificate: wildcard `*.upnm.edu.my`, Sectigo.
- Current expiry: 16 Januari 2027.
- Local Certbot/ACME timer: tiada.
- Renewal owner: **PENDING CONFIRMATION BY SYSTEM OWNER**.
- Escalation deadline yang dicadangkan: sekurang-kurangnya 90 hari sebelum
  expiry.

Item ini hanya boleh ditutup selepas nama pasukan/pegawai, proses permohonan,
lokasi pemasangan dan kaedah verifikasi chain direkodkan.

## Task yang memerlukan clearance

Semua task berikut berstatus **DEFERRED — DO NOT EXECUTE BEFORE OWNER
CLEARANCE**:

1. provision, clone/migrate atau tukar ke database production;
2. schema migration dan activation flags;
3. DNS cutover dan pembuangan hosts override;
4. perubahan Netplan/NetworkManager atau reboot;
5. pembukaan Nginx/UFW allowlist kepada pengguna umum;
6. OS security update yang melibatkan libc, OpenSSL atau NetworkManager;
7. HSTS rollout;
8. External Sync, cron, ODL atau Apply activation;
9. production dynamic login-banner DB mapping/activation;
10. credential atau TOTP keyring rotation;
11. service-provider production integration/cutover;
12. destructive cleanup backup, orphan asset atau shared-UAT records.

Senarai terperinci, rollback dan definition of done berada dalam
`docs/PRODUCTION_TRIAL_HANDOVER_20260809.md`.
