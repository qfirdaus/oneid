<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$login = (string) file_get_contents($root . '/index.php');
$api = (string) file_get_contents($root . '/lib/q_func.php');
$gate = (string) file_get_contents($root . '/app/Maintenance/MaintenanceGate.php');
$view = (string) file_get_contents($root . '/resources/views/maintenance.php');
$challenge = (string) file_get_contents($root . '/page/user_mfa_challenge.php');
$runtime = (string) file_get_contents($root . '/config/runtime.php');

$checks = [];
$checks['developer login exists in both supported document-root layouts'] =
    is_file($root . '/maintenance/developer-login.php')
    && is_file($root . '/public/maintenance/developer-login.php')
    && str_contains((string) file_get_contents($root . '/maintenance/developer-login.php'),
        "define('ONEID_DEVELOPER_MAINTENANCE_LOGIN', true)");
$checks['dedicated login is reachable only while maintenance and feature are active'] =
    str_contains((string) file_get_contents($root . '/maintenance/developer-login.php'), "!\$policy['active']")
    && str_contains((string) file_get_contents($root . '/maintenance/developer-login.php'), '!$featureEnabled');
$checks['public maintenance page hides developer login while feature is off'] =
    str_contains($view, 'oneid_maintenance_developer_access_enabled()')
    && str_contains($runtime, "ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED")
    && str_contains($view, '/maintenance/developer-login.php');
$checks['login form sends a dedicated developer maintenance marker'] =
    str_contains($login, 'name="maintenance_developer_login"')
    && str_contains($login, 'ONEID_DEVELOPER_MAINTENANCE_LOGIN');
$checks['dedicated login uses absolute local API and MFA URLs'] =
    str_contains($login, "APP_URL . '/lib/q_func.php'")
    && str_contains($login, "APP_URL . '/page/user-mfa-challenge'");
$checks['maintenance gate admits only exact developer login and pending MFA routes'] =
    str_contains($gate, "array_key_exists('auth', \$_POST)")
    && str_contains($gate, "maintenance_developer_login")
    && str_contains($gate, 'user_mfa_pending_developer_grant_id')
    && str_contains($gate, 'isPendingAdminMfaRoute');
$checks['password authentication precedes developer grant eligibility decision'] =
    strpos($api, 'func_authenticate($_POST') < strpos($api, '$maintenanceDeveloperDecision=$maintenanceDeveloperService->revalidate');
$checks['ineligible developer response does not disclose grant state'] =
    str_contains($api, "'code'=>'MAINTENANCE_ACCESS_DENIED'")
    && str_contains($api, 'The credentials or maintenance access are invalid.');
$checks['developer maintenance forces MFA even outside normal enforced population'] =
    str_contains($api, "new \\OneId\\App\\Auth\\UserMfa\\UserLoginMfaPolicy(")
    && str_contains($api, "'ENFORCED',\$policy->scope")
    && str_contains($api, 'MAINTENANCE_DEVELOPER_MFA_UNAVAILABLE');
$checks['pending transaction binds exact grant id and version'] =
    str_contains($api, "user_mfa_pending_developer_grant_id")
    && str_contains($api, "user_mfa_pending_developer_grant_version");
$checks['grant and maintenance are revalidated immediately around token finalization'] =
    substr_count($api, '$verifyMaintenanceDeveloper();') >= 2
    && strpos($api, 'if($maintenanceDeveloper){$verifyMaintenanceDeveloper();}')
        < strpos($api, '$coordinator->finalize(')
    && str_contains($api, "MaintenancePolicy::evaluate(\$maintenance)");
$checks['finalization compensates token and session when post-MFA checks fail'] =
    str_contains($api, 'update_specific_token_status')
    && str_contains($api, 'oneid_clear_local_authenticated_session();');
$checks['successful developer session remains u_type=0 and grant-bound'] =
    str_contains($api, "(string)(\$userInfo['u_type']??'')!=='0'")
    && str_contains($api, "oneid_maintenance_developer_grant_id")
    && str_contains($api, "oneid_maintenance_developer_grant_version");
$checks['developer finalization targets user dashboard and suppresses SP redirect'] =
    str_contains($api, "elseif(\$maintenanceDeveloperLogin)")
    && str_contains($api, "\$array['redirect_uri'] = 'page/dashboard'")
    && str_contains($api, "if(\$maintenanceDeveloper){\$site='';}");
$checks['cancel and failure clear every pending developer marker'] =
    substr_count($api, 'user_mfa_pending_developer_maintenance') >= 5
    && str_contains($api, 'USER_MFA_LOGIN_CANCELLED');
$checks['feature remains committed off and phase 6 progression is recorded'] =
    str_contains($runtime, "'ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED' => 'false'")
    && str_contains((string) file_get_contents($root . '/docs/MD5_LOGIN_DAN_MFA_DEVELOPER_MAINTENANCE.md'),
        'Fasa 5 diluluskan melalui arahan memulakan Fasa 6');
$checks['existing user MFA challenge remains the shared non-admin factor UI'] =
    str_contains($challenge, "\$maintenanceAdmin?'admin_mfa_factors':'user_mfa_factors'")
    && str_contains($challenge, 'user_mfa_email_verify');

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $failed += $passed ? 0 : 1;
}
printf('RESULT checks=%d failed=%d' . PHP_EOL, count($checks), $failed);
exit($failed === 0 ? 0 : 1);
