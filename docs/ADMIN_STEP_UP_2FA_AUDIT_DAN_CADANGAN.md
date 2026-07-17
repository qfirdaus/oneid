# Audit dan Cadangan Admin Step-Up 2FA

**Tarikh audit:** 16 Julai 2026  
**Tarikh semakan dual authentication:** 17 Julai 2026
**Status:** OWNER APPROVED / IMPLEMENTATION ON HOLD
**Skop:** Akses daripada dashboard pengguna ke bahagian Administrator  
**Keputusan semasa:** Dua kaedah diluluskan secara prinsip; pelaksanaan
ditangguhkan sehingga owner bersedia menyambung Fasa 7

## 1. Objektif

Dokumen ini merekod audit serta reka bentuk disyorkan untuk mewajibkan
authentication faktor kedua sebelum akaun berperanan admin boleh memasuki atau
menggunakan fungsi Administrator. Admin akan boleh memilih OTP melalui e-mel
atau kod TOTP melalui Microsoft Authenticator.

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
    -> pilih kaedah Admin Step-Up
       -> OTP e-mel; atau
       -> Microsoft Authenticator (TOTP)
    -> kod sah
    -> session rotation dan CSRF rotation
    -> Admin Dashboard
```

Halaman challenge menggunakan enam input digit, Verify dan tindakan kembali ke
My Account. Pilihan e-mel memaparkan alamat masked, countdown berdasarkan expiry
server dan Resend. Pilihan Microsoft Authenticator hanya dipaparkan sebagai
boleh digunakan selepas faktor TOTP berjaya didaftarkan dan disahkan.

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

### 4.5 Faktor Microsoft Authenticator melalui TOTP

Integrasi yang dipilih ialah standard TOTP, bukan Microsoft Entra push
notification. OneID menjana secret dan QR provisioning URI, admin mengimbas QR
menggunakan Microsoft Authenticator, kemudian memasukkan kod enam digit untuk
mengesahkan enrollment. Telefon tidak memerlukan internet untuk menghasilkan
kod selepas enrollment.

Cadangan table faktor:

```text
admin_mfa_factors
```

Medan minimum:

- admin user ID dan jenis faktor `TOTP`;
- secret TOTP yang dienkripsi, bukan plaintext atau hash sehala;
- key-encryption version;
- status aktif atau revoked;
- tarikh enrollment, confirmation, penggunaan terakhir dan revocation;
- label peranti yang selamat dan pilihan; dan
- actor serta correlation ID bagi perubahan faktor.

Application encryption key mesti berada di luar database dan tidak boleh
disimpan dalam repository. QR provisioning URI, secret dan kod TOTP tidak boleh
direkod dalam log. Server dan telefon mesti mempunyai masa yang tepat; verifier
hanya menerima time-window kecil dan menghalang penggunaan semula kod bagi
challenge yang sama.

Enrollment, penggantian atau revocation Authenticator ialah operasi sensitif.
Ia mesti memerlukan password semasa bersama step-up/faktor sedia ada, merotasi
session dan CSRF apabila sesuai, menghasilkan audit event serta menghantar
notifikasi keselamatan melalui e-mel.

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
| Tempoh kod TOTP | 30 saat |
| Toleransi masa TOTP | Window kecil yang didokumenkan dan diuji |

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

Kegagalan SMTP hanya menutup kaedah e-mel; admin yang telah mempunyai faktor
TOTP sah masih boleh memilih Microsoft Authenticator. Jika kedua-dua faktor
tidak tersedia atau tidak sah, akses Administrator kekal ditolak.

## 7. Audit Event Dicadangkan

- `ADMIN_2FA_REQUESTED`
- `ADMIN_2FA_SENT`
- `ADMIN_2FA_VERIFIED`
- `ADMIN_2FA_FAILED`
- `ADMIN_2FA_EXPIRED`
- `ADMIN_2FA_RATE_LIMITED`
- `ADMIN_2FA_ENABLED`
- `ADMIN_2FA_DISABLED`
- `ADMIN_MFA_METHOD_SELECTED`
- `ADMIN_TOTP_ENROLLED`
- `ADMIN_TOTP_CONFIRMED`
- `ADMIN_TOTP_VERIFIED`
- `ADMIN_TOTP_FAILED`
- `ADMIN_TOTP_REVOKED`
- `ADMIN_TOTP_RECOVERY_USED`

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

OTP e-mel dikekalkan sebagai kaedah alternatif dan recovery terkawal. Reset
Authenticator oleh service desk/DBA memerlukan pengesahan identiti, rekod actor,
sebab, correlation ID dan notifikasi kepada admin. Pendaftaran faktor baharu
tidak boleh dijadikan jalan pintas semasa challenge akses Administrator.

Emergency bypass melalui URL, hidden form field atau secret hardcoded tidak
disyorkan.

## 9. Urutan Implementasi Dicadangkan

1. Sahkan baseline, admin pilot, e-mel rasmi dan operational recovery owner.
2. Sediakan migration toggle, challenge table dan table faktor MFA.
3. Sediakan pengurusan application encryption key di luar database/repository.
4. Bina service request/verify/revoke challenge yang menyokong `EMAIL_OTP` dan
   `TOTP`.
5. Tambah page dan API guard server-side bagi semua halaman/action admin.
6. Bina enrollment, confirmation, revocation dan recovery TOTP.
7. Bina halaman pemilihan kaedah dan challenge enam digit yang konsisten.
8. Ubah pautan Administrator pada dashboard pengguna kepada entry challenge.
9. Tambah toggle khusus dalam SSO Configuration.
10. Tambah audit event, anti-replay, rate limit dan resend cooldown.
11. Tambah automated security contracts dan negative/bypass tests.
12. Jalankan UAT dengan setting disabled untuk membuktikan compatibility.
13. Enrollment Microsoft Authenticator pada satu akaun admin pilot.
14. Uji kedua-dua kaedah, recovery dan rollback sebelum enable secara rasmi.

## 10. Acceptance Criteria

- 2FA disabled mengekalkan behavior semasa.
- 2FA enabled menghalang akses terus ke semua halaman dan endpoint admin.
- OTP hanya sah untuk user, purpose, challenge dan session yang mengeluarkannya.
- Admin boleh memilih e-mel atau TOTP jika kedua-duanya tersedia.
- TOTP hanya boleh dipilih selepas enrollment dan confirmation yang sah.
- Secret TOTP dienkripsi menggunakan key di luar database dan repository.
- Kod TOTP tidak boleh direplay untuk melengkapkan challenge yang sama.
- OTP expired, consumed atau melebihi cubaan sentiasa ditolak.
- Resend dan rate limit dikuatkuasakan pada server.
- Verification berjaya merotasi session dan CSRF.
- Logout dan expiry membatalkan step-up.
- Setting enable/disable serta hasil verification direkod tanpa data sensitif.
- Kegagalan SMTP tidak memberikan akses admin.
- Kegagalan satu faktor tidak secara tersirat meluluskan atau bypass faktor lain.
- Enrollment, revocation dan recovery TOTP memerlukan kawalan serta audit khusus.
- Rollback migration dan operational recovery telah diuji.

## 11. Perkara yang Perlu Disahkan Owner

- had 5 permintaan sejam dan 10 sehari;
- Authenticator kekal pilihan setara atau menjadi kaedah utama selepas
  enrollment, dengan e-mel sebagai fallback;
- pemilik dan lokasi pengurusan application encryption key;
- senarai admin dan kesahan e-mel rasmi mereka;
- owner/on-call untuk kegagalan SMTP dan lockout;
- prosedur pengesahan identiti untuk reset faktor TOTP;
- maintenance window bagi migration dan pilot.

## 12. Keputusan Semasa

Owner telah bersetuju dengan OTP e-mel, tempoh step-up 15 minit, server-side
enforcement dan penambahan pilihan Microsoft Authenticator melalui TOTP. Selagi
step-up masih sah, kembali daripada My Account tidak memerlukan OTP baharu;
selepas expiry, logout, login semula atau pertukaran session, verification perlu
diulang.

Implementation belum dimulakan dan kekal **on hold** atas arahan owner. Dokumen
ini ialah baseline pelaksanaan Fasa 7 apabila kerja disambung semula. Tiada
Microsoft Entra push notification berada dalam skop semasa.
