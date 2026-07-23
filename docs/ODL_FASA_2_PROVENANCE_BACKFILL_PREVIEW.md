# ODL Fasa 2 — Provenance Backfill Preview

**Tarikh:** 23 Julai 2026

**Status:** `PASS / CLOSED`

## 1. Skop

Preview read-only menilai calon provenance bagi student source semasa
`asisdb..v210_sso_student_aktif`. Owner mengesahkan source ini khusus
undergraduate dan meluluskan source code `STUDENT_UG`.

Preview:

- hanya membaca external student view, `user_tbl` dan membership semasa;
- menggunakan exact canonical No. Matrik + IC;
- tidak memulangkan PII;
- tidak menulis registry, membership, profil atau status pengguna;
- sentiasa `can_apply=false`.

## 2. Hasil read-only

```text
source_rows=5452
valid_student_identities=5423
invalid_identity_rows=0
duplicate_pair_groups=29
duplicate_pair_rows=58
exact_duplicate_pair_groups=0
profile_variant_duplicate_groups=29
matric_conflict_groups=0
ic_conflict_groups=0
matched_active_users=5423
matched_inactive_users=0
protected_collisions=0
unmatched_external=0
ambiguous_user_matches=0
existing_memberships=0
candidate_memberships=5423
membership_conflicts=0
blocking_findings=0
review_findings=29
status=review
plan_digest=5b5185e2a79d1b46127298fbd3f60303647cf69db71b19c4f0cc4c9b12a7874b
```

## 3. Duplicate/profile finding

Semua 29 duplicate groups mempunyai exact No. Matrik+IC yang sama tetapi profil
berbeza:

| Canonical field | Groups dengan variasi |
|---|---:|
| Nama (`data1`) | 0 |
| IC (`data2`) | 0 |
| `data3` | 0 |
| No. Matrik (`data4`) | 0 |
| E-mel (`data5`) | 28 |
| Fakulti (`data6`) | 29 |
| Program (`data7`) | 0 |

Fasa 2 hanya merancang membership provenance. Ia tidak memilih atau menulis
profil, maka profile variants tidak menjadikan identity membership ambiguous.
Finding kekal untuk owner review dan tidak boleh digunakan sebagai authorization
untuk profile update.

## 4. Safety

Preview akan block jika terdapat:

- blank No. Matrik atau IC;
- satu No. Matrik dengan beberapa IC;
- satu IC dengan beberapa No. Matrik;
- protected manual collision;
- ambiguous OneID match; atau
- existing membership conflict.

Unmatched external identity dilaporkan tetapi tidak menjadi calon backfill.
Inactive OneID match dilaporkan berasingan dan Fasa 2 tidak mengubah
`avail_status`.

## 5. Verification

```text
Characterization fixture: 22/22 PASS
Static zero-mutation/runtime-wiring/writer contract: 12/12 PASS
Isolated transaction/rollback rehearsal: 2/2 PASS
Live Preview: can_apply=false
Raw identity in result: none
Membership writes: 0
User/profile/status writes: 0
```

## 6. Keputusan owner

Owner mengesahkan `asisdb..v210_sso_student_aktif` ialah undergraduate sahaja,
meluluskan source code `STUDENT_UG`, dan menerima exact Preview:

- 5,423 candidate memberships;
- zero blocking findings;
- 29 profile review findings yang tidak ditulis.

Backfill masih mesti melalui isolated transaction/rollback rehearsal, fresh
exact-count/digest guard dan post-commit reconciliation.

## 7. Backfill execution

**Change ID:** `ONEID-ODL-F2-20260723-01`

Fresh Preview sebelum transaction tepat sepadan dengan approval:

```text
source_rows=5452
candidate_memberships=5423
blocking_findings=0
review_findings=29
plan_digest=5b5185e2a79d1b46127298fbd3f60303647cf69db71b19c4f0cc4c9b12a7874b
```

Writer menggunakan advisory lock, satu database transaction, exact
count/digest guard dan post-insert reconciliation. Hasil:

```text
source=STUDENT_UG
memberships_inserted=5423
memberships_reconciled=5423
user_mutations=0
lifecycle=dormant
```

`STUDENT_ODL_PG` kekal `dormant` dengan zero membership.

## 8. Post-backfill idempotency

Preview selepas commit:

```text
existing_memberships=5423
candidate_memberships=0
membership_conflicts=0
blocking_findings=0
status=review
```

Ini membuktikan exact membership tidak dirancang semula. Profile-review groups
kekal 29 dan tidak ditulis kepada `user_tbl`.

## 9. Rollback

Guarded rollback hanya dibenarkan jika `STUDENT_UG` masih dormant dan exact
5,423 membership masih wujud:

```bash
ONEID_ODL_F2_CHANGE_ID=ONEID-ODL-F2-20260723-01 \
php tools/odl_f2_provenance_backfill.php --rollback
```

Rollback memadam membership/source `STUDENT_UG` sahaja dan tidak mengubah
`user_tbl`. Jangan jalankan selepas lifecycle berubah atau fasa source-aware
bermula tanpa approval/reconciliation baharu.
