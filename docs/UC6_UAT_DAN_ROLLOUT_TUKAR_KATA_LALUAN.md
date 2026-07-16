# UC6 — UAT dan Rollout Tukar Kata Laluan

**Status:** AUTOMATED CONTRACT PASS — MANUAL UAT PENDING

## Automated coverage

- UC0 baseline/control contract;
- UC1 UI, double-submit dan correlated feedback;
- UC2 atomic success, wrong current password, reuse dan rollback;
- UC3 authoritative forced-change enforcement;
- UC4 rate limit, session/CSRF rotation dan forced reauthentication;
- UC5 common/context password serta history lima hash;
- PHP lint dan whitespace validation.

## Manual UAT minimum

1. Voluntary change dengan current password salah dan sahkan code/correlation.
2. Confirmation mismatch dan password common ditolak tanpa mutation.
3. Voluntary success: browser kekal login, password baharu boleh login, token
   lain tamat dan request seterusnya menggunakan CSRF baharu.
4. Forced-change account: app launch/direct POST ditolak sebelum change; selepas
   success pengguna dikembalikan ke login.
5. OTP recovery lama yang aktif tidak boleh digunakan selepas success.
6. Cubaan current password salah kelima/keenam menghasilkan HTTP 429.
7. Cuba semula salah satu daripada lima password terdahulu dan sahkan ditolak.

Jangan jalankan rate-limit UAT menggunakan akaun operasi utama. Gunakan akaun
pilot terkawal kerana cooldown ialah 15 minit.

## Keputusan manual pilot — 16 Julai 2026

| Ujian | Keputusan | Bukti |
|---|---|---|
| Current password salah | PASS | `UC2_CURRENT_PASSWORD_INVALID`, correlation `0a21fad9ca2da049`; event 20 disahkan |
| Confirmation mismatch | PASS | Ditolak pada frontend tanpa request mutation |
| Weak/composition password | PASS | Ditolak dengan penerangan 12/upper/lower/number/symbol |
| Common-password denylist khusus | PENDING | Perlu nilai yang lulus composition tetapi berada dalam denylist |
| Voluntary password change | PASS | `UC4_PASSWORD_CHANGED_SESSION_ROTATED`, correlation `2a0fd0180ecb5d2c` |
| Browser kekal login | PASS | Disahkan owner |
| Login dengan password baharu | PASS | Disahkan owner |
| Session/token lain tamat | PASS | Disahkan owner; audit merekod `tokens_revoked=1` |
| Password history | PASS | Satu history row bagi pilot selepas voluntary change |
| Forced-change | ARMED/PENDING EXECUTION | Pilot `S1TEST-20260714`; correlation arm `3e25f2fa2b7d994d` |

## Rollback

Application rollback boleh dibuat kepada commit sebelumnya. Table history ialah
expanding dan boleh kekal dormant. Jangan drop table sehingga kod lama aktif dan
DBA mengesahkan tiada rollback-forward diperlukan.
