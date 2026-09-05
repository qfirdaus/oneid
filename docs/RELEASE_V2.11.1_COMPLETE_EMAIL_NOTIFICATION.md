# OneID 2.11.1 — Complete Email Notification

## Scope

Release 2.11.1 completes the shared branded e-mail presentation and user-security notification coverage introduced in 2.11.0. It adds the embedded UPNM header logo, user-readable summaries, missing account/password/MFA/MyDigital ID/login-warning flows, and environment-aware recipient routing.

## Environment policy

- Production uses `LIVE` and delivers notifications to the real affected recipient.
- Staging and WSL/local use `PILOT` for informational messages.
- OTP and verification-code messages in staging/local remain addressed to the user who must complete the verification.
- Informational and test messages in staging/local are redirected to the configured pilot account.
- An invalid PILOT recipient fails closed.

## Verification

Run:

```bash
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
php tools/oneid_email_template_contract.php
php tools/admin_email_notification_user_flow_contract.php
php tools/admin_email_notification_routing_contract.php
php tools/staging_email_recipient_policy_contract.php
```

No schema migration is required for this patch. The additive outbox schema from 2.11.0 remains canonical.
