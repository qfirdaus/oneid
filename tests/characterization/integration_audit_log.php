<?php

declare(strict_types=1);

$path = sys_get_temp_dir() . '/oneid-integration-audit-' . bin2hex(random_bytes(6)) . '.log';
putenv('ONEID_INTEGRATION_AUDIT_LOG=' . $path);
$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
require_once dirname(__DIR__, 2) . '/lib/integration_security.php';

oneid_integration_audit('api_access', [
    'endpoint' => 'sso_token',
    'auth_result' => 'missing_credentials',
    'mode' => 'observe',
    'token' => 'must-never-be-written',
    'password' => 'must-never-be-written',
]);

$raw = is_file($path) ? (string) file_get_contents($path) : '';
$record = json_decode(trim($raw), true);
$mode = is_file($path) ? (fileperms($path) & 0777) : 0;
@unlink($path);

$checks = [
    'dedicated JSON record is written' => is_array($record),
    'record contains operational fields' => ($record['component'] ?? null) === 'oneid_integration'
        && ($record['event'] ?? null) === 'api_access'
        && ($record['auth_result'] ?? null) === 'missing_credentials'
        && isset($record['recorded_at'], $record['request_id']),
    'sensitive context is excluded' => !str_contains($raw, 'must-never-be-written')
        && !array_key_exists('token', $record ?? [])
        && !array_key_exists('password', $record ?? []),
    'new log is not world readable' => ($mode & 0007) === 0,
    'record is one line' => substr_count(trim($raw), "\n") === 0,
];

$failures = 0;
foreach ($checks as $description => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
    $failures += $passed ? 0 : 1;
}
printf(
    "RESULT checks=%d failures=%d production_logs_changed=0 database_access=0\n",
    count($checks),
    $failures
);
exit($failures === 0 ? 0 : 1);
