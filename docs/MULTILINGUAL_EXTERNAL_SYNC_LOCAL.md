# External Sync Multilingual — Local WSL

Change reference: `ONEID-ML-EXTSYNC-LOCAL-20260726-01`

Status: **PASS / CLOSED** pada Local WSL.

Observation evidence: `ONEID-ML-EXTSYNC-LOCAL-20260726-01`

Tester dan approver: Firdaus, System Analyst/DBA.

## Scope implemented

- Parent synchronization modal and read-only Summary.
- Staff, Undergraduate and ODL Preview/Apply child presentation.
- Loading, empty, warning, blocked, success, failure and post-Apply feedback.
- Source-specific notifications, audit references and correlation feedback.
- Bahasa Melayu fallback and English catalogue parity.

## Security boundary

Translation is presentation-only. Source codes, action keys, counts, plan hashes,
Preview digests, approval identifiers, correlation identifiers, blocking/error
codes and exact confirmation phrases remain canonical. Exact confirmation text
is received from the server, compared byte-for-byte and returned unchanged.

Summary remains read-only. Existing source-scoped Apply authorization, source
isolation, manual-account protection and deactivation confirmation remain
authoritative. No scheduler or unattended mutation is enabled.

Admin Step-Up is explicitly outside this change.

## Local verification

Run:

```bash
php tools/multilingual_external_sync_contract.php
```

No schema or database migration is required. Rollback is limited to disabling or
reverting the presentation wiring; synchronization and audit data are preserved.

## Local observation closure

Owner mengesahkan BM dan English presentation, read-only Summary, Staff/UG/ODL
Preview dan Apply presentation, warning/blocked/error states, post-Apply serta
audit feedback semuanya PASS. Exact confirmation kekal canonical, source
isolation dan ACL regression PASS, dan mixed-language critical defects ialah
`0`.

Keputusan: **PASS / CLOSED**. Closure ini tidak membenarkan Git push, staging
atau Production deployment.
