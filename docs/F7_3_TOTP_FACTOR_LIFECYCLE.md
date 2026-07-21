# F7.3 — Microsoft Authenticator TOTP Factor Lifecycle

**Change ID:** `ONEID-F7-2FA-20260720-01`  
**Owner/UAT pilot:** `0530-09`  
**Status:** SCHEMA APPLIED / FEATURE OFF

F7.3 menyediakan RFC 6238 SHA-1 (30 saat, 6 digit, toleransi ±1 time-step),
Base32, URI `otpauth`, enrollment terikat session/browser, confirmation,
anti-replay, revoke dan recovery melalui grant OTP e-mel
`SECURITY_CONFIGURATION_CHANGE` yang masih sah.

Secret hanya dipulangkan sekali semasa enrollment. Database menyimpan libsodium
secretbox ciphertext, nonce dan key version. Audit tidak merekod secret, kod
TOTP atau URI. Enrollment memerlukan password semasa dan boleh dibuat ketika
feature OFF untuk controlled bootstrap, tetapi belum disambungkan kepada route
atau UI. Maka tiada faktor sebenar didaftarkan kepada `0530-09` dalam F7.3.

## Bukti verifikasi 20 Julai 2026

- RFC 6238/Base32/anti-replay/URI: 10/10 lulus.
- Service lifecycle: encrypted storage, confirmation, verification, replay
  rejection, e-mel recovery revoke dan secret-free audit semuanya lulus.
- Isolated forward/down: lulus dan database rehearsal dipadam.
- Live schema: kolum 3/3, indeks 1/1, event audit 6/6.
- Persistence lifecycle dan replay rejection lulus; rollback meninggalkan 0 row.
- `admin_2fa_enabled=0`; feature kekal OFF.

UI enrollment, preference per-admin, QR rendering, step-up orchestrator,
enforcement action matrix, browser UAT dan activation ialah skop F7.4 dan ke
atas, bukan F7.3.
