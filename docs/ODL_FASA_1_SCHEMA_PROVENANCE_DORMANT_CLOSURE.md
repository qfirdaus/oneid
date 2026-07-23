# ODL Fasa 1 — Schema Provenance Dormant Closure

**Tarikh:** 23 Julai 2026

**Status:** `PASS / CLOSED`

**Change ID:** `ONEID-ODL-F1-20260723-01`

## 1. Keputusan

Fasa 1 ditutup. Schema provenance additive telah dipasang pada OneID UAT dan
`STUDENT_ODL_PG` didaftarkan sebagai source `dormant`. Tiada membership
dibackfill, tiada datasource ODL dibaca oleh runtime, tiada Preview wiring dan
tiada user mutation.

## 2. Schema live yang disahkan

`user_tbl.u_id` pada OneID UAT:

```text
Engine: InnoDB
Table collation: utf8mb4_0900_ai_ci
Type: varchar(20)
Nullable: NO
Column collation: utf8mb4_0900_ai_ci
```

Jadual baharu menggunakan engine, charset, collation dan `u_id` type yang sama.

## 3. Artifact

| Artifact | Tujuan |
|---|---|
| `docs/migrations/20260723_odl_f1_provenance_up.sql` | Create registry/membership dan source dormant |
| `docs/migrations/20260723_odl_f1_provenance_down.sql` | Rollback ketika schema masih dormant/kosong |
| `tools/odl_f1_schema_contract.php` | Static additive/no-wiring contract |
| `tools/odl_f1_isolated_schema_rehearsal.php` | Rehearsal up/down dalam database sementara |
| `tools/odl_f1_schema_migrate.php` | Live check/apply/guarded rollback |

## 4. Installed state

Reconciliation selepas Apply:

```text
tables=2/2
source=student|dormant|0|1
memberships=0
```

Makna source state:

- `source_code = STUDENT_ODL_PG`;
- `source_family = student`;
- `lifecycle_state = dormant`;
- `is_required = 0`;
- `avail_status = 1`;
- zero row dalam `user_external_identity`.

`avail_status=1` bermaksud registry row sah. `lifecycle_state=dormant` memastikan
source tidak boleh digunakan oleh runtime sehingga fasa wiring yang diluluskan.

## 5. Constraint

- Satu external identity unik dalam setiap source.
- Satu akaun OneID mempunyai maksimum satu membership bagi setiap source.
- Membership mesti merujuk `user_tbl.u_id` yang wujud.
- Source code mesti wujud dalam registry.
- Delete user atau source yang masih mempunyai membership ditolak.
- Lifecycle hanya menerima `dormant`, `shadow`, `mandatory`, `optional`,
  `disabled` atau `retired`.
- `source_active`, `is_required` dan `avail_status` hanya menerima `0` atau `1`.

## 6. Verification

```text
Static schema contract: 12/12 PASS
Isolated forward/rollback rehearsal: 2/2 PASS
Rehearsal database removed: yes
Live schema check: PASS
Core sync regression: 10 suites, 219 checks, 0 failure
Dashboard baseline: 21/21 PASS
Safe wiring: 16/16 PASS
Operational: 24/24 PASS
Controlled Full: 17/17 PASS
Controlled Pilot: 17/17 PASS
```

Rehearsal membuktikan row `user_tbl` fixture tidak berubah sebelum/selepas
migration dan rollback. Static contract menolak sebarang `INSERT`, `UPDATE` atau
`DELETE` terhadap `user_tbl` dalam migration.

## 7. Rollback

Rollback hanya dibenarkan jika:

- kedua-dua jadual berada dalam complete installed state;
- semua source masih `dormant`;
- hanya registry source Fasa 1 wujud;
- `user_external_identity` kosong.

Arahan guarded rollback:

```bash
ONEID_ODL_F1_CHANGE_ID=ONEID-ODL-F1-20260723-01 \
php tools/odl_f1_schema_migrate.php --rollback
```

Jangan rollback selepas Fasa 2 backfill bermula tanpa pelan reconciliation dan
change approval baharu.

## 8. Authorization seterusnya

Fasa 2 ialah langkah seterusnya mengikut pelan. Ia hanya membenarkan Preview
calon provenance/backfill sumber sedia ada dan isolated backfill rehearsal.
Tiada live backfill boleh dibuat sehingga source semasa (`STUDENT_UG` atau nama
authoritative lain) disahkan dengan bukti, ambiguous identity dikeluarkan dan
exact counts diluluskan.

Adapter ODL, Shadow Preview dan Apply masih belum dibenarkan oleh closure Fasa 1.
