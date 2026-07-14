# S1 — Manual Add User Hardening dan Provenance

Tarikh: 14 Julai 2026  
Change ID database: `S1-20260714-132813`  
Owner perubahan: Pemilik sistem OneID  
Owner rollback: Pemilik sistem OneID  
Status: **SELESAI — DATABASE ACTIVE; MANUAL ADD, OTP DAN LOGIN PASS**

## 1. Outcome

S1 mengukuhkan Manual Add User dan melindungi akaun manual baharu daripada
full external sync. Login, dashboard, endpoint integration dan scheduled sync
tidak dipindahkan. Cron kekal retired.

Selepas S1:

- request manual divalidasi server-side;
- email sah diwajibkan untuk OTP onboarding;
- duplicate check, insert dan audit berlaku dalam satu transaction;
- duplicate-key race dipulangkan sebagai respons selamat;
- kegagalan mempunyai correlation ID tanpa raw exception;
- initial password kekal random, hashed dan tidak diketahui pentadbir;
- akaun baharu disimpan sebagai `account_source=manual` dan
  `sync_protected=1`;
- full sync tidak deactivate atau overwrite protected manual account;
- external account ditanda `account_source=external`, `sync_protected=0`.

## 2. Perubahan Aplikasi

### Input dan service

- `app/User/ManualUserInput.php`
  - `u_id`: maksimum 20 aksara mengikut schema;
  - `data1–data12`: maksimum 100 aksara;
  - ID menggunakan allowlist aksara;
  - email `data5` wajib dan menggunakan `FILTER_VALIDATE_EMAIL`;
  - control character serta `<`/`>` ditolak bagi input manual;
  - change hash menggunakan `SyncDataTransformer` dengan namespace kategori
    `manual:<category_id>`.
- `app/User/ManualUserCreator.php`
  - fail-closed jika migration belum tersedia;
  - transaction meliputi duplicate lookup, category validation, insert dan
    audit event 23;
  - rollback bagi duplicate, invalid category dan exception;
  - correlation ID untuk kegagalan generik.

### Persistence dan sync

- `Database::supportsUserProvenance()` mengesan expanding migration secara
  backward-compatible.
- `Database::isActiveUserCategory()` menolak category inactive/tidak wujud.
- manual insert menyimpan provenance dan protection.
- external upsert menetapkan provenance external dan mempunyai locking guard
  terhadap protected manual collision.
- legacy `run_admin_sync_user()` mengeluarkan protected manual identities dari
  matching, deactivation, staging dan upsert.

### UI

- User ID dan nama mempunyai `maxlength` mengikut schema.
- email menggunakan `type=email`, `maxlength=100` dan `required`.
- AJAX error callback memulihkan form dan memaparkan mesej generik.
- login limiter/input dinaikkan daripada had legacy 10 kepada 20 aksara supaya
  sama dengan `user_tbl.u_id VARCHAR(20)` dan allowlist Manual Add User.

## 3. Database Migration

Migration:

- `docs/migrations/S1_USER_PROVENANCE_UP.sql`;
- rollback: `docs/migrations/S1_USER_PROVENANCE_DOWN.sql`;
- runner: `tools/s1_provenance_migrate.php`.

Schema ditambah:

| Column/index | Definisi |
| --- | --- |
| `account_source` | `VARCHAR(16) NOT NULL DEFAULT 'external'` |
| `sync_protected` | `TINYINT(1) NOT NULL DEFAULT 0` |
| `idx_user_sync_scope` | `(avail_status, account_source, sync_protected)` |

Migration dilaksanakan pada MySQL 8.0.41/InnoDB terhadap 9,649 row. Selepas
migration:

```text
external / sync_protected=0 : 9649
manual   / sync_protected=1 : 0
total                         : 9649
```

Jumlah row sebelum dan selepas kekal 9,649. Existing account diklasifikasikan
sebagai external kerana provenance sejarah tidak boleh diteka dengan selamat.
Jika terdapat akaun manual lama, owner perlu menyediakannya dalam senarai
review berasingan sebelum mengubah provenance.

## 4. Backup Evidence

Backup private dicipta sebelum DDL:

```text
storage/backups/S1-20260714-132813/user_tbl.before.sql
SHA-256: 945df46b9b019e53f660fcf7d883a797e0f8b53960c2d29fb6e688312c731a99
```

Permission:

- direktori: `0700`;
- dump dan checksum: `0600`;
- `storage/backups/*` diabaikan Git dan berada di luar public root.

Dump mengandungi data sensitif dan tidak boleh disalin ke repository atau
document root.

## 5. Automated Verification

```bash
php tools/s1_provenance_migrate.php --status
php tools/s1_user_provisioning_contract.php
php tests/characterization/s1_manual_user_hardening.php
php tests/characterization/s1_sync_provenance_protection.php
php tools/restructure_smoke.php https://oneid.local --insecure
```

Keputusan:

| Semakan | Keputusan |
| --- | --- |
| S1 aggregate contract | 39/39 PASS |
| Manual validation/transaction fixture | 29/29 PASS |
| Protected sync fixture | 7/7 PASS |
| Legacy sync regression | 18/18 PASS |
| Dormant dry-run regression | 25/25 PASS |
| Anonymous HTTP smoke | 10/10 PASS |
| Provenance schema/index | ACTIVE, 2/2 columns dan 1/1 index |

## 6. UAT Manual Yang Masih Diperlukan

Owner menggunakan akaun test `S1TEST-20260714`. Evidence read-only menunjukkan:

| Event/state | Keputusan |
| --- | --- |
| Manual Add audit event 23 | PASS, 14 Julai 2026 13:39:15 |
| Provenance | `manual`, `sync_protected=1` |
| Email format | Sah |
| OTP audit event 9 | PASS, 14 Julai 2026 13:42:31 |
| OTP consumed | PASS, 14 Julai 2026 13:42:54 |
| Password reset event 21 | PASS, 14 Julai 2026 13:43:22 |
| `password_change_required` selepas reset | `0`, expected |
| Login audit event 2 | PASS, 14 Julai 2026 13:48:30 |

UAT turut menemui input login masih mempunyai limiter legacy 10 aksara.
`S1TEST-20260714` mempunyai 15 aksara dan sah dalam `u_id VARCHAR(20)`. Limiter,
HTML `maxlength` dan allowlist login telah diselaraskan kepada 20 aksara.

Owner membuat hard refresh dan berjaya login menggunakan `S1TEST-20260714`
dengan password yang baru ditetapkan. Jangan jalankan full external sync untuk
menguji protection sebelum S2 preview tersedia; protection telah dibuktikan
melalui fixture in-memory.

## 7. Rollback

### A. Rollback aplikasi sahaja — pilihan paling selamat selepas ada user manual

Revert commit S1 tetapi **kekalkan columns provenance**. Code lama akan
mengabaikan columns tambahan. Ini mengelakkan kehilangan metadata atau akaun
manual yang dicipta selepas S1.

```bash
git revert <commit-S1>
```

### B. Rollback schema — hanya sebelum penggunaan sebenar atau selepas review

```bash
php tools/s1_provenance_migrate.php --rollback
```

Runner membuat backup baharu sebelum membuang columns. Selepas columns dibuang,
Manual Add User pada code S1 akan fail-closed; flow lain menggunakan compatibility
legacy.

Jangan restore dump pra-S1 secara terus selepas akaun baharu dicipta kerana ia
akan menghilangkan akaun/perubahan selepas backup. Full table restore hanya
untuk disaster recovery yang diluluskan.

## 8. Residual Risk dan Sempadan S2/S3

- Pure `SyncPlanner` dormant belum memahami provenance; ia mesti dikemas kini
  dan dibuktikan zero-mutation/parity dalam S2 sebelum preview production.
- Full sync production masih tiada confirmation, server lock, partial-source
  threshold dan transaction-boundary fix; semuanya kekal skop S2/S3.
- Historical manual accounts belum dapat dikenal pasti secara automatik.
- Input manual menolak markup, tetapi output encoding menyeluruh untuk external
  data/dashboard kekal security task berasingan.
- Race yang berlaku selepas planning tetapi sebelum writer memerlukan single-run
  lock dan reconciliation dalam S3; DB guard mencegah overwrite protected row,
  tetapi summary race masih perlu dikesan.

## 9. Keputusan

Implementation S1 dan migration selesai serta aktif. Gate automated, Manual Add,
OTP/password reset dan login lulus. S1 ditutup sebagai `SELESAI`. Checkpoint Git
S1 boleh dibuat sebelum kerja bergerak ke S2.
