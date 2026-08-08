# User Session Timeout F2 — Endpoint dan Portal Expiry

**Tarikh:** 7 Ogos 2026  
**Status:** COMPLETE / STAGING UAT PASSED

## Skop

Fasa 2 menyediakan backend authoritative untuk Fasa 3 tanpa menambah popup:

- `user_session_status` — status teknikal, tidak menyegarkan idle;
- `user_session_renew` — Stay Connected eksplisit selepas auth, CSRF, token dan
  akaun disahkan;
- `user_session_expire` — menutup session dan cookie portal OneID sahaja;
- deadline idle, absolute dan efektif daripada server;
- kod stabil untuk expired, token revoked, akaun inactive dan CSRF invalid;
- deadline Administrator ialah baki terpendek antara PHP session dan grant;
- audit event 68, 69 dan 70.

Tiada perubahan dibuat kepada `api.php`, format token, validation service
provider atau local session aplikasi lain.

## Kontrak Endpoint

Semua action menggunakan `POST lib/q_func`, CSRF sedia ada dan authenticated
request guard.

Respons status/renew yang berjaya mengandungi:

```text
authenticated
idle_timeout_seconds
idle_remaining_seconds
absolute_remaining_seconds
effective_remaining_seconds
server_epoch
code
reason
```

Kod utama ialah `USER_SESSION_ACTIVE`, `USER_SESSION_RENEWED`,
`USER_SESSION_EXPIRED`, `SSO_TOKEN_REVOKED`, `ACCOUNT_INACTIVE`, `CSRF_INVALID`
dan `SESSION_STATUS_UNAVAILABLE`.

## Urutan Keselamatan Renewal

Action status, renew dan expiry diklasifikasikan sebagai teknikal semasa
bootstrap. Oleh itu request tidak boleh menyegarkan idle timestamp sebelum:

1. CSRF disahkan;
2. authenticated PHP session disahkan;
3. token browser masih aktif disahkan;
4. akaun masih aktif disahkan;
5. audit renewal berjaya ditulis.

Hanya selepas semua semakan lulus, `user_session_renew` menyegarkan timestamp
idle PHP dan expiry cookie menggunakan token yang sama. Token tidak di-rotate,
tidak direvoke dan timestamp token database tidak disentuh.

## Portal Expiry

Expiry automatik atau action tamat eksplisit:

- membersihkan authenticated PHP session;
- rotate PHP session ID;
- membersihkan cookie `sso_cre` pada domain OneID;
- menyimpan marker selamat untuk respons `USER_SESSION_EXPIRED`;
- tidak memanggil `update_specific_token_status`;
- tidak menjejaskan token/local session yang telah digunakan aplikasi lain.

Logout manual kekal berasingan dan masih revoke token.

## Cookie Portal

Cookie browser OneID tidak lagi hard-coded 30 minit. Retentionnya mengikuti
deadline portal efektif dan tidak melebihi absolute cap lapan jam. Renewal user
atau Administrator mengeluarkan semula cookie dengan nilai token yang sama dan
baki efektif semasa. Ini diperlukan supaya setting satu jam tidak gagal pada
minit ke-30 hanya kerana cookie portal telah tamat awal.

## Administrator

`admin_step_up_status` memulangkan deadline efektif terpendek antara PHP idle,
PHP absolute dan grant `ADMIN_ACCESS`. Polling tidak menyegarkan PHP idle.

`admin_step_up_renew` hanya menyegarkan PHP idle selepas renewal grant berjaya,
mengekalkan token yang sama dan tidak boleh melepasi absolute deadline.

## Audit Migration

```bash
php tools/user_portal_session_schema.php --check
php tools/user_portal_session_schema.php --apply
php tools/user_portal_session_schema.php --check
```

Event:

- 68 `USER_PORTAL_SESSION_EXPIRED`
- 69 `USER_PORTAL_SESSION_RENEWED`
- 70 `USER_PORTAL_SESSION_ENDED`

Rollback dictionary tersedia dalam
`docs/migrations/20260807_user_portal_session_audit_down.sql`.

## Automated Contract

```bash
php tests/characterization/user_session_timeout_f2_endpoints.php
```

Keputusan source selepas logout-scope regression fix: `RESULT checks=24 failed=0`.

## UAT Fasa 2

Gunakan browser developer tools untuk memanggil endpoint sebelum Fasa 3:

- status mengembalikan `USER_SESSION_ACTIVE` dan baki berkurang;
- status berulang tidak mengubah deadline idle;
- renew mengembalikan `USER_SESSION_RENEWED` dan idle kembali ke setting admin;
- tamat eksplisit mengembalikan `USER_SESSION_EXPIRED` serta membersihkan
  session/cookie portal;
- token yang sama masih diterima aplikasi lain selepas portal expiry;
- token revoked, akaun inactive dan CSRF salah menghasilkan kod berlainan;
- Admin Stay Connected mengembalikan baki efektif yang tidak melepasi PHP
  absolute deadline;
- login, MyDigital ID, password change, launch SSO dan logout manual kekal lulus.

## Rollback Source

Revert commit Fasa 2 dan jalankan migration down hanya selepas memastikan tiada
audit baharu memerlukan label event tersebut. FPM `gc_maxlifetime=28800` serta
source Fasa 1 boleh kekal.
