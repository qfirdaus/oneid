# Fasa 4 — Pelaksanaan dan Rollback OneID-UAT

**Tarikh pelaksanaan:** 13 Julai 2026  
**Rujukan:** `docs/PELAN_REMEDIASI_BERFASA_ONEID_UAT.md`  
**Skop:** Authentication guard, role-based authorization, CSRF protection, page guard dan ownership token action.

## 1. Ringkasan Status

Fasa 4 menambah satu lapisan default-deny di hadapan semua action `lib/q_func.php`. Request kini perlu menggunakan POST, membawa tepat satu action yang dikenali, lulus CSRF dan memenuhi tahap akses action tersebut.

| Komponen | Status |
|---|---|
| Inventori dan klasifikasi action | Selesai — 48 action |
| Authentication guard action pengguna | Selesai |
| Role guard action pentadbir | Selesai |
| Guard dashboard pengguna/pentadbir | Selesai |
| CSRF bagi active browser flow | Selesai |
| Default-deny unknown/multi-action request | Selesai |
| Ownership refresh/sign-off token | Selesai |
| Authenticated smoke test dengan akaun sebenar | Belum selesai |
| Service authentication `api.php`, `idms.php`, `skp_api.php` | Fasa 6 selepas inventori consumer |

## 2. Baseline Sebelum Perubahan

Sebelum Fasa 4:

- `lib/q_func.php` menjalankan action hanya berdasarkan kewujudan field POST.
- Tiada centralized authentication atau role guard.
- UI menyembunyikan link admin, tetapi server tidak menolak pengguna biasa secara menyeluruh.
- Dashboard admin dan `admin/user_list.php` tiada role guard pada awal page.
- AJAX request tidak membawa CSRF token.
- Caller boleh menghantar `u_id` sendiri kepada refresh-token action.
- Request boleh membawa lebih daripada satu action flag dan menyebabkan lebih daripada satu blok diproses.
- Direct GET kepada endpoint action tidak ditolak secara eksplisit.

## 3. Klasifikasi 48 Action

### 3.1 Public — 3

- `auth`
- `action_forgot_password`
- `action_submit_OTP`

Public bermaksud tidak memerlukan login. Ketiga-tiganya masih memerlukan session CSRF token yang dijana oleh login page.

### 3.2 Authenticated user — 7

- `check_default_password`
- `action_change_password`
- `admin_get_all_token_for_specific_user`
- `get_specific_user_app_list`
- `go_to_service_provider`
- `user_signoff_security_sessions`
- `update_specific_token_datetime`

Nama `admin_get_all_token_for_specific_user` ialah nama legacy yang mengelirukan. Implementasinya hanya membaca token milik `$_SESSION['login_user']`, maka ia diklasifikasikan sebagai user action. Penamaan semula ditangguhkan bagi mengelakkan perubahan frontend besar.

### 3.3 Administrator — 38

Semua action berikut memerlukan session sah dan `login_user_type === 1`:

- Carian dan pengurusan pengguna.
- Tambah, edit, deactivate/reactivate, resync dan reset pengguna.
- Pengurusan kategori pengguna dan ACL.
- Pengurusan application/service provider dan icon.
- Blacklist serta accessible application.
- System configuration.
- Global active-token view.
- Audit log.
- User sync serta sync-log detail.

Senarai literal disimpan dalam `oneid_q_func_action_map()` supaya setiap action baru perlu diklasifikasikan secara eksplisit sebelum boleh berjalan.

## 4. Shared Security Layer

Fail baharu: `lib/request_security.php`.

Fungsi utama:

| Fungsi | Tanggungjawab |
|---|---|
| `oneid_csrf_token()` | Jana token 256-bit menggunakan `random_bytes()` dan simpan dalam session |
| `oneid_is_authenticated()` | Sahkan login status dan session user |
| `oneid_is_admin()` | Sahkan session pengguna dan role administrator |
| `oneid_require_csrf()` | Banding token menggunakan `hash_equals()` |
| `oneid_guard_q_func_request()` | Method, action count, CSRF, authentication dan role guard |
| `oneid_require_authenticated_page()` | Lindungi dashboard pengguna |
| `oneid_require_admin_page()` | Lindungi page pentadbir |

### 4.1 Default-deny behavior

| Keadaan | HTTP status |
|---|---:|
| Method selain POST ke `q_func` | `405` |
| Tiada action / unknown action | `400` |
| Lebih daripada satu recognized action | `400` |
| CSRF token hilang/tidak sah | `403` |
| Action user/admin tanpa login | `401` |
| Pengguna biasa memanggil action admin | `403` |

Failure response ialah JSON generik dan tidak mendedahkan session, role atau token.

## 5. CSRF Integration

CSRF token dijana dalam PHP session dan dihantar oleh active page melalui header:

```text
X-CSRF-Token: <session token>
```

`$.ajaxSetup()` ditambah pada:

- `index.php`
- `page/dashboard.php`
- `admin/dashboard.php`

Semua AJAX request daripada page aktif mewarisi header ini, termasuk form URL-encoded dan upload multipart.

Login page kini memulakan session secara eksplisit sebelum SSO include. CSRF token tidak dimasukkan dalam URL dan tidak direkodkan dalam dokumen/log.

Fail login/dashboard lama tidak diberi compatibility bypass. Jika ia cuba memanggil `q_func` tanpa token, request akan ditolak. Ini ialah containment yang disengajakan bagi page legacy yang telah dikenal pasti sebagai calon quarantine.

## 6. Page Authorization

### 6.1 Dashboard pengguna

`page/dashboard.php` memerlukan session login sah selepas SSO cookie processing. Request tanpa authentication diarahkan ke `APP_URL`.

### 6.2 Page pentadbir

Guard admin ditambah pada:

- `admin/dashboard.php`
- `admin/user_list.php`
- `admin/dashboard_old.php` sebagai containment page legacy

Behavior:

- Tanpa login: redirect ke login page.
- Login sebagai pengguna biasa: HTTP `403 Forbidden`.
- Administrator: page diteruskan.

`admin/user_list.php` turut mengesahkan `category_id` sebagai integer dan melakukan HTML encoding pada `category_name` sebelum output.

## 7. Object Ownership

Action pengguna menggunakan identity daripada server-side session:

- Senarai application menggunakan `$_SESSION['login_user']`.
- Senarai security session menggunakan `$_SESSION['login_user']`.
- Sign-off token menggunakan gabungan session user dan `token_id` dalam klausa database.
- Refresh token kini mengabaikan `u_id` daripada POST dan menggunakan `$_SESSION['login_user']`.
- Launch service provider menyemak ACL pengguna session.
- Tukar password menggunakan pengguna session.

Operasi arbitrary user, application, category, ACL dan token global hanya tersedia kepada role administrator.

## 8. Endpoint Legacy

`lib/q_func_old.php` menggunakan guard yang sama. Ia tidak menerima bypass untuk request lama tanpa CSRF atau tanpa authorization.

Page `page/dashboard_old.php`, `page/dashboard2.php` dan `admin/dashboard_old.php` turut menerima authentication/role guard. AJAX legacy tidak diberi CSRF compatibility baharu; fail ini kekal calon quarantine dan tidak boleh digunakan sebagai laluan bypass.

Endpoint service-to-service berikut belum diubah dalam Fasa 4:

- `api.php`
- `idms.php`
- `skp_api.php`

Ia memerlukan inventori consumer dan client credential berasingan dalam Fasa 6. Menambah token secara sepihak sekarang boleh memutuskan consumer luar yang belum dikenal pasti.

## 9. Verification

### 9.1 Static verification

- 48 action dipetakan: 3 public, 7 user, 38 admin.
- Tiada action executable dalam `q_func.php` yang tidak diklasifikasikan.
- 47 fail PHP first-party, termasuk template config, lulus `php -l`.
- Fail yang diubah secara langsung lulus lint.
- `git diff --check` lulus selepas trailing whitespace dibersihkan; warning CRLF repository sedia ada tidak mengubah keputusan runtime.

### 9.2 HTTP/guard verification

| Ujian | Keputusan |
|---|---:|
| Root login page | `200` |
| Session cookie dijana | Lulus |
| CSRF token 64 hex chars dijana | Lulus |
| Direct GET `q_func` | `405` |
| POST tanpa CSRF | `403` |
| Public login action dengan CSRF sah | `200` |
| User action tanpa login | `401` |
| Admin action tanpa login | `401` |
| Multi-action request | `400` |
| Dashboard pengguna tanpa login | `302` ke login |
| Dashboard admin tanpa login | `302` ke login |
| Synthetic authenticated user memanggil admin action | `403` |
| Synthetic authenticated user memanggil user action | `200` |
| Synthetic administrator melepasi dispatcher admin | Lulus |

Synthetic session hanya digunakan untuk guard verification dan dimusnahkan selepas ujian. Tiada data pengguna sebenar diubah.

## 10. Manual Smoke Test yang Masih Diperlukan

- [ ] Login staf melalui browser sebenar.
- [ ] Login pelajar melalui browser sebenar.
- [ ] Forgot-password, resend OTP dan submit OTP.
- [ ] Dashboard user memuatkan application list.
- [ ] Tukar password sendiri.
- [ ] Sign-off security session sendiri.
- [ ] Launch application SSO dan non-SSO.
- [ ] Admin dashboard memuatkan widget/configuration.
- [ ] Admin carian dan pengurusan pengguna.
- [ ] Admin application, category, ACL dan blacklist.
- [ ] Admin sync dan audit log.
- [ ] Upload icon melalui multipart request dengan CSRF header.
- [ ] Browser network tab tidak menunjukkan request `401`/`403` yang tidak dijangka.

## 11. Rollback

### 11.1 Jika AJAX menerima `403 Invalid CSRF token`

1. Pastikan page berasal daripada active `index.php`, `page/dashboard.php` atau `admin/dashboard.php`.
2. Pastikan browser menerima `PHPSESSID` yang sama bagi page dan AJAX request.
3. Pastikan header `X-CSRF-Token` hadir dalam browser network inspector.
4. Pastikan load balancer tidak membuang custom header atau menukar session affinity.
5. Reload page untuk menjana token/session yang konsisten.

Jangan whitelist action sensitif atau mematikan CSRF secara global sebagai penyelesaian kekal.

### 11.2 Jika user menerima `401`

1. Semak `login_status`, `login_user` dan PHP session persistence.
2. Pastikan SSO cookie validation selesai sebelum page guard.
3. Semak session save path dan PHP-FPM permission.
4. Login semula dan ulang request.

### 11.3 Jika admin menerima `403`

1. Semak `login_user_type` daripada database/session.
2. Pastikan role administrator ialah nilai `1` seperti behavior legacy.
3. Jangan longgarkan action kepada user class tanpa semakan privilege.

### 11.4 Source rollback

Jika rollback source benar-benar diperlukan dalam maintenance window:

1. Pulihkan tujuh fail aplikasi daripada snapshot sebelum Fasa 4.
2. Keluarkan include `request_security.php` dan AJAX header secara konsisten; jangan rollback separuh yang menyebabkan semua request gagal.
3. Kekalkan ownership fix pada refresh-token jika boleh.
4. Anggap rollback authorization sebagai penerimaan semula risiko kritikal AUD-001.
5. Jalankan semula login, user dan admin smoke test.

## 12. Checksum Selepas Fasa 4

| Fail | SHA-256 |
|---|---|
| `lib/request_security.php` | `1f429c41dedfbc49e89c8393b5ca20c4f5b4fd9eb84cd3aa8c8f08a270ac20a9` |
| `lib/q_func.php` | `729d278674cb0a116532ee1049cdaa505fedfa5f70a52668f13e631b91b13c4d` |
| `lib/q_func_old.php` | `a42ec8fb5138dc802405f19ddca7a9dc3777a66bcba2d8e14748bd3dcecce4f3` |
| `index.php` | `26b2f9e590ded8ece97de58d17bfbf6c265dd6a8259afd496c9fb1656af66cf1` |
| `page/dashboard.php` | `bc2f127396f57724f72b6adef3bfe57221052f422488b8d87e356a42d2116279` |
| `admin/dashboard.php` | `a4bdf858045a55107ac8435ec5ec2b0c5f25ca847d887b55fc0ec255e0872092` |
| `admin/user_list.php` | `5b0512ea16e9f5fac3948aa19b0fc25b950ec0af6e66f7b48366d35cb80ce910` |

## 13. Exit Criteria

- [x] Semua 48 action diklasifikasikan.
- [x] User action memerlukan authentication.
- [x] Admin action memerlukan role administrator.
- [x] Active user/admin page mempunyai server-side guard.
- [x] CSRF token dijana dan dihantar oleh semua active browser flow.
- [x] Unknown dan multi-action request ditolak.
- [x] Token refresh/sign-off diikat kepada session user.
- [x] Status `400`, `401`, `403` dan `405` digunakan secara konsisten.
- [x] Legacy `q_func_old.php` tidak menjadi bypass.
- [x] Perubahan dan rollback didokumentasikan.
- [ ] Semua authenticated browser smoke test selesai.
- [ ] Service-to-service authorization Fasa 6 selesai.

## 14. Keputusan

Server-side access control bagi browser action utama telah dilaksanakan. Fasa 4 berada pada status **implemented, pending authenticated smoke test**. Ia tidak menutup risiko endpoint service-to-service yang dijadualkan untuk Fasa 6.
