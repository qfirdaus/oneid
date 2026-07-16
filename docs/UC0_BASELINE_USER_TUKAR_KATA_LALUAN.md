# UC0 — Baseline User Tukar Kata Laluan

**Tarikh snapshot:** 16 Julai 2026  
**Commit baseline:** `7504ab7`  
**Status:** COMPLETE — TECHNICAL BASELINE; RECOMMENDED OWNER DECISIONS APPROVED  
**Mutation:** Tiada

## Sempadan UC0

UC0 merekod source, schema, aggregate state, caller dan behavior semasa. Ia tidak
mengubah password, session, token, OTP, schema, audit event atau UI.

## Live aggregate baseline

| Pemerhatian | Snapshot |
|---|---:|
| Akaun aktif | 6,417 |
| Akaun aktif dengan `password_change_required=1` | 2 |
| Token aktif | 2 |

Nilai boleh berubah selepas snapshot dan bukan acceptance threshold. Tiada ID,
password/hash, token, OTP atau data profil diambil.

## Schema berkaitan

| Column | Jenis | Null |
|---|---|---|
| `u_id` | `varchar(20)` | Tidak |
| `u_password` | `varchar(255)` | Tidak |
| `password_change_required` | `tinyint(1)` | Tidak |
| `avail_status` | `int` | Tidak |

Audit event semasa ialah `20=SSO_CHANGE_PWD_ATTEMPT` dan
`21=SSO_CHANGE_PWD_SUCCESS`.

## Source evidence

| Fail | SHA-256 |
|---|---|
| `page/dashboard.php` | `c692a1853cc26a605deb267f4177702b16d9221a39cb4fb13de6b77b8520d9cf` |
| `lib/q_func.php` | `97bc6768ab2600110c71df84a5c20fe17916762879e4e644909b27d9f9b574c8` |
| `lib/Database.php` | `ef7bd32d521932093e0ad4bd31d9f671364ec808d95005f0354780f2d51e53de` |
| `lib/request_security.php` | `794a7589d8f230af3117322aff80211dd0258afaad04ec8abee13335a9cf7341` |
| `lib/session_security.php` | `4f6ae087d3f20d2e606b4444129c1b9f6f12c95d5d8b1e7e05ba50fdbeec3a7b` |
| `lib/auth_security.php` | `69e550f9e6cb4a8f5e8b891c76c0678bc9183c4d83283fbc43e390c9f2565f54` |

## Behavior yang dikunci contract

- kedua-dua action diklasifikasikan user-only dan melalui CSRF;
- forced-change semasa hanya UI-driven;
- current password diverifikasi di backend;
- polisi 12 aksara/composition dikuatkuasakan server-side;
- password menggunakan modern hash;
- token lama direvoke dan replacement token dicipta;
- event 20/21 digunakan;
- workflow belum transaction-bound;
- PHP session/CSRF dan OTP aktif belum dirotasi/invalidate; dan
- method legacy password writer masih wujud.

## Artefak UC0

- `docs/AUDIT_USER_TUKAR_KATA_LALUAN.md`
- `docs/UC0_USER_PASSWORD_CHANGE_CALLER_MAP.tsv`
- `docs/UC0_USER_PASSWORD_CHANGE_DECISION_REGISTER.tsv`
- `tools/uc0_user_password_change_contract.php`

Owner mengarahkan pelaksanaan UC2 dan fasa berikutnya pada 16 Julai 2026;
keputusan recommended direkod sebagai approved/implemented dalam register.
