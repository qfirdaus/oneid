<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = 0;
$failed = 0;
$report = static function (bool $condition, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$condition) {
        $failed++;
    }
};

$ms = require $root . '/config/locales/ms.php';
$en = require $root . '/config/locales/en.php';
$dashboard = file_get_contents($root . '/page/dashboard.php') ?: '';
$response = file_get_contents($root . '/lib/q_func.php') ?: '';

$report(array_keys($ms) === array_keys($en), 'BM and English catalogue keys have exact ordered parity');
$dashboardKeys = array_filter(array_keys($ms), static fn (string $key): bool => str_starts_with($key, 'dashboard.'));
$report(count($dashboardKeys) >= 70, 'User Dashboard catalogue has expected ML4 coverage');
$report(
    str_contains($dashboard, '<html lang="<?=htmlspecialchars(oneid_current_locale()')
    && str_contains($dashboard, 'href="?locale=ms"')
    && str_contains($dashboard, 'href="?locale=en"')
    && str_contains($dashboard, 'profile-locale-switcher'),
    'Dashboard exposes dynamic language metadata and accessible locale selector'
);
$report(
    str_contains($dashboard, 'oneid_promote_authenticated_locale')
    && str_contains($dashboard, "header('Location: ' . APP_URL . '/page/dashboard', true, 303)"),
    'Authenticated locale selection persists then redirects safely'
);
$report(
    str_contains($dashboard, 'const dashboardI18n =')
    && str_contains($dashboard, "oneid_translate('dashboard.apps.empty_search')")
    && str_contains($dashboard, "oneid_translate('dashboard.sessions.confirm_text')"),
    'JavaScript-generated empty, application and session states use catalogue values'
);
$report(
    str_contains($dashboard, 'application.sp_name')
    && str_contains($dashboard, 'application.sp_description')
    && str_contains($dashboard, 'group.sp_group_name')
    && !str_contains($dashboard, 'oneid_translate(application.sp_name')
    && !str_contains($dashboard, 'oneid_translate(group.sp_group_name'),
    'Application and category database metadata remains invariant'
);
$report(
    str_contains($dashboard, 'id="modal_faq"')
    && str_contains($dashboard, 'oneid_render_dashboard_faq()')
    && str_contains($dashboard, "require_once __DIR__ . '/../lib/shared_faq.php'"),
    'User Dashboard FAQ is delegated to the approved shared multilingual source'
);
$report(
    str_contains($response, "'translation_key'=>'dashboard.password.rate_limited'")
    && str_contains($response, "'translation_key']='dashboard.password.success'")
    && str_contains($response, "'msg'=>oneid_translate("),
    'Password responses expose stable translation keys while retaining legacy msg'
);
$report(
    str_contains($response, "'code'=>'UC4_RATE_LIMITED'")
    && str_contains($response, '\'code\'=>$e->reason')
    && str_contains($response, "'correlation_id'"),
    'Security response codes and correlation identifiers remain canonical'
);

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
