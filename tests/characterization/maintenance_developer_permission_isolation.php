<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__, 2);
require_once $root . '/lib/auth_security.php';
require_once $root . '/lib/request_security.php';
require_once $root . '/app/Maintenance/MaintenanceDeveloperSessionPolicy.php';

use OneId\App\Maintenance\MaintenanceDeveloperSessionPolicy;

$failed = 0;
$checks = 0;
$report = static function (bool $passed, string $label) use (&$failed, &$checks): void {
    $checks++;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    $failed += $passed ? 0 : 1;
};

$_SESSION = [
    'login_status' => 'true',
    'login_user' => 'DEV1',
    'login_user_type' => '0',
    'oneid_maintenance_developer_grant_id' => 8,
    'oneid_maintenance_developer_grant_version' => 2,
];
$report(oneid_is_authenticated(), 'maintenance developer remains an authenticated user');
$report(!oneid_is_admin(), 'maintenance developer is not an administrator');
$report(
    MaintenanceDeveloperSessionPolicy::decide(
        $_SESSION,
        ['allowed' => true, 'grant_id' => 8, 'configuration_version' => 2],
        true
    )['allowed'],
    'maintenance capability is independent from administrator role'
);

$actionMap = oneid_q_func_action_map();
$maintenanceAdminActions = [
    'admin_search_maintenance_developer_candidates',
    'admin_list_maintenance_developer_access',
    'admin_grant_maintenance_developer_access',
    'admin_revoke_maintenance_developer_access',
];
$report(
    count(array_intersect($maintenanceAdminActions, $actionMap['admin'])) === 4
    && count(array_intersect($maintenanceAdminActions, $actionMap['user'])) === 0
    && count(array_intersect($maintenanceAdminActions, $actionMap['public'])) === 0,
    'maintenance access administration actions are admin-only'
);
$report(
    oneid_admin_action_purpose('admin_grant_maintenance_developer_access') === 'SECURITY_CONFIGURATION_CHANGE'
    && oneid_admin_action_purpose('admin_revoke_maintenance_developer_access') === 'SECURITY_CONFIGURATION_CHANGE',
    'grant and revoke retain security configuration step-up purpose'
);

$adminPages = [
    $root . '/admin/dashboard.php',
    $root . '/admin/user_list.php',
    $root . '/admin/report_preview.php',
];
$allAdminPagesGuarded = true;
foreach ($adminPages as $file) {
    $source = (string) file_get_contents($file);
    $allAdminPagesGuarded = $allAdminPagesGuarded
        && str_contains($source, 'oneid_require_admin_page()')
        && str_contains($source, 'oneid_require_admin_step_up(');
}
$report($allAdminPagesGuarded, 'all root admin data pages require admin and step-up');

$publicWrappers = [
    'dashboard.php' => '/admin/dashboard.php',
    'user_list.php' => '/admin/user_list.php',
    'report_preview.php' => '/admin/report_preview.php',
];
$wrappersProtected = true;
foreach ($publicWrappers as $wrapper => $target) {
    $wrappersProtected = $wrappersProtected
        && str_contains((string) file_get_contents($root . '/public/admin/' . $wrapper), $target);
}
$report($wrappersProtected, 'public-root admin wrappers delegate to guarded root pages');

$dashboard = (string) file_get_contents($root . '/page/dashboard.php');
$report(
    str_contains($dashboard, "if(\$_SESSION['login_user_type'] == 1)")
    && str_contains($dashboard, 'id="administrator_entry"'),
    'user dashboard renders administrator entry only for u_type=1'
);
$report(
    str_contains($dashboard, "\$userRoleTranslationKey = in_array((int) (\$user_info['u_category']")
    && !str_contains($dashboard, 'MAINTENANCE_ACCESS_ALLOWED'),
    'user role presentation remains category-based and unchanged by maintenance grant'
);

$requestSecurity = (string) file_get_contents($root . '/lib/request_security.php');
$report(
    str_contains($requestSecurity, "function oneid_is_admin(): bool")
    && str_contains($requestSecurity, "login_user_type'] ?? '') === '1'")
    && !str_contains($requestSecurity, 'oneid_maintenance_developer_grant_id'),
    'admin authorization never consumes maintenance developer session markers'
);

$api = (string) file_get_contents($root . '/lib/q_func.php');
$report(
    in_array('get_specific_user_app_list', $actionMap['user'], true)
    && in_array('go_to_service_provider', $actionMap['user'], true)
    && str_contains($api, 'specfic_user_get_sp_list_by_group')
    && str_contains($api, 'specfic_user_get_sp_list_by_specific_sp')
    && str_contains($api, 'specfic_user_get_sp_blacklist'),
    'application access continues to use existing category specific and blacklist ACLs'
);
$report(
    str_contains($api, "if(\$maintenanceDeveloper){\$site='';}")
    && !str_contains($api, "maintenanceDeveloperDecision['admin']"),
    'maintenance login adds no automatic service-provider or admin entitlement'
);

$repository = (string) file_get_contents(
    $root . '/app/Maintenance/PdoMaintenanceDeveloperAccessRepository.php'
);
$report(
    !str_contains($repository, 'UPDATE user_tbl')
    && !str_contains($repository, 'SET u_type')
    && !str_contains($repository, 'SET u_category'),
    'maintenance persistence cannot mutate user role or category'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
