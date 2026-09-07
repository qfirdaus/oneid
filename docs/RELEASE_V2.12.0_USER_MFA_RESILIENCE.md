# OneID 2.12.0 — User MFA Resilience

## Scope

Release 2.12.0 introduces independently controlled User Login MFA and
Maintenance MFA, five canonical User MFA operating modes, controlled transition
workflows, bounded Emergency Bypass and deterministic restoration.

The release includes additive schema, production maker-checker, Admin Step-Up,
GRACE/IMMEDIATE handling, lifecycle worker, persistent weak-mode visibility,
manual restoration and environment/version-bound fail-closed controls.

## Staging evidence

- ENROLLMENT password-only login and Authenticator self-service passed.
- Restoration to ENFORCED passed.
- EMERGENCY_BYPASS password-only login, banner and User Security passed.
- Manual restore through the correct Admin Step-Up purpose passed.
- Database baseline is ENFORCED version 12 with no open transition.
- Maintenance MFA remains independently ENFORCED.
- Twenty-five related regression suites pass with zero failures.

## Deployment boundary

Production deployment is not authorized by this release document. Follow
`USER_MFA_RESILIENCE_MR7_RELEASE_RUNBOOK.md`, verify application and database
backups through a restore drill, apply the additive schema to `oneiddb_v2`,
deploy the exact approved commit, then keep operational changes dormant until
the lifecycle worker and monitoring pass activation readiness.

## Verification

```bash
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
php tools/user_mfa_resilience_mr7_hardening_contract.php
php tools/user_mfa_resilience_mr7_readiness.php --release
php tools/user_mfa_lifecycle_worker.php --check
```
