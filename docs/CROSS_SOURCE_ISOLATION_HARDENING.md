# Cross-source isolation hardening

**Status:** `IMPLEMENTATION COMPLETE / LIVE STAFF BACKFILL NOT AUTHORIZED`

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

No migration or membership backfill was executed. Staff enforcement must not be
enabled until registration and exact-digest backfill receive separate approval.
