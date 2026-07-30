# U8 — Enrollment dan pilot User Login MFA

**Tarikh pembangunan:** 30 Julai 2026
**Status kod lokal:** `IMPLEMENTED / TESTED / NOT DEPLOYED`
**Status staging:** `U0–U7 DEPLOYED / USER MFA OFF`

## Skop siap

- keputusan primary-auth dibuat sebelum token SSO dan authenticated session;
- `OFF` serta `ENROLLMENT` tidak mencabar login password;
- `PILOT_ENFORCED` hanya mencabar allowlist private;
- runtime dan polisi database mesti sepadan atau login fail-closed;
- OTP e-mel request/resend/verify menggunakan pending session binding;
- token, cookie dan session hanya diwujudkan selepas factor verified;
- Microsoft Authenticator self-enroll, confirm dan self-revoke;
- QR dijana same-origin, terikat kepada user/session/browser dan `no-store`;
- schema pilot menyimpan ID di database sahaja, bukan dalam Git;
- kategori pilot dibataskan kepada staf, pensyarah, pelajar tempatan dan
  antarabangsa; dan
- readiness serta rollback schema tool disediakan.

## Gate deployment

1. Dapatkan kelulusan owner untuk commit/push perubahan U8.
2. Pull commit tepat ke staging tetapi kekalkan runtime `OFF`.
3. Jalankan `php tools/user_login_mfa_u8_suite.php`.
4. Apply schema pilot hanya dalam change window dan dengan confirmation khusus.
5. Ulang `php tools/user_login_mfa_u8_readiness.php`.
6. Sediakan keyring staging sebelum `totp_enabled=1`.
7. Rekod 5–10 pilot (sasaran 8) secara private.
8. Dapatkan kelulusan berasingan sebelum menukar database/runtime kepada
   `ENROLLMENT`.
9. Uji self-enrollment; login biasa mesti kekal tanpa cabaran.
10. Dapatkan kelulusan berasingan sebelum `PILOT_ENFORCED`.

## Fail pilot private

Salin contoh `docs/USER_LOGIN_MFA_U8_PRIVATE_PILOT_PLAN.example.json` kepada:

```text
.private/user_mfa_pilot_plan.json
```

Tetapkan permission `0600`. Isi dua Administrator berlainan sebagai actor dan
verifier serta 5–10 pilot (sasaran 8). Tool hanya melaporkan bilangan,
kategori dan readiness; ID serta e-mel tidak dicetak.

Medan ID menerima canonical `u_id` atau identifier login biasa daripada
`u_id`, `data2`, `data3` atau `data8`. Setiap identifier mesti resolve kepada
tepat satu akaun; padanan kosong, duplicate atau ambiguous akan ditolak.

Preflight:

```bash
php tools/user_login_mfa_u8_pilot_plan.php --check
```

Apply hanya semasa mode `OFF`:

```bash
ONEID_USER_MFA_U8_PILOT_CONFIRMATION='APPLY PRIVATE USER MFA PILOT PLAN WITH MODE OFF' \
php tools/user_login_mfa_u8_pilot_plan.php --apply
```

## Transition polisi

Runtime mesti ditetapkan kepada target terlebih dahulu. Mismatch sementara
adalah fail-closed dan perlu diminimumkan dalam change window. Transition
memerlukan actor, verifier, reference, reason dan typed confirmation. Contoh
target enrollment:

```bash
ONEID_USER_MFA_U8_ACTOR='ADMIN_ID' \
ONEID_USER_MFA_U8_VERIFIER='SECOND_ADMIN_ID' \
ONEID_USER_MFA_U8_POLICY_REFERENCE='ONEID-USER-MFA-U8-ENROLLMENT-YYYYMMDD' \
ONEID_USER_MFA_U8_POLICY_REASON='Controlled enrollment for approved U8 pilot' \
ONEID_USER_MFA_U8_POLICY_CONFIRMATION='SET USER MFA POLICY ENROLLMENT' \
php tools/user_login_mfa_u8_policy_transition.php ENROLLMENT
```

Rollback menggunakan target dan confirmation `OFF`; selepas database kembali
`OFF`, runtime juga mesti segera dikembalikan kepada `OFF` dan authorization
`false`.

Global `ENFORCED` dan management activation kekal di luar U8.

## Rollback

- tukar runtime dan polisi database kembali `OFF`;
- jangan buang schema semasa terdapat pilot row;
- revoke pending transaction/challenge jika pilot dihentikan; dan
- sahkan login password serta MyDigital ID kembali kepada baseline.

Tiada nilai secret, OTP, token, alamat e-mel atau senarai pilot boleh dimasukkan
dalam dokumen/Git.
