# MyDigital ID Fasa 2 — Dormant Identity Link dan Authentication Audit Schema

> **Status supersession — 26 Julai 2026:** Migration telah diaplikasikan kepada
> shared development/staging `oneiddb` selepas backup dan readiness `10/10`.
> Dua table, tiga FK dan tiga CHECK wujud; `user_tbl` kekal 9,793 row dan
> strukturnya tidak berubah. Schema apply gate telah ditutup semula. Retention
> dan key custody production masih memerlukan DBA/security approval. Lihat
> `MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md`.

**Tarikh pelaksanaan:** 26 Julai 2026
**Environment:** OneID UAT/Staging
**Status:** COMPLETE — MIGRATION REHEARSED, NOT APPLIED TO LIVE UAT
**Feature flag:** `ONEID_MYDID_ENABLED=false`
**Live F2 tables:** `0`
**Public/runtime wiring:** Tiada
**Live database mutation:** Tiada

## 1. Objektif

Fasa 2 menyediakan:

- additive migration bagi pautan identiti MyDigital ID;
- additive migration bagi event authentication;
- migration rollback;
- keyed HMAC protection;
- dormant PDO repository;
- transaction boundary;
- isolated database rehearsal;
- negative/security tests.

Fasa ini tidak mengubah `user_tbl`, tidak mengaktifkan login dan tidak apply
schema ke database UAT live.

## 2. Live Schema Baseline

Read-only metadata audit mengesahkan:

| Item | Baseline |
|---|---|
| `user_tbl` engine | InnoDB |
| `user_tbl` collation | `utf8mb4_0900_ai_ci` |
| `user_tbl.u_id` | `varchar(20)`, NOT NULL, primary key |
| `user_tbl.data2` | `varchar(100)`, NOT NULL |
| `user_tbl.data4` | `varchar(100)`, NOT NULL |
| Existing F2 tables | 0 |

Migration menggunakan engine, charset, collation dan FK type yang serasi.

## 3. Model Data

### 3.1 `user_federated_identity`

Tujuan: menyimpan satu pautan stabil antara akaun OneID dan identity provider.

Medan utama:

| Medan | Fungsi |
|---|---|
| `identity_id` | Surrogate primary key |
| `u_id` | Canonical OneID user |
| `provider_code` | `mydigitalid` |
| `issuer` | Realm issuer yang diluluskan |
| `subject_hmac` | Keyed HMAC claim OIDC `sub` |
| `nric_hmac` | Keyed HMAC normalized NRIC |
| `hmac_key_id` | Identifier key untuk operasi/rotation |
| `identity_status` | `ACTIVE` atau `REVOKED` |
| `first_verified_at` | Masa pautan pertama |
| `last_verified_at` | Masa identity terakhir diverifikasi |
| `last_login_at` | Login berjaya terakhir |
| `login_count` | Jumlah login berjaya |

Constraint:

```text
UNIQUE(provider_code, issuer, subject_hmac)
UNIQUE(provider_code, u_id)
FOREIGN KEY u_id → user_tbl.u_id
CHECK identity_status IN (ACTIVE, REVOKED)
```

Implikasi:

- satu MyDigital ID subject tidak boleh dipautkan ke dua akaun;
- satu akaun OneID tidak boleh mempunyai dua link MyDigital ID;
- akaun OneID tidak boleh dipadam selagi link wujud;
- link tidak berpindah secara automatik.

### 3.2 `federated_auth_event`

Tujuan: audit login berjaya, ditolak atau error tanpa menyimpan payload/token.

Medan utama:

| Medan | Fungsi |
|---|---|
| `event_id` | Primary key |
| `identity_id` | Link jika diketahui |
| `u_id` | Akaun jika dipadankan |
| `provider_code` | `mydigitalid` |
| `outcome` | `SUCCESS`, `REJECTED`, `ERROR` |
| `reason_code` | Stable application reason |
| `subject_hmac` / `nric_hmac` | Masked correlation evidence |
| `hmac_key_id` | Key version |
| context HMAC | IP, user-agent dan session identifier |
| `correlation_id` | 32 hex, unique |
| `occurred_at` | Masa kejadian |

Constraint:

```text
UNIQUE(correlation_id)
FOREIGN KEY identity_id → user_federated_identity.identity_id
FOREIGN KEY u_id → user_tbl.u_id
CHECK outcome IN (SUCCESS, REJECTED, ERROR)
```

FK event menggunakan `ON DELETE SET NULL` supaya audit boleh dikekalkan jika
link/user dipadam melalui proses yang diluluskan.

## 4. Data yang Sengaja Tidak Disimpan

Kedua-dua jadual tidak mempunyai column:

- NRIC plain text;
- nama MyDigital ID;
- access token;
- refresh token;
- ID token;
- authorization code;
- client secret;
- provider payload;
- raw IP/session/user-agent.

Nama tidak disimpan kerana OneID sudah mempunyai nama sendiri dan owner telah
menetapkan data provider tidak boleh overwrite profil. `u_id` mencukupi untuk
menentukan siapa menggunakan MyDigital ID.

Jika nama snapshot diperlukan kemudian, ia memerlukan keputusan retention,
access-control dan encryption berasingan; ia tidak boleh ditambah secara
tersirat.

## 5. HMAC Identity Protection

`MyDigitalIdIdentityProtector` menggunakan:

```text
HMAC-SHA256
key = exactly 32 random bytes
key encoding = strict base64 dalam private runtime
```

Setiap domain menggunakan prefix berasingan:

```text
nric
subject
ip
user-agent
session-id
```

Ini memastikan nilai input sama dalam dua context tidak menghasilkan digest
sama.

Config:

```text
ONEID_MYDID_IDENTITY_HMAC_KEY_ID=
ONEID_MYDID_IDENTITY_HMAC_KEY_BASE64=
```

Committed default hanya mempunyai key ID kosong. Key material hanya mempunyai
placeholder kosong dalam private template. Tiada key sebenar dipasang.

Key HMAC:

- mesti berbeza daripada OIDC client secret;
- tidak boleh berada dalam Git;
- mesti diwujudkan sebelum live migration/wiring;
- perlu mempunyai backup dan custody;
- key rotation memerlukan migration/re-hash plan kerana subject lookup
  bergantung kepada key version.

## 6. Normalisasi Identiti

NRIC:

- trim;
- buang whitespace dan sengkang Unicode;
- mesti tepat 12 digit;
- raw value tidak dipulangkan dalam output/log.

Subject:

- issuer mesti sama tepat dengan realm UPNM;
- tidak boleh kosong;
- maksimum 255 byte;
- control character ditolak;
- digest mengikat issuer dan subject.

## 7. Dormant Repository

`PdoMyDigitalIdIdentityRepository` menyediakan:

| Method | Fungsi |
|---|---|
| `transactional()` | Commit/rollback bounded operation |
| `findActiveBySubject()` | Cari active link; >1 fail-closed |
| `createActiveLink()` | Cipta link dengan uniqueness/FK |
| `touchSuccessfulLogin()` | Increment hanya jika user, NRIC HMAC dan status sepadan |
| `recordEvent()` | Rekod allowlisted outcome/reason tanpa raw identity |

Repository:

- menggunakan prepared statements;
- mengembalikan generic reason code;
- tidak memasukkan PDO/server detail ke exception;
- mempunyai strict user ID, digest, key ID dan correlation validation;
- menolak reason code yang tidak dikenali;
- menyokong event rejected tanpa `u_id`;
- mempunyai transaction rollback apabila operation gagal.

Repository belum dirujuk oleh public endpoint atau login runtime.

## 8. Reason Code Allowlist

```text
MYDID_LOGIN_SUCCESS
MYDID_USER_NOT_FOUND
MYDID_USER_INACTIVE
MYDID_IDENTITY_AMBIGUOUS
MYDID_IDENTITY_MISMATCH
MYDID_STATE_INVALID
MYDID_NONCE_INVALID
MYDID_TOKEN_INVALID
MYDID_PROVIDER_ERROR
MYDID_CONFIGURATION_DISABLED
```

Penambahan reason code baharu perlu disertai test dan dokumentasi.

## 9. Migration

Forward:

[20260726_mydigitalid_f2_identity_audit_up.sql](/var/www/app/oneid-uat/docs/migrations/20260726_mydigitalid_f2_identity_audit_up.sql)

Rollback:

[20260726_mydigitalid_f2_identity_audit_down.sql](/var/www/app/oneid-uat/docs/migrations/20260726_mydigitalid_f2_identity_audit_down.sql)

Rollback menjatuhkan event table dahulu, kemudian identity table supaya FK
dependency dipatuhi.

Tiada live apply tool disediakan dalam Fasa 2. Ini disengajakan supaya migration
tidak boleh diaplikasi secara tidak sengaja sebelum backup, HMAC key, retention
dan change approval tersedia.

## 10. Isolated Rehearsal

Runner:

```bash
php tools/mydigitalid_f2_isolated_schema_rehearsal.php
```

Runner:

1. menjana nama database rawak dengan strict allowlist;
2. mencipta database terasing;
3. mencipta fixture `user_tbl` serasi;
4. mengambil digest state fixture user;
5. apply forward migration;
6. memeriksa table/FK/check/forbidden columns;
7. menguji transaction rollback;
8. mencipta link keyed-HMAC;
9. menguji lookup dan successful touch;
10. merekod success serta rejected event;
11. menguji duplicate subject/link;
12. menguji duplicate correlation;
13. menguji NRIC mismatch;
14. membuktikan `user_tbl` tidak berubah;
15. apply down migration;
16. membuktikan table hilang dan `user_tbl` kekal;
17. memadam database rehearsal dalam `finally`.

Keputusan:

```text
PASS forward
  tables=2
  foreign_keys=3
  checks=3
  forbidden_columns=0
  user_unchanged=yes

PASS repository
  transaction_rollback=yes
  link=yes
  login_count=1
  events=yes
  duplicate_link_blocked=yes
  duplicate_correlation_blocked=yes
  mismatch_blocked=yes
  hmac_only=yes
  user_unchanged=yes

PASS rollback
  tables=0
  user_unchanged=yes

RESULT
  checks=3
  failed=0
  live_schema_mutations=0
  rehearsal_database_removed=yes
  raw_pii_output=0
```

## 11. Tests dan Contracts

### Static contract

```bash
php tools/mydigitalid_f2_contract.php
```

```text
RESULT checks=9 failures=0 live_schema_mutations=0 runtime_wiring=0
```

### Identity protection characterization

```bash
php tests/characterization/mydigitalid_f2_identity_protection.php
```

```text
RESULT checks=11 failures=0 raw_identity_output=0 network_calls=0
```

Characterization membuktikan:

- deterministic NRIC normalization;
- lowercase 64-hex digest;
- domain separation;
- optional empty context menjadi `null`;
- short key ditolak;
- invalid key ID ditolak;
- invalid NRIC ditolak;
- issuer salah ditolak;
- blank subject ditolak;
- unknown HMAC context ditolak;
- runtime membaca dedicated HMAC secret tepat sekali apabila dibina.

## 12. Integrity Snapshot

| Fail | SHA-256 |
|---|---|
| up migration | `99922247cdd08dc7bdebce3160af19704cb77299d6b85de6790a2e3e9c28a634` |
| down migration | `0ee30110ee5e3b9a75b4338bbb545431ea455f2ef8e71da0941457d08b0a17f2` |
| `MyDigitalIdIdentityProtector.php` | `285125318389a5142f013c4a5b9a36a70b15f695ed46e8fc99a19f39f57fdef7` |
| `MyDigitalIdPersistenceException.php` | `07780ab2947f5b593bb5781477cf1a6b5f0c9f51c0776dc8bc7183c2c5a026d3` |
| `PdoMyDigitalIdIdentityRepository.php` | `de66552b9e4448cc0e4a7fa1debf7f4221427a741dc914dd8ad373b6caa5d8d0` |
| `tools/mydigitalid_f2_contract.php` | `1f0dfaefd5a9f58ac1b3433ee416733f8359838486c84a000bb3c0c87b468584` |
| isolated rehearsal | `75a7976c731f73b9cf86d065655f7e37a9f66af46cbb4ed86f80cd4302e89e31` |
| identity protection test | `06b90bf00cd491875232e82ccc7855a0c28a22e20fff263a3ca3e19e07a697c2` |
| `config/runtime.php` | `c1b3078989a27c50fdeaf24ae21f657d38fdb40c79bd5627b5b0250272f88fff` |
| private runtime template | `47aa244c720fff832f83bead22130cfcd4d8ad47204db82183d157ec788a5f82` |

Hash ialah snapshot selepas transaction dan negative-test enhancement selesai.

## 13. Retention dan Privacy Gate

Cadangan awal, belum diluluskan:

| Data | Cadangan |
|---|---|
| Active identity link | Selagi akaun/link aktif |
| Revoked link | 1 tahun selepas revoke |
| Success auth event | 1 tahun |
| Rejected/error auth event | 180 hari |

Cleanup scheduler tidak dibina dalam Fasa 2. Sebelum live apply/activation,
owner perlu menetapkan:

- retention rasmi;
- siapa boleh melihat audit;
- legal/security hold;
- deletion approval;
- HMAC key rotation/custody.

## 14. Rollback

### Sebelum live apply

Fasa 2 sekarang boleh dirollback dengan membuang:

- migration up/down;
- protector/repository/exception;
- F2 tests/tools/docs;
- HMAC config placeholders.

Tiada live database rollback diperlukan kerana table belum wujud.

### Selepas future live apply

1. pastikan feature flag `false`;
2. pastikan tiada public writer;
3. backup dua table;
4. rekod row count/checksum;
5. apply down migration;
6. sahkan table hilang;
7. sahkan `user_tbl` tidak berubah;
8. simpan/padam backup mengikut retention approval.

`DROP TABLE` selepas data wujud ialah destructive dan memerlukan explicit
approval.

## 15. Baki Gate Sebelum Live Apply

| Gate | Status |
|---|---|
| Migration design | PASS |
| Isolated forward rehearsal | PASS |
| Repository behavior | PASS |
| Isolated rollback | PASS |
| `user_tbl` unchanged | PASS |
| Raw identity/token exclusion | PASS |
| Live schema absent | PASS |
| Feature flag false | PASS |
| HMAC production/UAT key provisioned | PENDING |
| Key custody/rotation approved | PENDING |
| Retention approved | PENDING |
| Backup/change window approved | PENDING |
| Live apply approval | NOT REQUESTED |

## 16. Keputusan Fasa

```text
F2 COMPLETE
MIGRATION UP/DOWN READY
ISOLATED REHEARSAL PASS
IDENTITY LINK AND AUTH EVENT REPOSITORY DORMANT
RAW NRIC/NAME/TOKEN NOT STORED
USER_TBL UNCHANGED
LIVE F2 TABLES ABSENT
FEATURE FLAG FALSE
NO PUBLIC/RUNTIME WIRING
READY FOR FASA 3 CALLBACK FOUNDATION AFTER F2 LIVE-APPLY GATES ARE APPROVED
```
