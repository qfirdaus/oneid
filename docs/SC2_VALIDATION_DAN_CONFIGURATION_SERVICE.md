# SC2 — Validation dan Authentication Policy Configuration Service

**Tarikh pelaksanaan:** 16 Julai 2026  
**Skop:** Backend read/update bagi Authentication & SSO Token Policy  
**Status:** IMPLEMENTED / LOCAL CONTRACT VERIFIED  
**Schema atau nilai live database:** Tidak diubah

## 1. Objektif

Fasa 2 memindahkan validation daripada andaian UI kepada service backend yang
menjadi sumber kebenaran. Endpoint tidak lagi menghantar input mentah terus ke
`Database::update_configuration()` dan tidak lagi memulangkan seluruh row
`sys_config` kepada browser.

## 2. Komponen

| Komponen | Fungsi |
|---|---|
| `app/Admin/SsoConfigurationService.php` | Validation, normalisasi, projection dan result contract |
| `app/Admin/SsoConfigurationException.php` | Failure code dan correlation ID selamat |
| `lib/q_func.php` | Wiring endpoint dan failure projection |
| `admin/dashboard.php` | Menggunakan response contract SC2 |
| `tools/sc2_sso_configuration_service_contract.php` | Behavioral contract tanpa live mutation |

Authorization POST/admin/CSRF kekal dikuatkuasakan oleh
`lib/request_security.php` sebelum service dipanggil.

## 3. Validation Contract

### 3.1 Token lifetime

Hanya nilai string ternormalisasi berikut diterima:

```text
0.5, 1, 2, 12, 24, 48, 72, 168
```

Nilai negatif, terlalu besar, kosong, array atau nilai di luar whitelist ditolak
dengan `SC2_TOKEN_TIMEOUT_INVALID`.

### 3.2 Toggle

`multi_session` dan `email_OTP` hanya menerima scalar `0` atau `1`. Nilai seperti
`2`, `yes`, kosong atau array ditolak dengan code khusus:

- `SC2_MULTI_SESSION_INVALID`;
- `SC2_EMAIL_OTP_INVALID`.

### 3.3 Request shape

Update hanya menerima:

- action `update_configuration`;
- `token_timeout`;
- `sso_settings_multi_session`; dan
- `sso_settings_OTP_email`.

Field tambahan ditolak dengan `SC2_UNEXPECTED_FIELD`. Semua validation berlaku
sebelum persistence mutation.

## 4. Read Projection

Read response tidak lagi menggunakan raw `SELECT *` result sebagai response.
Service hanya memprojeksi:

```json
{
  "token_timeout": "0.5",
  "multi_session": 1,
  "email_OTP": 1
}
```

Jika medan baharu atau secret ditambah kepada `sys_config` pada masa hadapan, ia
tidak dihantar secara automatik kepada browser.

## 5. Response Contract

### Read berjaya

```json
{
  "status": 1,
  "code": "SC2_CONFIG_LOADED",
  "message": "Authentication and SSO token policy loaded.",
  "data": {
    "token_timeout": "0.5",
    "multi_session": 1,
    "email_OTP": 1
  },
  "correlation_id": "..."
}
```

### Update berjaya

Code ialah `SC2_CONFIG_UPDATED` dengan `changed: true`.

### Tiada perubahan

Code ialah `SC2_CONFIG_UNCHANGED` dengan `changed: false`.

### Ditolak atau gagal

```json
{
  "status": 0,
  "code": "SC2_...",
  "message": "Authentication policy was not updated.",
  "correlation_id": "..."
}
```

UI tidak lagi mentafsir sebarang nilai selain kejayaan yang dikenali sebagai
berjaya. Correlation ID dipaparkan untuk sokongan operasi tanpa mendedahkan
exception dalaman.

## 6. Failure Codes

| Code | Maksud |
|---|---|
| `SC2_TOKEN_TIMEOUT_INVALID` | Timeout bukan nilai whitelist |
| `SC2_MULTI_SESSION_INVALID` | Flag multiple token bukan `0/1` |
| `SC2_EMAIL_OTP_INVALID` | Flag e-mel OTP bukan `0/1` |
| `SC2_UNEXPECTED_FIELD` | Request mengandungi field tambahan |
| `SC2_CONFIG_NOT_FOUND` | Configuration row tidak ditemui |
| `SC2_STORED_MULTI_SESSION_INVALID` | Nilai database multiple token tidak sah |
| `SC2_STORED_EMAIL_OTP_INVALID` | Nilai database e-mel OTP tidak sah |
| `SC2_CONFIG_READ_FAILED` | Read gagal tanpa mendedahkan exception |
| `SC2_CONFIG_UPDATE_FAILED` | Update gagal tanpa mendedahkan exception |

## 7. Sempadan Fasa pada Masa Pelaksanaan

Pada masa SC2 dilaksanakan, fasa ini tidak mengubah:

- query `UPDATE sys_config` yang belum mempunyai targeted `WHERE`;
- schema/singleton constraint;
- audit trail perubahan;
- nilai konfigurasi live;
- token expiry dan legacy refresh window;
- revocation multiple token;
- Password Recovery behavior;
- PHP session timeout; atau
- role admin dan Admin Step-Up.

Targeted update, transaction dan audit kemudiannya dilaksanakan oleh SC3. Rujuk
`SC3_INTEGRITI_DATABASE_DAN_AUDIT_TRAIL.md` bagi keadaan semasa selepas
penambahbaikan tersebut.

## 8. Verification

Contract Fasa 2 menggunakan fake persistence dan tidak menyambung atau mengubah
database live. Ia mengesahkan:

- projection tiga medan sahaja;
- normalisasi input sah;
- `UPDATED` dan `UNCHANGED` dibezakan;
- timeout negatif dan luar whitelist ditolak;
- toggle selain `0/1` ditolak;
- field tambahan ditolak;
- semua input tidak sah ditolak sebelum mutation;
- row count lebih satu ditolak;
- endpoint menggunakan service dan correlation ID; dan
- UI menggunakan contract SC2.

Arahan verification:

```bash
php -l app/Admin/SsoConfigurationException.php
php -l app/Admin/SsoConfigurationService.php
php -l lib/q_func.php
php -l admin/dashboard.php
php tools/sc0_sso_configuration_contract.php
php tools/sc1_sso_configuration_ui_contract.php
php tools/sc2_sso_configuration_service_contract.php
git diff --check
```

## 9. UAT Manual

1. Buka halaman sebagai admin dan sahkan `SC2_CONFIG_LOADED` digunakan.
2. Simpan satu perubahan terkawal dan rekod correlation ID.
3. Simpan nilai sama dan sahkan `SC2_CONFIG_UNCHANGED`.
4. Hantar timeout di luar whitelist menggunakan request terkawal dan sahkan
   `SC2_TOKEN_TIMEOUT_INVALID` tanpa perubahan database.
5. Hantar toggle bukan `0/1` dan sahkan rejection.
6. Hantar field tambahan dan sahkan `SC2_UNEXPECTED_FIELD`.
7. Reload UI dan sahkan nilai live masih konsisten.
8. Pulihkan baseline UAT jika ujian mengubah nilai:
   `token_timeout=0.5`, `multi_session=1`, `email_OTP=1`.

## 10. Rollback

Rollback aplikasi:

1. pulihkan dua action dalam `lib/q_func.php` kepada wiring sebelumnya;
2. pulihkan parser response Fasa 1 dalam `admin/dashboard.php`;
3. keluarkan dua class SC2 dan contract berkaitan; dan
4. jalankan contract SC0/SC1 yang sepadan dengan release rollback.

Tiada rollback schema diperlukan. Nilai live hanya perlu dipulihkan jika UAT
manual secara sengaja mengubahnya.

## 11. Keputusan

Validation server-side, explicit projection dan structured response contract
telah dilaksanakan. Fasa 2 menutup penerimaan nilai konfigurasi arbitrari melalui
endpoint admin tanpa memperluas skop kepada migration, audit atau token
lifecycle.
