# SC6 — Pemisahan Password Recovery

**Tarikh:** 16 Julai 2026  
**Status:** IMPLEMENTED — AUTOMATED CONTRACT PASS; SMTP ACCEPTED, MAILBOX DELIVERY FAILED/PENDING INVESTIGATION

## Hasil

- `email_OTP` dinamakan semula kepada `password_reset_email_enabled` melalui
  migration reversible.
- SSO service dan panel kini hanya mengurus token lifetime dan multiple token.
- Password Recovery mempunyai panel, endpoint, validation dan audit tersendiri.
- SMTP health memaparkan configuration readiness tanpa mendedahkan credential.
- Admin boleh menghantar e-mel ujian bukan OTP ke mailbox UAT yang dimasukkan.
- Alamat penerima tidak dimasukkan dalam audit; hanya keputusan dan correlation.
- Setting ini tidak digunakan oleh login MFA atau Admin Step-Up 2FA.

## Fail-closed policy

Tiada saluran manual rasmi dalam sistem. Apabila delivery OFF, e-mel pengguna
tidak sah, atau SMTP gagal, flow awam kekal memberi respons generik tetapi tidak
membuka reset session yang boleh digunakan. Challenge yang sempat dicipta ketika
delivery gagal terus diinvalkan dan event 35 direkod.

## Audit

| ID | Event |
|---:|---|
| 33 | `ADMIN_PASSWORD_RECOVERY_POLICY_UPDATED` |
| 34 | `ADMIN_PASSWORD_RECOVERY_TEST_EMAIL` |
| 35 | `PASSWORD_RECOVERY_DELIVERY_FAILED` |

## UAT manual berbaki

### Bukti SMTP test

- Masa audit: `2026-07-16 12:18:12` (Asia/Kuala_Lumpur).
- Keputusan aplikasi ketika ujian: `SC6_TEST_EMAIL_SENT` (kini diperjelas
  sebagai `SC6_TEST_EMAIL_SMTP_ACCEPTED`).
- Correlation ID: `27cb2dc64a8939a7`.
- Audit event 34 ditemui dengan `result=sent` dan correlation yang sepadan.
- Audit tidak merekod alamat penerima atau credential SMTP.

Keputusan ini hanya mengesahkan SMTP server menerima mesej daripada aplikasi.
Pemilik melaporkan mesej tidak diterima di mailbox UAT `n***@upnm.edu.my`. Oleh itu,
mailbox delivery **belum lulus** dan perlu semakan Junk/Quarantine serta message
trace oleh pentadbir Microsoft 365. Aplikasi tidak mempunyai akses kepada
mailbox atau delivery trace.

Ujian kedua pada `2026-07-16 12:23:00` turut diterima SMTP tetapi tidak tiba di
mailbox. Correlation: `6b5072db43c5d1b8`. Kegagalan berulang ini diklasifikasikan
sebagai isu downstream mail delivery, bukan kegagalan sambungan/authentication
SMTP aplikasi. Ujian berikutnya akan memaparkan dan mengaudit `Message-ID` untuk
rujukan message trace.

1. Sahkan e-mel ujian diterima dalam mailbox UAT.
2. Jalankan satu Forgot Password menggunakan pengguna UAT bere-mel sah.
3. Sahkan OTP diterima, boleh digunakan sekali dan reset berjaya.

Jangan matikan delivery untuk UAT live tanpa recovery window yang dipersetujui.
