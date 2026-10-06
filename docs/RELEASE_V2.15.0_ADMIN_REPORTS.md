# OneID 2.15.0 — Administrative Reports Expansion

Release date: 6 October 2026

## Scope

The administrative Reports module continues to use the existing six category
tabs. Seven read-only reports are added, bringing the catalogue from 18 to 25:

1. MyDigital ID Executive Overview — Executive Overview.
2. MyDigital ID Linked Accounts — Users & Access.
3. Downstream SSO Usage — Applications & Production Readiness.
4. MyDigital ID Authentication Report — Sessions & Security.
5. Synchronisation Health & Freshness — Synchronisation.
6. Administrator Activity — Audit & Configuration.
7. MFA Policy Change History — Audit & Configuration.

## Data and privacy

MyDigital ID reporting reads `user_federated_identity` and
`federated_auth_event`. It does not render NRIC, subject, HMAC, session token,
provider token or secret values. The downstream report aggregates existing
90-day redirect audit events and does not render individual user identifiers.
Administrative reports omit IP addresses and configuration snapshots.

## Operational boundaries

- No database migration or write operation is included.
- No new report category tab is introduced.
- Production mobile login remains disabled.
- Existing opaque, short-lived administrator report references and step-up
  authorization remain in force.
