<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$root = dirname(__DIR__);
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label, string $detail = '') use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %-70s%s\n", $ok ? 'PASS' : 'FAIL', $label, $detail === '' ? '' : ' ' . $detail);
};

$files = [
    'app/User/Contracts/UserResyncApprovalStoreInterface.php',
    'app/User/Adapters/SessionUserResyncApprovalStore.php',
    'app/User/UserResyncException.php',
    'app/User/UserResyncService.php',
    'lib/external_data_source_API.php',
    'lib/Database.php',
    'lib/request_security.php',
    'lib/q_func.php',
    'admin/dashboard.php',
    'tests/characterization/m1_safe_user_resync.php',
];
$source = [];
foreach ($files as $relative) {
    $path = $root . '/' . $relative;
    $source[$relative] = is_file($path) ? (string) file_get_contents($path) : '';
    $output = [];
    $exit = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $exit);
    $report($source[$relative] !== '' && $exit === 0, 'source and PHP lint: ' . $relative);
}

$service = $source['app/User/UserResyncService.php'];
$store = $source['app/User/Adapters/SessionUserResyncApprovalStore.php'];
$external = $source['lib/external_data_source_API.php'];
$database = $source['lib/Database.php'];
$guard = $source['lib/request_security.php'];
$qFunc = $source['lib/q_func.php'];
$dashboard = $source['admin/dashboard.php'];

require_once $root . '/lib/request_security.php';
$map = oneid_q_func_action_map();
$report(in_array('admin_preview_specific_user_resync', $map['admin'], true), 'preview action is admin-only');
$report(in_array('admin_apply_specific_user_resync', $map['admin'], true), 'apply action is admin-only');
$report(!in_array('admin_resync_specific_user', $map['admin'], true), 'legacy direct-resync action removed from action map');
$report(str_contains($guard, 'Exactly one recognized action is required') && str_contains($guard, 'oneid_require_csrf();'), 'preview/apply inherit exactly-one-action and CSRF guard');

$report(!str_contains($qFunc, "if(isset( \$_POST['admin_resync_specific_user']))"), 'legacy direct mutation controller removed');
$report(str_contains($qFunc, 'admin_preview_specific_user_resync') && str_contains($qFunc, '->preview('), 'preview endpoint delegates to safe service');
$report(str_contains($qFunc, 'admin_apply_specific_user_resync') && str_contains($qFunc, '->apply('), 'apply endpoint delegates to safe service');
$report(str_contains($qFunc, 'UserResyncException') && str_contains($qFunc, "'correlation_id'"), 'endpoint emits safe correlated failures');

$specificStart = strpos($external, 'function EXTERNAL_DATA_SOURCE_GET_SPECIFIC_USER');
$specificEnd = strpos($external, 'function SAMPLE_DATA_SOURCE_GET_ALL_USER', $specificStart ?: 0);
$specific = $specificStart !== false && $specificEnd !== false
    ? substr($external, $specificStart, $specificEnd - $specificStart)
    : '';
$report($specific !== '' && !preg_match('/\b(?:UPDATE|INSERT|DELETE|MERGE|TRUNCATE)\b/i', $specific), 'external lookup contains SELECT-only SQL');
$report(str_contains($specific, 'WHERE nokp=') && str_contains($specific, 'WHERE CONVERT(VARCHAR(64),no_matrik)='), 'staff and student lookup use authoritative primary identities');
$report(str_contains($specific, 'explicit text') && str_contains($specific, 'implicit VARCHAR-to-INT conversion'), 'student lookup prevents Sybase identity type-conversion failure');
$report(str_contains($specific, 'no_matrik as data4') && str_contains($specific, 'ISNULL(nokp,"") as data2'), 'student lookup matches full-sync field mapping');
$report(str_contains($specific, "ONEID_STUDENT_SYNC_ODBC_DSN") && !str_contains($specific, "ONEID_STUDENT_LOOKUP_ODBC_DSN"), 'student resync uses the proven read-only full-sync source credential');
$report(
    str_contains($specific, "preg_match('/^[A-Za-z0-9._@-]+$/'")
        && str_contains($specific, '$identityLiteral')
        && str_contains($specific, 'odbc_exec('),
    'FreeTDS-compatible lookup uses strict allowlist before SQL literal'
);
$report(str_contains($specific, 'finally') && substr_count($specific, 'odbc_close(') >= 2, 'external connections close on success or failure');
$report(!str_contains($specific, 'exit;') && !str_contains($specific, 'echo '), 'external lookup throws instead of terminating HTTP response');

$report(str_contains($service, "!== 'external'") && str_contains($service, 'RESYNC_MANUAL_PROTECTED'), 'service enforces external provenance and manual protection');
$report(str_contains($service, "return 'staff'") && str_contains($service, "return 'student'") && str_contains($service, 'sourceFamily($current'), 'service routes resync to the matching external source family');
$report(str_contains($service, 'RESYNC_EXTERNAL_USER_NOT_FOUND') && str_contains($service, 'RESYNC_EXTERNAL_USER_AMBIGUOUS'), 'zero and multiple source rows fail closed');
$report(str_contains($service, 'RESYNC_EXTERNAL_IDENTITY_MISMATCH'), 'external identity must match selected account');
$report(str_contains($service, 'PREVIEW_READY') && str_contains($service, 'NO_CHANGES'), 'preview separates ready and no-change outcomes');
$report(str_contains($service, 'fingerprint') && str_contains($service, 'RESYNC_PREVIEW_MISMATCH'), 'apply is bound to preview fingerprint');
$report(str_contains($store, 'consume(') && str_contains($store, 'unset($records[$approvalId])'), 'approval is server-side and one-time');
$report(str_contains($service, 'APPROVAL_TTL_SECONDS = 300') && str_contains($service, 'RESYNC_APPROVAL_EXPIRED'), 'approval expires within five minutes');
$report(str_contains($service, 'beginTransaction()') && str_contains($service, 'commit()') && str_contains($service, 'rollback()'), 'apply has transaction commit and rollback');
$report(str_contains($service, 'admin_update_specific_user_info_all_data(') && str_contains($service, 'syslog_record('), 'profile update and correlated audit share apply service');
$report(str_contains($service, 'RESYNC_AUDIT_NOT_WRITTEN') && str_contains($service, 'RESYNC_UPDATE_NOT_APPLIED'), 'failed update or audit rejects apply');
$report(str_contains($service, "['data2', 'data3', 'data4']") && str_contains($service, 'maskIdentity('), 'identity fields are masked in preview response');

$report(str_contains($database, 'admin_get_user_for_resync') && str_contains($database, 'LIMIT 1 FOR UPDATE'), 'apply can lock selected user row');
$report(str_contains($database, 'account_source, sync_protected'), 'resync read includes provenance fields');
$report(str_contains($dashboard, 'admin_preview_specific_user_resync') && str_contains($dashboard, 'admin_apply_specific_user_resync'), 'modal uses preview then apply endpoints');
$report(str_contains($dashboard, "title: 'Apply resync preview?'") && str_contains($dashboard, 'showCancelButton: true'), 'modal requires explicit preview confirmation');
$report(str_contains($dashboard, 'RESYNC_MANUAL_PROTECTED') && str_contains($dashboard, 'RESYNC_PREVIEW_MISMATCH'), 'modal explains important fail-closed outcomes');
$report(!str_contains($dashboard, "data: {admin_resync_specific_user:"), 'modal no longer calls legacy direct resync');

$fixtureOutput = [];
$fixtureExit = 1;
exec(
    escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg($root . '/tests/characterization/m1_safe_user_resync.php') . ' 2>&1',
    $fixtureOutput,
    $fixtureExit
);
$report(
    $fixtureExit === 0 && in_array('RESULT checks=31 failed=0', $fixtureOutput, true),
    'M1 in-memory zero/live-I/O fixture passes 31/31',
    implode(' | ', array_slice($fixtureOutput, -2))
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
