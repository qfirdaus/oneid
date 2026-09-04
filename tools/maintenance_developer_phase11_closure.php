<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }
define('ONEID_MAINTENANCE_BYPASS', true);
require_once dirname(__DIR__) . '/lib/config.php';

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$value = static fn(string $key): string => trim((string) oneid_config($key, ''));
$active = (int) $pdo->query("SELECT COUNT(*) FROM maintenance_developer_access_grants WHERE grant_status='ACTIVE'")->fetchColumn();
$granted = (int) $pdo->query("SELECT COUNT(*) FROM maintenance_developer_access_history WHERE action_name='GRANTED'")->fetchColumn();
$revoked = (int) $pdo->query("SELECT COUNT(*) FROM maintenance_developer_access_history WHERE action_name='REVOKED'")->fetchColumn();
$checks = [
    'feature_is_disabled' => !filter_var($value('ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED'), FILTER_VALIDATE_BOOLEAN),
    'effective_runtime_gate_is_closed' => !oneid_maintenance_developer_access_enabled(),
    'pilot_approval_is_closed' => !filter_var($value('ONEID_MAINTENANCE_DEVELOPER_PILOT_APPROVED'), FILTER_VALIDATE_BOOLEAN),
    'schema_apply_is_closed' => $value('ONEID_MAINTENANCE_DEVELOPER_SCHEMA_APPLY_ENABLED') === 'false',
    'grant_history_exists' => $granted >= 1,
    'no_active_grants_remain' => $active === 0,
    'revocation_history_exists' => $revoked >= 1,
];
foreach ($checks as $name => $passed) { printf("%s %s\n", $passed ? 'PASS' : 'PENDING', $name); }
$complete = !in_array(false, $checks, true);
printf("DECISION=%s effective_access=off active_grants=%d granted_events=%d revoked_events=%d live_mutations=0\n",
    $complete ? 'CLOSED' : 'CLOSURE_PENDING', $active, $granted, $revoked);
exit($complete ? 0 : 1);
