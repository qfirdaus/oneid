# MyDigital ID — Staging Implementation Close-out dan Baki Kerja

**Tarikh rekod:** 26 Julai 2026  
**Environment aplikasi:** staging (`https://oneid-uat.upnm.edu.my/`)  
**Database:** shared development/staging `oneiddb` pada `mysql8-DEV`  
**Production:** tidak disentuh dan tidak diluluskan  
**Release semasa:** OneID `2.6.4`
**Change reference:** `ONEID-V264-CHANGELOG-20260726-01`

## 1. Keputusan keseluruhan

Integrasi dua-login telah dilaksanakan dan diaktifkan secara terkawal di
staging:

- form ID pengguna/kata laluan OneID kekal tersedia;
- MyDigital ID menggunakan Authorization Code Flow, PKCE S256, state dan nonce;
- hanya pengguna OneID aktif dengan exact-one NRIC match dibenarkan;
- tiada auto-registration dan tiada profile overwrite;
- identity link/audit disimpan berasingan daripada `user_tbl`;
- successful pilot login melalui MyDigital ID telah dibuktikan;
- identiti yang tiada akses ditolak dan diaudit;
- feature flag runtime boleh melakukan rollback segera; dan
- callback access log baharu tidak merekod query authorization.

Staging implementation ialah **GO untuk controlled acceptance testing**.
Ia bukan production approval.

Keseluruhan Fasa 0–7 kini digabungkan sebagai release OneID `2.6.4`.
Paparan sejarah versi turut ditulis semula dalam bahasa mudah dengan parity
BM/English. Audit teknikal terperinci dalam dokumen fasa kekal sebagai evidence.

## 2. Deployment dan source evidence

| Item | Evidence |
|---|---|
| Remote/branch | `git@github.com:qfirdaus/oneid.git`, `main` |
| Initial integrated release | `7f8e6bddad91788677b68a2fb4e2cea9fa87734c` |
| Login secure-session hotfix | `081087ad8dead36e73a081c5cd01b120d4b4390d` |
| Login card redesign | `584a1440f66604a6a2188f243937107d7efbedac` |
| Simplified card copy | `557ec32232304a8dc1e259c845053c62f5634c70` |
| F7 rejected account switching | `0a0f4ad3c542d2dbb487d00b9102bb970c24aa92` |
| Safe-log documentation | `5592929a25220ecf5a549540b0a9524d1ba06d56` |
| Staging directory | `/var/www/oneid-uat` |
| PHP/runtime | PHP 8.3 FPM; `ext-intl`/`Normalizer` PASS |
| Composer | `--no-dev --classmap-authoritative`; validate/audit PASS |

Setiap deployment yang menambah class PHP mesti menjalankan Composer install
selepas `git pull`; authoritative classmap tidak menemui class baharu sehingga
autoload dijana semula.

## 3. Runtime dan secret

Staging private runtime:

```text
ONEID_ENVIRONMENT=staging
ONEID_MYDID_ENABLED=true
ONEID_MYDID_SCHEMA_APPLY_ENABLED=false
```

Client secret dan 32-byte HMAC key telah lulus runtime validation tanpa
memaparkan nilai. Private runtime dimiliki `iqs:www-data`, mode `640`; directory
`.private` mode `750`. Secret tidak berada dalam Git.

Committed default `ONEID_MYDID_ENABLED=false` kekal fail-closed. Production
memerlukan secret/HMAC key berasingan dan rotation/custody approval.

## 4. Schema application evidence

Sebelum migration:

- target F2 table count `0`;
- full `oneiddb` backup:
  `/home/iqs/db-backups/oneiddb-pre-mydid-f2-20260726-125045.sql.gz`;
- backup size `21,067,537` bytes;
- SHA-256
  `7d8e8e0a656dcb3e39c8cf3ce0d97a8f13cafc12b48fddfc21ec3834e4069bf9`;
- gzip integrity dan dump completion marker PASS;
- readiness `checks=10 blocked=0`.

Migration result:

```text
database=oneiddb
tables=2
foreign_keys=3
checks=3
forbidden_columns=0
user_rows=9793
user_structure_unchanged=yes
migration_sha256=99922247cdd08dc7bdebce3160af19704cb77299d6b85de6790a2e3e9c28a634
change_reference=MYDID-F2-DEVDB-20260726-01
backup_reference=ONEIDDB-PRE-MYDID-F2-20260726-125045
retention_reference=MYDID-NONPROD-RETAIN-UNTIL-DBA-REVIEW
```

Schema apply flag telah dikembalikan kepada `false`. Backup ini ialah evidence
non-production sahaja dan bukan backup production.

## 5. Provider dan pilot evidence

Provider discovery, TLS, issuer, authorize/token/UserInfo/JWKS/logout endpoint,
Authorization Code Flow dan PKCE S256 semuanya PASS. Registered staging values:

```text
issuer=https://sso.digital-id.my/realms/upnm
client_id=upnm-generic
redirect_uri=https://oneid-uat.upnm.edu.my/auth/mydigitalid/callback.php
post_logout_redirect_uri=https://oneid-uat.upnm.edu.my/
scope=openid
```

Pilot reference `0530-09`:

- exact-one active OneID match PASS;
- live claims `sub`, `nama`, `nric` berjaya diproses;
- initial identity link dicipta;
- dua successful MyDigital ID authentication events diperhatikan;
- authenticated OneID dashboard dicapai; dan
- `user_tbl` tidak diubah oleh linking flow.

Negative identity evidence:

- event 3–14 direkod `REJECTED / MYDID_USER_NOT_FOUND`;
- rejected subject dan NRIC HMAC berbeza daripada pilot link;
- tiada link/pengguna baharu dicipta; dan
- kejadian mengesahkan provider authentication tidak memberikan authorization
  OneID secara automatik.

## 6. F7 rejection/account-switch

F7 menyimpan verified rejected ID token dalam server-side session sahaja,
TTL 300 saat dan one-use. Endpoint account-switch:

- POST sahaja;
- CSRF wajib;
- membersihkan transaction/state/nonce/PKCE;
- regenerate session ID;
- redirect ke official provider logout menggunakan `id_token_hint`; dan
- tidak menyimpan raw token dalam database/log/output.

Automated state/logout test `9/9` dan F7 contract `8/8` lulus. Manual browser
acceptance bagi butang `Cuba akaun MyDigital ID lain` masih perlu direkod.

## 7. Nginx log hardening

Nginx staging menggunakan format `oneid_safe` yang merekod:

```text
"$request_method $uri $server_protocol"
```

dan tidak merekod `$request`, `$request_uri`, `$args`, `$query_string` atau
`$http_referer`. `nginx -t`, reload dan service health PASS. Query-redaction
canary pada 14:17:27 direkod sebagai path sahaja dan canary query tidak ditemui.

Config backup:

```text
/etc/nginx/sites-available/oneid-uat.backup-20260726-141433
```

Historical access log sebelum hardening mengandungi authorization code/state.
Retention, access restriction dan rotation perlu melalui proses operasi;
jangan padam secara ad hoc.

## 8. Automated evidence semasa

Staging security/regression suite:

```text
commands=24
failures=0
local_mutations=0
feature_activation=0
f2_tables=2
rehearsal_cleanup=0
Composer advisories=0
```

Suite merangkumi F0–F7, protocol/crypto contracts, isolated schema/linking,
password regression, logout, rejected token TTL/replay, Composer validate dan
security audit.

## 9. Acceptance yang masih belum selesai

| ID | Item | Status/owner |
|---|---|---|
| STG-01 | Dedicated rejected-user page melalui VPN | F8 code/contract PASS; paparan browser perlu direkod |
| STG-02 | Klik `Cuba akaun MyDigital ID lain` → provider logout → QR baharu | PENDING browser acceptance |
| STG-03 | Login pilot selepas account-switch | PENDING chained acceptance |
| STG-04 | Inactive OneID live identity | PENDING approved fixture; automated coverage PASS |
| STG-05 | Ambiguous/duplicate NRIC live identity | PENDING approved fixture; isolated coverage PASS |
| STG-06 | Password login staf selepas activation | Initial smoke PASS; final acceptance record disyorkan |
| STG-07 | Password login pelajar tempatan | PENDING manual acceptance |
| STG-08 | Pelajar antarabangsa nombor matrik/passport | PENDING manual acceptance |
| STG-09 | ACL parity selepas MyDigital ID login | PENDING manual comparison |
| STG-10 | Authenticated MyDigital ID logout local + provider | Basic redirect observed; explicit provider-session acceptance PENDING |
| STG-11 | Provider timeout/unavailable UX | Automated fail-closed PASS; controlled manual test optional |
| STG-12 | Monitoring threshold/channel dan observation window | PENDING Operations |

## 10. Production gates — semuanya masih tertutup

Production belum disentuh. Sebelum production:

1. dapatkan production issuer/client/redirect/post-logout registration yang
   disahkan MyDigital ID;
2. provision production client secret dan HMAC key secara berasingan;
3. luluskan key custody, rotation dan incident procedure;
4. luluskan audit-event retention serta purge/archival process;
5. DBA menyediakan backup, restore evidence, change window dan rollback owner;
6. jalankan production DB baseline/collision/read-only pilot checks;
7. apply migration melalui gated runner pada target production yang disahkan;
8. apply safe Nginx log format sebelum callback pertama;
9. jalankan dormant regression, controlled pilot dan observation;
10. dapatkan business/security/DBA/infra production GO.

Staging backup, HMAC key, change reference dan acceptance tidak boleh digunakan
sebagai production approval.

## 11. Reference-folder dan secret close-out

`resources/references/mydigital-id/` kekal ignored dan bukan runtime dependency.
Sebelum task ditutup sepenuhnya:

- padam reference file yang mengandungi token/key seperti diarahkan owner;
- semak Git history/status untuk pendedahan secret;
- rotate credential sebelum production dan jika credential ujian pernah
  dikongsi melalui saluran tidak diluluskan; dan
- simpan hanya dokumentasi yang dibenarkan oleh klasifikasi organisasi.
