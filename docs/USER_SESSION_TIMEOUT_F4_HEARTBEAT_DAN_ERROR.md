# User Session Timeout F4 — Heartbeat dan Error Handling

**Tarikh:** 7 Ogos 2026  
**Status:** IMPLEMENTED / PENDING STAGING UAT

## Objektif

Fasa 4 membuang `location.reload(true)` daripada kegagalan heartbeat dashboard
dan membezakan terminal authentication state daripada masalah rangkaian atau
server sementara.

Heartbeat token lima minit sedia ada dikekalkan untuk compatibility token dan
kekal diklasifikasikan sebagai technical heartbeat. Ia tidak menyegarkan PHP
idle session.

## Routing Kegagalan

Hanya kod berikut dianggap terminal:

- `USER_SESSION_EXPIRED`;
- `SSO_TOKEN_REVOKED`;
- `ACCOUNT_INACTIVE`.

Kod terminal dihantar kepada controller SweetAlert Fasa 3. Jika feature flag
presentation dimatikan, dashboard menggunakan fallback redirect ke landing
untuk kod terminal yang tepat sahaja.

HTTP 401 tanpa kod stabil tidak terus dianggap expiry. Controller membuat
status revalidation kepada endpoint Fasa 2. Offline, timeout, respons malformed
dan HTTP 5xx:

- tidak reload halaman;
- tidak logout pengguna;
- tidak revoke token;
- memaparkan toast localized yang dihadkan kepada sekali seminit;
- mencuba status semula selepas 15 saat.

## Penyelarasan Request Bermakna

Request jQuery dashboard yang berjaya menghantar event
`oneid:user-activity-committed`. Technical action berikut dikecualikan:

- `update_specific_token_datetime`;
- `user_session_status`;
- `admin_step_up_status`.

Account Security menghantar event sama selepas mutation authenticated berjaya.
Controller kemudian membaca semula deadline authoritative daripada backend.
Tiada `setInterval` status baharu diwujudkan, maka mekanisme ini tidak boleh
menghidupkan session dengan polling sendiri.

## Fail Berkaitan

- `page/dashboard.php`
- `page/user_mfa_security.php`
- `public/dist/js/oneid-user-session.js`
- `tests/characterization/user_session_timeout_f4_heartbeat.php`

## Automated Contract

```bash
php tests/characterization/user_session_timeout_f4_heartbeat.php
```

Keputusan source: `RESULT checks=17 failed=0`.

## UAT

1. Buka dashboard dan pastikan heartbeat berjaya tanpa perubahan deadline idle.
2. Simulasikan offline melepasi satu heartbeat; halaman tidak reload/logout dan
   toast unavailable dipaparkan.
3. Pulihkan rangkaian; status session diselaraskan tanpa reload loop.
4. Revoke token semasa dari sesi Administrator; heartbeat/status seterusnya
   memaparkan terminal state dan membawa pengguna ke landing.
5. Nyahaktifkan akaun UAT; sahkan `ACCOUNT_INACTIVE` berbeza daripada network
   failure.
6. Lakukan favourite, launch SSO atau mutation Account Security; deadline popup
   disusun semula daripada status server.
7. Pastikan dua tab, BM/English, dashboard Administrator dan aplikasi lain kekal
   seperti kontrak sebelumnya.

Tiada migration database atau perubahan service-provider API dalam Fasa 4.
