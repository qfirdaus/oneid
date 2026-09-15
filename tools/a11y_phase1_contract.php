<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if ($content === false) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $content;
};
$checks = [];
$pages = [
    'index.php',
    'page/dashboard.php',
    'page/user_mfa_security.php',
    'page/user_mfa_challenge.php',
    'resources/views/maintenance.php',
    'public/errors/404.html',
];

foreach ($pages as $page) {
    $checks[$page . ' loads the Phase 1 baseline'] = str_contains(
        $read($page),
        'oneid-accessibility-baseline.css?v=20260915-1'
    );
}

foreach (['page/dashboard.php'] as $page) {
    $source = $read($page);
    $checks[$page . ' permits browser zoom'] = !str_contains($source, 'user-scalable=no')
        && !str_contains($source, 'maximum-scale=1.0');
}

$css = $read('public/dist/css/oneid-accessibility-baseline.css');
$checks['baseline uses relative body type'] = str_contains($css, '--oneid-a11y-font-body: 1rem');
$checks['operational text has a readable floor'] = str_contains($css, '--oneid-a11y-font-secondary: .875rem');
$checks['keyboard focus is visible'] = str_contains($css, ':focus-visible')
    && str_contains($css, 'outline: 3px solid');
$checks['form controls have usable targets'] = str_contains($css, 'min-height: 44px');
$checks['tables use local horizontal overflow'] = str_contains($css, 'overflow-x: auto');
$checks['reduced motion is respected'] = str_contains($css, '@media (prefers-reduced-motion: reduce)');
$checks['forced colors retain focus'] = str_contains($css, '@media (forced-colors: active)');
$checks['forgot password uses a semantic button'] = str_contains(
    $read('index.php'),
    'type="button" class="text-primary oneid-forgot-password oneid-link-button"'
);
$checks['session refresh has a keyboard target and accessible name'] = str_contains(
    $read('page/dashboard.php'),
    'class="pull-left inline-block refresh mr-15 oneid-icon-button"'
) && str_contains($read('page/dashboard.php'), 'aria-label="<?=htmlspecialchars(oneid_translate(');
$checks['admin dashboard remains outside the user baseline'] = !str_contains(
    $read('admin/dashboard.php'),
    'oneid-accessibility-baseline.css?v=20260915-1'
);
$checks['admin support pages remain outside the user baseline'] = !str_contains(
    $read('admin/user_list.php') . $read('admin/report_preview.php') . $read('page/admin_step_up.php'),
    'oneid-accessibility-baseline.css?v=20260915-1'
);

$failed = 0;
foreach ($checks as $label => $passed) {
    fwrite(STDOUT, ($passed ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL);
    if (!$passed) {
        $failed++;
    }
}

exit($failed === 0 ? 0 : 1);
