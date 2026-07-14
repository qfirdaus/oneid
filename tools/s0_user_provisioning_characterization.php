<?php

/**
 * S0 read-only characterization for external sync and manual user creation.
 *
 * No application entry point is executed. No database, HTTP endpoint, external
 * source, session or writable runtime location is accessed.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$projectRoot = dirname(__DIR__);
if (is_file($projectRoot . '/docs/S1_MANUAL_USER_HARDENING_DAN_PROVENANCE.md')
    && !in_array('--force-baseline', $argv, true)) {
    echo "SKIP S0 historical baseline: superseded by S1; use Git commit 93d7445 to reproduce the original 50/50 result.\n";
    exit(0);
}
$contracts = require $projectRoot . '/tests/characterization/s0_user_provisioning_contracts.php';
$checks = 0;
$failed = 0;

$report = static function (bool $ok, string $item, string $detail = '') use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %-62s%s\n", $ok ? 'PASS' : 'FAIL', $item, $detail === '' ? '' : ' ' . $detail);
};

$sources = [];
foreach ($contracts['runtime_files'] as $relative) {
    $path = $projectRoot . '/' . $relative;
    $sources[$relative] = is_file($path) ? (string) file_get_contents($path) : '';
    $report($sources[$relative] !== '', 'source available: ' . $relative);

    $lintOutput = [];
    $lintExit = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $lintOutput, $lintExit);
    $report($lintExit === 0, 'PHP lint: ' . $relative);

    $actualHash = is_file($path) ? hash_file('sha256', $path) : '';
    $expectedHash = $contracts['runtime_sha256'][$relative] ?? '';
    $report(
        $expectedHash !== '' && hash_equals($expectedHash, $actualHash),
        'runtime baseline hash: ' . $relative,
        'sha256=' . ($actualHash ?: '-')
    );
}

$dashboard = $sources['admin/dashboard.php'];
$qFunc = $sources['lib/q_func.php'];
$requestSecurity = $sources['lib/request_security.php'];
$database = $sources['lib/Database.php'];
$syncRunner = $sources['lib/sync_user_runner.php'];

$actionMap = require $projectRoot . '/lib/request_security.php';
unset($actionMap);
$mappedActions = oneid_q_func_action_map();
foreach ($contracts['admin_actions'] as $action) {
    $report(in_array($action, $mappedActions['admin'], true), 'admin-only action map: ' . $action);
}

$guardPosition = strpos($qFunc, 'oneid_guard_q_func_request($_POST);');
$syncActionPosition = strpos($qFunc, "if(isset( \$_POST['admin_add_sync_user']))");
$manualActionPosition = strpos($qFunc, "if(isset( \$_POST['action_add_new_user_manual_check_user_id']))");
$report(
    $guardPosition !== false
        && $syncActionPosition !== false
        && $manualActionPosition !== false
        && $guardPosition < $syncActionPosition
        && $guardPosition < $manualActionPosition,
    'shared request guard executes before both actions'
);
$report(str_contains($dashboard, 'oneid_require_admin_page();'), 'admin dashboard requires administrator session');
$report(str_contains($dashboard, "headers: {'X-CSRF-Token':"), 'dashboard AJAX sends CSRF token');

$report(str_contains($dashboard, 'onclick="pick_add_sync_user();"'), 'external sync button remains exposed to admin');
$report(str_contains($dashboard, "data: {admin_add_sync_user:''}"), 'external sync posts production action directly');
$report(str_contains($dashboard, 'onclick="pick_add_single_user();"'), 'manual add button remains exposed to admin');
$report(
    str_contains($dashboard, "data.push({name: 'action_add_new_user_manual_check_user_id', value: ''});"),
    'manual form posts production action'
);

foreach ($contracts['manual_fields']['required'] as $field) {
    $pattern = '/<(?:input|select)\\b(?=[^>]*\\bname=["\']' . preg_quote($field, '/') . '["\'])(?=[^>]*\\brequired(?:=["\'][^"\']*["\'])?)[^>]*>/i';
    $report(preg_match($pattern, $dashboard) === 1, 'browser-required manual field: ' . $field);
}
$emailField = preg_quote($contracts['manual_fields']['optional_email'], '/');
$emailPattern = '/<input\\b(?=[^>]*\\bname=["\']' . $emailField . '["\'])[^>]*>/i';
$emailTag = preg_match($emailPattern, $dashboard, $emailMatch) === 1 ? $emailMatch[0] : '';
$report($emailTag !== '' && !preg_match('/\\brequired(?:=|\\s|>)/i', $emailTag), 'manual email remains optional');

$syncUiStart = strpos($dashboard, 'function pick_add_sync_user()');
$syncUiEnd = strpos($dashboard, 'function pick_add_single_user()', $syncUiStart ?: 0);
$syncUi = $syncUiStart !== false && $syncUiEnd !== false
    ? substr($dashboard, $syncUiStart, $syncUiEnd - $syncUiStart)
    : '';
$report(
    $syncUi !== '' && !preg_match('/\\b(confirm|dry[_-]?run|preview)\\b/i', $syncUi),
    'external sync has no preview/dry-run/confirmation'
);

$manualUiStart = strpos($dashboard, 'var form_add_new_user_manual');
$manualUiEnd = strpos($dashboard, 'function nav_back_to_category_listing', $manualUiStart ?: 0);
$manualUi = $manualUiStart !== false && $manualUiEnd !== false
    ? substr($dashboard, $manualUiStart, $manualUiEnd - $manualUiStart)
    : '';
$report(
    preg_match('/error:\s*function\s*\([^)]*\)\s*\{\s*\}/s', $manualUi) === 1,
    'manual-add AJAX error handler remains empty'
);

$syncBlock = $syncActionPosition !== false && $manualActionPosition !== false
    ? substr($qFunc, $syncActionPosition, $manualActionPosition - $syncActionPosition)
    : '';
$report(str_contains($syncBlock, 'run_admin_sync_user($operation, $triggered_by)'), 'sync action calls legacy production runner');
$report(str_contains($syncBlock, "['error' => \$e->getMessage()]"), 'sync action returns raw exception message');

$nextActionPosition = strpos($qFunc, "if(isset( \$_POST['admin_resync_specific_user']))", $manualActionPosition ?: 0);
$manualBlock = $manualActionPosition !== false && $nextActionPosition !== false
    ? substr($qFunc, $manualActionPosition, $nextActionPosition - $manualActionPosition)
    : '';
$lookupPosition = strpos($manualBlock, 'get_specific_user_info(');
$insertPosition = strpos($manualBlock, 'action_add_new_user(');
$report($lookupPosition !== false && $insertPosition !== false && $lookupPosition < $insertPosition, 'manual duplicate check precedes insert');
$report(
    str_contains($manualBlock, 'oneid_password_hash(bin2hex(random_bytes(32)))'),
    'manual account receives unknown cryptographic initial password'
);
$report(str_contains($manualBlock, 'setPasswordChangeRequired('), 'manual account requires password change');
$report(str_contains($manualBlock, 'syslog_record(23,'), 'manual creation writes audit event 23');
$report(
    !str_contains($manualBlock, 'beginTransaction(')
        && !str_contains($manualBlock, 'commit(')
        && !str_contains($manualBlock, 'rollback('),
    'manual insert/flag/audit has no transaction boundary'
);
$report(
    !str_contains($manualBlock, 'filter_var(')
        && !str_contains($manualBlock, 'FILTER_VALIDATE_EMAIL')
        && !str_contains($manualBlock, 'mb_strlen('),
    'manual action has no server-side format/length/email validation'
);
$report(
    str_contains($manualBlock, 'hash("sha256",$_POST[\'add_new_manual_user_name\'])'),
    'manual change hash covers name only'
);

$report(
    str_contains($database, 'INSERT INTO  user_tbl(')
        && str_contains($database, '$this->pdo->prepare($Q)')
        && str_contains($database, "password_change_required,u_type,avail_status")
        && str_contains($database, 'VALUES (:u_id,:u_category,:u_password,1,0,1'),
    'manual persistence uses prepared insert and change-required default'
);
$report(
    str_contains($database, 'WHERE avail_status = 1')
        && !str_contains($database, 'account_source')
        && !str_contains($database, 'sync_protected'),
    'sync reads all active accounts without provenance protection'
);

$beginPosition = strpos($syncRunner, '$operation->beginTransaction();');
$sourcePosition = strpos($syncRunner, 'EXTERNAL_DATA_SOURCE_GET_ALL_USER();');
$tryPosition = strpos($syncRunner, 'try {', $sourcePosition ?: 0);
$report(
    $beginPosition !== false && $sourcePosition !== false && $beginPosition < $sourcePosition,
    'legacy transaction begins before external-source fetch'
);
$report(
    $sourcePosition !== false && $tryPosition !== false && $sourcePosition < $tryPosition,
    'external fetch remains outside rollback try/catch'
);
$report(str_contains($syncRunner, "admin_update_user_status(\$rm_list['u_id'],0)"), 'unmatched active account is deactivated');
$report(str_contains($syncRunner, "return ['10'];"), 'only hardcoded UID 10 is excluded from sync');
$report(
    !preg_match('/(?:flock|GET_LOCK|advisory[_ ]lock|sync[_ ]lock)/i', $syncRunner),
    'legacy sync has no server-side concurrency lock'
);
$report(
    str_contains($syncRunner, "admin_update_ext_header_status(\$header_id,3,'ext_head_initial_sourcedata',0)")
        && !str_contains($syncRunner, 'deactivation_threshold'),
    'empty source is guarded but partial-source mass deactivation is not'
);
$report(
    str_contains($database, 'WHERE avail_status = 1')
        && str_contains($syncRunner, '$to_remove_list[] = $sso;')
        && str_contains($syncRunner, "admin_update_user_status(\$rm_list['u_id'],0)"),
    'manual account absent externally can enter deactivation path'
);

$runFixture = static function (string $relative, string $expectedResult) use ($projectRoot): array {
    $output = [];
    $exitCode = 1;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($projectRoot . '/' . $relative) . ' 2>&1', $output, $exitCode);
    return [$exitCode === 0 && in_array($expectedResult, $output, true), implode(' | ', array_slice($output, -2))];
};

[$legacyOk, $legacyDetail] = $runFixture('tests/characterization/r52_sync_orchestration.php', 'RESULT checks=18 failed=0');
$report($legacyOk, 'legacy in-memory sync regression remains 18/18', $legacyDetail);

[$dryRunOk, $dryRunDetail] = $runFixture('tests/characterization/r52_sync_dry_run_zero_mutation.php', 'RESULT checks=25 failed=0');
$report($dryRunOk, 'dormant pure dry-run remains zero-mutation 25/25', $dryRunDetail);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
