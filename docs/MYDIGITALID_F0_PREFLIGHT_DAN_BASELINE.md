# MyDigital ID Fasa 0 — Preflight dan Baseline

> **Status supersession — 26 Julai 2026:** Baseline ini kekal sebagai rekod
> pre-implementation. Semua gate F0 telah lulus di staging. Status semasa,
> evidence deployment dan baki kerja dirujuk dalam
> `MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md`.

**Tarikh pelaksanaan:** 26 Julai 2026
**Environment:** OneID UAT/Staging
**Domain:** `https://oneid-uat.upnm.edu.my/`
**Status:** PASS WITH RECORDED BASELINE DEBT
**Mutation aplikasi/database:** Tiada
**PII/secret dalam evidence:** Tiada

## 1. Objektif

Fasa 0 membuktikan readiness minimum sebelum dependency, konfigurasi OIDC,
schema atau endpoint MyDigital ID dibina. Semua database query ialah `SELECT`
atau metadata read-only. Tiada token, secret atau NRIC penuh dikeluarkan oleh
tool/evidence.

## 2. Keputusan Ringkas

| Gate | Keputusan |
|---|---|
| PHP/runtime baseline | PASS |
| MyDigital ID masih dormant | PASS |
| Password/session/token baseline tersedia | PASS |
| Mapping NRIC staf/pelajar | PASS |
| Provider DNS dan TLS | PASS |
| OIDC discovery dan issuer | PASS |
| Authorization Code Flow | PASS |
| PKCE S256 advertised | PASS |
| UAT DNS/VPN/TLS | PASS |
| NTP/timezone | PASS |
| Database read-only connectivity | PASS |
| Pilot reference `0530-09` | PASS |
| Active eligible NRIC collision | PASS — zero collision |
| Callback URI recognized by provider | PASS BY ONE-TIME AUTHORIZATION PREFLIGHT |
| Existing unrelated/stale contracts | RECORDED DEBT |
| Reference folder Git protection | PASS — scoped ignore |

Fasa 0 tidak memasang library, tidak menyimpan client secret dalam config
committed, tidak menambah table dan tidak mengaktifkan login.

## 3. Source Baseline

| Fail | SHA-256 |
|---|---|
| `index.php` | `44c5c58ce0a0641442650c0449bb3b92a3eaddde728ff5d20fe1e43d82903bc8` |
| `public/index.php` | `e550ce62d12d05bd22308e1d6f665cc00f7c48986627949f59bc973150f817b7` |
| `lib/session_security.php` | `9cd05fc10d1de6d23f7b7ca2df51afc4001642407c6cb4d9b207c12bb2d6ce9f` |
| `lib/request_security.php` | `bf3a5191e32f0ed3b78aba47ea5ea479fd8c72703d637a4f733f57153584fb5b` |
| `lib/Database.php` | `a57620a59033e3a65f8a209250182f931ab982ef47d895493d1a62cb8cb70574` |
| `lib/SSO_IDP_INC.php` | `c59bd3fc34bbe6fee8a9e2c7b69e187f310143f2731c10bc91028820ef5220eb` |
| `lib/config.php` | `07c0598d03eddc9301f60a0cdc93646b69b1e4a2afe117a4ecbb594834752344` |
| `lib/secrets.php` | `cc4dcc041f525c9006a7a920b18e2c4a1914cbb495d831d1cf8c0f18bda099e5` |
| `config/runtime.php` | `e948b8f68c14f26dcbde10956254250cc2161a2f3aebd211e72ab6e883e86845` |
| `tools/login_mydigitalid_logo_contract.php` | `44be3707accd5db38a01f763b61c363f1b9db1d01a0923b4a5ed442dc5731e40` |
| `tools/mydigitalid_f0_contract.php` | `1727dc2e6b6706d933add651220152749bc132efddb5c4ffc0fd332c6d5cb7c2` |
| `tools/mydigitalid_f0_preflight.php` | `4b52ad070a698e091637f8e286e1f0ee8c8a624cf94d0f47baf4df4152c3680b` |

Hash tool ialah snapshot selepas contract F0 diselaraskan supaya terus
mengesahkan keadaan fail-closed apabila foundation dormant Fasa 1 wujud.

## 4. Static Baseline Contract

Arahan:

```bash
php tools/mydigitalid_f0_contract.php
```

Keputusan:

```text
RESULT checks=10 failures=0 mutation_statements=0
```

Contract mengesahkan:

- PHP 8.3 atau lebih baharu;
- `public/index.php` kekal thin wrapper;
- visual MyDigital ID belum menjadi action login;
- belum ada runtime activation MyDigital ID;
- secret boundary environment/private file tersedia;
- authenticated session meregenerate session ID;
- password authentication dan local token policy masih tersedia;
- staf menggunakan `data4` bagi NRIC;
- pelajar menggunakan `data2` bagi NRIC;
- reference dan endpoint MyDigital ID tiada dalam public root.

## 5. Network, TLS dan OIDC Preflight

Arahan:

```bash
php tools/mydigitalid_f0_preflight.php
```

Keputusan keseluruhan:

```text
RESULT checks=19 failures=0 mutation_statements=0 raw_nric_output=0
```

Pemerhatian:

- `sso.digital-id.my` resolve dari host UAT;
- discovery endpoint membalas HTTP 200;
- TLS verification result ialah `0`;
- issuer sama tepat dengan
  `https://sso.digital-id.my/realms/upnm`;
- authorization, token, UserInfo, JWKS dan logout endpoint sama dengan
  baseline;
- provider mengiklankan Authorization Code Flow;
- provider mengiklankan PKCE `S256`;
- `oneid-uat.upnm.edu.my` resolve dari host melalui alamat rangkaian UAT/VPN;
- halaman UAT membalas HTTP 200 dengan TLS verification result `0`;
- server menggunakan `Asia/Kuala_Lumpur`;
- NTP synchronized.

IP address provider/UAT tidak dijadikan contract kerana ia boleh berubah.

## 6. Callback Registration Evidence

Satu authorization preflight tanpa credential pengguna telah dibuat dengan:

```text
client_id=upnm-generic
redirect_uri=https://oneid-uat.upnm.edu.my/auth/mydigitalid/callback.php
response_type=code
scope=openid
PKCE=S256
```

Provider membalas HTTP `303` dan meneruskan flow kepada broker MyDigital ID,
bukannya memulangkan invalid redirect error. Ini ialah bukti operasi bahawa
client dan redirect URI tersebut dikenali pada tarikh snapshot.

Provider session code, tab ID dan transient redirect data tidak disalin ke
dokumen atau repository.

Pengesahan rasmi konfigurasi provider masih digalakkan sebelum production,
tetapi callback registration bukan lagi blocker UAT semasa.

## 7. Database dan Pilot Read-only Audit

Database connection berjaya. Tool tidak mengeluarkan canonical user ID, nama,
NRIC atau credential.

Pilot reference yang diberikan owner:

```text
0530-09
```

Keputusan masked:

| Semakan | Keputusan |
|---|---|
| Staff reference row count | 1 |
| Akaun aktif | Ya |
| NRIC pada `data4` | Bentuk normalized 12 digit |
| Cross-field account match bagi pilot NRIC | Tepat 1 |

Ini membuktikan pilot positif sesuai untuk UAT tanpa mendedahkan NRIC sebenar.

## 8. Population dan Collision Baseline

### 8.1 Active eligible population

Candidate definition:

- staf aktif: NRIC 12 digit daripada `data4`;
- pelajar aktif: NRIC 12 digit daripada `data2`.

Keputusan:

| Metric | Count |
|---|---:|
| Active eligible identity rows | 6,512 |
| Active unique identities | 6,512 |
| Active duplicate identity groups | 0 |

Maka exact-one matching terhadap **akaun aktif yang layak** feasible pada
snapshot ini.

### 8.2 Historical/all-account population

| Metric | Count |
|---|---:|
| Identity groups | 8,945 |
| Duplicate identity groups | 671 |
| Rows dalam duplicate groups | 1,344 |

Finding ini tidak bermaksud active collision; active population mempunyai sifar
collision. Ia menunjukkan rekod sejarah/tidak aktif boleh berkongsi NRIC,
kemungkinan disebabkan lifecycle atau akaun lama.

Keputusan reka bentuk:

1. initial candidate query hanya memilih akaun aktif dan identity shape sah;
2. keputusan mesti tepat satu;
3. zero/multiple active match ditolak;
4. historical duplicates direkod sebagai risk signal tetapi tidak boleh
   menyebabkan arbitrary first-row selection;
5. subsequent login menggunakan `issuer + sub` link dan memeriksa akaun masih
   aktif serta NRIC masih konsisten.

Jika owner mahu semua historical duplicate turut block walaupun hanya satu akaun
aktif, keputusan itu perlu dibuat secara eksplisit sebelum Fasa 4 kerana ia akan
menjejaskan sekurang-kurangnya populasi historical yang direkodkan.

## 9. Password/Login Baseline dan Existing Debt

`tools/mydigitalid_f0_contract.php` menjadi acceptance baseline khusus task ini
dan lulus. Beberapa contract lama turut dijalankan untuk discovery:

### 9.1 `login_mydigitalid_logo_contract.php`

Empat daripada lima check lulus. Check teks disabled gagal kerana contract
lama mencari literal Bahasa Melayu dalam HTML, sedangkan UI kini menggunakan
`oneid_translate('login.integration_disabled')`. Translation dan
`pointer-events: none` masih wujud. Ini stale test selepas multilanguage work,
bukan runtime failure MyDigital ID.

### 9.2 `configuration_contract.php`

Contract lama menjangka 66 key tetapi committed template kini mempunyai 92 key.
Configuration audit mengesahkan semua 66 key lama wujud tetapi melaporkan key
baharu sebagai unknown. Ini drift registry daripada task terdahulu, bukan
credential/config failure MyDigital ID.

### 9.3 `restructure_smoke.php`

Surface utama lulus. Legacy `/idms.php` dan `/skp_api.php` membalas 404 pada
deployment semasa. Kedua-duanya ialah existing compatibility/integration debt
dan bukan endpoint MyDigital ID.

Debt tersebut perlu dibaiki dalam scope housekeeping berasingan atau sebelum
full regression gate jika owner mahu semua suite lama hijau. Ia tidak mengubah
keputusan Fasa 0 kerana contract khusus MyDigital ID dan preflight lulus.

## 10. Privacy dan Zero-mutation Evidence

Tool Fasa 0:

- tidak mempunyai operasi database mutation;
- hanya mengeluarkan counts dan boolean status;
- tidak mengeluarkan raw NRIC;
- tidak mengeluarkan nama/canonical user ID;
- tidak membaca atau mencetak client secret;
- tidak menyimpan provider response yang mengandungi user claims;
- tidak membuat schema change.

Temporary discovery/header files yang digunakan ketika manual audit berada di
`/tmp`, bukan repository, dan tidak menjadi runtime dependency.

Folder `resources/references/mydigital-id/` dilindungi oleh rule `.gitignore`
yang scoped. Fail fizikal tidak dipadam dalam Fasa 0 dan masih boleh digunakan
sebagai rujukan tempatan sehingga task selesai.

## 11. Gate Fasa 0

| Gate | Status | Evidence |
|---|---|---|
| Requirement baseline | PASS | Dokumen audit/pelan |
| Static runtime baseline | PASS | 10/10 |
| Provider discovery/TLS | PASS | HTTP 200, TLS verify 0 |
| OIDC flow/PKCE capability | PASS | Advertised oleh discovery |
| UAT DNS/VPN/TLS | PASS | HTTP 200, TLS verify 0 |
| NTP/timezone | PASS | Synchronized, Asia/Kuala_Lumpur |
| Pilot exact-one active match | PASS | Masked read-only evidence |
| Active population collision | PASS | 6,512 rows, 6,512 unique |
| Callback recognition | PASS | Authorization preflight HTTP 303 |
| Password/local session baseline | PASS | Static F0 contract |
| Existing stale test debt | RECORDED | Bukan MyDigital ID blocker |
| Reference credential commit prevention | PASS | Scoped `.gitignore`; tiada pemadaman |
| Runtime/schema mutation | NONE | Tool result |

## 12. Keputusan Fasa

Fasa 0 selesai dengan status:

```text
F0 PASS WITH RECORDED BASELINE DEBT
NETWORK AND PROVIDER READY
PILOT READY
ACTIVE EXACT-ONE MATCHING FEASIBLE
CALLBACK RECOGNIZED
NO RUNTIME OR DATABASE MUTATION
READY FOR FASA 1 DORMANT FOUNDATION
```

Fasa seterusnya ialah Fasa 1: dependency, configuration dan protocol client
dormant. Feature flag mesti kekal `false`, dan client secret mesti dipindahkan
ke private runtime store tanpa dimasukkan ke Git.
