# User Session Timeout F0 — Baseline dan Contract Lock

**Tarikh:** 7 Ogos 2026

**Environment:** Source/UAT workspace

**Status:** IMPLEMENTED / READ-ONLY RUNTIME

**Dokumen induk:** `USER_SESSION_TIMEOUT_SWEETALERT_REKA_BENTUK_DAN_PELAN.md`

## 1. Objektif Fasa 0

Fasa ini mengunci behavior session, token, logout dan integrasi sebelum perubahan
runtime dibuat. Ia tidak mengubah database, konfigurasi, PHP session behavior,
token, cookie, endpoint atau UI.

Artefak pelaksanaan Fasa 0 ialah:

- characterization contract
  `tests/characterization/user_session_timeout_f0_baseline.php`;
- rekod baseline ini;
- smoke-test, rollback dan gate untuk memasuki Fasa 1.

## 2. Baseline yang Dikunci

### 2.1 PHP Session

- idle timeout semasa ialah lebih 1,800 saat;
- absolute timeout semasa ialah lebih 28,800 saat;
- kedua-dua nilai masih hard-coded;
- `update_specific_token_datetime` tidak mengemas kini human idle activity;
- `admin_step_up_status` juga tidak mengemas kini human idle activity;
- request bermakna, termasuk explicit admin renewal, mengemas kini activity
  melalui lifecycle session semasa.

### 2.2 Dashboard User

- token heartbeat berjalan setiap 300,000 ms;
- kegagalan heartbeat kini memanggil `location.reload(true)`;
- SweetAlert library tersedia;
- controller `oneid-user-session.js` belum wujud atau dimuatkan;
- dashboard user tidak memuatkan controller session admin.

### 2.3 Token dan Aplikasi Lain

- `api.php` menerima kontrak flag `1` untuk token validation;
- medan respons legacy `respond_flag`, `respond` dan `respond_user_packet`
  dikekalkan;
- lifetime token menggunakan `sys_config.token_timeout` dan `token_issued_at`;
- legacy refresh window masih aktif;
- heartbeat mengubah `token_datetime`, bukan `token_issued_at`;
- tiada format token atau kontrak service provider diubah dalam Fasa 0.

### 2.4 Idle Expiry dan Logout

- idle PHP expiry mengosongkan session tetapi tidak revoke token database;
- idle PHP expiry semasa tidak membersihkan cookie dalam blok expiry tersebut;
- valid SSO flow boleh membina authenticated session daripada token;
- cookie SSO OneID semasa mempunyai lifetime tetap 1,800 saat;
- manual logout revoke token, membersihkan cookie dan memusnahkan PHP session.

### 2.5 Hierarchy Administrator

- admin ialah grant `ADMIN_ACCESS` di atas authenticated PHP session;
- status polling admin berasingan daripada explicit renewal;
- explicit renewal mengganti grant admin lama dengan grant baharu;
- renewal grant admin tidak memanggil token revocation;
- perubahan Fasa 2 kelak mesti memastikan renewal admin juga menghormati baki
  idle dan absolute base session.

## 3. Characterization Contract

Jalankan:

```bash
php -l tests/characterization/user_session_timeout_f0_baseline.php
php tests/characterization/user_session_timeout_f0_baseline.php
```

Keputusan baseline pada 7 Ogos 2026:

```text
No syntax errors detected
RESULT checks=17 failed=0
```

Semakan read-only database turut mengesahkan token issuance backfill/index dan
kontrak lifetime:

```text
SC4 RESULT: checks=11 failures=0
AS1 CHECK: lifetime_hours=0.5 candidates=2 natural=2
AS1 RESULT: housekeeping=check_pass mutation_statements=0
```

Nilai `token_timeout` UAT yang dibaca ketika baseline ialah `0.5` jam (30 minit).
Semakan housekeeping tidak menjalankan mutation.

Contract ini sengaja akan memerlukan kemas kini terkawal pada Fasa 1–4 apabila
behavior lama diganti. Kegagalan test tidak boleh diselesaikan dengan membuang
assertion tanpa merekod behavior baharu dalam dokumen induk dan test pengganti.

## 4. Existing Regression Contracts

Suite minimum yang berkaitan:

```bash
php tests/characterization/as1_idle_heartbeat_policy.php
php tests/characterization/as2_revoked_token_enforcement.php
php tests/characterization/admin_access_session_renewal.php
php tools/sc4_token_lifetime_contract.php
php tools/r52_authenticated_logout.php
```

`tools/sc4_token_lifetime_contract.php` dan authenticated logout test boleh
memerlukan database/environment UAT. Kegagalan sambungan environment perlu
direkod berasingan daripada assertion source.

## 5. Smoke-Test Sebelum dan Selepas Setiap Fasa

### 5.1 Authentication dan Dashboard

- [ ] Login password berjaya.
- [ ] Login MyDigital ID berjaya jika feature aktif.
- [ ] Dashboard user memuatkan profil, aplikasi dan active sessions.
- [ ] User yang tidak authenticated diarahkan ke login.
- [ ] Akaun inactive tidak boleh meneruskan action authenticated.

### 5.2 Aplikasi SSO

- [ ] Lancarkan sekurang-kurangnya satu aplikasi SSO daripada dashboard.
- [ ] Aplikasi menerima token sedia ada tanpa perubahan payload.
- [ ] Validation `flag=1` masih memberikan kontrak respons sedia ada.
- [ ] Aplikasi yang sedang digunakan tidak terganggu oleh idle expiry portal
  OneID apabila fungsi tersebut diperkenalkan.
- [ ] Manual logout menyebabkan token ditolak pada validation berikutnya seperti
  behavior baseline.

### 5.3 Password dan MFA

- [ ] Forced password-change modal masih static dan boleh diselesaikan.
- [ ] Tukar password biasa masih berjaya.
- [ ] Reauthentication selepas perubahan password masih betul.
- [ ] Account Security boleh enroll, confirm atau revoke TOTP mengikut polisi.
- [ ] MFA SweetAlert tidak bertindih dengan session SweetAlert.

### 5.4 Administrator

- [ ] Administrator entry dan step-up masih berjaya.
- [ ] Popup admin muncul dua minit sebelum grant tamat.
- [ ] Stay Connected admin mengganti grant lama secara atomic.
- [ ] User-session popup tidak muncul serentak pada halaman admin.
- [ ] Logout admin masih melalui logout authenticated sedia ada.

### 5.5 Browser dan Failure Mode

- [ ] Dua tab OneID tidak menghasilkan keputusan session bercanggah.
- [ ] Kembali daripada background/sleep mendapatkan status server sebenar.
- [ ] Offline atau HTTP 5xx tidak dianggap sebagai session expired.
- [ ] Tiada reload loop.
- [ ] Browser Back tidak memaparkan data authenticated selepas logout.

## 6. Rollback Fasa 0

Fasa 0 tidak mempunyai runtime mutation. Rollback hanya melibatkan artefak baharu:

1. keluarkan characterization contract Fasa 0 daripada suite jika ia sendiri
   rosak atau salah merekod baseline;
2. pulihkan dokumen induk dan baseline melalui version control;
3. jalankan existing regression contracts untuk membuktikan tiada runtime file
   berubah.

Jangan gunakan rollback Fasa 0 untuk mengubah atau memadam behavior production.
Tiada migration database, feature flag atau cache bust diperlukan dalam fasa ini.

## 7. Gate Memasuki Fasa 1

Fasa 1 hanya boleh bermula apabila:

- [x] kontrak source aplikasi lain direkod;
- [x] idle expiry dan manual logout dibezakan;
- [x] heartbeat user/admin direkod;
- [x] hierarchy base session dan admin grant direkod;
- [x] smoke-test dan rollback tersedia;
- [x] characterization test Fasa 0 lulus;
- [x] setting `sys_config.token_timeout` UAT direkod sebagai `0.5` jam melalui
  semakan read-only;
- [ ] pemilik sistem meluluskan permulaan Fasa 1;
- [ ] nilai runtime web `session.gc_maxlifetime` dan session save handler UAT
  disahkan pada PHP SAPI yang melayan request sebenar.

## 8. Perkara Belum Disahkan pada Host UAT

Source sahaja tidak boleh membuktikan:

- nilai sebenar `session.gc_maxlifetime` pada PHP-FPM/Apache. PHP CLI melaporkan
  `1440` saat, tetapi nilai ini tidak boleh dianggap mewakili web SAPI;
- session save handler web dan permission storage. PHP CLI melaporkan handler
  `files` dengan path `/var/lib/php/sessions`;
- sama ada deployment mempunyai lebih daripada satu web node;
- behavior sebenar setiap service provider selepas local session mereka terbina;
- header cache daripada reverse proxy/web server.

Semua item ini perlu disahkan secara read-only sebelum Fasa 1 mengubah polisi
PHP session.
