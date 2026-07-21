# F7 — Runbook Setup Admin 2FA untuk Staging dan Production

**Change ID:** `ONEID-F7-2FA-20260720-01`
**Skop:** Microsoft Authenticator (TOTP), OTP e-mel dan Admin Step-Up
**Status staging:** Berfungsi dan dalam ujian berterusan
**Status production:** Belum dilaksanakan; memerlukan kelulusan pemilik sistem

## 1. Tujuan

Dokumen ini ialah rujukan operasi lengkap untuk menyediakan Admin 2FA pada
staging dan, apabila diluluskan kelak, production. Ia meliputi deployment kod,
runtime, keyring, permission PHP-FPM, migration, activation, smoke test,
monitoring dan rollback.

Keyring, password, OTP, TOTP secret dan kandungan `.private/runtime.php` tidak
boleh dimasukkan ke Git, tiket, e-mel, screenshot atau log pelaksanaan.

## 2. Prinsip pemisahan environment

| Item | Staging | Production |
|---|---|---|
| Issuer Authenticator | `OneID@UPNM UAT` | `OneID@UPNM` |
| Application URL | `https://oneid-uat.upnm.edu.my` | URL production yang diluluskan |
| Keyring | Keyring staging sahaja | Keyring production baharu dan berasingan |
| Database | Database staging/UAT | Database production |
| Activation | Pilot dan functional test | Controlled rollout selepas approval |
| Monitoring | Ujian berterusan | Monitoring operasi dan incident response |

Keyring staging tidak boleh disalin ke production. Keyring juga mesti kekal
merentasi deployment dalam environment yang sama; release kod tidak boleh
menjana atau menggantikannya secara automatik.

## 3. Prasyarat

Sebelum setup:

1. branch/commit release telah dikenal pasti dan diuji;
2. backup database dan bukti restore tersedia;
3. PHP mempunyai extension Sodium;
4. akaun service PHP-FPM telah dikenal pasti;
5. SMTP dan e-mel rasmi admin berfungsi;
6. migration F7 disemak, tetapi belum dijalankan secara membuta tuli;
7. sekurang-kurangnya satu recovery path pentadbir tersedia; dan
8. owner, masa perubahan, rollback owner serta Change ID direkodkan.

Semakan asas:

```bash
php -v
php -m | grep -i '^sodium$'
ps -eo user,group,comm,args | grep '[p]hp-fpm'
```

## 4. Deployment kod

Contoh staging:

```bash
cd /var/www/oneid-uat
git fetch origin
git switch agent/admin-2fa-f7-accepted
git pull --ff-only
git rev-parse HEAD
git status --short
```

Production mesti menggunakan commit/tag release yang telah diluluskan, bukan
branch kerja yang berubah-ubah. `git status --short` mesti disemak sebelum dan
selepas deployment. Jangan overwrite perubahan runtime atau fail operasi yang
tidak dijejak Git.

## 5. Setup staging yang telah disahkan

### 5.1 Runtime staging

Dalam `.private/runtime.php` staging, tetapkan sekurang-kurangnya:

```php
'ONEID_TOTP_ISSUER' => 'OneID@UPNM UAT',
'ONEID_TOTP_KEYRING_PATH' => '/etc/oneid/keys/admin-totp-keyring.php',
'ONEID_APP_URL' => 'https://oneid-uat.upnm.edu.my',
'ONEID_ENVIRONMENT' => 'staging',
```

### 5.2 Keyring staging

Staging asalnya mempunyai keyring di bawah home `iqs`, tetapi PHP-FPM berjalan
sebagai `www-data`. Lokasi operasi yang telah disahkan ialah:

```text
/etc/oneid/keys/admin-totp-keyring.php
```

Pemasangan salinan keyring sedia ada:

```bash
sudo install -d -o root -g www-data -m 750 /etc/oneid/keys
sudo install -o root -g www-data -m 640 \
  /home/iqs/.config/oneid/keys/admin-totp-keyring.php \
  /etc/oneid/keys/admin-totp-keyring.php
```

Ujian menggunakan identiti PHP-FPM:

```bash
cd /var/www/oneid-uat
sudo -u www-data php -r '
require "app/Auth/TotpKeyring.php";
$keyring = \OneId\App\Auth\TotpKeyring::fromFile(
    "/etc/oneid/keys/admin-totp-keyring.php"
);
echo "PASS active_version=", $keyring->activeVersion(), PHP_EOL;
'
```

Output mesti `PASS active_version=v1` atau version aktif yang diluluskan.

### 5.3 Functional test staging

Uji mengikut urutan:

1. login admin;
2. verification OTP e-mel untuk `SECURITY_CONFIGURATION_CHANGE`;
3. jana QR dan setup key;
4. scan QR dalam Microsoft Authenticator;
5. sahkan kod enam digit pertama;
6. logout dan login semula;
7. masuk Admin menggunakan TOTP;
8. uji reset dengan SweetAlert dan reason minimum 10 aksara;
9. pastikan faktor lama direvoke dan QR baharu boleh didaftarkan; dan
10. jalankan snapshot/monitoring F7.6.

```bash
php tools/f7_6_uat_snapshot.php
```

## 6. Setup production — hanya selepas approval

### 6.1 Pre-deployment gate

Sebelum production, rekod keputusan `GO` yang mengesahkan:

- staging stabil dan diterima owner;
- commit/tag release tepat;
- backup database selesai;
- migration plan dan rollback plan disahkan;
- URL, e-mel, NTP/time sync dan PHP-FPM production betul;
- maintenance/change window diluluskan; dan
- keyring custodian serta recovery owner dikenal pasti.

### 6.2 Jana keyring production baharu

Jalankan pada server production sahaja. Jangan gunakan keyring UAT:

```bash
cd /var/www/oneid
sudo install -d -o root -g www-data -m 750 /etc/oneid/keys
sudo env \
  ONEID_TOTP_KEYRING_FILE=/etc/oneid/keys/admin-totp-keyring.php \
  php tools/f7_1_keyring_setup.php
```

Output normal untuk setup pertama ialah:

```text
PASS keyring=created active_version=v1 ...
```

Jika output menyatakan `existing`, hentikan proses dan sahkan provenance fail.
Jangan overwrite keyring tanpa pelan rotation/recovery yang diluluskan.

Tetapkan custody akhir dan uji:

```bash
sudo chown root:www-data /etc/oneid/keys/admin-totp-keyring.php
sudo chmod 640 /etc/oneid/keys/admin-totp-keyring.php
namei -l /etc/oneid/keys/admin-totp-keyring.php

sudo -u www-data php -r '
require "app/Auth/TotpKeyring.php";
$keyring = \OneId\App\Auth\TotpKeyring::fromFile(
    "/etc/oneid/keys/admin-totp-keyring.php"
);
echo "PASS active_version=", $keyring->activeVersion(), PHP_EOL;
'
```

### 6.3 Runtime production

Gunakan URL production sebenar yang telah diluluskan:

```php
'ONEID_TOTP_ISSUER' => 'OneID@UPNM',
'ONEID_TOTP_KEYRING_PATH' => '/etc/oneid/keys/admin-totp-keyring.php',
'ONEID_APP_URL' => 'https://<production-host>',
'ONEID_ENVIRONMENT' => 'production',
```

Semak hanya nilai tidak sensitif tanpa mencetak keseluruhan runtime:

```bash
php -r '
$c = require ".private/runtime.php";
echo "issuer=", ($c["ONEID_TOTP_ISSUER"] ?? "MISSING"), PHP_EOL;
echo "keyring=", ($c["ONEID_TOTP_KEYRING_PATH"] ?? "MISSING"), PHP_EOL;
echo "app_url=", ($c["ONEID_APP_URL"] ?? "MISSING"), PHP_EOL;
echo "environment=", ($c["ONEID_ENVIRONMENT"] ?? "MISSING"), PHP_EOL;
'
```

### 6.4 Schema dan activation

Semak status schema dahulu dan gunakan tooling F7.1 serta change evidence yang
berkaitan. Migration production hanya boleh dijalankan selepas backup dan
approval. Jangan jalankan schema-down atau memadam table/factor sebagai rollback
biasa.

Feature hendaklah kekal OFF semasa foundation, schema dan smoke-check awal.
Controlled bootstrap dilakukan oleh owner yang diluluskan hanya selepas e-mel,
TOTP enrollment, audit dan recovery path disahkan. Activation tidak boleh
dianggap selesai hanya kerana halaman UI boleh dibuka.

### 6.5 Smoke test production

Gunakan akaun pilot production yang diluluskan dan uji:

- OTP e-mel sampai ke mailbox rasmi;
- QR dijana secara lokal dan label ialah `OneID@UPNM`;
- secret disimpan encrypted dan tidak muncul dalam log/audit;
- confirmation serta verification TOTP berjaya;
- replay code ditolak;
- grant terikat kepada admin, session, browser dan purpose;
- reset/revoke beraudit berfungsi;
- logout memadam authentication lifecycle yang berkaitan; dan
- dashboard serta fungsi bukan 2FA tidak mengalami regresi.

## 7. Monitoring selepas deployment

Pantau HTTP 5xx, PHP-FPM/Nginx error, kegagalan SMTP, rate limit, replay,
pending factor, orphan grant, key-version/decrypt failure dan admin lockout.
Rekod correlation ID tetapi jangan rekod OTP, secret atau provisioning URI.

Hard-stop jika berlaku bypass authorization, kebocoran secret, repeated 5xx,
decrypt/keyring failure, cross-session grant atau owner kehilangan kedua-dua
kaedah akses.

## 8. Rollback

Rollback biasa:

1. hentikan mutation dan rollout baharu;
2. rekod masa, symptom dan correlation ID;
3. matikan feature melalui perubahan configuration beraudit yang diluluskan;
4. revoke challenge/grant terbuka jika diperlukan;
5. kekalkan encrypted factor dan keyring untuk recovery/forensic;
6. sahkan baseline admin access ketika feature OFF; dan
7. restore database hanya jika data/schema rollback telah diluluskan.

Jangan padam keyring, factor atau table sebagai langkah rollback pertama.
Pemadaman keyring menyebabkan secret TOTP sedia ada tidak boleh didecrypt.

## 9. Backup dan rotation keyring

Keyring production perlu mempunyai backup encrypted, akses minimum dan rekod
custodian. Backup mesti diuji boleh dipulihkan tanpa mencetak key. Rotation ialah
change berasingan: tambah version baharu, kekalkan version lama untuk decrypt,
tetapkan active version baharu dan uji sebelum retirement version lama.

## 10. Rekod pelaksanaan

Untuk setiap environment, rekod:

| Perkara | Nilai |
|---|---|
| Environment dan hostname | |
| Tarikh/masa MYT | |
| Change ID | |
| Commit/tag | |
| Operator dan approver | |
| PHP-FPM user/group | |
| Keyring path dan active version | |
| Backup evidence | |
| Migration result | |
| Smoke-test result | |
| Activation decision | |
| Monitoring owner | |
| Rollback decision/result | |

Rujukan berkaitan: `F7_1_SCHEMA_DAN_ENCRYPTION_FOUNDATION.md`,
`F7_5_UI_BOOTSTRAP_DAN_RECOVERY.md` dan
`F7_6_UAT_CONTROLLED_ROLLOUT_DAN_OBSERVATION.md`.
