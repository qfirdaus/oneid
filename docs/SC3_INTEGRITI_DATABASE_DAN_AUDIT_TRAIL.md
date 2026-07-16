# SC3 — Integriti Database dan Audit Trail Authentication Policy

**Tarikh pelaksanaan:** 16 Julai 2026  
**Skop:** Singleton `sys_config`, targeted update dan audit atomik  
**Status:** IMPLEMENTED IN UAT / AUTOMATED AND MANUAL UAT VERIFIED  
**Nilai polisi selepas migration:** Tidak berubah

## 1. Objektif

Fasa 3 memastikan tepat satu row konfigurasi boleh wujud, update menyasarkan row
yang telah dikunci, dan setiap perubahan berjaya mempunyai audit before/after
dalam transaction yang sama.

## 2. Baseline dan Migration

Sebelum migration, `sys_config` mempunyai satu row dengan primary key `id`, tetapi
aplikasi membaca row tanpa selector dan mengemas kini semua row tanpa `WHERE`.

Migration menambah:

```text
singleton_key TINYINT NOT NULL DEFAULT 1
CHECK singleton_key = 1
UNIQUE singleton_key
```

Gabungan CHECK dan UNIQUE memastikan:

- hanya nilai singleton `1` sah; dan
- tidak boleh wujud row kedua dengan nilai tersebut.

Artifak:

- `docs/migrations/20260716_sc3_sys_config_singleton_up.sql`;
- `docs/migrations/20260716_sc3_sys_config_singleton_down.sql`; dan
- `tools/sc3_sys_config_schema_migrate.php` dengan mode `--check`/`--apply`.

Migration telah digunakan pada UAT. Backup/restore kekal diurus DBA dalam
environment lain mengikut arahan owner.

## 3. Targeted Persistence

Read biasa kini memilih explicit fields dengan `WHERE singleton_key = 1`.

Semasa mutation:

```text
BEGIN
    -> SELECT explicit fields WHERE singleton_key=1 FOR UPDATE
    -> normalize dan banding before/after
    -> jika unchanged: COMMIT tanpa update/audit
    -> UPDATE WHERE id=:config_id AND singleton_key=1
    -> INSERT audit event 19
COMMIT
```

Jika targeted update bukan tepat satu row atau audit gagal, transaction di-
rollback dan response kejayaan tidak dikeluarkan.

## 4. Audit Trail

Event sedia ada digunakan:

```text
19 — ADMIN_UPDATE_SSO_CONFIG
```

Audit mutation merekod:

- admin ID;
- action `update_sso_config`;
- before/after token timeout;
- before/after multiple-session flag;
- before/after password-reset email flag;
- IP melalui medan `syslog.ip_addr`;
- timestamp database; dan
- correlation ID.

Audit tidak mengandungi token, OTP, cookie, session ID atau credential. No-op
tidak menghasilkan audit mutation kerana tiada nilai berubah.

## 5. Failure Contract Baharu

| Code | Maksud |
|---|---|
| `SC3_ADMIN_ID_INVALID` | Actor admin tidak sah |
| `SC3_IP_ADDRESS_INVALID` | IP audit kosong/terlalu panjang/control character |
| `SC3_CONFIG_NOT_FOUND` | Singleton row tidak ditemui ketika lock |
| `SC3_CONFIG_UPDATE_NOT_APPLIED` | Targeted update bukan tepat satu row |
| `SC3_AUDIT_NOT_WRITTEN` | Audit gagal dan perubahan di-rollback |

Failure tidak mendedahkan exception database kepada browser. Correlation ID
digunakan untuk siasatan server-side.

## 6. Verification

Automated verification mengesahkan:

- migration check menemui satu row, column, CHECK dan UNIQUE;
- database menolak cubaan row singleton kedua;
- nilai polisi UAT kekal `0.5`, `1`, `1`;
- event audit 19 mempunyai nama yang betul;
- persistence menggunakan ID dan singleton selector;
- service menggunakan `FOR UPDATE`, transaction dan audit;
- fake persistence membuktikan audit failure menyebabkan rollback; dan
- semua contract SC0–SC2 kekal lulus selepas transition.

Arahan:

```bash
php tools/sc3_sys_config_schema_migrate.php --check
php tools/sc3_sso_configuration_integrity_contract.php
php tools/sc2_sso_configuration_service_contract.php
```

Contract integriti membuat cubaan INSERT yang dijangka ditolak oleh UNIQUE; ia
tidak mengubah row live.

## 7. UAT Manual

UAT manual melalui session admin browser telah selesai pada 16 Julai 2026.

| Langkah | Correlation ID | Audit ID | Masa | Perubahan | Keputusan |
|---|---|---:|---|---|---|
| Perubahan terkawal | `408b32704ce034ad` | 306441 | 11:12:15 | `token_timeout: 0.5 -> 1` | PASS |
| Pemulihan baseline | `98a8112b17d2a977` | 306442 | 11:16:58 | `token_timeout: 1 -> 0.5` | PASS |

Kedua-dua event:

- menggunakan `ADMIN_UPDATE_SSO_CONFIG` (`log_type=19`);
- merekod admin `820705025923` dan IP `127.0.0.1`;
- merekod before/after dan correlation ID yang sepadan dengan UI;
- menghasilkan tepat satu event bagi setiap mutation; dan
- mengekalkan `multi_session=1` serta `email_OTP=1`.

Selepas pemulihan, singleton row disahkan kembali kepada baseline:

```text
token_timeout = 0.5
multi_session = 1
email_OTP = 1
```

Rollback semasa audit failure telah disahkan melalui fake persistence contract;
fault injection terhadap database live tidak dilakukan.

## 8. Rollback

Rollback aplikasi perlu dilakukan sebelum rollback schema:

1. pulihkan read/update persistence dan service kepada release SC2;
2. jalankan `20260716_sc3_sys_config_singleton_down.sql` oleh DBA;
3. sahkan tepat satu row `sys_config` kekal;
4. sahkan nilai polisi; dan
5. jalankan contract release rollback.

Audit history yang sah tidak perlu dipadam semasa rollback. Event 19 telah wujud
sebelum SC3 dan dikekalkan.

## 9. Sempadan Fasa

Fasa ini tidak mengubah token lifecycle, legacy refresh window, revocation,
Password Recovery behavior, PHP session timeout atau Admin Step-Up. Audit bagi
validation rejection juga belum ditambah; SC3 memberi jaminan atomik bagi
mutation konfigurasi berjaya.

## 10. Keputusan

Singleton database, targeted locking/update dan audit mutation atomik telah
dilaksanakan serta disahkan melalui UAT UI sebenar. Risiko semua row berubah,
duplicate configuration dan perubahan berjaya tanpa audit kini ditutup bagi
flow ini.
