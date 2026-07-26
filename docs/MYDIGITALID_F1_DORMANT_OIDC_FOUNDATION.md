# MyDigital ID Fasa 1 — Dormant OIDC Foundation

**Tarikh pelaksanaan:** 26 Julai 2026
**Environment:** OneID UAT/Staging
**Status:** COMPLETE — DORMANT, FAIL-CLOSED, NOT ACTIVATED
**Feature flag:** `ONEID_MYDID_ENABLED=false`
**Public endpoint baharu:** Tiada
**Schema/database mutation:** Tiada
**Client secret dipasang:** Tidak

## 1. Objektif

Fasa 1 menyediakan dependency, autoload, konfigurasi dan protocol client OIDC
yang diperlukan oleh fasa login akan datang. Foundation ini sengaja tidak:

- menambah butang/action login;
- menambah callback/logout endpoint;
- membaca client secret ketika disabled;
- membuat authentication request;
- mencipta session pengguna;
- menyimpan token;
- mengubah schema atau data.

## 2. Dependency Decision

Library dipilih:

```text
jumbojett/openid-connect-php v1.0.2
```

Sebab:

- library yang sama dengan sample rasmi yang diterima, tetapi sample menggunakan
  versi lama `v0.9.10`;
- versi stabil terkini yang tersedia melalui Composer ketika Fasa 1;
- menyokong Authorization Code Flow;
- menyokong discovery, JWKS dan signature validation;
- menyokong PKCE `S256`;
- mempunyai TLS peer/host verification;
- serasi dengan PHP 8.3;
- dependency lock semasa tidak mempunyai security advisory.

Dependency transitif terkunci:

| Package | Version |
|---|---|
| `jumbojett/openid-connect-php` | `v1.0.2` |
| `phpseclib/phpseclib` | `3.0.55` |
| `paragonie/constant_time_encoding` | `v3.1.3` |
| `paragonie/random_compat` | `v9.99.100` |

Composer audit:

```text
security advisories=0
abandoned packages=0
```

Dependency tidak disalin daripada sample `vendor`. Ia dipasang daripada
`composer.lock` dan generated `vendor/` tidak masuk Git.

## 3. Composer dan Autoload

Root project kini mempunyai:

```text
composer.json
composer.lock
```

Composer menetapkan:

- PHP platform `8.3.0`;
- `ext-curl`;
- `ext-json`;
- PSR-4 `OneId\App\` kepada `app/`;
- optimized autoloader;
- `block-insecure=true`;
- proprietary project license.

Install reproducible:

```bash
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
```

Production runtime lama belum diubah untuk memuatkan Composer secara global.
Fasa 3 thin endpoint akan memuatkan autoloader secara explicit. Ini mengurangkan
blast radius Fasa 1.

## 4. Committed Configuration

Default bukan secret:

```text
ONEID_MYDID_ENABLED=false
ONEID_MYDID_ISSUER=https://sso.digital-id.my/realms/upnm
ONEID_MYDID_CLIENT_ID=upnm-generic
ONEID_MYDID_REDIRECT_URI=https://oneid-uat.upnm.edu.my/auth/mydigitalid/callback.php
ONEID_MYDID_POST_LOGOUT_REDIRECT_URI=https://oneid-uat.upnm.edu.my/
ONEID_MYDID_SCOPE=openid
ONEID_MYDID_HTTP_TIMEOUT_SECONDS=12
ONEID_MYDID_PKCE_METHOD=S256
```

Secret hanya didokumenkan sebagai placeholder kosong dalam private runtime
template:

```text
ONEID_MYDID_CLIENT_SECRET=
```

Secret sebenar daripada reference tidak disalin ke:

- `composer.json`/lock;
- `config/runtime.php`;
- template;
- service;
- test;
- dokumen;
- Git.

## 5. Fail-closed Config Contract

`MyDigitalIdConfig`:

- menerima hanya boolean ketat `true/false/1/0`;
- memaksa exact issuer UPNM;
- memvalidasi bounded client ID;
- memaksa exact HTTPS callback UAT;
- melarang port, credential, query dan fragment pada redirect;
- memaksa exact post-logout root URI;
- memaksa scope `openid`;
- memaksa PKCE `S256`;
- mengehadkan timeout 3–30 saat;
- tidak membaca client secret apabila disabled;
- memerlukan secret 20–512 aksara tanpa whitespace/control character apabila
  enabled;
- menggunakan reason code tanpa memasukkan nilai sensitif dalam exception.

Runtime verification:

```text
enabled=false
issuer_match=yes
client_id=upnm-generic
redirect_match=yes
secret_loaded=no
```

## 6. Provider Metadata Contract

`MyDigitalIdProviderMetadata` menerima metadata hanya apabila:

- issuer sama tepat;
- authorization endpoint sama tepat;
- token endpoint sama tepat;
- UserInfo endpoint sama tepat;
- JWKS endpoint sama tepat;
- logout endpoint sama tepat;
- response type `code` disokong;
- grant `authorization_code` disokong;
- PKCE `S256` disokong.

Redirect atau endpoint daripada discovery tidak dipercayai secara bebas jika
berbeza daripada baseline yang diluluskan.

## 7. Dormant Protocol Client

`MyDigitalIdProtocolClient` hanya boleh dibina apabila flag enabled dan secret
sah. Jika disabled:

```text
MYDID_DISABLED
```

Client pins:

```text
flow                     = Authorization Code
response_type            = code
scope                    = openid
PKCE                     = S256
token endpoint auth      = client_secret_basic
TLS verify peer          = true
TLS verify host          = true
HTTP timeout             = 12 seconds
issuer validation        = exact match
implicit flow            = disabled by library default
redirect URL inference   = disabled
```

Endpoint provider dimasukkan secara explicit supaya runtime tidak mengikuti
endpoint yang berubah/tidak diluluskan.

## 8. Library Security Notes untuk Fasa 3

Library memenuhi foundation requirement, tetapi thin callback Fasa 3 mesti
menambah guard aplikasi sebelum memanggil `authenticate()`:

1. method dan parameter allowlist;
2. application state existence, expiry, one-use dan `hash_equals`;
3. provider error normalization;
4. callback rate limit;
5. safe session initialization;
6. correlation ID;
7. cleanup terminal.

Audit source dependency mendapati code exchange library berlaku sebelum
internal state comparison dalam callback code path. Oleh itu Fasa 3 mesti
memvalidasi state di application boundary **sebelum** library dibenarkan membuat
token request. Library validation kekal sebagai defense in depth.

Library mempunyai default clock leeway 300 saat dan tiada public setter pada
versi ini. Fasa 3 perlu merekod keputusan sama ada:

- menerima bounded library default dengan NTP monitoring; atau
- menambah post-auth stricter `iat/nbf/exp` validation di application layer.

Cadangan: tambah stricter application validation.

## 9. Fail yang Ditambah/Diubah

### Ditambah

```text
composer.json
composer.lock
app/Auth/MyDigitalId/MyDigitalIdConfigurationException.php
app/Auth/MyDigitalId/MyDigitalIdConfig.php
app/Auth/MyDigitalId/MyDigitalIdProviderMetadata.php
app/Auth/MyDigitalId/MyDigitalIdProtocolClient.php
tools/mydigitalid_f1_contract.php
tests/characterization/mydigitalid_f1_foundation.php
docs/MYDIGITALID_F1_DORMANT_OIDC_FOUNDATION.md
```

### Diubah

```text
.gitignore
config/runtime.php
docs/examples/oneid-secrets.example.php
docs/MYDIGITALID_SSO_AUDIT_DAN_PELAN_PELAKSANAAN.md
```

Tiada fail dalam `public/`, login page atau database diubah.

## 10. Source Integrity Snapshot

| Fail | SHA-256 |
|---|---|
| `composer.json` | `a58437422e06ae17c8c72886a755ebaa8688c4e85ab0cfae05251dde833a6464` |
| `composer.lock` | `1094a4620e6e0f28b5b792f85d61dabe85cdeecf6c78e458afacd6f9e88bf6f4` |
| `config/runtime.php` | `15cbc80add1fc7930ee3e1d66d64ad729463c286808ac1a0d1a0f330eff2b881` |
| `docs/examples/oneid-secrets.example.php` | `e9c5fc29704ad418ccd294562fd6d247a8a92c02067edfbb2a239c93598b3a47` |
| `MyDigitalIdConfig.php` | `ed774a6198e995126374606774b48bca07fa5dd7ca83de9f739d8e2acbcaf54a` |
| `MyDigitalIdConfigurationException.php` | `798e4657e2144325fd623c83ef24c77fe8771e496f3bff36ebc772861346299f` |
| `MyDigitalIdProtocolClient.php` | `d2fcec6588f8b0767cab6af30fcd8fd88d1262d6d50987dbce150e4ce8aa506f` |
| `MyDigitalIdProviderMetadata.php` | `1cefc57cee9a0ab9f52898d32c9bc20e2868d7c6511dd8b78e05103425c17f04` |
| `tools/mydigitalid_f1_contract.php` | `2a650cc1d1ff01cbe6716fb22c055442d736dc47e7f6650936a1d527a256769f` |
| `tests/characterization/mydigitalid_f1_foundation.php` | `90f4d877b8a31b3d8a012dc5047bfb00d84c42109e52f5b8b28b9d2b6eec9112` |

## 11. Verification

Commands:

```bash
composer validate --strict
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
composer audit --no-dev
php tools/mydigitalid_f1_contract.php
php tests/characterization/mydigitalid_f1_foundation.php
```

Results:

```text
Composer validation                     PASS
Reproducible install                    PASS
Security audit                          PASS, zero advisories
F1 static contract                      9/9 PASS
F1 configuration/protocol tests         18/18 PASS
Network calls dalam characterization    0
Runtime activation                      0
Schema mutation                         0
```

Negative tests meliputi:

- disabled client build;
- invalid enabled flag;
- issuer mismatch;
- invalid client ID;
- HTTP callback;
- callback query injection;
- invalid logout path;
- expanded scope;
- excessive timeout;
- weak PKCE `plain`;
- missing enabled secret;
- provider metadata issuer mismatch;
- provider tanpa PKCE S256.

## 12. Rollback

Fasa 1 rollback:

1. feature flag sudah `false`; tiada activation perlu dihentikan;
2. buang empat class MyDigital ID;
3. buang Composer manifest/lock jika Composer foundation hendak dibatalkan
   sepenuhnya;
4. buang generated `vendor/`;
5. buang key `ONEID_MYDID_*` daripada committed defaults/template;
6. buang F1 test/contract/doc;
7. jalankan F0 contract dan login baseline.

Tiada database rollback atau token/session cleanup diperlukan.

## 13. Gate Fasa 1

| Gate | Status |
|---|---|
| Dependency stable dan locked | PASS |
| PHP 8.3 compatibility | PASS |
| Security advisory | PASS — zero |
| PSR-4 autoload | PASS |
| Fail-closed config | PASS |
| Secret absent daripada committed source | PASS |
| Runtime feature flag false | PASS |
| Exact provider metadata | PASS |
| Authorization Code + PKCE S256 | PASS |
| TLS peer/host verification | PASS |
| No public endpoint | PASS |
| No login UI activation | PASS |
| No database/schema mutation | PASS |
| Negative tests | PASS |

## 14. Keputusan Fasa

```text
F1 COMPLETE
OIDC DEPENDENCY REPRODUCIBLY LOCKED
CONFIGURATION FAIL-CLOSED
PROTOCOL CLIENT DORMANT
FEATURE FLAG FALSE
CLIENT SECRET NOT LOADED
NO PUBLIC ENDPOINT
NO SESSION/TOKEN/DATABASE MUTATION
READY FOR FASA 2 SCHEMA DESIGN AND REHEARSAL
```
