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
    !str_contains($adminDashboard, "oneid_translate('dashboard.")
    && !str_contains($adminStepUp, "oneid_translate('dashboard."),
    'Administrator Dashboard and Admin Step-Up remain outside ML4'
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
    str_contains($dashboard, 'userAppText(application.sp_name)')
    && str_contains($dashboard, 'userAppText(application.sp_description)')
    && str_contains($dashboard, 'userAppText(groupNameRaw)'),
    'database metadata is rendered safely without translation'
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
