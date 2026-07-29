# User Login MFA U4 — OTP E-mel Login

**Tarikh:** 30 Julai 2026

**Status:** `PASS / CLOSED LOCALLY / DORMANT`

**Runtime/login wiring:** `0`

## Objektif

U4 membina servis OTP e-mel khusus untuk pending login U3. Ia belum disambung
kepada endpoint atau login semasa.

## Tingkah laku yang disediakan

- request dan resend hanya bagi pending transaction yang sah;
- binding kepada sesi dan browser;
- destinasi dipulangkan dalam bentuk masked;
- destinasi dalam challenge disimpan sebagai HMAC, bukan e-mel mentah;
- OTP enam digit disimpan sebagai hash Argon2id sahaja;
- TTL, maksimum percubaan, resend cooldown dan hourly rate limit;
- resend membatalkan challenge lama dan memadam hash OTP lama;
- kegagalan sender membatalkan challenge serta memadam hash OTP;
- verify yang sah menggunakan lock dan secara atomik consume challenge serta
  menukar pending transaction kepada `VERIFIED`;
- wrong, expired, cross-session dan replay gagal secara tertutup; dan
- audit request/sent/verified/rejected tidak mengandungi OTP, e-mel penuh atau
  session ID mentah.

## Boundary penghantaran

`UserMfaEmailSenderInterface` ialah boundary penghantaran sahaja. Ujian U4
menggunakan fake sender:

- tiada SMTP/network call;
- tiada e-mel sebenar dihantar;
- OTP mentah hanya wujud sementara ketika pemanggilan sender;
- tiada OTP mentah dicetak; dan
- delivery failure menghasilkan compensation.

## Dormant boundary

- tiada endpoint request/resend/verify;
- tiada implementation persistence live;
- tiada perubahan schema staging;
- tiada wiring `lib/q_func.php`;
- mode committed kekal `OFF`;
- MyDigital ID kekal di luar skop `PASSWORD_ONLY`; dan
- Admin Step-Up tidak berubah.

## Bukti dan keputusan

```text
U4 characterization: 12/12 PASS
U4 static/lint contract: PASS
Network/SMTP calls: 0
Live database mutations: 0
Runtime activation: 0
Raw OTP output: 0
```

U4 ditutup `PASS / CLOSED` secara lokal. U5 boleh membina Microsoft
Authenticator dan self-service di atas foundation ini, tanpa mengaktifkan User
MFA atau melakukan migration staging.
