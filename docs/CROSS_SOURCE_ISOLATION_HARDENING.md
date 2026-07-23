# Cross-source isolation hardening

**Status:** `BACKFILL RECONCILED / CLOSURE PENDING`

**Environment:** UAT

**Authorization:** `ONEID-SOURCE-ISOLATION-20260723-01`

## Invariant

Preview, approval, Apply, persistence and notifications must retain the same
source boundary. A category is not source provenance. Manual protected accounts
remain visible only as collision guards and cannot become mutation targets.

## Implemented

- UG active and inactive reads require `STUDENT_UG` membership;
- Preview and fresh Apply rebuild use the same scope;
- update/deactivate recheck source ownership at persistence;
- new/reactivated users record source membership in the same transaction;
- a remaining active source prevents account deactivation;
- ODL writer remains fixed to `STUDENT_ODL_PG`;
- Staff has a dormant `STAFF_HR` registry migration, aggregate-only Preview and
  strict feature gate defaulting to `false`;
- summary notifications use per-source action counts;
- cross-source matrix covers Staff, UG, ODL and protected manual accounts.

## Staff Preview evidence

```text
source_rows=1061
valid_source_identities=1061
matched_users=1061
candidate_memberships=1061
invalid=0
duplicate=0
ambiguous=0
protected_collision=0
membership_conflict=0
blocking=0
mutation_statements=0
plan_digest=25bbb78c5ab62b980064adf1c79a2a5d686df491ca7230f2fca636e6c5cb3c94
```

## UAT registration and backfill

Registration dan exact `1061` membership backfill diluluskan menggunakan:

```text
Backup: ONEID-UAT-BACKUP-20260723-02
Window: 23 Julai 2026, 11:45 PM–11:59 PM MYT
Change: ONEID-SOURCE-ISOLATION-20260723-01
```

Run pertama menghasilkan count 1061 tetapi independent semantic reconciliation
mengesan extractor tidak mengulangi category filter Preview. Keseluruhan 1061
membership dan dormant registry dirollback; `user_tbl` kekal tidak berubah.
Extractor dibetulkan dan regression ditambah untuk shared Staff/student
identity.

Run kedua direconcile seperti berikut:

```text
STAFF_HR registry=dormant
memberships=1061
active memberships=1061
distinct users=1061
distinct external IDs=1061
existing reconciliation=1061
conflict=0
blocking=0
user mutations=0
```

Reconciliation turut menemui dan membetulkan comparison-only defects bagi
hyphenated dan numeric-only identifiers. Tiada data diubah untuk kedua-dua
defect tersebut. Staff provenance gate hanya diaktifkan selepas semantic
reconciliation mencapai 1061/1061.

Final active source scopes:

```text
STAFF_HR=1061
STUDENT_UG=5423
STUDENT_ODL_PG=3
Staff actions=0
UG actions=0
ODL=50 candidate new / 3 keep
```
