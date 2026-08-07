# User Session Timeout F0 — Baseline dan Contract Lock

**Tarikh:** 7 Ogos 2026

**Environment:** Source/UAT workspace

**Status:** COMPLETED / STAGING VALIDATED

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
- [x] PHP 8.3 FPM, `session.gc_maxlifetime=1440` dan file-based session handler
  pada staging direkod;
- [ ] pemilik sistem meluluskan permulaan Fasa 1;
- [ ] mekanisme cleanup, effective save path dan topology web node disahkan.

## 8. Perkara Belum Disahkan pada Host UAT

Semakan source dan konfigurasi host masih belum membuktikan:

- sama ada deployment mempunyai lebih daripada satu web node;
- behavior sebenar setiap service provider selepas local session mereka terbina;
- header cache daripada reverse proxy/web server.

Semua item ini perlu disahkan secara read-only sebelum Fasa 1 mengubah polisi
PHP session.

## 9. Bukti Konfigurasi Web Staging

Semakan read-only pada `AppsStagingv1` pada 7 Ogos 2026 mengesahkan:

```text
PHP version:              8.3.33
Web runtime:              PHP 8.3 FPM, pool www
FPM service:              php8.3-fpm.service active/running
session.save_handler:     files
session.gc_maxlifetime:   1440 saat (24 minit)
session.gc_probability:   0
session.gc_divisor:       1000
session.save_path:        /var/lib/php/sessions
session cleanup:          systemd phpsessionclean setiap 30 minit (:09/:39)
project-level override:   tiada ditemui
session directory:        /var/lib/php/sessions
session directory mode:   1733 (drwx-wx-wt), root:root
token_timeout UAT:        0.5 jam (30 minit)
PHP-FPM socket:            /run/php/php8.3-fpm.sock
FPM pool scope:            shared by OneID and multiple other applications
session storage usage:     1 file / 8 KiB
filesystem capacity:       85 GiB available (24% used)
```

FPM SAPI mengesahkan path efektif `/var/lib/php/sessions`. Konfigurasi
`/etc/php/8.3/fpm/php.ini` menetapkan `session.gc_maxlifetime = 1440`,
`session.gc_probability = 0` dan file handler. Tiada override aktif ditemui
dalam FPM pool, Apache, Nginx atau source OneID bagi arahan session yang diaudit.

Oleh sebab probabiliti GC dalam request ialah sifar, cleanup dilaksanakan oleh
`phpsessionclean.timer`. Timer aktif sejak 28 Julai 2026 dan menjalankan
`/usr/lib/php/sessionclean` setiap 30 minit pada minit `:09` dan `:39`. Run yang
disemak pada 7 Ogos 2026 selesai dengan status berjaya.

### 9.1 Risiko yang Disahkan

Nilai garbage-collection 24 minit lebih pendek daripada setting Administrator
30 minit. Oleh sebab storage menggunakan fail dan cleanup berjalan setiap 30
minit, session tanpa file access boleh menjadi layak selepas 24 minit dan
dibersihkan pada run timer berikutnya. Secara operasi, deletion boleh berlaku
kira-kira 24 hingga 54 minit selepas file session kali terakhir disentuh.

Heartbeat dashboard yang membuka PHP session setiap lima minit mungkin mengubah
mtime fail dan menyembunyikan risiko semasa tab terus berjalan. Ia bukan jaminan:
browser sleep, tab ditutup, request gagal atau halaman authenticated tanpa
heartbeat masih boleh kehilangan fail session sebelum lifecycle aplikasi yang
dirancang selesai. Fasa 1 tidak boleh bergantung pada heartbeat untuk retention.

Fasa 1 tidak boleh menganggap aplikasi sahaja mampu menguatkuasakan timeout.
Konfigurasi storage PHP mesti menyimpan session sekurang-kurangnya sehingga
absolute deadline yang diluluskan. Dengan absolute cap semasa lapan jam, nilai
minimum yang dicadangkan untuk `session.gc_maxlifetime` ialah `28800` saat,
tertakluk kepada semakan mekanisme `phpsessionclean` dan semua web node.

### 9.2 Gate Baki

Sebelum perubahan konfigurasi diluluskan:

- [x] sahkan nilai efektif FPM bagi `session.save_path`, `gc_probability` dan
  `gc_divisor`;
- [x] semak timer/service `phpsessionclean` tanpa menjalankan cleanup manual;
- [x] rekod host pelaksanaan `AppsStagingv1` dan shared local FPM/session storage;
- [x] sediakan dan validate perubahan global FPM staging serta rollback
  `gc_maxlifetime` yang diterima pemilik sistem;
- [x] kekalkan absolute cap lapan jam semasa sebagai retention minimum Fasa 1.

### 9.3 Shared FPM Impact dan Keputusan Pemilik Sistem

Nginx active configuration menunjukkan OneID berkongsi socket
`/run/php/php8.3-fpm.sock` dengan sekurang-kurangnya aplikasi berikut:

- APEL;
- e-Facility;
- e-HEPA;
- e-PMS;
- e-Prestasi;
- e-PR;
- IQS Framework;
- MyMOHES;
- SAP UAT;
- SPK UAT;
- Survey UAT.

Perubahan global pada `/etc/php/8.3/fpm/php.ini` akan memanjangkan retention fail
session bagi semua aplikasi yang menggunakan pool tersebut. Pada 7 Ogos 2026,
pemilik sistem menerima perubahan global untuk staging berdasarkan keadaan
berikut:

1. staging ialah environment testing terkawal;
2. semua sistem berkaitan mempunyai timeout aplikasi masing-masing;
3. perubahan `gc_maxlifetime` hanya menetapkan kelayakan cleanup fail dan tidak
   menggantikan enforcement timeout pada aplikasi tersebut;
4. kapasiti storage semasa mencukupi;
5. smoke-test sistem berkongsi FPM dan rollback konfigurasi diwajibkan;
6. production OneID mempunyai server khas dan tidak berkongsi FPM dengan sistem
   lain.

Keputusan pelaksanaan ialah menaikkan `session.gc_maxlifetime` global FPM staging
daripada `1440` kepada `28800` saat. `session.gc_probability` kekal `0` dan
`phpsessionclean.timer` kekal sebagai mekanisme cleanup. Perubahan tidak boleh
mengubah `session.save_path`, socket FPM, Nginx vhost atau source aplikasi lain.

Sebelum activation, salinan konfigurasi bertarikh, `php-fpm8.3 -t`, graceful
reload, effective-value verification dan smoke-test mesti dibuat. Rollback ialah
memulihkan nilai `1440`, mengesahkan konfigurasi dan melakukan graceful reload.

### 9.4 Activation Staging

Perubahan global FPM staging diaktifkan pada 7 Ogos 2026:

```text
php-fpm8.3 configuration test: successful
reload method:                  systemctl reload php8.3-fpm
effective gc_maxlifetime:       28800 => 28800
FPM service after reload:       active/running
FPM reload errors:              none observed
Nginx error output:             none observed in inspected tail
OneID public endpoint:          HTTP 200
IQS Framework endpoint:         HTTP 200
e-Facility endpoint:            HTTP 200
planned pre-change backup:      missing
controlled rollback artifact:   php.ini.rollback-oneid-session-20260807
rollback difference:            gc_maxlifetime 28800 -> 1440 only
rollback configuration test:    successful
```

Activation tidak mengubah Nginx, socket FPM, save path, source atau database.
Service/log dan unauthenticated HTTP smoke-test aplikasi berkongsi pool lulus.
Fail backup bernama `php.ini.before-oneid-session-20260807` tidak diwujudkan
sebelum activation. Sebagai kawalan gantian, artefak rollback
`php.ini.rollback-oneid-session-20260807` dibina daripada konfigurasi aktif dan
hanya mengembalikan `gc_maxlifetime` kepada `1440`. Perbandingan mengesahkan
hanya satu baris berbeza dan konfigurasi rollback lulus `php-fpm8.3 -t`.

### 9.5 Authenticated Smoke-Test

Pemilik sistem melaksanakan smoke-test browser selepas graceful reload. Keputusan:

```text
Login OneID:          PASS
Dashboard user:       PASS
Administrator:        PASS
Launch aplikasi SSO:  PASS
Sistem UAT lain:      PASS
```

Tiada regression dilaporkan pada OneID, akses Administrator, pelancaran SSO atau
sistem UAT lain. Dengan contract, konfigurasi, rollback dan smoke-test lengkap,
Fasa 0 ditutup dan staging bersedia untuk perubahan aplikasi Fasa 1.
