# SC6 — Pemisahan Password Recovery

**Tarikh:** 16 Julai 2026  
**Status:** COMPLETE — AUTOMATED CONTRACT, MAILBOX DELIVERY, OTP E2E DAN RESILIENCE HOTFIX UAT PASS

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

## UAT manual

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

### Penutupan insiden mailbox — 17 Julai 2026

DBA mengesahkan punca kegagalan terdahulu ialah mailbox kehabisan storan. DBA
telah membersihkan storan dan owner kemudian mengesahkan e-mel ujian Password
Recovery diterima dalam mailbox. Kandungan diterima ialah template ujian bukan
OTP dan tidak memerlukan tindakan pengguna.

Audit aplikasi terbaru yang sepadan:

- masa SMTP acceptance: `2026-07-17 07:27:52`;
- code semantik: `SC6_TEST_EMAIL_SMTP_ACCEPTED`;
- correlation: `8feb00eba0828c18`;
- Message-ID tersedia dalam audit untuk message trace; dan
- pengesahan delivery akhir: owner/mailbox, PASS.

Kesimpulan: sambungan SMTP, acceptance dan mailbox delivery kini **PASS**.
Insiden terdahulu ditutup sebagai isu kapasiti mailbox, bukan defect aplikasi.

### Forgot Password OTP end-to-end — PASS

Owner mengesahkan flow penuh menggunakan akaun pilot berjaya pada 17 Julai
2026. Bukti server-side tanpa merekod OTP mentah:

- OTP dihantar: `2026-07-17 12:13:38`, event 9;
- expiry ditetapkan tepat lima minit selepas creation;
- OTP digunakan dengan `otp_attempts=0` dan `otp_consumed_at=2026-07-17 12:14:33`;
- password reset selesai: `2026-07-17 12:14:52`, event 21;
- OTP diterima dalam mailbox dan hanya digunakan sekali; dan
- login menggunakan password baharu disahkan berjaya oleh owner.

OTP mentah, hash OTP, password dan token tidak direkod dalam dokumen ini.

Jangan matikan delivery untuk UAT live tanpa recovery window yang dipersetujui.

### Resilience hotfix staging — PASS

Selepas insiden login/session dan loading Forgot Password pada staging, hotfix
v2.0.7 menambah bounded timeout, double-submit protection, session-lock release
semasa SMTP, correlation bagi semua recovery request dan operational feedback
yang sentiasa menutup loading state.

Owner melaksanakan UAT selepas deployment dan PHP-FPM reload:

- ID login kosong: inline validation PASS;
- password kosong: inline validation PASS;
- credential salah: inline failure feedback PASS;
- credential sah: login PASS tanpa refresh berulang;
- logout/refresh: PASS;
- Password Recovery selesai dalam tempoh timeout dan SweetAlert memaparkan
  Reference;
- e-mel OTP diterima oleh owner.

Bukti audit shared database:

- masa: `2026-07-17 17:53:52`;
- event: 9;
- outcome: `smtp_accepted`;
- pilot: `S1TEST-20260714`;
- correlation: `0af02512003452fc`;
- identifier hanya direkod sebagai SHA-256.

Automated resilience contract: 11/11 PASS. UAT hotfix login dan Password
Recovery: **COMPLETE**.
