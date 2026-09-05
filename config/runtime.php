<?php

/**
 * Committed, non-secret runtime defaults.
 *
 * Resolution order is: environment variable, .private/runtime.php, default.
 * Environment-specific values and credentials belong in .private/runtime.php.
 */
function oneid_config(string $key, mixed $fallback = null): mixed
{
    static $defaults = [
        'ONEID_APP_URL' => 'https://oneid.local',
        'ONEID_APP_DEBUG' => false,
        // Fail closed: every deployment must set its own private runtime value.
        'ONEID_ENVIRONMENT' => '',
        'ONEID_TIMEZONE' => 'Asia/Kuala_Lumpur',
        'ONEID_DEFAULT_LOCALE' => 'ms',
        // Public banner reader is fail-safe and remains off until the environment is approved.
        'ONEID_LOGIN_BANNER_ENABLED' => 'false',
        // Fail closed per environment; staging activation requires its own ML9 approval.
        'ONEID_LOCALE_INFRASTRUCTURE_ENABLED' => 'false',
        'ONEID_ML1_SCHEMA_APPLY_ENABLED' => 'false',
        'ONEID_ML1_CHANGE_REFERENCE' => '',
        'ONEID_ML1_BACKUP_REFERENCE' => '',
        'ONEID_ML1_WINDOW_START' => '',
        'ONEID_ML1_WINDOW_END' => '',
        'ONEID_ML1_EXPECTED_EXISTING_PREFERENCES' => '0',
        'ONEID_DB_CHARSET' => 'latin1',
        'ONEID_SSO_IDP_URL' => 'https://oneid.local/',
        'ONEID_SSO_DASHBOARD_URL' => 'https://oneid.local/page/dashboard',
        // Internal API identity is provisioned independently per environment.
        'ONEID_API_INTERNAL_CLIENT_ID' => '',
        // e-Madani reminder integration is opt-in and fails open on provider errors.
        'ONEID_EMADANI_ASNB_API_ENABLED' => 'false',
        'ONEID_EMADANI_ASNB_API_URL' => '',
        'ONEID_EMADANI_ASNB_API_CLIENT_ID' => 'oneid',
        'ONEID_EMADANI_ASNB_API_TIMEOUT_SECONDS' => '5',
        // MyDigital ID F1 foundation is dormant. Secret remains private and
        // activation is not permitted until later phase gates pass.
        'ONEID_MYDID_ENABLED' => 'false',
        'ONEID_MYDID_ISSUER' => 'https://sso.digital-id.my/realms/upnm',
        'ONEID_MYDID_CLIENT_ID' => 'upnm-generic',
        'ONEID_MYDID_REDIRECT_URI' => 'https://oneid-uat.upnm.edu.my/auth/mydigitalid/callback.php',
        'ONEID_MYDID_POST_LOGOUT_REDIRECT_URI' => 'https://oneid-uat.upnm.edu.my/',
        'ONEID_MYDID_SCOPE' => 'openid',
        'ONEID_MYDID_HTTP_TIMEOUT_SECONDS' => '12',
        'ONEID_MYDID_PKCE_METHOD' => 'S256',
        // HMAC key material is private; this committed identifier stays empty
        // until a separately approved key is provisioned.
        'ONEID_MYDID_IDENTITY_HMAC_KEY_ID' => '',
        // Live schema application is a separately approved, time-bounded action.
        'ONEID_MYDID_SCHEMA_APPLY_ENABLED' => 'false',
        'ONEID_MYDID_SCHEMA_CHANGE_REFERENCE' => '',
        'ONEID_MYDID_SCHEMA_BACKUP_REFERENCE' => '',
        'ONEID_MYDID_SCHEMA_WINDOW_START' => '',
        'ONEID_MYDID_SCHEMA_WINDOW_END' => '',
        'ONEID_MYDID_AUDIT_RETENTION_REFERENCE' => '',
        'ONEID_SMTP_HOST' => 'smtp.office365.com',
        'ONEID_SMTP_PORT' => 587,
        'ONEID_SMTP_ENCRYPTION' => 'tls',
        'ONEID_SMTP_FROM_NAME' => 'sysadmin@upnm',
        // Required only when TOTP endpoints are wired; key material remains outside the repository.
        'ONEID_TOTP_KEYRING_PATH' => '',
        'ONEID_TOTP_ISSUER' => 'OneID@UPNM',
        'ONEID_STEP_UP_EMAIL_ADMIN_HOURLY_LIMIT' => '10',
        'ONEID_STEP_UP_EMAIL_ADMIN_DAILY_LIMIT' => '30',
        'ONEID_STEP_UP_EMAIL_SESSION_HOURLY_LIMIT' => '10',
        'ONEID_STEP_UP_EMAIL_IP_HOURLY_LIMIT' => '50',
        // AS3 controlled single-session revocation remains dormant until staging approval.
        'ONEID_ACTIVE_SESSION_REVOCATION_ENABLED' => 'false',
        'ONEID_ACTIVE_SESSION_REVOCATION_PILOT_STATES' => 'due,expired',
        'ONEID_ACTIVE_SESSION_REVOCATION_ALLOW_ADMIN_TARGET' => 'false',
        'ONEID_ACTIVE_SESSION_REVOCATION_ALLOW_REVOKE_ALL' => 'false',
        // User idle-warning presentation is activated per environment after F3 UAT approval.
        'ONEID_USER_SESSION_WARNING_ENABLED' => 'false',
        // Scheduled DB-token lifecycle cleanup remains deployment opt-in.
        'ONEID_SESSION_HOUSEKEEPING_SCHEDULED_ENABLED' => 'false',
        // Administrative notification delivery is activated only after the
        // outbox worker and recipient policy pass environment verification.
        'ONEID_ADMIN_EMAIL_NOTIFICATION_ENABLED' => 'false',
        'ONEID_ADMIN_EMAIL_NOTIFICATION_DELIVERY_MODE' => 'OFF',
        'ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID' => '',
        'ONEID_ADMIN_EMAIL_NOTIFICATION_LIVE_APPROVED' => 'false',
        'ONEID_ADMIN_EMAIL_NOTIFICATION_MAX_ATTEMPTS' => '5',
        'ONEID_ADMIN_EMAIL_NOTIFICATION_RETRY_SECONDS' => '300',
        // Developer maintenance access is dormant by default. Environment
        // approval is one-time; each operation is controlled in the Admin UI.
        'ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED' => 'false',
        'ONEID_MAINTENANCE_DEVELOPER_LOCAL_APPROVED' => 'false',
        'ONEID_MAINTENANCE_DEVELOPER_STAGING_APPROVED' => 'false',
        'ONEID_MAINTENANCE_DEVELOPER_PRODUCTION_APPROVED' => 'false',
        // User Login MFA U1 remains dormant. Schema apply and every later
        // activation require separate approval; committed mode stays OFF.
        'ONEID_USER_MFA_MODE' => 'OFF',
        'ONEID_USER_MFA_SCOPE' => 'PASSWORD_ONLY',
        'ONEID_USER_MFA_EMAIL_ENABLED' => 'true',
        'ONEID_USER_MFA_TOTP_ENABLED' => 'false',
        'ONEID_USER_MFA_PENDING_TTL_SECONDS' => '300',
        'ONEID_USER_MFA_OTP_TTL_SECONDS' => '300',
        'ONEID_USER_MFA_MAX_ATTEMPTS' => '5',
        'ONEID_USER_MFA_RESEND_COOLDOWN_SECONDS' => '60',
        'ONEID_USER_MFA_HOURLY_SEND_LIMIT' => '10',
        'ONEID_USER_MFA_SCHEMA_APPLY_ENABLED' => 'false',
        'ONEID_USER_MFA_ACTIVATION_AUTHORIZED' => 'false',
        'ONEID_USER_MFA_SCHEMA_CHANGE_REFERENCE' => '',
        'ONEID_USER_MFA_SCHEMA_BACKUP_REFERENCE' => '',
        'ONEID_USER_MFA_SCHEMA_WINDOW_START' => '',
        'ONEID_USER_MFA_SCHEMA_WINDOW_END' => '',
        'ONEID_USER_MFA_RETENTION_REFERENCE' => '',
        // Maintenance developer access foundation remains dormant. Schema
        // application and feature activation require separate approvals.
        'ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED' => 'false',
        'ONEID_MAINTENANCE_DEVELOPER_SCHEMA_APPLY_ENABLED' => 'false',
        'ONEID_MAINTENANCE_DEVELOPER_SCHEMA_CHANGE_REFERENCE' => '',
        'ONEID_MAINTENANCE_DEVELOPER_SCHEMA_BACKUP_REFERENCE' => '',
        'ONEID_MAINTENANCE_DEVELOPER_SCHEMA_WINDOW_START' => '',
        'ONEID_MAINTENANCE_DEVELOPER_SCHEMA_WINDOW_END' => '',
        // Controlled UAT pilot activation is separately approved after MD9.
        'ONEID_MAINTENANCE_DEVELOPER_PILOT_APPROVED' => 'false',
        'ONEID_MAINTENANCE_DEVELOPER_PILOT_CHANGE_REFERENCE' => '',
        'ONEID_MAINTENANCE_DEVELOPER_PILOT_USER_ID' => '',
        'ONEID_MAINTENANCE_DEVELOPER_PILOT_WINDOW_START' => '',
        'ONEID_MAINTENANCE_DEVELOPER_PILOT_WINDOW_END' => '',
        'ONEID_LEGACY_MD5_DEADLINE' => '2026-10-13 23:59:59 Asia/Kuala_Lumpur',
        'ONEID_SYNC_APPLY_ENABLED' => 'false',
        'ONEID_SYNC_ENGINE' => 'disabled',
        'ONEID_SYNC_PILOT_ENABLED' => 'false',
        'ONEID_SYNC_PILOT_NEW_LIMIT' => '2',
        'ONEID_SYNC_PILOT_UPDATE_LIMIT' => '1',
        'ONEID_SYNC_PILOT_DEACTIVATE_LIMIT' => '0',
        'ONEID_SYNC_PILOT_REACTIVATE_LIMIT' => '0',
        // Full sync requires environment-private exact counts and a 64-char plan hash.
        'ONEID_SYNC_FULL_ENABLED' => 'false',
        'ONEID_SYNC_FULL_EXPECTED_NEW' => '0',
        'ONEID_SYNC_FULL_EXPECTED_UPDATE' => '0',
        'ONEID_SYNC_FULL_EXPECTED_DEACTIVATE' => '0',
        'ONEID_SYNC_FULL_EXPECTED_REACTIVATE' => '0',
        'ONEID_SYNC_FULL_EXPECTED_PLAN_HASH' => '',
        // Routine Apply uses a fresh, one-time preview approval; deployment opt-in is required.
        'ONEID_SYNC_OPERATIONAL_ENABLED' => 'false',
        'ONEID_SYNC_OPERATIONAL_WARN_NEW' => '500',
        'ONEID_SYNC_OPERATIONAL_WARN_UPDATE' => '1000',
        'ONEID_SYNC_OPERATIONAL_WARN_REACTIVATE' => '100',
        'ONEID_SYNC_OPERATIONAL_WARN_TOTAL' => '1500',
        'ONEID_SYNC_OPERATIONAL_MAX_DEACTIVATE' => '50',
        // Conditional CLI sync. Defaults are deliberately disabled and dry-run.
        'ONEID_SYNC_CRON_ENABLED' => 'false',
        'ONEID_SYNC_CRON_DRY_RUN' => 'true',
        // Staging-only opt-in: bypass cron volume/deactivation limits and warnings;
        // integrity blocking codes, lock, exact-plan validation and reconciliation remain.
        'ONEID_SYNC_CRON_ALLOW_ALL_SAFE_CHANGES' => 'false',
        'ONEID_SYNC_CRON_SOURCES' => 'STAFF_HR,STUDENT_UG,STUDENT_ODL_PG',
        'ONEID_SYNC_CRON_MAX_DEACTIVATE' => '0',
        'ONEID_SYNC_CRON_MAX_NEW_STAFF_HR' => '50',
        'ONEID_SYNC_CRON_MAX_UPDATE_STAFF_HR' => '250',
        'ONEID_SYNC_CRON_MAX_REACTIVATE_STAFF_HR' => '20',
        'ONEID_SYNC_CRON_MAX_TOTAL_STAFF_HR' => '300',
        'ONEID_SYNC_CRON_MAX_NEW_STUDENT_UG' => '50',
        'ONEID_SYNC_CRON_MAX_UPDATE_STUDENT_UG' => '250',
        'ONEID_SYNC_CRON_MAX_REACTIVATE_STUDENT_UG' => '20',
        'ONEID_SYNC_CRON_MAX_TOTAL_STUDENT_UG' => '300',
        'ONEID_SYNC_CRON_MAX_NEW_STUDENT_ODL_PG' => '20',
        'ONEID_SYNC_CRON_MAX_UPDATE_STUDENT_ODL_PG' => '100',
        'ONEID_SYNC_CRON_MAX_REACTIVATE_STUDENT_ODL_PG' => '10',
        'ONEID_SYNC_CRON_MAX_TOTAL_STUDENT_ODL_PG' => '120',
        'ONEID_SYNC_CRON_SERVICE_IDENTITY' => 'ONEID-CRON',
        // ODL F7 implementation is dormant until separate Preview/Apply approvals.
        'ONEID_ODL_PILOT_PREVIEW_ENABLED' => 'false',
        'ONEID_ODL_PILOT_APPLY_ENABLED' => 'false',
        'ONEID_ODL_FULL_PREVIEW_ENABLED' => 'false',
        'ONEID_ODL_FULL_APPLY_ENABLED' => 'false',
        'ONEID_ODL_FULL_EXPECTED_SOURCE_ROWS' => '0',
        'ONEID_ODL_FULL_EXPECTED_NEW' => '0',
        'ONEID_ODL_FULL_EXPECTED_KEEP' => '0',
        // F9 manual ODL operational sync. Apply requires a separate exact-plan approval.
        'ONEID_ODL_OPERATIONAL_PREVIEW_ENABLED' => 'false',
        'ONEID_ODL_OPERATIONAL_APPLY_ENABLED' => 'false',
        // Staging/UAT only: fresh one-time Preview approval replaces static plan/window authorization.
        'ONEID_ODL_OPERATIONAL_ON_DEMAND_ENABLED' => 'false',
        // Routine manual Apply uses the shared fresh, one-time Operational approval.
        'ONEID_ODL_MANUAL_OPERATIONAL_ENABLED' => 'false',
        'ONEID_ODL_OPERATIONAL_EXPECTED_SOURCE_ROWS' => '0',
        'ONEID_ODL_OPERATIONAL_EXPECTED_NEW' => '0',
        'ONEID_ODL_OPERATIONAL_EXPECTED_UPDATE' => '0',
        'ONEID_ODL_OPERATIONAL_EXPECTED_DEACTIVATE' => '0',
        'ONEID_ODL_OPERATIONAL_EXPECTED_REACTIVATE' => '0',
        'ONEID_ODL_OPERATIONAL_EXPECTED_PLAN_HASH' => '',
        'ONEID_ODL_OPERATIONAL_CHANGE_REFERENCE' => '',
        'ONEID_ODL_OPERATIONAL_BACKUP_REFERENCE' => '',
        'ONEID_ODL_OPERATIONAL_WINDOW_START' => '',
        'ONEID_ODL_OPERATIONAL_WINDOW_END' => '',
        'ONEID_SYNC_STAFF_PROVENANCE_ENABLED' => 'false',
        'ONEID_SYNC_TRIGGERED_BY' => 'Sync Agent',
    ];
    static $local = null;

    $environmentValue = getenv($key);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }

    if ($local === null) {
        $local = [];
        $runtimeFile = oneid_runtime_file_path();
        if (is_file($runtimeFile) && is_readable($runtimeFile)) {
            $loaded = require $runtimeFile;
            if (!is_array($loaded)) {
                throw new RuntimeException('OneID private runtime configuration has an invalid format.');
            }
            $local = $loaded;
        }
    }

    return $local[$key] ?? $defaults[$key] ?? $fallback;
}

/**
 * Runtime capability gate. During an approved controlled pilot the feature
 * fails closed outside its exact time window, even if the raw flag is left on.
 */
function oneid_maintenance_developer_access_enabled(?DateTimeImmutable $now = null): bool
{
    if (!filter_var(oneid_config('ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN)) {
        return false;
    }
    $approvalKey = match ((string) oneid_config('ONEID_ENVIRONMENT', '')) {
        'local' => 'ONEID_MAINTENANCE_DEVELOPER_LOCAL_APPROVED',
        'staging' => 'ONEID_MAINTENANCE_DEVELOPER_STAGING_APPROVED',
        'production' => 'ONEID_MAINTENANCE_DEVELOPER_PRODUCTION_APPROVED',
        default => null,
    };
    return $approvalKey !== null
        && filter_var(oneid_config($approvalKey, 'false'), FILTER_VALIDATE_BOOLEAN);
}

/** Fail-closed notification routing. LIVE requires a separate explicit approval. */
function oneid_admin_email_notification_delivery_mode(): string
{
    if (!filter_var(oneid_config('ONEID_ADMIN_EMAIL_NOTIFICATION_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN)) {
        return 'OFF';
    }
    $mode = strtoupper(trim((string) oneid_config('ONEID_ADMIN_EMAIL_NOTIFICATION_DELIVERY_MODE', 'OFF')));
    if ($mode === 'PILOT') {
        $pilot = trim((string) oneid_config('ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID', ''));
        return preg_match('/\A[A-Za-z0-9._@-]{1,20}\z/', $pilot) === 1 ? 'PILOT' : 'OFF';
    }
    if ($mode === 'LIVE'
        && filter_var(oneid_config('ONEID_ADMIN_EMAIL_NOTIFICATION_LIVE_APPROVED', 'false'), FILTER_VALIDATE_BOOLEAN)
    ) {
        return 'LIVE';
    }
    return 'OFF';
}
