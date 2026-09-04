# MD7 — Permission Isolation dan Authorization Hardening

**Tarikh:** 4 September 2026  
**Rujukan:** `ONEID-MAINTENANCE-DEVELOPER-20260904-01`  
**Fasa:** 7 — pengasingan permission  
**Status:** disahkan owner; Fasa 8 dibenarkan

## 1. Objektif

Fasa ini membuktikan bahawa `MAINTENANCE_ACCESS` hanya membolehkan developer
melalui maintenance gate. Ia tidak mengubah role, kategori, ACL aplikasi atau
Admin Step-Up authorization.

## 2. Dapatan authorization

Sesi developer kekal:

```text
login_status=true
login_user_type=0
```

`oneid_is_admin()` hanya menerima exact `login_user_type=1` dan tidak membaca
grant ID/version developer. Semua action pengurusan maintenance developer
berada dalam action map admin; grant/revoke turut memerlukan purpose
`SECURITY_CONFIGURATION_CHANGE`.

Halaman data admin berikut memanggil `oneid_require_admin_page()` dan Admin
Step-Up:

- `/admin/dashboard`;
- `/admin/user_list`; dan
- `/admin/report_preview`.

Public-root files hanya wrapper kepada halaman root yang sama. `admin/index`
juga hanya menganggap exact `u_type=1` sebagai authenticated admin.

## 3. Paparan pengguna

Developer masuk ke `/page/dashboard`. Badge role kekal ditentukan oleh kategori
asal staf/pelajar. Pautan Administrator hanya dirender untuk `u_type=1`, maka
maintenance grant tidak menambah menu atau paparan admin.

Tiada label role `Developer` ditambah kerana ia akan memberi gambaran bahawa
role akaun berubah. Perbezaan developer hanyalah capability runtime ketika
maintenance.

## 4. Aplikasi dan SSO

Login maintenance tidak redirect secara automatik kepada service provider.
Selepas masuk portal, senarai aplikasi masih dibina daripada:

- ACL kategori asal;
- ACL khusus pengguna; dan
- blacklist pengguna.

Ini bermaksud developer hanya boleh membuka aplikasi yang memang tersedia
kepada akaun pengguna tersebut. Maintenance grant tidak menambah mana-mana
entitlement aplikasi.

## 5. Persistence boundary

Repository maintenance developer tidak mempunyai statement yang mengemas kini
`user_tbl`, `u_type` atau `u_category`. Grant/revoke hanya menyentuh dua jadual
additive Fasa 2.

## 6. Verifikasi

```bash
php tests/characterization/maintenance_developer_permission_isolation.php
php tools/maintenance_developer_phase6_contract.php
php tools/maintenance_developer_phase5_contract.php
php tools/maintenance_mode_contract.php
```

## 7. Acceptance gate Fasa 7

- [x] Developer authenticated tetapi `oneid_is_admin()` false.
- [x] Semua action pengurusan grant berada pada level admin.
- [x] Grant/revoke memerlukan Security Configuration Change Step-Up.
- [x] Semua halaman data admin dilindungi admin page guard.
- [x] Public-root admin wrapper tidak memintas guard.
- [x] Menu Administrator hanya untuk `u_type=1`.
- [x] Badge role kekal berdasarkan kategori pengguna.
- [x] ACL aplikasi kekal category/specific/blacklist sedia ada.
- [x] Login maintenance tidak auto-redirect ke external SP.
- [x] Repository tidak boleh memutasi role atau kategori.
- [x] Feature flag kekal OFF dan live database tidak dimutasi.

Fasa seterusnya ialah end-to-end security regression, operational readiness dan
controlled UAT preparation. Ia memerlukan pengesahan owner bagi Fasa 7.
