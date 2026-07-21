# OneID v2.5.4 — Admin Step-Up 2FA

**Versi:** 2.5.4
**Tarikh release:** 22 Julai 2026
**Change ID:** `ONEID-F7-2FA-20260720-01`
**Status:** Staging accepted; production belum diaktifkan

## Ringkasan

Release ini menyatukan semua perubahan Fasa 7 Admin 2FA yang telah dibina,
diuji dan diterima dalam staging. Akses Administrator dan perubahan konfigurasi
sensitif kini menggunakan step-up authentication melalui OTP e-mel atau
Microsoft Authenticator (TOTP).

## Perubahan utama

- purpose berasingan untuk `ADMIN_ACCESS`, `SECURITY_CONFIGURATION_CHANGE` dan
  persediaan masa hadapan `ACTIVE_SESSION_REVOCATION`;
- OTP e-mel dengan expiry, resend cooldown, rate limit, replay protection,
  audit dan penghantaran melalui alamat rasmi admin;
- Microsoft Authenticator dengan enrollment QR lokal, setup key, confirmation,
  verification RFC 6238 dan anti-replay;
- TOTP secret dienkripsi menggunakan Sodium dan versioned keyring di luar
  repository, database configuration serta web root;
- factor lifecycle `PENDING`, `ACTIVE` dan `REVOKED`, termasuk reset dan
  enrollment semula;
- preference faktor bagi setiap admin tanpa mencipta kategori pengguna baharu;
- grant step-up terikat kepada admin, session, browser dan exact purpose;
- session ID dan CSRF rotation selepas verification dengan grant rebind
  server-side;
- controlled bootstrap dengan current password, Change ID, typed confirmation,
  configuration revision dan audit transaksi;
- lifetime grant boleh dikonfigurasi dengan default 15 minit bagi grant baharu;
- UI factor-first, status configuration, recovery flow dan local QR;
- reset confirmation menggunakan SweetAlert, bukan native browser confirm;
- issuer boleh ditetapkan mengikut environment: Local, UAT atau Production;
- API mengeluarkan diagnosis keyring yang selamat tanpa mendedahkan path,
  encryption key, TOTP secret atau OTP; dan
- runbook staging/production, monitoring F7.6, hard-stop serta rollback telah
  didokumenkan.

## Hasil staging

Functional UI dan service flow telah disahkan, termasuk OTP e-mel, enrollment,
QR, confirmation, TOTP login, reset/revoke dan enrollment semula. Observation
24 jam selesai tanpa hard-stop dan F7.6 diterima. Monitoring diteruskan sebagai
aktiviti operasi berkala.

Isu custody keyring staging turut diselesaikan. PHP-FPM berjalan sebagai
`www-data`, maka keyring operasi diletakkan pada
`/etc/oneid/keys/admin-totp-keyring.php` dengan directory `0750`, fail `0640`
dan ownership `root:www-data`.

## Batas release

- Production belum diaktifkan dan memerlukan change/approval berasingan.
- Keyring staging tidak boleh digunakan dalam production.
- Active-session revocation dan scheduler activation kekal task pembangunan
  berasingan.
- `.private/runtime.php`, credential, OTP dan kandungan keyring tidak termasuk
  dalam Git.

## Rujukan operasi

- `ADMIN_STEP_UP_2FA_AUDIT_DAN_CADANGAN.md`
- `F7_0_CHANGE_RECORD_ONEID-F7-2FA-20260720-01.md`
- `F7_5_UI_BOOTSTRAP_DAN_RECOVERY.md`
- `F7_6_UAT_CONTROLLED_ROLLOUT_DAN_OBSERVATION.md`
- `F7_ADMIN_2FA_STAGING_DAN_PRODUCTION_RUNBOOK.md`
