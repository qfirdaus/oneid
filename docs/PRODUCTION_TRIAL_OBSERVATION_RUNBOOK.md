# OneID Production Trial Observation Runbook

**Environment:** production trial  
**Server:** `APPSSSOPRODv1`  
**Release baseline:** OneID `v2.8.4`  
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

