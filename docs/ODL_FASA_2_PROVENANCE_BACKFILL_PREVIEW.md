# ODL Fasa 2 — Provenance Backfill Preview

**Tarikh:** 23 Julai 2026

**Status:** `PREVIEW COMPLETE / OWNER DECISION REQUIRED / NO BACKFILL`

## 1. Skop

Preview read-only menilai calon provenance bagi student source semasa
`asisdb..v210_sso_student_aktif`. Source code sementara ialah
`STUDENT_ASIS_ACTIVE` sehingga owner mengesahkan nama dan authoritative scope.

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
Characterization fixture: 20/20 PASS
Static zero-mutation/runtime-wiring contract: 8/8 PASS
Live Preview: can_apply=false
Raw identity in result: none
Membership writes: 0
User/profile/status writes: 0
```

## 6. Keputusan diperlukan

Sebelum isolated backfill writer atau live backfill boleh disediakan:

1. Owner perlu mengesahkan sama ada
   `asisdb..v210_sso_student_aktif` ialah:
   - undergraduate sahaja; atau
   - active student source yang lebih luas.
2. Owner perlu meluluskan source code:
   - `STUDENT_UG` jika view dijamin undergraduate sahaja; atau
   - `STUDENT_ASIS_ACTIVE` jika scope lebih luas/tidak eksklusif.
3. Owner perlu menerima exact Preview:
   - 5,423 candidate memberships;
   - zero blocking findings;
   - 29 profile review findings yang tidak ditulis.

Tiada live backfill dibenarkan sebelum tiga keputusan ini direkodkan. Selepas
approval, source registry masih perlu ditambah secara dormant dan backfill mesti
melalui isolated transaction/rollback rehearsal serta exact-count
reconciliation.
