<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/app/Maintenance/MaintenanceDeveloperSessionPolicy.php';

use OneId\App\Maintenance\MaintenanceDeveloperSessionPolicy;

$session = [
    'login_status' => 'true',
    'login_user' => 'DEV1',
    'login_user_type' => '0',
    'oneid_maintenance_developer_grant_id' => 12,
    'oneid_maintenance_developer_grant_version' => 3,
];
$server = ['allowed' => true, 'grant_id' => 12, 'configuration_version' => 3];
$checks = [];
$checks['exact authenticated user grant and active token are allowed'] =
    MaintenanceDeveloperSessionPolicy::decide($session, $server, true)['allowed'];
$wrongType = $session;
$wrongType['login_user_type'] = '1';
$checks['administrator role cannot use developer session capability'] =
    MaintenanceDeveloperSessionPolicy::decide($wrongType, $server, true)['code']
        === 'MAINTENANCE_DEVELOPER_SESSION_INVALID';
$missingGrant = $session;
unset($missingGrant['oneid_maintenance_developer_grant_id']);
$checks['missing session grant is rejected'] =
    MaintenanceDeveloperSessionPolicy::decide($missingGrant, $server, true)['allowed'] === false;
$checks['revoked SSO token is rejected'] =
    MaintenanceDeveloperSessionPolicy::decide($session, $server, false)['code'] === 'SSO_TOKEN_REVOKED';
$checks['server-side grant rejection overrides session values'] =
    MaintenanceDeveloperSessionPolicy::decide($session, ['allowed' => false], true)['code']
        === 'MAINTENANCE_ACCESS_REVALIDATION_FAILED';
$wrongGrant = $server;
$wrongGrant['grant_id'] = 13;
$checks['grant id substitution is rejected'] =
    MaintenanceDeveloperSessionPolicy::decide($session, $wrongGrant, true)['allowed'] === false;
$wrongVersion = $server;
$wrongVersion['configuration_version'] = 4;
$checks['stale grant version is rejected'] =
    MaintenanceDeveloperSessionPolicy::decide($session, $wrongVersion, true)['allowed'] === false;

$root = dirname(__DIR__);
$gate = (string) file_get_contents($root . '/app/Maintenance/MaintenanceGate.php');
$config = (string) file_get_contents($root . '/lib/config.php');
$requestSecurity = (string) file_get_contents($root . '/lib/request_security.php');
$checks['gate revalidates marked developer before API maintenance exemption'] =
    strpos($gate, 'self::enforceDeveloperSession($operation, $policy)')
        < strpos($gate, "if (\$path==='/api.php'||\$path==='/api') return;");
$checks['first maintenance request is safe when no PHP session exists'] =
    str_contains($gate, "isset(\$_SESSION) && is_array(\$_SESSION) ? \$_SESSION : []")
    && str_contains($gate, "array_key_exists('oneid_maintenance_developer_grant_id', \$session)")
    && !str_contains($gate, "array_key_exists('oneid_maintenance_developer_grant_id', \$_SESSION)");
$checks['gate reads active token and server-side access service'] =
    str_contains($gate, 'oneid_sso_cookie_token()')
    && str_contains($gate, 'is_specific_token_active')
    && str_contains($gate, 'MaintenanceDeveloperAccessService');
$checks['invalid session revokes token with reason then clears local session'] =
    str_contains($gate, 'update_specific_token_status($user, $token, 0, $decision[\'code\'])')
    && str_contains($gate, 'oneid_clear_local_authenticated_session()');
$checks['termination is audited without raw token'] =
    str_contains($gate, 'action=maintenance_developer_session outcome=terminated reason=')
    && !str_contains($gate, "' token=' . \$token");
$checks['runtime dependencies are loaded before central gate executes'] =
    strpos($config, 'MaintenanceDeveloperSessionPolicy.php') < strpos($config, 'MaintenanceGate.php');
$checks['admin routes retain exact u_type one boundary'] =
    str_contains($requestSecurity, "login_user_type'] ?? '') === '1'")
    && str_contains($requestSecurity, 'oneid_require_admin_page');
$checks['maintenance end drops capability markers but preserves ordinary login'] =
    strpos($gate, "if (!\$policy['active']) {") < strpos($gate, '$hasDeveloperSessionMarker')
    && str_contains($gate, "\$_SESSION['oneid_maintenance_developer_grant_id']")
    && str_contains($gate, "\$_SESSION['oneid_maintenance_developer_grant_version']")
    && !str_contains(substr(
        $gate,
        strpos($gate, "if (!\$policy['active']) {"),
        strpos($gate, '$hasDeveloperSessionMarker') - strpos($gate, "if (!\$policy['active']) {")
    ), 'oneid_clear_local_authenticated_session');

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $failed += $passed ? 0 : 1;
}
printf('RESULT checks=%d failed=%d' . PHP_EOL, count($checks), $failed);
exit($failed === 0 ? 0 : 1);
