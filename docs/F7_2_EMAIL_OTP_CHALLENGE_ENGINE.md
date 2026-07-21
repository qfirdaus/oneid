# F7.2 Email OTP Challenge Engine

**Change ID:** `ONEID-F7-2FA-20260720-01`  
**Tarikh:** 20 Julai 2026  
**Status:** IMPLEMENTED / VERIFIED / ACCEPTED — FEATURE OFF  
**UI dan admin guard:** NOT WIRED

## Skop

F7.2 membina enjin request dan verify OTP e-mel untuk tiga purpose berasingan:

- `ADMIN_ACCESS`;
- `SECURITY_CONFIGURATION_CHANGE`; dan
- `ACTIVE_SESSION_REVOCATION`.

Challenge diikat kepada canonical admin ID, hash session, digest browser,
purpose dan requesting IP. OTP enam digit hanya dihantar kepada sender; database
menyimpan Argon2id hash. Response hanya mengandungi challenge ID rawak, masked
email, expiry dan cooldown.

## Polisi dilaksanakan

- OTP sah 5 minit dan sekali guna.
- Maksimum 5 verification attempt.
- Cooldown 60 saat.
- Had 5 request sejam dan 10 sehari bagi admin/purpose.
- Had tambahan session 5 sejam dan IP/purpose 20 sejam.
- Request baharu merevoke challenge terbuka bagi admin/session/purpose sama.
- Challenge hanya boleh diverifikasi selepas `sent_at` direkod.
- SMTP failure merevoke challenge dan tidak menghasilkan grant.
- Verification berjaya mengosongkan OTP hash dan mencipta grant 15 minit secara
  atomik untuk purpose yang tepat.
- Feature `OFF` menolak request sebelum challenge atau e-mel diwujudkan.

## Transaction dan audit

Challenge serta `ADMIN_2FA_REQUESTED` ditulis atomik. Selepas SMTP menerima
e-mel, `sent_at` dan `ADMIN_2FA_SENT` ditulis dalam transaction kedua. Wrong OTP
dan audit failure tidak boleh menghasilkan grant. Successful consume, grant dan
`ADMIN_2FA_VERIFIED` berada dalam transaction sama.

Audit event 37-43 dipasang untuk requested, sent, verified, failed, expired,
rate-limited dan delivery-failed. Log tidak mengandungi OTP, hash OTP, alamat
e-mel, session ID atau challenge ID.

## Schema

Migration F7.2 menambah `sent_at` serta index rate limit admin/purpose dan
IP/purpose pada `admin_step_up_challenges`. Forward/down rehearsal lulus dalam
database sementara. Down migration mengekalkan event dictionary supaya audit
history lama terus boleh dibaca.

## Verification

- Service/negative contract: 15/15 lulus.
- Isolated schema forward/down: 2/2 lulus dan database rehearsal dibuang.
- Live schema: `sent_at=1`, index 2/2, event 7/7.
- Live feature-OFF contract mesti membuktikan tiada sender call atau row baharu.
- Live persistence transaction contract lulus dan rollback meninggalkan 0 rekod.
- F7.1 live schema dan crypto contracts kekal wajib lulus.

## Baki sebelum activation

F7.2 tidak menambah action dalam `q_func`, UI atau admin guard. Endpoint request
dan verify akan disediakan bersama authorization tier/wiring terkawal selepas
purpose matrix ditutup. `admin_2fa_enabled` kekal `0`; jangan aktifkan feature
sebelum TOTP, enforcement, recovery dan UAT fasa berikutnya lengkap.
