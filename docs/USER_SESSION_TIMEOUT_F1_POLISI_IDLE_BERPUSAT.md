# User Session Timeout F1 — Polisi Idle Berpusat

**Tarikh:** 7 Ogos 2026

**Environment sasaran:** Staging/UAT

**Status:** IMPLEMENTED / PENDING STAGING ACTIVATION

**Dokumen induk:** `USER_SESSION_TIMEOUT_SWEETALERT_REKA_BENTUK_DAN_PELAN.md`

## 1. Objektif

Fasa 1 menggantikan hard-coded idle timeout PHP 30 minit dengan nilai
`sys_config.token_timeout` yang ditetapkan Administrator. Fasa ini tidak
menambah popup, endpoint status/renew baharu atau perubahan frontend.

## 2. Behavior

| Setting Administrator | PHP idle timeout | Absolute cap efektif |
|---:|---:|---:|
| 30 minit (`0.5`) | 1,800 saat | 8 jam |
| 1 jam (`1`) | 3,600 saat | 8 jam |
| 2 jam (`2`) | 7,200 saat | 8 jam |
| 12 jam dan lebih | ikut nilai setting | 8 jam |

Absolute cap lapan jam kekal. Oleh itu, setting idle yang lebih panjang tidak
boleh memanjangkan authenticated PHP session melepasi lapan jam.

## 3. Bootstrap dan Enforcement Boundary

Urutan request ialah:

```text
session_start
  -> preserve created_at dan last_activity asal
  -> load config dan Database
  -> baca sys_config.token_timeout
  -> semak idle + absolute expiry
  -> jika valid: update activity hanya bagi request bermakna
  -> jika expired: clear authenticated session + rotate session ID
  -> teruskan guard/SSO/page action
```

Timestamp tidak dikemas kini ketika `session_start`. Ini penting supaya request
yang sudah expired tidak menjadi valid semula sebelum setting Administrator
sempat diperiksa.

`lib/config.php` ialah shared enforcement boundary. Selepas `Database` tersedia,
ia memanggil polisi session jika PHP session sedang aktif. CLI yang menggunakan
`ONEID_CONFIG_SKIP_DATABASE` tidak menjalankan enforcement web tersebut.

## 4. Validasi Setting dan Fallback

Nilai yang diterima sama dengan pilihan Administrator sedia ada:

```text
0.5, 1, 2, 12, 24, 48, 72, 168 jam
```

Nilai missing, kosong, malformed atau tidak dibenarkan ditolak oleh policy.
Jika pembacaan/validasi konfigurasi gagal, aplikasi menggunakan fallback selamat
30 minit dan merekod kelas kegagalan dalam server log tanpa mendedahkan nilai
atau exception message kepada pengguna.

Fallback tidak digunakan apabila database sendiri gagal dibuka kerana bootstrap
aplikasi sedia ada kekal fail-closed dengan HTTP 500 generik.

## 5. Technical Heartbeat

Behavior sedia ada dikekalkan:

- `update_specific_token_datetime` tidak mengemas kini human idle activity;
- `admin_step_up_status` tidak mengemas kini human idle activity;
- explicit `admin_step_up_renew` ialah request bermakna dan mengemas kini base
  PHP idle activity sebelum grant Administrator diperbaharui;
- request user biasa mengemas kini activity hanya selepas session disahkan belum
  expired.

Fasa 1 belum mengubah reload-on-heartbeat-error pada dashboard; item tersebut
kekal dalam Fasa 4.

## 6. Perkara yang Tidak Berubah

- payload atau respons `api.php` service provider;
- format dan lifetime token SSO;
- `token_issued_at` dan legacy refresh;
- cookie SSO 30 minit sedia ada;
- manual logout dan token revocation;
- grant/lifetime `ADMIN_ACCESS`;
- setting multi-session;
- aplikasi atau service provider lain;
- SweetAlert user.

Idle expiry portal-only, kod respons khusus dan pembersihan cookie akan ditangani
dalam Fasa 2. Popup user hanya diperkenalkan dalam Fasa 3.

## 7. Fail Pelaksanaan

- `app/Auth/UserSessionTimeoutPolicy.php`
- `lib/session_security.php`
- `lib/config.php`
- `app/Auth/MyDigitalId/MyDigitalIdLoginEndpoint.php`
- `tests/characterization/user_session_timeout_f0_baseline.php`
- `tests/characterization/user_session_timeout_f1_policy.php`

MyDigital ID login endpoint memuatkan shared configuration boundary selepas
session dibuka supaya authenticated-session conflict turut menggunakan setting
Administrator semasa.

## 8. Characterization Contract

```bash
php tests/characterization/user_session_timeout_f1_policy.php
```

Contract meliputi:

- mapping 30 minit, 1 jam, 2 jam dan 1 minggu;
- rejection nilai tidak sah;
- exact idle boundaries;
- absolute cap lapan jam;
- setting reader dan fallback;
- meaningful request pada minit ke-33 bagi setting satu jam;
- technical heartbeat pada minit ke-33;
- expiry pada satu jam + satu saat;
- shared bootstrap wiring;
- kontrak API aplikasi lain tidak disentuh.

Keputusan source sebelum staging activation:

```text
RESULT checks=20 failed=0
```

## 9. UAT Fasa 1

### 9.1 Setting 30 Minit

- login dan rekod masa;
- request bermakna sebelum 30 minit menyegarkan idle timestamp;
- technical heartbeat sahaja tidak menyegarkan idle timestamp;
- selepas lebih 30 minit idle, request authenticated ditolak/redirect mengikut
  behavior Fasa 1 semasa;
- tiada popup dijangka.

### 9.2 Setting 1 Jam

- admin ubah setting melalui preview/audit flow sedia ada;
- login baharu dan session sedia ada membaca setting satu jam pada request
  berikutnya;
- session masih authenticated selepas lebih 30 minit tetapi kurang satu jam;
- selepas lebih satu jam idle, session tamat;
- pulihkan setting kepada 30 minit selepas ujian jika itu baseline operasi.

### 9.3 Regression

- login password dan MyDigital ID;
- dashboard user dan Administrator;
- admin Stay Connected;
- Account Security/MFA;
- manual logout;
- launch aplikasi SSO;
- aplikasi UAT lain tidak terjejas;
- tiada HTTP 500/502 atau reload loop baharu.

## 10. Rollback

Rollback source mengembalikan:

- enforcement hard-coded 30 minit dalam `oneid_start_secure_session`;
- mengeluarkan shared configured-policy call daripada `lib/config.php`;
- mengeluarkan policy class dan F1 contract;
- mengembalikan MyDigital ID login bootstrap asal.

Konfigurasi FPM `gc_maxlifetime=28800` boleh kekal kerana ia ialah retention
storage, bukan enforcement idle. Jika rollback host diperlukan secara berasingan,
gunakan artefak `/etc/php/8.3/fpm/php.ini.rollback-oneid-session-20260807` yang
telah disahkan dalam Fasa 0.

## 11. Gate Fasa 2

Fasa 2 hanya bermula apabila:

- [x] policy/source contracts lulus;
- [ ] Fasa 1 dipush dan diaktifkan pada staging;
- [ ] setting 30 minit disahkan;
- [ ] setting 1 jam disahkan melepasi sempadan 30 minit;
- [ ] admin hierarchy dan technical heartbeat lulus staging;
- [ ] regression authenticated/SSO lulus;
- [ ] rollback source disahkan.
