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
        'ONEID_DB_CHARSET' => 'latin1',
        'ONEID_SSO_IDP_URL' => 'https://oneid.local/',
        'ONEID_SSO_DASHBOARD_URL' => 'https://oneid.local/page/dashboard',
        'ONEID_SAMPLE_DATA_URL' => 'http://localhost/SSO_IDP/sample_data.php',
        'ONEID_SMTP_HOST' => 'smtp.office365.com',
        'ONEID_SMTP_PORT' => 587,
        'ONEID_SMTP_ENCRYPTION' => 'tls',
        'ONEID_SMTP_FROM_NAME' => 'sysadmin@upnm',
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
        'ONEID_SYNC_TRIGGERED_BY' => 'Sync Agent',
    ];
    static $local = null;

    $environmentValue = getenv($key);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }

    if ($local === null) {
        $local = [];
        $runtimeFile = getenv('ONEID_RUNTIME_FILE');
        if ($runtimeFile === false || trim($runtimeFile) === '') {
            $runtimeFile = PROJECT_ROOT . '/.private/runtime.php';
        }
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
