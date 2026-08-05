# F7.4 — Server-Side Admin Step-Up Enforcement

**Change ID:** `ONEID-F7-2FA-20260720-01`  
**Owner/UAT pilot:** `0530-09`  
**Status:** IMPLEMENTED / VERIFIED / FEATURE OFF

> **Nota status 5 Ogos 2026:** `FEATURE OFF` ialah snapshot penutupan F7.4 pada
> 20 Julai, bukan keadaan selepas F7.6; pilot Step-Up kemudiannya diaktifkan dan
> diterima. Revalidation 5 Ogos pada awal audit menghasilkan 11/14 kerana
> inventory action telah berubah. Expectation baseline telah direconcile kepada
> 72 action. Selepas dua action AS3 ditambah, inventory menjadi 74 action (16
> security configuration, 56 ordinary Admin, 2 session revocation) dan
> contract kembali 14/14.

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
`ADMIN_ACCESS`. Snapshot code 5 Ogos selepas wiring AS3 mempunyai 74 action admin dalam
`oneid_q_func_action_map()`:

- `SECURITY_CONFIGURATION_CHANGE` (16): empat configuration action asal,
  mutation locale/metadata, polisi/exemption User MFA dan enam mutation Login
  Banner.
- `ADMIN_ACCESS` (56): semua action admin lain, termasuk endpoint read-only.
- `ACTIVE_SESSION_REVOCATION` (2): endpoint Preview dan Apply targeted revoke.
  Skop pilot
  terkini dirujuk dalam
  `AS3_CONTROLLED_ACTIVE_SESSION_REVOCATION_AUDIT_20260805.md`.

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
