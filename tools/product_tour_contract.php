<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$dashboard = file_get_contents($root . '/page/dashboard.php') ?: '';
$controller = file_get_contents($root . '/public/dist/js/oneid-product-tour.js') ?: '';
$css = file_get_contents($root . '/public/dist/css/oneid-product-tour.css') ?: '';
$runtime = file_get_contents($root . '/config/runtime.php') ?: '';
$requestSecurity = file_get_contents($root . '/lib/request_security.php') ?: '';
$endpoint = file_get_contents($root . '/lib/q_func.php') ?: '';
$database = file_get_contents($root . '/lib/Database.php') ?: '';
$ms = require $root . '/config/locales/ms.php';
$en = require $root . '/config/locales/en.php';
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    $failed += $ok ? 0 : 1;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
};

$report(str_contains($runtime, "'ONEID_PRODUCT_TOUR_ENABLED' => 'false'"), 'feature fails closed outside approved environments');
$report(str_contains($dashboard, "oneid_config('ONEID_PRODUCT_TOUR_ENABLED', 'false')"), 'dashboard activation is environment controlled');
$report(str_contains($dashboard, "'tourVersion' => \$productTourVersion") && str_contains($controller, "'oneid.product-tour.'+config.id"), 'versioned completion state is isolated per tour');
$report(str_contains($dashboard, 'data-oneid-product-tour-start') && str_contains($controller, '[data-oneid-product-tour-start]'), 'user can replay the guide from the sidebar');
$report(str_contains($dashboard, "'#user_app_search'") && str_contains($dashboard, "'.user-app-favourite'") && str_contains($dashboard, "'.oneid-display-settings__trigger'") && str_contains($dashboard, "'#tab_user_mfa_security'") && str_contains($dashboard, "'[data-oneid-user-session-renew]'"), 'pilot covers five approved dashboard features');
$report(str_contains($controller, 'availableSteps()') && str_contains($controller, '.filter(function(step)') && str_contains($controller, 'visible(document.querySelector(step.selector))'), 'role-specific or unavailable targets are skipped');
$report(str_contains($controller, 'scrollIntoView') && str_contains($controller, "addEventListener('orientationchange'") && str_contains($controller, "addEventListener('resize'"), 'target placement responds to scrolling resizing and rotation');
$report(str_contains($controller, "event.key==='Escape'") && str_contains($controller, "event.key==='Tab'") && str_contains($controller, "role','dialog'") && str_contains($controller, "aria-modal','true'"), 'dialog supports keyboard escape focus containment and semantics');
$report(!str_contains($controller, 'go_to_service_provider') && !str_contains($controller, '$.post'), 'tour does not mutate access or application data');
$report(str_contains($css, '@media(max-width:767px)') && str_contains($css, '48dvh') && str_contains($css, 'safe-area-inset-bottom'), 'mobile uses a safe-area-aware bottom sheet');
$report(str_contains($css, 'min-height:44px') && str_contains($css, 'prefers-reduced-motion'), 'touch targets and reduced motion are supported');

$keys = ['eyebrow','step','back','next','skip','finish','search.title','search.body','favourite.title','favourite.body','display.title','display.body','security.title','security.body','session.title','session.body'];
$localized = true;
foreach ($keys as $key) {
    $full = 'dashboard.tour.' . $key;
    if (!isset($ms[$full], $en[$full]) || trim((string) $ms[$full]) === '' || trim((string) $en[$full]) === '') {
        $localized = false;
    }
}
$report($localized, 'BM and English tour content is complete');
$up=(string)file_get_contents($root.'/docs/migrations/20260916_user_product_tour_progress_up.sql');
$report(str_contains($up,'PRIMARY KEY (u_id, tour_id, tour_version)')&&is_file($root.'/docs/migrations/20260916_user_product_tour_progress_down.sql'),'per-account progress has additive reversible schema');
$report(str_contains($controller,'user_set_product_tour_status')&&str_contains($controller,"'X-CSRF-Token':config.csrfToken")&&str_contains($requestSecurity,"'user_set_product_tour_status'")&&str_contains($endpoint,"isset(\$_POST['user_set_product_tour_status'])"),'completion synchronizes through authenticated CSRF endpoint');
$report(str_contains($controller,'Local completion remains the safe fallback')&&str_contains($database,'getUserProductTourStatus'),'database is authoritative with local fallback');

echo "RESULT checks={$checks} failed={$failed}" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
