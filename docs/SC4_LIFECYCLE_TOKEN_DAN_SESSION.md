# SC4 — Lifecycle Token SSO dan Session

**Tarikh pelaksanaan:** 16 Julai 2026  
**Status:** IMPLEMENTED / CONSUMER PILOT PASS / COMPATIBILITY WINDOW RETAINED  
**Keputusan owner:** Absolute token lifetime; legacy refresh satu jam dikekalkan sementara

## 1. Masalah yang Ditemui

Sebelum SC4, `token_datetime` digunakan serentak sebagai masa token dikeluarkan
dan last activity. Dashboard mengemas kini medan itu setiap lima minit. Akibatnya,
setting yang dipanggil token timeout sebenarnya menjadi sliding lifetime bagi
pengguna aktif dan tidak boleh menguatkuasakan absolute lifetime.

Pengiraan lama turut membundarkan umur token kepada 0.1 jam, menyebabkan boundary
expiry kurang tepat.

## 2. Model Baharu

| Medan | Makna | Boleh berubah |
|---|---|---|
| `token_issued_at` | Masa issuance yang menjadi asas absolute lifetime | Tidak |
| `token_datetime` | Last activity heartbeat bagi compatibility/UI | Ya |

Token baharu menyimpan kedua-dua timestamp menggunakan `NOW()`. Heartbeat hanya
mengemas kini `token_datetime` dan tidak memanjangkan `token_issued_at`.

## 3. Migration

Migration UAT:

1. tambah nullable `token_issued_at`;
2. backfill token legacy daripada `token_datetime` semasa;
3. jadikan medan `NOT NULL`; dan
4. tambah index `idx_token_issued_at`.

Backfill 244,637 row selesai tanpa null. Menggunakan last-activity timestamp
semasa sebagai issuance transition memberi token legacy baseline selamat ketika
cutover; ia tidak cuba mereka semula issuance asal yang tidak pernah disimpan.

Artifak:

- `docs/migrations/20260716_sc4_token_issued_at_up.sql`;
- `docs/migrations/20260716_sc4_token_issued_at_down.sql`; dan
- `tools/sc4_token_issued_at_schema_migrate.php`.

## 4. State Machine Berpusat

`app/Auth/SsoTokenLifetimePolicy.php` menggunakan ketepatan saat:

| State | Syarat |
|---|---|
| `active` | Umur 0 hingga absolute lifetime, termasuk boundary tepat |
| `legacy_refresh` | Selepas absolute expiry tetapi kurang satu jam selepasnya |
| `expired` | Pada atau selepas akhir refresh window |
| `future_invalid` | Issuance timestamp lebih lewat daripada masa server |

Tiada hidden clock-skew allowance diperkenalkan. Timestamp masa hadapan ditolak
seperti behavior keselamatan lama, tetapi kini mempunyai state eksplisit.

## 5. Enforcement

### API consumer

`api.php` kini menggunakan `token_issued_at` dan state machine berpusat:

- `active`: contract validation biasa dikekalkan;
- `legacy_refresh`: token lama dibatalkan dan token baharu dikeluarkan menggunakan
  `respond_flag=2` seperti contract legacy;
- `expired`: token ditolak; dan
- `future_invalid`: token ditolak.

### Active-session listing

Senarai sesi user dan admin menggunakan absolute issuance. Token selain `active`
ditandakan tidak aktif dan tidak dipaparkan. Senarai sesi tidak menggunakan
compatibility refresh kerana refresh hanya berlaku melalui validation API.

### PHP session

PHP session tidak berubah:

- idle timeout 30 minit; dan
- absolute timeout 8 jam.

Ia kekal berasingan daripada absolute lifetime token SSO.

## 6. Compatibility dan Risiko

Legacy refresh satu jam sengaja dikekalkan berdasarkan keputusan owner. Ia kini
konstan dan boleh diuji melalui `LEGACY_REFRESH_SECONDS=3600`, bukan syarat
aritmetik tersembunyi.

Penamatan refresh masih memerlukan:

- registry owner setiap consumer;
- contract/UAT consumer;
- pilot terkawal;
- monitoring `respond_flag=2`; dan
- rollback contact.

## 7. Verification

Contract SC4 mengesahkan 11 perkara:

- satu saat sebelum expiry aktif;
- boundary expiry tepat masih aktif;
- satu saat selepas expiry memasuki refresh;
- satu saat sebelum refresh tamat masih refreshable;
- boundary akhir refresh telah expired;
- timestamp masa hadapan ditolak;
- token baharu menyimpan issuance dan activity;
- heartbeat tidak mengubah issuance;
- API menggunakan policy berpusat;
- kedua-dua senarai sesi menggunakan issuance; dan
- live backfill tiada null serta index tersedia.

Arahan:

```bash
php tools/sc4_token_issued_at_schema_migrate.php --check
php tools/sc4_token_lifetime_contract.php
```

Semua contract SC0–SC3 turut perlu kekal lulus.

## 8. UAT Consumer Pilot

Consumer pilot: `BTOG4WZNQP` — IQS-Framework.

### 8.1 Controlled refresh pilot

Runner `tools/sc4_consumer_refresh_pilot.php` menggunakan akaun ujian
`S1TEST-20260714` yang mempunyai direct allow kepada IQS-Framework:

- token khusus dicipta dan diletakkan 31 minit selepas issuance;
- `POST /api.php` memberi HTTP 200;
- response menggunakan `respond_flag=2`, `respond=1` dan token baharu;
- token baharu berjaya divalidasi sebagai aktif untuk `BTOG4WZNQP`; dan
- semua token khusus ujian dipadam selepas selesai.

Keputusan: 6/6 PASS. Token mentah tidak dicetak atau disimpan dalam dokumen.

### 8.2 Browser login, return dan logout

Owner menjalankan private browser UAT pada kira-kira 11:30, 16 Julai 2026:

| Gate | Evidence | Keputusan |
|---|---|---|
| OneID entry | `GET /?site_id=BTOG4WZNQP` pada 11:29:36, HTTP 200 | PASS |
| Authentication | `POST /lib/q_func` pada 11:29:43, HTTP 200 | PASS |
| Audit | `SSO_LOGIN_SUCCESS`, audit ID 306443 pada 11:29:43 | PASS |
| Consumer return | Browser tiba di `https://iqs-framework.upnm.edu.my/index.php` | PASS |
| Redirect loop | Owner mengesahkan tiada loop | PASS |
| Logout | Consumer kembali ke halaman login | PASS |
| OneID 5xx | Tiada 5xx dalam tetingkap 11:29–11:32 | PASS |

Consumer menggunakan hostname luaran `iqs-framework.upnm.edu.my`; access/error
log consumer tersebut tidak berada pada host OneID ini. Keputusan consumer page
dan logout ialah owner-observed evidence, disokong oleh HTTP/audit OneID. Ini
direkod sebagai batas observability, bukan kegagalan pilot.

### 8.3 Regression selepas pilot

- OneID restructure smoke: 10/10 PASS;
- IQS public/login landing: HTTP 200;
- absolute/refresh boundary contract: 11/11 PASS; dan
- controlled pilot token cleanup: PASS.

Ujian boundary tidak mengubah timestamp token pengguna sebenar; ia menggunakan
token khusus akaun pilot dan melakukan cleanup dalam `finally`.

## 9. Rollback

1. pulihkan `api.php`, `lib/q_func.php` dan `lib/Database.php` kepada release SC3;
2. hentikan code yang membaca `token_issued_at`;
3. DBA jalankan migration down;
4. sahkan `token_datetime` heartbeat kembali menjadi sumber lama; dan
5. jalankan contract release rollback.

Backup/restore kekal diurus DBA dalam environment lain.

## 10. Keputusan

Token lifetime kini benar-benar absolute dan tidak lagi dipanjangkan oleh
heartbeat. Legacy refresh satu jam kekal untuk compatibility tetapi telah
dipusatkan, dinamakan dan dilindungi oleh boundary contract. Controlled refresh
serta browser login/return/logout IQS-Framework telah lulus tanpa loop atau 5xx
OneID.
