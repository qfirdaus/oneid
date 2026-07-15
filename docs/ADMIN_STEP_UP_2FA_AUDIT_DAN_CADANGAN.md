# Audit dan Cadangan Admin Step-Up 2FA

**Tarikh audit:** 16 Julai 2026  
**Status:** PROPOSED / NOT IMPLEMENTED  
**Skop:** Akses daripada dashboard pengguna ke bahagian Administrator  
**Keputusan semasa:** Ditangguhkan untuk semakan dan verification owner

## 1. Objektif

Dokumen ini merekod audit serta reka bentuk disyorkan untuk mewajibkan
authentication faktor kedua melalui e-mel sebelum akaun berperanan admin boleh
memasuki atau menggunakan fungsi Administrator.

Tiada perubahan database, konfigurasi, session guard, endpoint, e-mel atau UI
dibuat sebagai sebahagian daripada audit ini.

## 2. Keadaan Sistem Semasa

Pautan `Administrator` pada dashboard pengguna membawa terus ke
`/admin/dashboard`. Guard server semasa mengesahkan bahawa pengguna:

1. telah authenticated; dan
2. mempunyai `login_user_type = 1`.

Guard itu belum memerlukan step-up authentication. Oleh itu, menambah modal
OTP hanya pada event klik tidak mencukupi kerana URL atau endpoint admin masih
boleh dicapai secara terus.

Forgot Password OTP semasa telah mempunyai kawalan berikut:

- kod enam digit dijana menggunakan sumber rawak selamat;
- hanya hash OTP disimpan;
- luput selepas lima minit;
- maksimum lima cubaan;
- cooldown permintaan selama 60 saat;
- had lima permintaan sehari;
- challenge lama dibatalkan apabila OTP baharu dicipta;
- OTP dikonsum selepas verification berjaya.

Flow itu khusus untuk reset password dan belum diikat kepada purpose admin,
session login, challenge ID atau browser tertentu. Ia tidak patut digunakan
terus sebagai Admin 2FA.

`sys_config` semasa mengandungi setting session timeout, multiple session dan
OTP email delivery. Setting `email_OTP` berkaitan penghantaran Forgot Password
OTP dan tidak patut digunakan sebagai toggle Admin 2FA.

## 3. Risiko Jika Hanya Dilaksanakan pada UI

- URL `/admin/dashboard` boleh dibuka terus.
- Endpoint POST admin boleh dipanggil tanpa melalui modal.
- Status verification dalam JavaScript, `localStorage` atau query string boleh
  diubah oleh pengguna.
- OTP reset password berpotensi diterima untuk purpose lain jika stor yang sama
  digunakan tanpa purpose binding.
- OTP daripada satu browser boleh digunakan pada session lain jika challenge
  tidak diikat kepada session.

Kesimpulan audit ialah enforcement mesti dibuat pada server untuk halaman dan
semua endpoint admin.

## 4. Reka Bentuk Disyorkan

### 4.1 Toggle khusus

Tambah setting database:

```text
admin_2fa_enabled
```

- `0`: role admin berfungsi seperti keadaan semasa.
- `1`: role admin mesti melengkapkan step-up OTP.

Toggle ini dipaparkan sebagai row baharu dalam SSO Configuration. Perubahan
setting mesti direkod dalam Audit Log. `email_OTP` tidak digunakan sebagai
pengganti toggle ini.

### 4.2 Aliran pengguna

```text
Dashboard pengguna
    -> klik Administrator
    -> Admin 2FA Challenge
    -> OTP sah
    -> session rotation dan CSRF rotation
    -> Admin Dashboard
```

Halaman challenge menggunakan enam input digit seperti UI OTP login, e-mel
masked, countdown berdasarkan expiry server, Verify, Resend dan tindakan kembali
ke My Account.

### 4.3 Enforcement server-side

Guard halaman admin perlu mengesahkan:

```text
authenticated
AND role admin
AND (Admin 2FA disabled OR step-up session masih sah)
```

Guard yang sama mesti melindungi halaman admin dan action admin dalam
`/lib/q_func`. Endpoint request dan verify Admin 2FA perlu berada pada level
authorization khas yang memerlukan authenticated admin tetapi belum memerlukan
step-up, supaya flow tidak terkunci secara rekursif.

### 4.4 Stor challenge berasingan

Cadangan table:

```text
admin_step_up_challenges
```

Medan minimum:

- challenge ID rawak;
- admin user ID;
- OTP hash;
- session-binding hash;
- purpose tetap `ADMIN_ACCESS`;
- created dan expiry timestamp;
- attempts dan resend count;
- consumed dan revoked timestamp;
- requesting IP;
- digest User-Agent.

OTP mentah, session ID mentah dan credential SMTP tidak boleh disimpan atau
direkod dalam log.

## 5. Polisi Keselamatan Dicadangkan

| Kawalan | Nilai cadangan |
|---|---:|
| Panjang OTP | 6 digit |
| Tempoh OTP | 5 minit |
| Maksimum verification | 5 cubaan |
| Resend cooldown | 60 saat |
| Had permintaan | 5 sejam, 10 sehari |
| Tempoh step-up session | 15 minit |
| Penggunaan OTP | Sekali sahaja |

Step-up dibatalkan apabila logout, login semula, role berubah, setting 2FA
berubah, authenticated session bertukar atau tempoh 15 minit tamat.

Selepas verification berjaya, session ID dan CSRF token perlu dirotasi.

## 6. Penghantaran E-mel

Gunakan e-mel khusus dengan subject seperti:

```text
OneID@UPNM - Administrator Access Verification
```

Kandungan menerangkan bahawa kod digunakan untuk akses Administrator, sah lima
minit dan perlu diabaikan/dilaporkan jika bukan diminta pengguna. UI hanya
memaparkan alamat e-mel masked.

Admin 2FA mesti fail closed:

- e-mel tiada atau tidak sah: akses admin ditolak;
- SMTP gagal: challenge tidak dianggap berjaya dihantar;
- OTP salah atau expired: akses admin kekal ditolak.

## 7. Audit Event Dicadangkan

- `ADMIN_2FA_REQUESTED`
- `ADMIN_2FA_SENT`
- `ADMIN_2FA_VERIFIED`
- `ADMIN_2FA_FAILED`
- `ADMIN_2FA_EXPIRED`
- `ADMIN_2FA_RATE_LIMITED`
- `ADMIN_2FA_ENABLED`
- `ADMIN_2FA_DISABLED`

Log tidak boleh mengandungi OTP, OTP hash, e-mel penuh, SMTP credential atau
session ID penuh.

## 8. Recovery dan Lockout

Sebelum feature diaktifkan:

- sahkan e-mel semua administrator;
- uji SMTP pada staging;
- ambil backup database;
- sediakan forward dan rollback migration;
- dokumentasikan prosedur DBA untuk menetapkan `admin_2fa_enabled = 0` jika
  sistem e-mel gagal sepenuhnya;
- jalankan pilot menggunakan satu akaun admin terkawal.

Emergency bypass melalui URL, hidden form field atau secret hardcoded tidak
disyorkan.

## 9. Urutan Implementasi Dicadangkan

1. Sediakan migration setting dan challenge table.
2. Bina service request/verify/revoke challenge.
3. Tambah page dan API guard server-side.
4. Bina halaman OTP dengan UI konsisten.
5. Ubah pautan Administrator kepada entry challenge.
6. Tambah toggle SSO Configuration.
7. Tambah audit event dan rate limit.
8. Tambah automated security contracts.
9. Jalankan UAT dengan setting disabled.
10. Jalankan controlled pilot sebelum enable secara rasmi.

## 10. Acceptance Criteria

- 2FA disabled mengekalkan behavior semasa.
- 2FA enabled menghalang akses terus ke semua halaman dan endpoint admin.
- OTP hanya sah untuk user, purpose, challenge dan session yang mengeluarkannya.
- OTP expired, consumed atau melebihi cubaan sentiasa ditolak.
- Resend dan rate limit dikuatkuasakan pada server.
- Verification berjaya merotasi session dan CSRF.
- Logout dan expiry membatalkan step-up.
- Setting enable/disable serta hasil verification direkod tanpa data sensitif.
- Kegagalan SMTP tidak memberikan akses admin.
- Rollback migration dan operational recovery telah diuji.

## 11. Perkara yang Perlu Disahkan Owner

- tempoh step-up session 15 minit;
- had 5 permintaan sejam dan 10 sehari;
- sama ada step-up perlu diulang setiap kali kembali daripada My Account atau
  hanya apabila tempoh authorization tamat;
- senarai admin dan kesahan e-mel rasmi mereka;
- owner/on-call untuk kegagalan SMTP dan lockout;
- maintenance window bagi migration dan pilot.

## 12. Keputusan Semasa

Implementation belum dibenarkan dan belum dimulakan. Dokumen ini menjadi input
untuk semakan owner sebelum task Admin Step-Up 2FA disambung pada masa akan
datang.
