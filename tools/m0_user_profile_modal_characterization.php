<?php

/**
 * M0 static characterization for the admin User Info modal.
 *
 * No application entry point is executed. The runner does not open a session,
 * database connection, HTTP connection or writable runtime location.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$projectRoot = dirname(__DIR__);
if (is_file($projectRoot . '/docs/M1_SAFE_USER_RESYNC_DAN_ROLLBACK.md')
    && !in_array('--force-baseline', $argv, true)) {
    echo "SKIP M0 historical baseline: superseded by M1; use --force-baseline only against the pre-M1 source.\n";
    exit(0);
}
$contracts = require $projectRoot . '/tests/characterization/m0_user_profile_modal_contracts.php';
$checks = 0;
$failed = 0;

$report = static function (bool $ok, string $item, string $detail = '') use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %-72s%s\n", $ok ? 'PASS' : 'FAIL', $item, $detail === '' ? '' : ' ' . $detail);
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
}

$dashboard = $sources['admin/dashboard.php'];
$qFunc = $sources['lib/q_func.php'];
$requestSecurity = $sources['lib/request_security.php'];
$database = $sources['lib/Database.php'];
$external = $sources['lib/external_data_source_API.php'];

require_once $projectRoot . '/lib/request_security.php';
$actionMap = oneid_q_func_action_map();
foreach ($contracts['admin_actions'] as $action) {
    $report(in_array($action, $actionMap['admin'], true), 'admin-only action map: ' . $action);
}

$report(str_contains($dashboard, 'oneid_require_admin_page();'), 'admin dashboard requires administrator session');
$report(str_contains($dashboard, "headers: {'X-CSRF-Token':"), 'dashboard AJAX sends CSRF token');
$report(str_contains($requestSecurity, 'Exactly one recognized action is required'), 'request guard requires exactly one action');
$report(str_contains($requestSecurity, 'oneid_require_csrf();') && str_contains($requestSecurity, 'oneid_is_admin()'), 'request guard enforces CSRF and admin role');

$report(
    preg_match('/#tab_user \\.user-search-input-wrap \\.tt-suggestion\s*\{[^}]*text-align:\s*left;[^}]*vertical-align:\s*top;/s', $dashboard) === 1,
    'autocomplete result is left/top aligned'
);
$report(
    preg_match('/#tab_user \\.user-result-card,[^{]+\{[^}]*align-items:\s*flex-start;/s', $dashboard) === 1
        && preg_match('/#tab_user \\.user-result-profile\s*\{[^}]*align-items:\s*flex-start;/s', $dashboard) === 1,
    'selected result card is top aligned'
);

$report(str_contains($dashboard, 'function view_user_profile(user_id,status)'), 'modal profile entry function exists');
$report(str_contains($dashboard, 'get_specific_user_profile_info(user_id,status);'), 'modal requests profile details');
$report(str_contains($dashboard, 'get_specific_user_sp_access_list(user_id);'), 'modal requests effective application access');
$report(str_contains($dashboard, 'onclick="modal_user_profile_resetPwd()"'), 'reset-password action is exposed');
$report(str_contains($dashboard, 'onclick="modal_user_profile_resync()"'), 'resync action is exposed');
$report(str_contains($dashboard, 'onclick="user_info_deactivate_user();"'), 'deactivate action is exposed');
$report(str_contains($dashboard, 'onclick="user_info_reactivate_user();"'), 'reactivate action is exposed');
$report(str_contains($dashboard, 'class="form-control modal_user_profile_input modal_user_profile_input_enable" id="modal_user_profile_name"'), 'known risk: profile name appears editable');
$report(str_contains($dashboard, 'class="btn btn-primary waves-effect user_info_btn" onclick="">Save</button>'), 'known risk: Save handler is empty');
$report(str_contains($dashboard, "on('change', '#modal_user_profile_category'"), 'known risk: category mutates on dropdown change');

$resyncStart = strpos($qFunc, "if(isset( \$_POST['admin_resync_specific_user']))");
$resyncEnd = strpos($qFunc, "if(isset( \$_POST['admin_reactivate_user_record']))", $resyncStart ?: 0);
$resyncBlock = $resyncStart !== false && $resyncEnd !== false
    ? substr($qFunc, $resyncStart, $resyncEnd - $resyncStart)
    : '';
$report($resyncBlock !== '', 'resync controller block located');
$report(str_contains($resyncBlock, 'SAMPLE_DATA_SOURCE_GET_SPECIFIC_USER('), 'known risk: resync uses sample data source');
$report(!str_contains($resyncBlock, 'EXTERNAL_DATA_SOURCE_GET_SPECIFIC_USER($_POST') || str_contains($resyncBlock, '// $results = EXTERNAL_DATA_SOURCE_GET_SPECIFIC_USER'), 'known risk: authoritative lookup is not active');
$report(str_contains($external, 'http://localhost/SSO_IDP/sample_data.php'), 'known risk: sample lookup targets localhost fixture');
$report(str_contains($external, 'function SAMPLE_DATA_SOURCE_GET_SPECIFIC_USER') && str_contains($external, 'return [];'), 'known risk: missing sample identity returns empty array');
$report(str_contains($resyncBlock, "\$results['data1']") && str_contains($resyncBlock, "\$results['data12']"), 'known risk: resync consumes fields without result schema guard');
$report(str_contains($resyncBlock, 'admin_update_specific_user_info_all_data('), 'known risk: resync directly mutates user profile');
$report(!str_contains($resyncBlock, 'beginTransaction(') && !str_contains($resyncBlock, 'rollback('), 'known risk: resync has no transaction boundary');
$report(!str_contains($resyncBlock, 'account_source') && !str_contains($resyncBlock, 'sync_protected'), 'known risk: resync has no provenance protection');

$resetStart = strpos($qFunc, "if(isset( \$_POST['admin_reset_password_user']))");
$resetEnd = strpos($qFunc, "?>", $resetStart ?: 0);
$resetBlock = $resetStart !== false && $resetEnd !== false
    ? substr($qFunc, $resetStart, $resetEnd - $resetStart)
    : '';
$report($resetBlock !== '', 'reset-password controller block located');
$report(str_contains($resetBlock, 'bin2hex(random_bytes(32))'), 'reset uses an unknown cryptographic password');
$report(str_contains($resetBlock, 'set_user_password(') && str_contains($resetBlock, ', 1);'), 'reset requires subsequent password change');
$report(str_contains($resetBlock, 'update_whole_token_status('), 'reset revokes SSO tokens');
$report(!str_contains($resetBlock, 'syslog_record('), 'known risk: admin reset has no audit event');
$report(!str_contains($resetBlock, 'otp_invalidate_active('), 'known risk: admin reset does not explicitly invalidate active OTP');
$report(!str_contains($resetBlock, 'beginTransaction('), 'known risk: admin reset has no transaction boundary');

$reactivateStart = strpos($qFunc, "if(isset( \$_POST['admin_reactivate_user_record']))");
$deactivateStart = strpos($qFunc, "if(isset( \$_POST['admin_deactivate_user_record']))");
$statusEnd = strpos($qFunc, "if(isset( \$_POST['action_add_new_category']))", $deactivateStart ?: 0);
$reactivateBlock = $reactivateStart !== false && $deactivateStart !== false
    ? substr($qFunc, $reactivateStart, $deactivateStart - $reactivateStart)
    : '';
$deactivateBlock = $deactivateStart !== false && $statusEnd !== false
    ? substr($qFunc, $deactivateStart, $statusEnd - $deactivateStart)
    : '';
$report(str_contains($deactivateBlock, 'update_whole_token_status(') && str_contains($deactivateBlock, 'syslog_record(25,'), 'deactivate revokes tokens and writes audit event');
$report(str_contains($reactivateBlock, 'syslog_record(26,'), 'reactivate writes audit event');
$report(str_contains($reactivateBlock, "'status' => 1") && str_contains($deactivateBlock, "'status' => 1"), 'known risk: status endpoints return unconditional success');
$report(!str_contains($reactivateBlock . $deactivateBlock, 'beginTransaction('), 'known risk: status changes have no transaction boundary');

$categoryStart = strpos($qFunc, "if(isset( \$_POST['admin_change_user_category']))");
$categoryEnd = strpos($qFunc, "if(isset( \$_POST['add_new_specific_apps_to_user']))", $categoryStart ?: 0);
$categoryBlock = $categoryStart !== false && $categoryEnd !== false
    ? substr($qFunc, $categoryStart, $categoryEnd - $categoryStart)
    : '';
$report(str_contains($categoryBlock, "\$_POST['category_id'] == 9"), 'known risk: admin category ID is hardcoded');
$report(str_contains($categoryBlock, 'update_whole_token_status(') && str_contains($categoryBlock, 'syslog_record(18,'), 'category change revokes tokens and writes audit event');
$report(!str_contains($categoryBlock, 'isActiveUserCategory('), 'known risk: category action does not validate active category');

$aclStart = strpos($qFunc, "if(isset( \$_POST['add_new_specific_apps_to_user']))");
$aclEnd = strpos($qFunc, "if(isset( \$_POST['admin_get_specific_service_provider']))", $aclStart ?: 0);
$aclAddBlock = $aclStart !== false && $aclEnd !== false
    ? substr($qFunc, $aclStart, $aclEnd - $aclStart)
    : '';
$denyStart = strpos($qFunc, "if(isset( \$_POST['admin_set_deny_access_record']))");
$denyEnd = strpos($qFunc, "if(isset( \$_POST['update_configuration']))", $denyStart ?: 0);
$denyBlock = $denyStart !== false && $denyEnd !== false
    ? substr($qFunc, $denyStart, $denyEnd - $denyStart)
    : '';
$upliftStart = strpos($qFunc, "if(isset( \$_POST['admin_uplift_blacklist_record']))");
$upliftEnd = strpos($qFunc, "if(isset( \$_POST['admin_get_all_token_for_specific_user']))", $upliftStart ?: 0);
$upliftBlock = $upliftStart !== false && $upliftEnd !== false
    ? substr($qFunc, $upliftStart, $upliftEnd - $upliftStart)
    : '';
$report(str_contains($database, 'INSERT INTO  acl_single') && str_contains($database, 'INSERT INTO  acl_blacklist'), 'ACL persistence uses prepared inserts');
$report(!str_contains($aclAddBlock . $denyBlock . $upliftBlock, 'syslog_record('), 'known risk: individual ACL changes have no audit event');
$report(!str_contains($aclAddBlock . $denyBlock . $upliftBlock, 'beginTransaction('), 'known risk: individual ACL changes have no transaction boundary');
$report(str_contains($dashboard, "+response[i]['sp_name']+"), 'known risk: ACL app name is concatenated into HTML');
$report(preg_match_all('/error:\s*function\s*\([^)]*\)\s*\{\s*\}/s', $dashboard) > 0, 'known risk: modal/dashboard contains empty AJAX error callbacks');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
