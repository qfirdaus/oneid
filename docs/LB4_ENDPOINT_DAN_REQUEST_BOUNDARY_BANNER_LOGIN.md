# LB4 — Endpoint dan Request Boundary Banner Login

**Status:** LOCAL PASS / SCHEMA DORMANT / UI DAN LOGIN RUNTIME BELUM DIUBAH

LB4 membuka boundary pentadbiran sahaja melalui `lib/q_func.php`. Ia tidak
mengaktifkan migration, tidak menambah UI Administrator dan tidak menukar dua
banner statik pada halaman login.

## Action dan kawalan

| Action | Jenis | Admin Step-Up purpose |
|---|---|---|
| `admin_login_banner_list` | baca | `ADMIN_ACCESS` |
| `admin_login_banner_create_draft` | mutasi | `SECURITY_CONFIGURATION_CHANGE` |
| `admin_login_banner_publish` | mutasi | `SECURITY_CONFIGURATION_CHANGE` |
| `admin_login_banner_inactivate` | mutasi | `SECURITY_CONFIGURATION_CHANGE` |
| `admin_login_banner_reorder` | mutasi | `SECURITY_CONFIGURATION_CHANGE` |
| `admin_login_banner_rollback` | mutasi | `SECURITY_CONFIGURATION_CHANGE` |

Semua action hanya menerima `POST` dan melalui shared request guard: tepat satu
action dikenali, CSRF sah, sesi Administrator sah, token SSO masih aktif, akaun
dan password aktif, kemudian grant Admin Step-Up yang sepadan. Request yang
gagal tidak sampai kepada domain service.

## Boundary data

- environment mesti datang daripada `ONEID_ENVIRONMENT`; tiada inference
  daripada HTTP Host;
- schema lima jadual LB1 diperiksa dahulu. Schema yang belum dipasang memberi
  `LB4_SCHEMA_UNAVAILABLE` dan HTTP 503;
- upload melalui pipeline LB2 dan mutasi melalui service atomic LB3;
- reorder menerima JSON list maksimum 4096 bait;
- respons senarai mengandungi immutable filename dan metadata paparan, bukan
  absolute filesystem path;
- staging kekal di `storage/runtime/login-banner-staging`, manakala fail siap
  berada di public `login_banners` dengan nama immutable.

## Respons

HTTP 200 digunakan untuk kejayaan, 404 untuk banner tiada, 409 untuk stale
version/state conflict/active limit, 422 untuk input atau transition ditolak,
503 untuk schema/persistence unavailable dan 500 untuk kegagalan tidak dijangka.
Setiap kegagalan mempunyai stable code dan `correlation_id`; body sentiasa JSON
serta `Cache-Control: no-store`.

`ApiResponseLocalizer` memetakan code LB2/LB3/LB4 kepada katalog `ms` atau `en`
tanpa membuang stable code. Antara outcome ialah loaded, draft created,
published, inactivated, reordered, rolled back, schema unavailable dan generic
failure.

## Verifikasi dan had skop

- `php tests/characterization/lb4_login_banner_admin_endpoint.php`
- `php tools/lb4_login_banner_contract.php`

Ujian membuktikan schema dormant fail-closed, penggabungan BM/English, tiada
server path terdedah, action asing ditolak dan reorder malformed tidak dimutasi.
Ujian source mengikat allowlist, keseluruhan security chain, dispatch guarded,
environment, direktori, localization serta static login fallback.

LB5 seterusnya ialah UI Administrator. Migration LB1 masih belum diaplikasi dan
public login kekal menggunakan banner statik sehingga LB6 diberi authorization.
