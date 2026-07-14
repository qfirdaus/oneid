# Fasa 6A — Asas Keselamatan Integrasi, Inventori dan Rollback

**Tarikh pelaksanaan:** 13 Julai 2026  
**Status:** Dilaksanakan dalam mod `observe`; belum enforce secara global.  
**Skop:** API guard per consumer, audit, scope, rate-limit foundation, ODBC query hardening dan TLS verification.

## 1. Keputusan Pelaksanaan

Fasa 6 dipecahkan kerana OneID mempunyai hampir 30 consumer merentas in-house dan vendor. Perubahan protokol serentak boleh menyebabkan outage yang sukar dirollback.

Fasa 6A memasang kawalan yang boleh hidup bersama integrasi lama:

- consumer legacy masih dibenarkan ketika mode `observe`;
- setiap panggilan direkod tanpa token, payload atau data peribadi;
- client credential baharu boleh diuji dengan endpoint dan scope tertentu;
- mode `enforce` tersedia tetapi **belum diaktifkan**;
- `new_sso_cre` belum dibuang dalam fasa ini.

## 2. Fail Diubah

| Fail | Perubahan |
|---|---|
| `lib/integration_security.php` | Guard integrasi, credential SHA-256, scope/endpoint/IP checks, request ID, audit dan fixed-window rate limit |
| `api.php` | Guard scope `sso:validate`, JSON validation dan security headers |
| `idms.php` | Guard `idms:read`, limit maksimum 100, ODBC prepared statement dan error sanitization |
| `skp_api.php` | Guard berasingan `skp:profile`/`skp:sync`, validasi chunk dan prepared lookup |
| `lib/external_data_source_API.php` | Prepared statement bagi carian staff/pelajar spesifik |
| `lib/skp_api.php` | Prepared statement bagi carian staff/pelajar spesifik |
| `lib/SSO_IDP_INC.php` | TLS peer dan hostname verification diaktifkan |
| `docs/examples/oneid-secrets.example.php` | Template konfigurasi Fasa 6A |
| `docs/FASA_6_INVENTORI_CONSUMER.md` | Snapshot 34 aplikasi dan borang maklumat onboarding |

Tiada schema atau data database diubah dalam Fasa 6A.

## 3. Mod Operasi

### `observe` — keadaan semasa

- request tanpa credential terus diproses;
- credential salah juga tidak menyebabkan outage, tetapi direkod sebagai `invalid_secret`, `unknown_client` atau sebab berkaitan;
- rate-limit berlebihan direkod tetapi tidak diblok;
- sesuai untuk baseline sekurang-kurangnya 30 hari dan onboarding consumer.

### `enforce` — belum diaktifkan

- missing/invalid credential menerima `401`;
- client disabled, scope, endpoint atau IP yang salah menerima `403`;
- melebihi rate limit menerima `429`;
- hanya diaktifkan selepas semua client bagi endpoint tersebut bersedia. Implementasi semasa menggunakan satu mode global, maka activation production masih menunggu kawalan mode per-client/per-endpoint dalam Fasa 6B.

## 4. Format Credential

Header request baharu:

```http
X-OneID-Client-ID: nama-client-unik
Authorization: Bearer <secret-raw-yang-hanya-diberi-sekali>
```

Contoh `ONEID_API_CLIENTS_JSON` dalam secret store:

```json
{
  "oneid-internal-uat": {
    "secret_hash": "<64-aksara-sha256>",
    "status": "active",
    "endpoints": ["sso_token"],
    "scopes": ["sso:validate"],
    "ip_allowlist": ["127.0.0.1"]
  },
  "vendor-skp-uat": {
    "secret_hash": "<64-aksara-sha256-berbeza>",
    "status": "active",
    "endpoints": ["skp"],
    "scopes": ["skp:profile"],
    "ip_allowlist": []
  }
}
```

Setiap consumer mesti menerima secret berbeza. Secret mentah tidak disimpan dalam repository atau JSON; hanya hash SHA-256 disimpan. `ip_allowlist` ialah lapisan tambahan, bukan pengganti credential.

## 5. Audit Event

Audit dihantar ke PHP/web error log sebagai JSON dengan medan:

- `component=oneid_integration`;
- `event`, `request_id`, `ip`, `endpoint`, `scope`;
- `client_id`, `auth_result` dan `mode`.

Audit tidak mengandungi bearer secret, token SSO, request body, kata kunci carian atau response data. Log perlu dihantar ke centralized logging dalam Fasa 6B kerana local log sahaja tidak mencukupi untuk retention dan alerting.

## 6. SQL dan Data

Penambahbaikan yang selesai:

- filter `idms.php` menggunakan placeholder ODBC;
- limit IDMS dicast dan dihadkan kepada 100;
- carian `nokp` SKP/staff/student menggunakan placeholder;
- raw SQL dan `odbc_errormsg()` tidak lagi dipulangkan oleh laluan IDMS yang diubah;
- indeks chunk SKP divalidasi sebelum digunakan.

Perkara yang sengaja belum diubah kerana memerlukan persetujuan consumer:

- `api.php` masih memulangkan user packet legacy `data1..data12`;
- full sync SKP masih mengandungi data sangat sensitif termasuk IC, alamat, telefon dan keluarga;
- field minimum setiap consumer belum disahkan;
- full sync belum dipecah kepada profile/scope yang lebih granular pada response level.

## 7. TLS

Panggilan aktif `lib/SSO_IDP_INC.php` kini menggunakan:

- `CURLOPT_SSL_VERIFYHOST=2`;
- `CURLOPT_SSL_VERIFYPEER=true`.

Ujian `https://oneid.local/` berjaya dengan HTTP 200 dan `ssl_verify_result=0`. Tetapan disable verification pada helper sample localhost turut dibuang. Fail legacy yang telah dikuarantin tidak diubah dalam fasa ini.

## 8. Ujian Dilaksanakan

| Ujian | Keputusan |
|---|---|
| PHP lint first-party aktif | Lulus, 44 fail |
| Legacy call `api.php` tanpa client credential dalam mode observe | Lulus; invalid test token diproses sebagai token tidak sah |
| `idms.php` tanpa query | Lulus; response terkawal dan audit direkod |
| Integration audit event | Lulus; event JSON muncul dalam web error log |
| TLS OneID UAT | Lulus; HTTP 200, certificate verify result 0 |
| Disable TLS verification dalam fail aktif Fasa 6A | 0 occurrence |
| World-writable dalam repository | 0 |
| Runtime ODBC query terhadap data sebenar | Belum dibuat; PHP CLI host tidak memuatkan extension ODBC dan ujian perlu dilakukan melalui UAT dengan owner data |
| Unit test credential valid dalam mode enforce | Lulus; client, endpoint, scope dan IP yang betul authenticated |
| Unit test scope tidak dibenarkan | Lulus; request ditolak dengan `integration_access_denied` |
| End-to-end endpoint production dalam mode enforce | Belum dibuat; sengaja menunggu client dalaman didaftarkan supaya tiada outage |

## 9. Checksum Selepas Pelaksanaan

| Fail | SHA-256 |
|---|---|
| `lib/integration_security.php` | `afd89b6db6738e88865c44967b5508508fd1ecb08be6e4867bc570e23f64aaed` |
| `api.php` | `e604203383169a9e335a357de440d415163b819ad80e2758db7bef63b0918d78` |
| `idms.php` | `b964001b15d97bef47d2b1f8257a428c31b709378d7a5d67f85e80d3c0bb07d2` |
| `skp_api.php` | `143d44ac6fed163657a7cfe031adfd22727cd9e91bbd2a8bed6e14143f42013e` |
| `lib/external_data_source_API.php` | `025a6b4c83112e7b14b36352eb263a9137b47799fe409e650c8593a79504437e` |
| `lib/skp_api.php` | `816fe0346a52839fbcd4c1c32ea6db2b35b0b8f45883a0f522dfe37761460399` |
| `lib/SSO_IDP_INC.php` | `032d58030905401eb58909deecbb4607eabe669f90eb865bafb73aeb27e1893b` |
| `docs/examples/oneid-secrets.example.php` | `22fcf5e7672abed1aa0730cc0211c0c89b069b77492daf259b06f1b5792ba219` |

Checksum dokumen pelaksanaan ini sendiri tidak direkod kerana nilainya berubah apabila checksum dimasukkan.

## 10. Langkah Activation Selamat

1. Kekalkan `ONEID_API_AUTH_MODE=observe`.
2. Lengkapkan owner dan endpoint bagi semua 34 rekod dalam inventori.
3. Daftarkan `oneid-internal-uat` dahulu dan ubah internal cURL untuk menghantar credential.
4. Pilih 2–3 pilot in-house dan beri credential unik.
5. Pantau sehingga setiap panggilan pilot menunjukkan `auth_result=authenticated`.
6. Tambah mode per-client/per-endpoint dalam Fasa 6B.
7. Enforce hanya consumer yang lulus UAT; consumer lain kekal legacy bertarikh.

## 11. Rollback

### Rollback konfigurasi segera

Jika enforcement menyebabkan gangguan, tetapkan semula:

```php
'ONEID_API_AUTH_MODE' => 'observe',
```

Ini memulihkan compatibility request tanpa membuang credential atau audit.

### Rollback TLS sementara

Jangan disable certificate verification sebagai penyelesaian kekal. Jika CA failure berlaku:

1. semak expiry, hostname dan CA chain;
2. pulihkan CA bundle/server chain;
3. hanya rollback code melalui change approval jika operasi kritikal terhenti;
4. catat exception dan expiry date.

### Rollback code

Rollback hanya fail Fasa 6A yang disenaraikan dalam Seksyen 2. Jangan rollback `lib/secrets.php`, hardening authentication Fasa 5 atau migration database terdahulu.

## 12. Baki dan Gate Fasa 6A

Fasa 6A dianggap **implemented in observe mode**, bukan complete sepenuhnya. Gate sebelum Fasa 6B enforcement:

- [ ] owner dan contact disahkan bagi semua consumer;
- [ ] sekurang-kurangnya 30 hari telemetry disemak;
- [ ] client dalaman mempunyai credential sendiri;
- [ ] 2–3 pilot in-house lulus UAT;
- [ ] field data minimum dipersetujui owner data;
- [ ] centralized log, alert dan retention disediakan;
- [ ] rollback contact dan change window disahkan.

## 13. Fasa Seterusnya

Fasa seterusnya ialah **Fasa 6B — onboarding consumer dan dual-protocol migration**:

- mode `legacy/dual/new` per `sp_id` atau client, bukan global;
- credential dalaman dan pilot in-house;
- authorization code one-time sebagai pengganti token dalam `new_sso_cre`;
- integration pack vendor dan evidence UAT;
- data-minimized claims per scope;
- disable legacy satu consumer pada satu masa.

Fasa 7 cleanup besar hanya bermula selepas boundary integrasi Fasa 6 stabil.
