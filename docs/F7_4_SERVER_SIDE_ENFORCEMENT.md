# F7.4 — Server-Side Admin Step-Up Enforcement

**Change ID:** `ONEID-F7-2FA-20260720-01`  
**Owner/UAT pilot:** `0530-09`  
**Status:** IMPLEMENTED / VERIFIED / FEATURE OFF

## Authorization boundary

Guard menilai state database pada setiap request. Akses hanya dibenarkan apabila
admin masih aktif dan berperanan admin serta sama ada `admin_2fa_enabled=0`, atau
terdapat grant yang belum luput dan belum direvoke untuk kombinasi tepat admin,
hash session, browser digest dan purpose. Session/browser lain, purpose lain,
grant luput, role berubah dan akaun tidak aktif gagal secara fail-closed.

Penolakan direkod sebagai event `ADMIN_2FA_FAILED` dengan reason allowlist dan
correlation ID. Session ID mentah, cookie, OTP, TOTP dan secret tidak direkod.
Jika audit gagal, akses turut ditolak.

## Purpose matrix lengkap

Dua halaman `admin/dashboard.php` dan `admin/user_list.php` memerlukan
`ADMIN_ACCESS`. Daripada 48 action admin dalam `oneid_q_func_action_map()`:

- `SECURITY_CONFIGURATION_CHANGE` (4): `update_password_recovery`, `test_password_recovery_email`,
  `preview_configuration_update`, `update_configuration`.
- `ADMIN_ACCESS` (44): semua action admin lain, termasuk tiga endpoint read-only
  bagi settings, configuration history dan password recovery settings.
- `ACTIVE_SESSION_REVOCATION`: tiada mutation endpoint tersedia lagi; endpoint
  revoke akan diwajibkan menggunakan purpose ini apabila dibina.

Empat endpoint challenge yang dirizabkan — `admin_step_up_status`,
`admin_step_up_request_email`, `admin_step_up_verify_email` dan
`admin_step_up_verify_totp` — berada pada tier authenticated-admin khas tanpa
grant supaya challenge tidak terkunci secara rekursif. Endpoint ini belum
diimplementasi dalam `q_func.php`; request tanpa handler tidak memberi akses atau
mutation. Enrollment/revoke/preference TOTP sengaja tidak dikecualikan.

## Bukti verifikasi 20 Julai 2026

- Enforcement decision/matrix/direct page wiring: 13/13 lulus.
- Subprocess direct-bypass: 5/5 lulus untuk required, purpose mismatch,
  exact-purpose grant, feature-OFF compatibility dan bounded challenge tier.
- Persistence authorization: tiga purpose kekal terasing, browser lain ditolak,
  dan rollback meninggalkan 0 row.
- Live feature-OFF: akses dibenarkan seperti baseline, grant count tidak berubah.
- Regression F7.1–F7.3 dan document contract lulus.

Keadaan live akhir kekal `admin_2fa_enabled=0` dengan 0 factor, preference,
challenge dan grant. F7.4 tidak mengaktifkan 2FA dan tidak menyediakan UI;
challenge UI, endpoint wiring, session/CSRF rotation dan controlled bootstrap
ialah F7.5.
