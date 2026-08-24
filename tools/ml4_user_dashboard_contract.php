<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;
$failed = 0;
$report = static function (bool $condition, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$condition) {
        $failed++;
    }
};

foreach ([
    'page/dashboard.php',
    'lib/q_func.php',
    'tests/characterization/ml4_user_dashboard_locale.php',
] as $relative) {
    exec('php -l ' . escapeshellarg($root . '/' . $relative) . ' 2>&1', $output, $status);
    $report($status === 0, "source and lint {$relative}");
}

$dashboard = file_get_contents($root . '/page/dashboard.php') ?: '';
$adminDashboard = file_get_contents($root . '/admin/dashboard.php') ?: '';
$adminStepUp = file_get_contents($root . '/page/admin_step_up.php') ?: '';
$ms = file_get_contents($root . '/config/locales/ms.php') ?: '';
$en = file_get_contents($root . '/config/locales/en.php') ?: '';

$report(
    str_contains($dashboard, "oneid_translate('dashboard.menu.applications')")
    && str_contains($dashboard, "oneid_translate('dashboard.apps.search_placeholder')")
    && str_contains($dashboard, "oneid_translate('dashboard.password.title')"),
    'User Dashboard shell, directory and password surfaces are wired'
);
$report(
    str_contains($dashboard, 'aria-label="<?=htmlspecialchars(oneid_translate(')
    && str_contains($dashboard, 'aria-live="assertive"')
    && str_contains($dashboard, 'role="status"'),
    'accessibility labels and live feedback remain present'
);
$report(
    substr_count($adminDashboard, "oneid_translate('dashboard.") === 1
    && str_contains($adminDashboard, "oneid_translate('dashboard.profile_photo')")
    && !str_contains($adminStepUp, "oneid_translate('dashboard."),
    'Administrator surfaces only reuse the shared dashboard profile-photo label'
);
$report(
    str_contains($dashboard, 'profile-locale-switcher')
    && str_contains($dashboard, '$user_info[\'data7\']')
    && str_contains($adminDashboard, 'profile-locale-switcher')
    && str_contains($adminDashboard, '$_SESSION[\'login_user\']')
    && str_contains($dashboard, 'oneid-locale-switcher.css')
    && str_contains($adminDashboard, 'oneid-locale-switcher.css'),
    'Login-style locale selector is centered below User and Administrator profile details'
);
$report(
    str_contains($dashboard, "'dashboard.role.staff'")
    && str_contains($dashboard, "'dashboard.role.student'")
    && str_contains($dashboard, 'oneid_translate($userRoleTranslationKey)')
    && str_contains($dashboard, 'oneid-user-role-badge')
    && str_contains($ms, "'dashboard.role.staff' => 'STAF'")
    && str_contains($ms, "'dashboard.role.student' => 'PELAJAR'")
    && str_contains($en, "'dashboard.role.staff' => 'STAFF'")
    && str_contains($en, "'dashboard.role.student' => 'STUDENT'"),
    'User profile cover exposes the bilingual Staff or Student account category'
);
$report(
    str_contains($dashboard, 'userAppText(application.sp_name)')
    && str_contains($dashboard, 'userAppText(application.sp_description)')
    && str_contains($dashboard, 'userAppText(groupNameRaw)'),
    'database metadata is rendered safely without translation'
);
$report(
    str_contains($dashboard, "window.open('about:blank', '_blank')")
    && str_contains($dashboard, 'go_to_service_provider(String($(this).data(\'app-id\') || \'\'), applicationWindow)')
    && str_contains($dashboard, 'applicationWindow.location.replace(destination)')
    && str_contains($dashboard, 'window.location.assign(destination)')
    && substr_count($dashboard, 'applicationWindow.close()') >= 2,
    'Application launch reserves a user-initiated tab with a same-tab Safari fallback'
);
$report(
    str_contains($dashboard, 'function userAppGroupIsNonSso(group)')
    && str_contains($dashboard, 'userAppGroupsWithNonSsoLast(userAppDirectoryGroups)')
    && str_contains($dashboard, 'return ssoGroups.concat(nonSsoGroups)')
    && str_contains($adminDashboard, 'function adminWebAppGroupIsNonSso(group)')
    && str_contains($adminDashboard, 'adminWebAppGroupsWithNonSsoLast(adminWebAppGroups)'),
    'User and Administrator directories always render Non SSO category tabs last'
);
$report(
    !str_contains($dashboard, 'APPLY SYNC')
    && !str_contains($dashboard, 'ONEID_ODL_')
    && !str_contains($dashboard, 'source_code'),
    'External Sync and exact confirmation surfaces are not introduced'
);

passthru('php ' . escapeshellarg($root . '/tests/characterization/ml4_user_dashboard_locale.php'), $characterization);
$report($characterization === 0, 'ML4 User Dashboard characterization passes');

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
