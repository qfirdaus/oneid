# Audit Penutupan User Session Timeout — OneID v2.8.2

**Tarikh audit ditutup:** 8 Ogos 2026  
**Environment disahkan:** Staging/UAT  
**Release:** 2.8.2  
**Commit release:** `a282017`  
**Status:** PEMBETULAN UAT POPUP DIPERLUKAN / VALIDASI SEMULA STAGING PENDING

## 1. Objektif dan Keputusan

Task ini menyediakan amaran tamat sesi staf dan pelajar yang mengikuti setting
Administrator tanpa memerlukan perubahan pada aplikasi lain. Fasa 0 hingga 5
telah selesai. Kontrak API service provider, format token dan validation SSO
sedia ada dikekalkan.

## 2. Behavior Akhir yang Diaudit

| Keadaan | Behavior akhir |
|---|---|
| Setting Administrator 30 minit | Idle portal 30 minit; warning sekitar minit 28 |
| Setting Administrator 1 jam | Idle portal 1 jam; warning sekitar minit 58 |
| Aktiviti OneID bermakna | Idle activity dikemas kini dan deadline diselaraskan |
| Heartbeat/status polling | Technical sahaja; tidak memanjangkan idle session |
| Klik Stay Connected | Renew portal ikut setting semasa tanpa rotate/revoke token |
| Tiada tindakan hingga 00:00 | Session dan cookie portal tamat; token DB tidak direvoke |
| End OneID Session | Portal ditutup secara eksplisit tanpa global logout |
| Logout manual | Token direvoke, cookie dibersihkan dan PHP session dimusnahkan |
| Offline/HTTP 5xx | Tidak dianggap expiry; tiada forced reload/logout |
| Token revoked/account inactive | Terminal state khusus dan routing terkawal |
| Had mutlak | Sesi portal tidak melepasi lapan jam |

## 3. Sempadan SSO dan Aplikasi Lain

- Tiada perubahan kod diperlukan pada aplikasi lain.
- `api.php` dan respons token validation legacy kekal serasi.
- Portal idle expiry tidak revoke token dan tidak menutup local session aplikasi
  lain yang sudah dibuka.
- OneID tidak menganggap heartbeat aplikasi lain sebagai aktiviti manusia.
- Risiko bahawa aplikasi lain menyemak token pada masa berbeza diterima sebagai
  sempadan reka bentuk berisiko rendah.

## 4. Hierarchy Administrator

Administrator ialah authenticated user dengan grant tambahan `ADMIN_ACCESS`.
Deadline halaman Admin ialah baki terpendek antara PHP idle, PHP absolute dan
grant Admin. Status polling tidak memperbaharui sesi. Admin Stay Connected
memperbaharui base idle dan grant secara terkawal tanpa mutasi token, tetapi
tidak boleh menghidupkan semula base user session yang telah tamat.

## 5. Kawalan Keselamatan dan Audit

- Backend menjadi sumber kebenaran bagi idle, absolute dan effective remainder.
- Renewal memerlukan authenticated session, CSRF, token aktif dan akaun aktif.
- Multi-tab diselaraskan; tab visible/bfcache membuat revalidation server.
- Password/OTP dibersihkan ketika portal tamat.
- Event audit dictionary:
  - 68 `USER_PORTAL_SESSION_EXPIRED`;
  - 69 `USER_PORTAL_SESSION_RENEWED`;
  - 70 `USER_PORTAL_SESSION_ENDED`.
- Popup tersedia dalam Bahasa Melayu dan English serta tidak dimuatkan bersama
  controller popup Administrator.

## 6. Fasa dan Bukti

| Fasa | Hasil | Contract |
|---|---|---:|
| F0 | Baseline session, token, logout dan integrasi dikunci | 17/17 |
| F1 | PHP idle timeout mengikuti setting Administrator | 20/20 |
| F2 | Endpoint status/renew/expire dan audit | 24/24 |
| F3 | SweetAlert, locale, multi-tab dan modal safety | 20/20 |
| F4 | Heartbeat/error routing tanpa reload loop | 17/17 |
| F5 | Regression dan controlled rollout gate | 14 contracts, 0 failure |

Contract tambahan untuk idle policy, revoked token, Admin renewal, SSO config,
token lifetime, password change, multilingual dan MyDigital ID turut lulus dalam
readiness staging.

## 7. Bukti Deployment Staging

Output deployment yang diterima daripada staging:

```text
HEAD=a282017 release: publish OneID v2.8.2
ONEID_APP_VERSION=2.8.2
release metadata checks=18 failed=0
version documentation checks=4 failed=0
RESULT mode=--staging contracts=14 failures=0
PHP-FPM configuration test=successful
OneID HTTP=200
```

Pemilik sistem turut melaporkan login OneID, dashboard user, Administrator,
launch aplikasi SSO, logout dan sistem UAT lain berjaya dalam smoke test.

## 8. Runtime dan Insiden Semasa Rollout

PHP-FPM staging menggunakan file session handler dan retention global 28,800
saat. Enforcement idle sebenar kekal dalam OneID dan mengikuti setting Admin.

Formatter `.private/runtime.php` pada penggunaan awal telah menetapkan mode
`0600 iqs:iqs`, menyebabkan PHP-FPM `www-data` tidak dapat membaca konfigurasi
dan menghasilkan HTTP 500. Insiden dipulihkan tanpa rollback data:

```text
.private/runtime.php = 0640 iqs:www-data
PHP-FPM readable = PASS
OneID HTTP = 200
```

Punca telah dibetulkan dalam commit `07a5b0a`: formatter kini mengekalkan mode
asal. Semua 107 key dan value runtime disahkan kekal semasa format. Backup
bertimestamp diwujudkan dan fail private tidak dimasukkan ke Git.

## 9. Rollback dan Production Gate

Rollback presentation paling rendah risiko ialah menetapkan
`ONEID_USER_SESSION_WARNING_ENABLED=false`, menguji konfigurasi PHP-FPM dan
reload service. Endpoint serta audit dictionary boleh kekal dormant dan tiada
aplikasi lain perlu diubah.

Task staging dianggap ditutup. Production belum termasuk dalam penutupan ini.
Sebelum production:

1. sahkan host/pool OneID dan `session.gc_maxlifetime >= 28800`;
2. backup source, runtime, FPM dan database;
3. deploy dengan feature flag awal `false`;
4. apply/check audit dictionary 68–70;
5. jalankan readiness source/staging dan smoke test canary;
6. aktifkan flag hanya selepas kelulusan pemilik sistem;
7. pantau HTTP 401/403/5xx serta audit expiry/renew/end.

## 10. Keputusan Audit

Ujian masa sebenar pada 8 Ogos 2026 mendapati popup tidak muncul kerana modal
Bootstrap tersembunyi masih mempunyai class `in`. Modal tersebut mempunyai
`display:none`, saiz `0x0` dan `aria-hidden=true`, tetapi pengesanan lama hanya
menilai selector class. Finding ini membatalkan penutupan staging sebelumnya.

Pembetulan mengehadkan penghalang popup kepada dialog yang benar-benar visible
dan menaikkan cache version controller. Acceptance test 30 minit, Stay Connected
dan tanpa tindakan mesti diulang sebelum status dikembalikan kepada staging
validated. Production kekal tidak diluluskan.
