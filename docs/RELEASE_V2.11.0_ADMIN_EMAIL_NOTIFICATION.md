# OneID 2.11.0 — Admin Email Notification

## Release scope

OneID 2.11.0 adds a transactional administrative e-mail outbox, immutable delivery history, bounded delivery worker, MFA exemption expiry worker, bilingual branded messages and notification hooks for account security, MFA, maintenance, security policies, External Sync, applications, Login Banner, locale and metadata.

The committed runtime baseline is `OFF`. Production must not enter `LIVE` mode during initial deployment.

## Production deployment checklist

### 1. Change gate

- Record the approved change reference, deployment start/end window and operator.
- Confirm `/var/www/oneid` is the production path and database host/name are resolved from production secrets; never infer the database name from repository defaults.
- Confirm a current application backup and database backup exist and are restorable.
- Confirm SMTP configuration and the approved production pilot account without printing credentials.

### 2. Preflight

```bash
cd /var/www/oneid
git status --short
git fetch origin
git rev-parse --short HEAD
git rev-parse --short origin/main
php -v
```

Stop if the working tree is dirty, the expected release commit does not match, or the production database backup has not been independently confirmed.

### 3. Schema migration

- Run `docs/migrations/20260905_admin_email_notification_outbox_up.sql` against the database resolved by production secrets.
- Run `php tools/admin_email_notification_schema_contract.php` before and after migration.
- Confirm both `admin_email_notification_outbox` and `admin_email_notification_delivery_history` exist.
- Do not enable the worker yet.

### 4. Code release

```bash
git rebase origin/main
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
php tools/admin_email_notification_foundation_contract.php
php tools/admin_email_notification_routing_contract.php
php tools/admin_email_notification_priority1_contract.php
php tools/admin_email_notification_mfa_maintenance_contract.php
php tools/admin_email_notification_system_policy_contract.php
php tools/admin_email_notification_sync_contract.php
php tools/admin_email_notification_content_contract.php
```

Verify `ONEID_APP_VERSION=2.11.0` through `config/application.php`.

### 5. Dormant verification

Production private runtime must initially use:

```php
'ONEID_ADMIN_EMAIL_NOTIFICATION_ENABLED' => 'false',
'ONEID_ADMIN_EMAIL_NOTIFICATION_DELIVERY_MODE' => 'OFF',
'ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID' => '',
'ONEID_ADMIN_EMAIL_NOTIFICATION_LIVE_APPROVED' => 'false',
'ONEID_ADMIN_EMAIL_NOTIFICATION_MAX_ATTEMPTS' => '5',
'ONEID_ADMIN_EMAIL_NOTIFICATION_RETRY_SECONDS' => '300',
```

Confirm ordinary administrative actions remain available and no notification is queued while delivery is `OFF`.

### 6. Controlled production pilot

- Populate only the separately approved pilot user ID in private runtime.
- Set delivery mode to `PILOT`, enable the notification master switch and keep `LIVE_APPROVED=false`.
- Perform one approved low-risk action and run `php tools/admin_email_notification_worker.php 1` manually.
- Confirm pilot subjects use `[PRODUCTION PILOT]` in production and `[STAGING PILOT]` in staging.
- Verify exact pilot recipient, event, correlation ID, one SENT history row and zero delivery to other recipients.
- Keep the scheduled worker disabled until pilot acceptance is recorded.

### 7. LIVE activation

LIVE activation is a separate approval. Set `DELIVERY_MODE=LIVE` and `LIVE_APPROVED=true` only after recipient/data-classification approval, production pilot acceptance and rollback confirmation. Install schedules for the delivery worker and MFA expiry worker using a single-run lock and bounded interval appropriate to operations.

### 8. Rollback

- First set notification delivery to `OFF` and stop its schedules.
- Preserve outbox/history evidence before code rollback.
- Revert code to the pre-release tag.
- Apply the down migration only when retention approval explicitly permits deleting notification history; it is not the default rollback action.
- Confirm the core administrative mutation remains independent from SMTP delivery.

## Staging evidence

Controlled staging pilot and real UI UAT passed. Application, Login Banner, locale and metadata cleanup restored the staging baseline. At final verification all notification rows targeted only the approved pilot and no row remained pending, processing or failed.
