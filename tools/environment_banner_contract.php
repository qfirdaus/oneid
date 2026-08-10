<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/environment_banner.php';

$checks = 0;
$failures = 0;
$check = static function (bool $result, string $description) use (&$checks, &$failures): void {
    $checks++;
    $failures += $result ? 0 : 1;
    printf("%s %s\n", $result ? 'PASS' : 'FAIL', $description);
};

$development = oneid_environment_banner_state('local');
$staging = oneid_environment_banner_state('staging');
$unknown = oneid_environment_banner_state('');
$check(($development['mode'] ?? '') === 'development', 'local maps to development');
$check(($staging['mode'] ?? '') === 'staging', 'staging maps to staging');
$check(oneid_environment_banner_state('production') === null, 'production renders no banner state');
$check(($unknown['mode'] ?? '') === 'warning', 'unknown runtime fails visibly');

ob_start();
oneid_render_environment_banner('production');
$check(ob_get_clean() === '', 'production renders zero markup');

ob_start();
oneid_render_environment_banner('local');
$localMarkup = (string) ob_get_clean();
$check(str_contains($localMarkup, 'DEVELOPMENT ENVIRONMENT'), 'development markup has explicit label');
$check(!str_contains($localMarkup, '<script'), 'banner requires no script');

$root = dirname(__DIR__);
$stylesheet = (string) file_get_contents($root . '/public/dist/css/oneid-environment-banner.css');
$check(
    str_contains($stylesheet, 'linear-gradient(118deg, #075b9a 0%, #087fbd 58%, #09a3c6 100%)'),
    'staging uses the approved MyDigital ID gradient'
);
$surfaces = [
    'index.php',
    'page/dashboard.php',
    'admin/dashboard.php',
    'admin/user_list.php',
    'page/user_mfa_challenge.php',
    'page/user_mfa_security.php',
    'page/admin_step_up.php',
];
foreach ($surfaces as $surface) {
    $source = (string) file_get_contents($root . '/' . $surface);
    $check(
        str_contains($source, 'oneid_render_environment_banner()')
            && str_contains($source, 'oneid-environment-banner.css'),
        $surface . ' wires the shared banner'
    );
}

printf("RESULT checks=%d failures=%d\n", $checks, $failures);
exit($failures === 0 ? 0 : 1);
