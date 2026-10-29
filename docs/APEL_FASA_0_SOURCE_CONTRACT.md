# APEL Fasa 0 — Source Contract

**Status:** IMPLEMENTED / DORMANT

## Canonical boundary

- source code: `STUDENT_APEL`;
- source family: `student`;
- source view: `vw_oneid_active_students`;
- OneID category: `Pelajar/10`;
- primary user identity: canonical student matric number;
- secondary collision identity: canonical identity/passport number;
- connection: environment-private MySQL connection, independent from ODL;
- provenance, baseline, Preview, Apply, audit and cron remain independent from ODL.

## Safety decision

All APEL runtime gates default to `false`. Connection values default to empty,
therefore no upstream connection is attempted until an administrator fills the
private runtime. Cron is not enabled for APEL in this phase.

Cross-source overlap with Undergraduate or ODL remains fail-closed. A separate
read-only overlap audit must be completed before any controlled Apply. No policy
is relaxed by this phase.

## Required upstream contract

The fixed view must expose the same student fields used by the ODL mapping:
`nama`, `no_kad_pengenalan`, `no_matrik`, `emel_alfateh`, `fakulti`, `program`
and `status_code`. Actual column metadata and the definition of an active row
must be verified read-only after private connection values are supplied.

## Deployment boundary

This phase does not authorize Git push, production deployment, database schema
mutation, Preview, Apply, scheduler or user mutation.
