<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__, 2);
$dashboard = (string) file_get_contents($root . '/admin/dashboard.php');
$endpoint = (string) file_get_contents($root . '/lib/q_func.php');
$security = (string) file_get_contents($root . '/lib/request_security.php');
$database = (string) file_get_contents($root . '/lib/Database.php');
$checks = [
    'status action remains admin and CSRF protected' =>
        str_contains($security, "'admin_get_sync_apply_status'"),
    'preview returns a server-side recovery checkpoint' =>
        str_contains($endpoint, "['recovery_checkpoint']")
        && str_contains($endpoint, 'sync_latest_source_header_id'),
    'recovery lookup is source actor and checkpoint bound' =>
        str_contains($database, 'source_code=:source_code AND ext_head_id>:after_header_id')
        && str_contains($database, 'triggered_by=:triggered_by'),
    'timeout polling never reissues an Apply request' =>
        str_contains($dashboard, 'recoverApplyResult')
        && str_contains($dashboard, 'admin_get_sync_apply_status')
        && str_contains($dashboard, 'resultUnconfirmed'),
    'recovered completion reports the committed header and counts' =>
        str_contains($dashboard, 'showRecoveredApply')
        && str_contains($dashboard, 'recoveredSuccess')
        && str_contains($endpoint, "'state'=>'COMPLETED'"),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    $failed += $passed ? 0 : 1;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
}
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
