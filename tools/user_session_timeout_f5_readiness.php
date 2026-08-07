<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }

$mode = $argv[1] ?? '--source';
if (!in_array($mode, ['--source', '--staging'], true)) {
    fwrite(STDERR, "Usage: php tools/user_session_timeout_f5_readiness.php [--source|--staging]\n");
    exit(2);
}

$root = dirname(__DIR__);
$contracts = [
    'tests/characterization/user_session_timeout_f0_baseline.php',
    'tests/characterization/user_session_timeout_f1_policy.php',
    'tests/characterization/user_session_timeout_f2_endpoints.php',
    'tests/characterization/user_session_timeout_f3_presentation.php',
    'tests/characterization/user_session_timeout_f4_heartbeat.php',
    'tests/characterization/as1_idle_heartbeat_policy.php',
    'tests/characterization/as2_revoked_token_enforcement.php',
    'tests/characterization/admin_access_session_renewal.php',
    'tests/characterization/ml4_user_dashboard_locale.php',
    'tools/sc0_sso_configuration_contract.php',
    'tools/sc2_sso_configuration_service_contract.php',
    'tools/sc4_token_lifetime_contract.php',
    'tools/uc0_user_password_change_contract.php',
    'tools/mydigitalid_f5_contract.php',
];

$failures = 0;
foreach ($contracts as $contract) {
    echo "\n=== {$contract} ===\n";
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/' . $contract);
    passthru($command, $status);
    if ($status !== 0) {
        $failures++;
        echo "FAIL contract={$contract} exit={$status}\n";
    }
}

if ($mode === '--staging') {
    define('ONEID_CONFIG_SKIP_DATABASE', true);
    require_once $root . '/lib/config.php';

    $enabled = filter_var(
        oneid_config('ONEID_USER_SESSION_WARNING_ENABLED', 'false'),
        FILTER_VALIDATE_BOOLEAN
    );
    echo ($enabled ? 'PASS' : 'FAIL') . " staging_feature_flag_enabled\n";
    $failures += $enabled ? 0 : 1;

    try {
        $pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $events = (int) $pdo->query(
            "SELECT COUNT(*) FROM syslog_event_conf
             WHERE (syslog_event_id=68 AND syslog_event_name='USER_PORTAL_SESSION_EXPIRED')
                OR (syslog_event_id=69 AND syslog_event_name='USER_PORTAL_SESSION_RENEWED')
                OR (syslog_event_id=70 AND syslog_event_name='USER_PORTAL_SESSION_ENDED')"
        )->fetchColumn();
        echo ($events === 3 ? 'PASS' : 'FAIL') . " staging_audit_dictionary_events={$events}/3\n";
        $failures += $events === 3 ? 0 : 1;

        $timeout = trim((string) $pdo->query(
            'SELECT token_timeout FROM sys_config WHERE singleton_key=1'
        )->fetchColumn());
        $allowed = in_array($timeout, ['0.5','1','2','12','24','48','72','168'], true);
        echo ($allowed ? 'PASS' : 'FAIL') . ' staging_admin_timeout_allowed=' . ($allowed ? $timeout : 'invalid') . "\n";
        $failures += $allowed ? 0 : 1;
    } catch (Throwable $exception) {
        $failures++;
        echo 'FAIL staging_readonly_database_check class=' . get_class($exception) . "\n";
    }
}

echo "\nRESULT mode={$mode} contracts=" . count($contracts) . " failures={$failures}\n";
exit($failures === 0 ? 0 : 1);
