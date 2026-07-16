# Audit User — Tukar Kata Laluan

**Tarikh audit:** 16 Julai 2026  
**Skop:** Menu pengguna `Tukar Kata Laluan` dan forced password change selepas login  
**Status:** AUDITED — UC0 HINGGA UC5 IMPLEMENTED; UC6 MANUAL UAT PENDING  
**Kaedah:** Semakan source dan database aggregate secara read-only

## Ringkasan

Aliran semasa mempunyai kawalan asas yang baik: authenticated session, CSRF,
current-password verification, validation server-side, modern password hash,
token revocation dan audit event. Risiko utama bukan pada hashing tetapi pada
workflow yang tidak atomic, forced-change yang hanya dipaksa melalui UI, tiada
rate limit current-password attempt, serta PHP session/CSRF yang tidak dirotasi
selepas perubahan.

## Aliran sebenar

```text
Menu atau forced-change modal
  -> frontend semak composition dan confirmation
  -> POST action_change_password
  -> guard authentication + CSRF
  -> verify current password daripada login_user dalam session
  -> validate minimum 12 aksara, upper/lower/number/symbol
  -> simpan password_hash dan clear password_change_required
  -> clear flag dalam PHP session
  -> revoke semua token pengguna
  -> cipta token baharu dan cookie browser semasa
  -> event 21; kegagalan verification/confirmation menggunakan event 20
```

## Kawalan tersedia

- User ID tidak diterima daripada browser; ia datang daripada authenticated session.
- Current password mesti sah.
- Polisi password dikuatkuasakan semula di backend.
- Password disimpan menggunakan `password_hash()`.
- Semua token lama direvoke dan token browser semasa diganti.
- Endpoint ialah user-only dan dilindungi CSRF.
- Audit tidak merekod password, hash, token atau OTP.

## Penemuan

| ID | Risiko | Penemuan |
|---|---|---|
| UC-A01 | Tinggi | Update password, token revocation, token creation dan audit bukan satu transaction; partial success boleh menyebabkan lockout atau audit hilang. |
| UC-A02 | Tinggi | `password_change_required` hanya membuka modal melalui JavaScript; endpoint lain dan launch SSO tidak disekat backend. |
| UC-A03 | Tinggi | PHP session ID dan CSRF tidak dirotasi selepas password berubah. |
| UC-A04 | Tinggi | Tiada rate limit/cooldown bagi percubaan current password daripada authenticated session. |
| UC-A05 | Tinggi | Hasil update password/token/audit tidak diperiksa lengkap sebelum session flag dianggap selesai. |
| UC-A06 | Sederhana/Tinggi | OTP Forgot Password aktif tidak diinvalkan selepas perubahan password. |
| UC-A07 | Sederhana | Password baharu boleh sama dengan password semasa; tiada password history. |
| UC-A08 | Sederhana | Polisi hanya composition-based; tiada common/compromised-password screening. |
| UC-A09 | Sederhana | Audit tiada correlation ID, structured reason atau token revocation count. |
| UC-A10 | Sederhana | UI menyebut 8 aksara pada initial copy tetapi frontend/backend memerlukan 12; reset indicator menggunakan ID yang tidak sepadan. |
| UC-A11 | Sederhana | Tiada loading/double-submit guard; transport error kosong dan response tidak structured. |
| UC-A12 | Sederhana | Method legacy `action_change_password()` menerima nilai password terus dan menjadi footgun walaupun bukan caller aktif. |
| UC-A13 | Rendah/Sederhana | Input tiada `autocomplete=current-password/new-password`, Caps Lock feedback atau show/hide yang terkawal. |

## Pelan cadangan

1. **UC0 — Baseline dan contract:** kunci behavior, caller, schema dan keputusan owner.
2. **UC1 — UI dan feedback:** selaraskan polisi 12 aksara, loading, double-submit,
   autocomplete, response code dan correlation.
3. **UC2 — Atomic service:** lock user, verify, validate, update, revoke token/OTP,
   create replacement token dan audit dalam satu transaction.
4. **UC3 — Forced-change enforcement:** backend hanya benarkan change password,
   status minimum dan logout apabila flag aktif.
5. **UC4 — Session dan abuse control:** session/CSRF rotation, current-password
   rate limit, cooldown dan structured rejected-attempt audit.
6. **UC5 — Password quality:** tolak password sama, tentukan history dan common/
   compromised-password policy.
7. **UC6 — UAT/rollout:** failure rollback, bypass attempt, concurrent submit,
   token/OTP/session rotation dan consumer SSO.

## Keputusan audit

Aliran sesuai sebagai baseline legacy tetapi belum cukup kukuh bagi operasi
credential sensitif. Keutamaan ialah atomic service dan enforcement backend;
pembaikan UI sahaja tidak menyelesaikan risiko bypass atau partial mutation.

## Status remediasi 16 Julai 2026

- UC0 baseline, caller map, decision register dan contract: selesai.
- UC1 UI dan operational feedback: selesai.
- UC2 atomic service, token/OTP revocation dan rollback: selesai.
- UC3 authoritative forced-change enforcement: selesai.
- UC4 session/CSRF rotation, rate limit dan forced reauthentication: selesai.
- UC5 password quality, history lima hash dan legacy writer retirement: selesai.
- UC6 automated regression: lulus; manual pilot UAT masih diperlukan.
