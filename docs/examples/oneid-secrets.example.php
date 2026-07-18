<?php

// Template only. Keep the real file outside the public document root and Git.
return [
    // Application
    'ONEID_APP_URL' => 'https://oneid.local',
    'ONEID_ENVIRONMENT' => 'local',
    'ONEID_APP_DEBUG' => false,
    'ONEID_TIMEZONE' => 'Asia/Kuala_Lumpur',
    'ONEID_DB_CHARSET' => 'latin1',
    'ONEID_LEGACY_MD5_DEADLINE' => '2026-10-13 23:59:59 Asia/Kuala_Lumpur',

    // SSO and sample data
    'ONEID_SSO_IDP_URL' => 'https://oneid.local/',
    'ONEID_SSO_DASHBOARD_URL' => 'https://oneid.local/page/dashboard',
    'ONEID_SAMPLE_DATA_URL' => 'http://localhost/SSO_IDP/sample_data.php',

    // API security
    'ONEID_API_AUTH_MODE' => 'observe',
    'ONEID_API_RATE_LIMIT_PER_MINUTE' => '600',
    'ONEID_API_CLIENTS_JSON' => '{}',

    // Primary database
    'ONEID_DB_DSN' => '',
    'ONEID_DB_USERNAME' => '',
    'ONEID_DB_PASSWORD' => '',

    // Email and SMTP
    'ONEID_SMTP_HOST' => 'smtp.office365.com',
    'ONEID_SMTP_PORT' => '587',
    'ONEID_SMTP_ENCRYPTION' => 'tls',
    'ONEID_SMTP_FROM_NAME' => 'sysadmin@upnm',
    'ONEID_SMTP_USERNAME' => '',
    'ONEID_SMTP_PASSWORD' => '',

    // External Sync: shared runtime
    'ONEID_SYNC_APPLY_ENABLED' => 'false',
    'ONEID_SYNC_ENGINE' => 'disabled',
    'ONEID_SYNC_TRIGGERED_BY' => 'Sync Agent',

    // External Sync: Operational mode
    'ONEID_SYNC_OPERATIONAL_ENABLED' => 'false',
    'ONEID_SYNC_OPERATIONAL_WARN_NEW' => '500',
    'ONEID_SYNC_OPERATIONAL_WARN_UPDATE' => '1000',
    'ONEID_SYNC_OPERATIONAL_WARN_REACTIVATE' => '100',
    'ONEID_SYNC_OPERATIONAL_WARN_TOTAL' => '1500',
    'ONEID_SYNC_OPERATIONAL_MAX_DEACTIVATE' => '50',

    // External Sync: Controlled Full mode
    'ONEID_SYNC_FULL_ENABLED' => 'false',
    'ONEID_SYNC_FULL_EXPECTED_NEW' => '0',
    'ONEID_SYNC_FULL_EXPECTED_UPDATE' => '0',
    'ONEID_SYNC_FULL_EXPECTED_DEACTIVATE' => '0',
    'ONEID_SYNC_FULL_EXPECTED_REACTIVATE' => '0',
    'ONEID_SYNC_FULL_EXPECTED_PLAN_HASH' => '',

    // External Sync: Controlled Pilot mode
    'ONEID_SYNC_PILOT_ENABLED' => 'false',
    'ONEID_SYNC_PILOT_NEW_LIMIT' => '2',
    'ONEID_SYNC_PILOT_UPDATE_LIMIT' => '1',
    'ONEID_SYNC_PILOT_DEACTIVATE_LIMIT' => '0',
    'ONEID_SYNC_PILOT_REACTIVATE_LIMIT' => '0',

    // Backup and restore rehearsal
    'ONEID_REHEARSAL_ALLOWED_SERVER_HOSTNAME' => '',
    'ONEID_REHEARSAL_ALLOWED_SOURCE_DATABASE' => '',

    // Staff source
    'ONEID_STAFF_ODBC_DSN' => '',
    'ONEID_STAFF_ODBC_USERNAME' => '',
    'ONEID_STAFF_ODBC_PASSWORD' => '',

    // Student Sync source
    'ONEID_STUDENT_SYNC_ODBC_DSN' => '',
    'ONEID_STUDENT_SYNC_ODBC_USERNAME' => '',
    'ONEID_STUDENT_SYNC_ODBC_PASSWORD' => '',

    // Student Lookup source
    'ONEID_STUDENT_LOOKUP_ODBC_DSN' => '',
    'ONEID_STUDENT_LOOKUP_ODBC_USERNAME' => '',
    'ONEID_STUDENT_LOOKUP_ODBC_PASSWORD' => '',

    // SKP source
    'ONEID_SKP_ODBC_DSN' => '',
    'ONEID_SKP_ODBC_USERNAME' => '',
    'ONEID_SKP_ODBC_PASSWORD' => '',

    // IDMS source
    'ONEID_IDMS_ODBC_CONNECTION' => '',
    'ONEID_IDMS_ODBC_USERNAME' => '',
    'ONEID_IDMS_ODBC_PASSWORD' => '',

    // Diagnostic agent
    'ONEID_DIAG_AGENT_TOKEN' => '',
    'ONEID_DIAG_MYSQL_DSN' => '',
    'ONEID_DIAG_MYSQL_USERNAME' => '',
    'ONEID_DIAG_MYSQL_PASSWORD' => '',
    'ONEID_DIAG_SYBASE_ODBC_DSN' => '',
    'ONEID_DIAG_SYBASE_USERNAME' => '',
    'ONEID_DIAG_SYBASE_PASSWORD' => '',

    // General diagnostics
    'ONEID_DIAGNOSTIC_TOKEN' => '',
];
