# Fasa 3 — Pelaksanaan dan Rollback OneID-UAT

**Tarikh pelaksanaan:** 13 Julai 2026  
**Rujukan:** `docs/PELAN_REMEDIASI_BERFASA_ONEID_UAT.md`  
**Skop:** Inventori secrets, pemindahan secrets keluar daripada source/document root, persediaan rotation dan pembetulan permission.

## 1. Ringkasan Status

Fasa 3 telah menyiapkan pemindahan teknikal secrets dan membuang permission `777` daripada working tree aplikasi. Nilai credential semasa dikekalkan bagi mengelakkan gangguan servis.

Status sebenar:

| Komponen | Status |
|---|---|
| Inventori secret dan consumer | Selesai |
| Hardcoded secret dalam PHP working tree | Dipindahkan |
| Secret store di luar Nginx document root | Selesai |
| Environment variable override | Selesai |
| Template tanpa nilai sebenar | Selesai |
| World-writable files/directories | Sifar |
| Rotation credential pada sistem pemilik | **Belum selesai** |
| Revocation credential lama | **Belum selesai** |
| Full authenticated/integration smoke test | **Belum selesai** |
| Pemisahan OS user deployer dan PHP-FPM | **Belum selesai** |

Fasa ini tidak mendakwa secrets sudah selamat sepenuhnya. Nilai lama pernah berada dalam source dan berkemungkinan masih ada dalam Git history, backup, deployment lama atau salinan developer. Oleh itu rotation dan revocation masih wajib.

## 2. Baseline Sebelum Perubahan

### 2.1 Lokasi hardcoded secrets

Secrets ditemui dalam:

- `lib/config.php`
- `lib/q_func.php`
- `lib/q_func_old.php`
- `lib/external_data_source_API.php`
- `lib/skp_api.php`
- `skp_api.php`
- `idms.php`
- `diag/agent.php`
- `diagnostic/index.php`
- komen lama dalam `atest.php`

Nilai sebenar sengaja tidak direkodkan dalam dokumen ini.

### 2.2 Permission

Baseline filesystem menunjukkan ribuan fail dan hampir semua direktori aplikasi menggunakan `777`. Ini membenarkan mana-mana local user/process menulis atau menggantikan kod, aset dan konfigurasi.

Antara lokasi kritikal yang menggunakan `777` ialah:

- root projek;
- fail PHP aktif;
- `lib/`;
- `public_img/`;
- `cron/` dan `cron/logs/`;
- aset frontend dan dependency.

### 2.3 Runtime identity

| Proses | OS user |
|---|---|
| Nginx worker | `www-data` |
| PHP-FPM pool OneID | `iqs` |
| Pemilik majoriti source/deployment | `iqs` |

Nginx document root ialah `/var/www/app/oneid-uat`. PHP `open_basedir` tidak diketatkan, maka PHP-FPM boleh membaca secret store di parent directory.

## 3. Inventori Secrets dan Rotation Owner

| Secret / sistem | Consumer dalam projek | Owner yang perlu sahkan/rotate | Pemindahan | Rotation |
|---|---|---|---|---|
| MySQL utama OneID | `lib/config.php`, `lib/Database.php` | DBA OneID | Selesai | Belum |
| SMTP Microsoft 365 | forgot-password/OTP dalam `lib/q_func.php` dan fail lama | Pentadbir e-mel/M365 | Selesai | Belum |
| ODBC staf | external data source dan SKP helper | Owner SSO/eHRM DB | Selesai | Belum |
| ODBC pelajar sync | scheduled/user sync | Owner pangkalan data pelajar | Selesai | Belum |
| ODBC pelajar lookup | lookup pengguna khusus | Owner pangkalan data pelajar | Selesai | Belum |
| ODBC SKP | `skp_api.php` | Owner sistem SKP | Selesai | Belum |
| ODBC IDMS | `idms.php` | Owner IDMS/eHRM | Selesai | Belum |
| Token diagnostic agent | `diag/agent.php` | Infrastruktur/NOC | Selesai | Belum |
| DB health-check diagnostic | `diag/agent.php` | DBA dan Infrastruktur/NOC | Selesai | Belum |
| Token AI-NOC diagnostic | `diagnostic/index.php` | Infrastruktur/NOC | Selesai | Belum |

Nama owner di atas ialah fungsi/tanggungjawab yang dicadangkan. Individu sebenar dan tarikh rotation perlu direkodkan dalam change ticket organisasi.

## 4. Pemindahan Secrets

### 4.1 Central secret loader

Fail baharu `lib/secrets.php` menyediakan fungsi `oneid_secret()`.

Urutan lookup:

1. Environment variable dengan nama key yang diminta.
2. Fail yang ditetapkan melalui `ONEID_SECRETS_FILE`.
3. Fallback UAT `/var/www/app/.oneid-uat-secrets.php`.

Loader akan fail secara tertutup dengan `RuntimeException` jika secret store tiada, tidak boleh dibaca, formatnya bukan array, atau required key kosong. Mesej exception tidak mengandungi nilai secret.

### 4.2 Secret store runtime

Secret store semasa:

```text
/var/www/app/.oneid-uat-secrets.php
```

Kawalan:

- berada di luar document root `/var/www/app/oneid-uat`;
- permission `600`;
- owner `iqs:iqs`;
- tidak berada dalam repository OneID;
- hanya menyimpan nilai semasa sebagai langkah migrasi, bukan bukti rotation.

Jangan salin fail ini ke `docs/`, root projek, shared folder atau tiket. Backup mesti menggunakan saluran encrypted/secret manager yang diluluskan.

### 4.3 Environment override

Setiap key dalam template boleh diberi melalui environment. Environment mempunyai precedence lebih tinggi daripada secret file. Ini membolehkan migration seterusnya kepada systemd credential, Vault atau secret manager tanpa mengubah consumer code.

`ONEID_SECRETS_FILE` pula boleh menunjuk kepada lokasi secret store berlainan bagi UAT/production.

### 4.4 Template tanpa secret

Template disediakan di `docs/examples/oneid-secrets.example.php`. Semua nilai kosong dan fail ini selamat dijadikan rujukan struktur sahaja.

### 4.5 Consumer yang dimigrasikan

- Database utama OneID.
- SMTP forgot-password/OTP.
- Staff/student ODBC integration.
- SKP ODBC integration.
- IDMS ODBC endpoint.
- Lightweight diagnostic agent token dan DB health checks.
- AI-NOC diagnostic token.
- Hardcoded credential dalam komen `atest.php` dibuang.

Imbasan exact-value selepas migrasi tidak menemui nilai lama dalam fail PHP working tree. Imbasan ini tidak membersihkan Git history.

## 5. Permission Selepas Fasa 3

Polisi yang dilaksanakan, tidak termasuk `.git`:

| Jenis | Permission | Bilangan selepas perubahan |
|---|---:|---:|
| Direktori aplikasi | `755` | 793 |
| PHP first-party/vendor | `640` | 83 |
| Aset, dependency dan dokumen biasa | `644` | 5,239 |
| Fail sensitif/temp/log khusus | `600` | 11 |
| Secret store luar document root | `600` | 1 |

Keputusan:

- World-writable file: `0`.
- World-writable directory: `0`.
- `public_img/` kekal owner-writable untuk upload yang sah.
- `cron/logs/` dan fail log sync kekal owner-writable.
- `sso_db.sql`, script scheduler dan metadata OS diletakkan pada `600`.
- `.git` sengaja tidak diubah oleh normalisasi permission.

### 5.1 Baki risiko ownership

PHP-FPM dan deployment owner menggunakan user sama, `iqs`. Walaupun group/other tidak lagi boleh menulis source, proses PHP yang compromised masih mempunyai kuasa owner dan direktori milik `iqs`.

Pemisahan penuh memerlukan tindakan pentadbir OS:

1. Cipta deployment owner berasingan, contoh `oneid-deploy`.
2. Jalankan PHP-FPM sebagai runtime user berasingan, contoh `oneid-web`.
3. Jadikan source milik deployer dan read-only kepada runtime user.
4. Berikan write access runtime hanya pada upload, session/cache dan log yang disahkan.
5. Uji deploy, upload, session dan cron sebelum production.

Perubahan OS ini tidak dilakukan dalam Fasa 3 semasa kerana memerlukan akses pentadbir dan perubahan PHP-FPM service.

## 6. Verification yang Dilaksanakan

- 46 fail PHP first-party, termasuk template kosong, lulus `php -l`.
- 11 fail yang disentuh secara langsung lulus lint berasingan.
- Secret loader membaca runtime store dengan berjaya.
- Exact-value scan bagi credential lama dalam PHP working tree: tiada padanan.
- Login page `https://oneid.local/`: HTTP `200`.
- `public_img/`: owner masih boleh menulis.
- `cron/logs/sync_cron.log`: owner masih boleh menulis.
- World-writable file dan directory: sifar.
- Secret store disahkan berada di luar Nginx document root dengan mode `600`.

HTTP `200` root membuktikan bootstrap dan sambungan database utama tidak terputus pada masa ujian. Ia tidak membuktikan semua ODBC, SMTP atau authenticated flow berjaya.

## 7. Runbook Rotation

Laksanakan satu sistem pada satu masa dalam maintenance window:

1. Sahkan system owner, semua consumer dan privilege minimum.
2. Cipta credential/token baharu tanpa mematikan nilai lama.
3. Simpan nilai baharu dalam secret manager atau environment UAT. Jangan masukkan dalam source atau command history.
4. Restart/reload PHP-FPM atau service berkaitan jika environment berubah.
5. Jalankan smoke test khusus consumer.
6. Pantau application, DB, SMTP dan integration log tanpa mencetak credential.
7. Revoke credential lama hanya selepas semua consumer disahkan.
8. Uji semula selepas revocation untuk membuktikan tiada consumer tersembunyi.
9. Rekod tarikh, change ticket, owner dan expiry seterusnya—bukan nilai secret.

Urutan dicadangkan:

1. Diagnostic tokens kerana consumer sedikit.
2. SMTP OTP.
3. IDMS dan SKP.
4. Student/staff ODBC.
5. MySQL diagnostic.
6. MySQL utama OneID selepas rollback DB diuji.

## 8. Manual Smoke Test yang Masih Diperlukan

- [ ] Login staf.
- [ ] Login pelajar.
- [ ] Dashboard pengguna dan admin.
- [ ] Forgot-password dan penghantaran OTP SMTP.
- [ ] Lookup staf.
- [ ] Lookup dan sync pelajar.
- [ ] SKP API.
- [ ] IDMS API.
- [ ] Kedua-dua diagnostic agent menggunakan token baharu selepas rotation.
- [ ] Upload application icon.
- [ ] Scheduled sync dan penulisan log.
- [ ] Reboot/restart PHP-FPM dan pastikan secret store masih boleh dibaca.

## 9. Rollback

### 9.1 Jika secret store gagal dibaca

1. Jangan pulihkan hardcoded secret ke source.
2. Semak owner/mode fail dan pastikan PHP-FPM berjalan sebagai `iqs`.
3. Pastikan `/var/www/app/.oneid-uat-secrets.php` wujud, `600`, dan memulangkan PHP array.
4. Jika menggunakan lokasi lain, set `ONEID_SECRETS_FILE` kepada absolute path yang betul.
5. Gunakan environment variable untuk key terjejas sebagai rollback sementara.
6. Reload PHP-FPM dan jalankan smoke test semula.

### 9.2 Jika rotation baharu gagal

1. Aktifkan semula credential lama hanya jika belum direvoke dan change owner meluluskan rollback.
2. Pulihkan key terjejas dalam secret manager/store—bukan dalam repository.
3. Reload consumer.
4. Sahkan servis pulih.
5. Analisis kegagalan sebelum menjadualkan rotation semula.

### 9.3 Jika permission mengganggu operasi

Jangan rollback global kepada `777`.

- Jika upload gagal, sahkan path sebenar dan beri write hanya pada direktori upload yang diperlukan, lazimnya `750`/`770` dengan owner/group khusus.
- Jika cron log gagal, beri write hanya pada fail/direktori log berkaitan.
- Jika aset tidak boleh dibaca, gunakan `644` untuk fail dan `755` untuk parent directory.
- Jika PHP tidak boleh dibaca, semak runtime user/group sebelum melonggarkan permission.

## 10. Checksum Selepas Fasa 3

| Fail | SHA-256 |
|---|---|
| `lib/secrets.php` | `59e2b8a669479c5db6e7ad4f1655acf0749467db7c942d7864e282d00c1e85d5` |
| `lib/config.php` | `a1500726a7d5e651d3106c32ef74ebcd21d56e6e5eb09f7853aa81252098041d` |
| `lib/q_func.php` | `08dd3c193e02e94096038f86d90c8bebea39f2ae69e24b275e0a1c5c660be7b4` |
| `lib/q_func_old.php` | `5711d97d15cbe7460a42b2c1e6194945dafff05b5fabc32d86a9d49598791426` |
| `lib/external_data_source_API.php` | `bb5d5a52428a975fe48243f76f063d9d76be991098d59362f7cc353844bbefd6` |
| `lib/skp_api.php` | `a431e23014552f01a1f024907bbe45c5bb784bd94c3541e9a49c9e2cd85c96ca` |
| `skp_api.php` | `2e067d28c837faede33da3eac5d1d9ba55ff5abb36ecc6ef06240d8a6d53a64b` |
| `idms.php` | `a18368213b33cac039f822f85182282555649f17b52b64ebd01797c99c8008bb` |
| `diag/agent.php` | `af96246df58a5cda34d6bf17702678e64c0b3d81d8ab39029815aba0cc6be5b1` |
| `diagnostic/index.php` | `2d94aa16d453ead9f6574c02524f2442b8e02c2280df973642ecbcb562e2eff7` |
| `atest.php` | `6840dbf212c4cfc7485a82edabf0de1fafddbf63ed6f0d49535a89b1442eac03` |

Secret store runtime tidak diberi checksum dalam dokumen kerana checksum boleh digunakan untuk mengesahkan tekaan kandungan dan ia akan berubah semasa rotation.

## 11. Exit Criteria

- [x] Semua secret dan consumer utama diinventori.
- [x] Hardcoded secret dikeluarkan daripada PHP working tree.
- [x] Secret store berada di luar document root dan mode `600`.
- [x] Environment override tersedia.
- [x] Template tanpa nilai sebenar tersedia.
- [x] Tiada world-writable file/directory dalam aplikasi.
- [x] Upload dan cron log kekal owner-writable.
- [x] PHP lint dan root HTTP smoke test lulus.
- [x] Perubahan, runbook dan rollback didokumentasikan.
- [ ] Semua credential/token telah dirotasi.
- [ ] Credential/token lama telah direvoke.
- [ ] Semua integration smoke test selesai.
- [ ] Runtime OS user dipisahkan daripada deployment owner.

## 12. Keputusan

Bahagian implementasi tempatan Fasa 3 selesai, tetapi Fasa 3 keseluruhan masih **partial** sehingga rotation, revocation dan integration smoke test dilaksanakan bersama system owner.
