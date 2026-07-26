# MyDigital ID Fasa 6 — Automated Security dan Regression Suite

> **Status supersession — 26 Julai 2026:** Suite telah diperluas kepada 23
> command termasuk F7 dan lulus di staging dengan `failures=0`,
> `local_mutations=0` dan `feature_activation=0`. Residual manual acceptance
> kekal direkod dalam `MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md`.

Tarikh: 26 Julai 2026
Status historical: local pre-push lengkap; staging suite dan pilot positif lulus

## One-command suite

Jalankan dari project root:

```bash
php tools/mydigitalid_f6_security_suite.php
```

Suite semasa menjalankan 23 command dan gagal jika mana-mana command gagal. Ia mengambil
snapshot bilangan `user_tbl`, `token_tbl`, kewujudan table F2 dan struktur
`user_tbl` sebelum/selepas untuk mengesan mutation local yang tidak disengajakan.

## Coverage

### Protocol dan callback

- issuer dan endpoint exact;
- Authorization Code flow;
- PKCE `S256`;
- state missing/mismatch/replay/expiry;
- callback GET dan parameter allowlist;
- issuer callback asing;
- provider rejection;
- state disahkan sebelum token exchange;
- TLS peer/host verification;
- ID token signature verification oleh library;
- aplikasi hanya menerima `RS256` dengan `kid`;
- audience/issuer/expiry/not-before;
- nonce wajib dan sepadan;
- ID-token/UserInfo `sub` mesti sama; dan
- protocol failure tidak sampai ke account matching.

### Identity dan account authorization

- NRIC tepat 12 digit;
- staf `data4`, pelajar `data2`;
- zero/one/multiple active matches;
- inactive account;
- existing subject link;
- subject takeover;
- linked NRIC mismatch;
- no auto-registration;
- no profile overwrite; dan
- HMAC domain separation.

### Local login dan logout

- rejected decision tidak mencipta token/sesi;
- single/multi-session policy;
- local token format dan hashed persistence seam;
- session regeneration helper;
- `auth_method=mydigitalid`;
- compensating token revocation;
- partial-session cleanup;
- safe dashboard redirect;
- local-first provider logout;
- password logout compatibility; dan
- invalid logout token rejection.

### UI, privacy dan deployment

- feature flag default false;
- preview/button conditional rendering;
- BM/English locale coverage;
- generic callback errors;
- tiada raw internal reason pada UI;
- reference/provider files di-ignore;
- `.private` dan `vendor` di-ignore;
- Composer lock validation dan advisory audit;
- isolated migration/linking rehearsals dibuang; dan
- local DB mutation guard.

## Keputusan local

```text
commands=23
failures=0
local_mutations=0
feature_activation=0
F0 online/read-only preflight=19/19
F6 security contract=11/11
rehearsal leftovers=0
Composer advisories=0
```

Baseline selepas suite:

```text
F2 table pada local DB=0
ONEID_MYDID_ENABLED=false
```

## Residual tests yang hanya boleh dibuat di staging

Automasi local tidak menggantikan ujian browser/provider sebenar. Selepas Git
pull dan gated migration di staging, jalankan:

1. discovery/JWKS/token/UserInfo melalui TLS staging;
2. sahkan ID token sebenar menggunakan `RS256` dan mempunyai `kid`;
3. login akaun pilot `0530-09` melalui VPN;
4. QR/deep-link/authenticator behavior jika dipaparkan provider;
5. authorization code reuse;
6. browser cookie `Secure`, `HttpOnly`, `SameSite=Lax`;
7. session ID berubah selepas login;
8. local token wujud sebagai hash sahaja;
9. logout OneID dan end-session MyDigital ID;
10. password login dan pelajar antarabangsa kekal berfungsi;
11. generic error presentation dalam BM dan English; dan
12. rollback readiness serta backup restore reference.

Jika provider staging mengeluarkan algoritma selain `RS256`, jangan longgarkan
polisi secara automatik. Dapatkan pengesahan MyDigital ID dan rekodkan keputusan
algoritma sebelum perubahan konfigurasi/kod.
