# OneID Admin E-mail Notification Delivery Plan

## Safety contract

- Administrative mutations commit independently from SMTP delivery.
- Every notification is inserted into a transactional outbox using a unique idempotency key.
- A bounded CLI worker performs delivery, retry and terminal failure handling.
- E-mail contains no password, OTP belonging to another workflow, API code, token, NRIC or raw session identifier.
- Recipient address is resolved server-side from the current account record or an approved administrator distribution list.
- Staging tests run in `PILOT` mode and are forcibly routed to the approved pilot account; `LIVE` remains fail-closed without separate approval.
- BM and English content reuse the shared `OneIdEmailTemplate` presentation.

## Delivery sequence

1. Foundation: outbox, delivery history, dispatcher and bounded worker. **Implemented on staging; committed defaults remain disabled and staging is temporarily activated in PILOT mode.**
2. Account security: password reset, deactivate/reactivate, ACL and session revocation. **Connected to the transactional outbox and passed controlled pilot delivery.**
3. MFA and maintenance developer access: exemption, grant, revoke and expiry. **Grant/revoke integrations, maintenance expiry and the bounded MFA expiry worker are implemented; all six bilingual lifecycle templates passed pilot delivery.**
4. System operations: maintenance mode and authentication/security policy changes. **Maintenance, SSO, password recovery, global MFA and category MFA changes are connected; five pilot deliveries passed.**
5. Synchronisation: completion, warning and failure summaries to operators. **Pilot, full and operational apply paths are connected using non-blocking queue delivery; all three pilot e-mails passed.**
6. Content and application administration: applications, banners, locale and metadata. **Application lifecycle, Login Banner mutations, default-locale updates and changed metadata translations are connected; all four pilot e-mails passed.**
7. Optional scheduled reports and security digest after data-classification approval.

Schema deployment and runtime activation are separate controlled changes. The committed runtime default remains disabled.

## Staging acceptance

- Controlled pilot delivery was confirmed for MFA and maintenance developer lifecycle events, maintenance/security policy events, External Sync summaries, and content/application administration events.
- Real Administrator UI actions were verified for application creation, Login Banner reorder, default locale change and bilingual metadata update.
- Cleanup was completed: default locale restored to `ms`, Login Banner order restored, and the temporary UAT application archived.
- Cleanup notifications were delivered to the pilot recipient with no pending, processing or failed outbox record at acceptance time.
- LIVE recipient delivery remains unapproved and fail-closed.
