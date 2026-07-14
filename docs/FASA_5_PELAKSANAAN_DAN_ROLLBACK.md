# Fasa 5 — Pelaksanaan dan Rollback OneID-UAT

**Tarikh pelaksanaan:** 13 Julai 2026  
**Rujukan:** `docs/PELAN_REMEDIASI_BERFASA_ONEID_UAT.md`  
**Skop:** Migrasi MD5, reset password, OTP, PHP session, browser cookie dan token SSO.

## 1. Ringkasan Status

Fasa 5 menggunakan expanding migration dan backward compatibility. Akaun MD5 lama kekal boleh login sehingga deadline dan terus dinaik taraf kepada `password_hash()` selepas login berjaya.

| Komponen | Status |
|---|---|
| Schema DB modern hash/token/OTP | Selesai dan diaplikasi pada UAT |
| Login MD5 serta automatic rehash | Selesai |
| Tarikh tamat MD5 fallback | 13 Oktober 2026 secara default |
| Password baharu/reset dengan `password_hash()` | Selesai |
| Reset kepada IC/ID/SOS | Dihentikan |
| OTP hash, expiry, one-time, attempts dan rate limit | Selesai |
| PHP session dan cookie hardening | Selesai |
| Token `random_bytes()` dan hashed DB storage | Selesai |
| Token dalam `new_sso_cre` query string | **Belum — tunggu consumer migration** |
| Authenticated end-to-end SSO smoke test | Belum selesai |

## 2. Database Migration

Migration: `docs/migrations/FASA_5_AUTH_HARDENING.sql`.

| Jadual/column | Sebelum | Selepas |
|---|---|---|
| `user_tbl.u_password` | `VARCHAR(50)` | `VARCHAR(255)` |
| `user_tbl.password_change_required` | Tiada | `TINYINT(1)` |
| `token_tbl.token_id` | `VARCHAR(25)` | `VARCHAR(64)` |
| `otp_codes.otp_code` | `VARCHAR(6)` | `VARCHAR(255)` |
| `otp_codes.otp_expires_at` | Tiada | `DATETIME` |
| `otp_codes.otp_attempts` | Tiada | `TINYINT UNSIGNED` |
| `otp_codes.otp_consumed_at` | Tiada | `DATETIME` |

Migration memanjangkan column dan menambah metadata; tiada row pengguna dipadam.

### 2.1 Data migration UAT

- 244,559 row token legacy ditukar daripada raw token kepada SHA-256.
- Selepas migration: 0 token format legacy; semua 244,559 token row ialah hash 64 aksara.
- 5,275 OTP plaintext lama ditransform secara irreversible dan ditandakan consumed/expired.
- Selepas migration: 0 OTP plaintext enam aksara tinggal.
- 9,649 daripada 9,649 akaun masih MD5 pada baseline dan akan migrate ketika login/reset.

Hanya aggregate count direkodkan; tiada ID pengguna, password, token atau OTP dimasukkan dalam dokumen.

## 3. Password Migration

### 3.1 Login compatibility

`lib/auth_security.php` dan `lib/Database.php` kini:

1. Mengenal pasti hash MD5 melalui format 32 hex.
2. Membanding MD5 legacy menggunakan `hash_equals()`.
3. Selepas login berjaya, menghasilkan `password_hash(PASSWORD_DEFAULT)` daripada password yang baru dihantar.
4. Mengemas kini DB dalam request yang sama.
5. Menggunakan `password_verify()` pada login seterusnya.

Password plaintext tidak disimpan atau dilog.

### 3.2 MD5 deadline

Default deadline ialah `2026-10-13 23:59:59 Asia/Kuala_Lumpur`. Ia boleh diubah melalui:

```text
ONEID_LEGACY_MD5_DEADLINE=2026-10-13 23:59:59 Asia/Kuala_Lumpur
```

Selepas deadline, akaun MD5 perlu menggunakan Lupa Kata Laluan untuk menetapkan password moden.

### 3.3 Password policy

Password baharu memerlukan minimum 12 aksara, huruf kecil, huruf besar, nombor dan simbol. Policy dikuatkuasakan di server; JavaScript hanya memberikan maklum balas awal.

### 3.4 Akaun baharu dan admin reset

- Sync/manual user baru tidak lagi menerima IC, ID atau `SOS` sebagai password.
- Sistem menyimpan hash bagi random 256-bit initial value yang tidak didedahkan.
- User baru menggunakan Lupa Kata Laluan untuk menetapkan password pertama.
- Admin reset menetapkan random unknown password, menandakan reset-required dan revoke semua token.
- Admin tidak menerima temporary password.

### 3.5 Password change

Current password disahkan melalui compatibility layer. Password baharu disimpan dengan `password_hash()`, reset-required flag dibersihkan, semua token lama direvoke dan current browser menerima token baharu.

## 4. Reset Password dan OTP

Forgot-password kini sentiasa memberikan response generik yang tidak membezakan akaun wujud, tidak wujud, suspended atau rate-limited.

| Kawalan OTP | Nilai |
|---|---:|
| Generator | `random_int()` |
| Panjang | 6 digit |
| Penyimpanan | `password_hash()` |
| Expiry | 5 minit |
| Maksimum percubaan | 5 |
| Resend cooldown | 60 saat |
| Maksimum request | 5 sehari/user |
| Penggunaan | One-time |

Target user diikat kepada PHP session; `u_id` daripada browser tidak digunakan untuk memilih akaun reset.

Flow baharu:

1. Server menerima identifier dan memberi generic response.
2. OTP yang sah ditandakan consumed.
3. Session menerima reset authorization selama 10 minit.
4. Pengguna wajib memasukkan password baharu dua kali.
5. Semua token user direvoke.
6. Pengguna diarahkan login semula.

OTP tidak lagi menukar password kepada IC dan tidak auto-login pengguna.

## 5. Session Hardening

`lib/session_security.php` memperkenalkan:

- `session.use_only_cookies=1`;
- `session.use_strict_mode=1`;
- cookie `Secure`, `HttpOnly`, `SameSite=Lax`;
- idle timeout 30 minit;
- absolute timeout 8 jam;
- `session_regenerate_id(true)` selepas authentication.

Active dan contained legacy dashboard/logout endpoints menggunakan centralized session bootstrap.

## 6. SSO Cookie

Sebelum Fasa 5, `sso_cre` mengandungi token bersama seluruh user packet dan dibaca JavaScript. Selepas Fasa 5:

- cookie hanya mengandungi opaque raw SSO token;
- tiada user profile dalam cookie;
- `Secure`, `HttpOnly`, `SameSite=Lax` digunakan;
- JavaScript tidak lagi membaca cookie;
- refresh mengambil token daripada HttpOnly cookie dan user daripada session;
- existing JSON cookie dibaca untuk compatibility dan ditulis semula dalam format minimum;
- logout revoke current token, clear cookie dan destroy session.

## 7. SSO Token

### 7.1 Selesai

- `uniqid()` diganti dengan `bin2hex(random_bytes(32))` pada active path.
- Raw token mempunyai 256-bit entropy; DB hanya menyimpan SHA-256.
- Semua token legacy UAT telah di-hash tanpa memutuskan raw client token.
- Token dirotasi selepas login, password change dan API auto-reissue.
- Token direvoke selepas password/admin reset, deactivation dan perubahan privilege/category.
- Hash token dalam security-session listing tidak boleh digunakan sebagai bearer token API.

### 7.2 Belum selesai: token dalam URL

`new_sso_cre` masih digunakan bagi direct SSO handoff. Menukarnya kepada one-time authorization code memerlukan semua external consumer mengemas kini exchange flow serentak. Perubahan unilateral boleh menyebabkan outage, maka ia menunggu consumer inventory Fasa 6.

Mitigasi semasa: entropy tinggi, DB hashed storage, strict-origin referrer policy dan token rotation/revocation. Access log lama yang mungkin mengandungi raw token mesti dianggap sensitif.

## 8. Verification

### 8.1 Transaction tests

- Legacy MD5 login dan automatic rehash: lulus.
- Modern password verification: lulus.
- Default-password flag: lulus.
- New/legacy token hash lookup dan migration: lulus.
- OTP hashed storage: lulus.
- OTP blocked selepas lima percubaan: lulus.
- OTP one-time consumption: lulus.
- Temporary test user tinggal: 0.

### 8.2 HTTP/static tests

- Root login page: HTTP `200`.
- PHP session cookie: `Secure; HttpOnly; SameSite=Lax`.
- Full temporary HTTP login: lulus dan test row dibersihkan.
- Session ID regenerated dengan flags selamat: lulus.
- `sso_cre` flags: lulus; token hasil login disimpan hashed dalam DB.
- Unknown-user forgot-password: HTTP `200`, generic response.
- Reset tanpa verified OTP: HTTP `403`.
- `q_func_old.php`: HTTP `410`.
- 49 current dispatcher actions semuanya diklasifikasikan.
- Active auth/token paths: 0 direct `md5()` dan 0 `uniqid()`.
- 48 fail PHP first-party lulus lint.
- World-writable kekal 0.

## 9. Manual Smoke Test yang Masih Diperlukan

- [ ] Login staf dan pelajar MD5 sebenar; sahkan automatic rehash.
- [ ] Login kali kedua menggunakan modern hash.
- [ ] Forced password change bagi default-password user.
- [ ] Forgot-password menggunakan mailbox UAT sebenar.
- [ ] Resend cooldown, daily limit dan lima OTP salah.
- [ ] OTP berjaya membuka form password baharu dan tidak auto-login.
- [ ] User/admin dashboard selepas session regeneration.
- [ ] Password change rotate token.
- [ ] Logout current session.
- [ ] Launch semua service provider SSO yang diluluskan.
- [ ] API auto-reissue token.
- [ ] Admin reset/deactivate/category change revoke target sessions.

## 10. Rollback

### 10.1 Code dan schema

Code boleh dipulihkan kepada snapshot sebelum Fasa 5, tetapi schema expanded mesti dikekalkan. Jangan kecilkan `u_password` kepada 50 atau `token_id` kepada 25 kerana modern values akan truncate.

### 10.2 Password

Jangan bulk-convert modern hash kembali kepada MD5; plaintext tidak tersedia. Jika login bermasalah, pulihkan compatibility code sambil mengekalkan modern row.

### 10.3 OTP

Historical OTP telah di-expire dan tidak boleh dipulihkan. Jika flow gagal, hentikan reset sementara dan baiki flow; jangan kembali kepada plaintext OTP atau reset IC.

### 10.4 Token

Raw DB token telah ditransform dan tidak boleh dipulihkan. Code lama yang mencari raw token tidak compatible. Rollback mesti mengekalkan hash-aware lookup atau revoke semua token dan memaksa login semula.

### 10.5 Cookie/session

Semak HTTPS, cookie path, `SameSite=Lax` dan session storage. Jangan matikan `HttpOnly` untuk memulihkan JavaScript refresh; refresh kini server-side.

## 11. Checksum Selepas Fasa 5

| Fail | SHA-256 |
|---|---|
| `lib/auth_security.php` | `7b9e5e60f29ae91dfb784d3b0dfc8e41f7ecf3c9b7bbf83b15f8b10ed0b5e16a` |
| `lib/session_security.php` | `4f6ae087d3f20d2e606b4444129c1b9f6f12c95d5d8b1e7e05ba50fdbeec3a7b` |
| `lib/Database.php` | `f9aff1d051c3cc7aa9d8902b299292fe49e70b34b997a69a2feb931109547658` |
| `lib/q_func.php` | `2533d44b4d8d6575e466d4b01a695c2a632bebbfac6d9dc28623a081b35ca28c` |
| `lib/request_security.php` | `9166ebc1ef94f92e44b6000bc6529d9d1519f1df6261625eaba7c9b7e8efde65` |
| `lib/SSO_IDP_INC.php` | `dfeeee2fae397c0493ea3da6ad4163859e43434ef351633ab3ad1fa6887e7926` |
| `lib/sync_user_runner.php` | `78362eed2e33e6b037dde9e32e0b54a1d67c7c8b062acf754a56a34a6ca63dd1` |
| `api.php` | `f36844e58d9e33701868164ce449bd6e98c9ea1137b7463ecd75b251c2aa6249` |
| `index.php` | `b0c76fdbcea9b60e04f09ff910c4693d336d4d9980dead140434581a27e13634` |
| `page/dashboard.php` | `ab642c87057549a1148293b14d6f635a4985c249839d8840a762c1cf7a456a8b` |
| `admin/dashboard.php` | `2651e9530d51c4e8feee671af6f0fa785bc7d46e48ac8caea2d0bac46c8a599d` |
| `page/logout.php` | `b8a87f8f6907d7dc8b720201fbce57117484b582db4104f347af9222e2f5ee2f` |
| `admin/logout.php` | `b8a87f8f6907d7dc8b720201fbce57117484b582db4104f347af9222e2f5ee2f` |
| `docs/migrations/FASA_5_AUTH_HARDENING.sql` | `1559e54a92ba8fe4826787211f443f2523b2b2966e8f5f5104677abd08390449` |

## 12. Exit Criteria

- [x] Schema dan existing token/OTP migration selesai.
- [x] MD5 incremental migration serta deadline tersedia.
- [x] Modern hash digunakan untuk password baru/change/reset.
- [x] Predictable password dihentikan bagi akaun baharu/reset.
- [x] OTP hash, expiry, one-time, attempts dan limits dilaksanakan.
- [x] Reset response generik dan tidak auto-login.
- [x] PHP session dan SSO cookie diharden.
- [x] Entire user packet dibuang daripada active cookie.
- [x] Token generator/storage/rotation diharden.
- [x] Perubahan dan rollback didokumentasikan.
- [ ] Authenticated manual smoke test selesai.
- [ ] `new_sso_cre` diganti dengan authorization code selepas consumer inventory.

## 13. Keputusan

Fasa 5 ialah **implemented with staged password migration**. Kawalan tempatan utama telah dilaksanakan dan diuji. Baki utama ialah token SSO dalam query string yang memerlukan coordinated consumer migration.
