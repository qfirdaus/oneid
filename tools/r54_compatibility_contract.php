<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$checks = 0;
$failed = 0;

$report = static function (bool $passed, string $label, string $detail = '') use (&$checks, &$failed): void {
    $checks++;
    if (!$passed) {
        $failed++;
    }

    printf(
        "%s %-64s%s\n",
        $passed ? 'PASS' : 'FAIL',
        $label,
        $detail === '' ? '' : ' ' . $detail
    );
};

$sharedTarget = "'/app/Auth/LogoutEndpoint.php'";
foreach (['public/admin/logout.php', 'public/page/logout.php'] as $wrapper) {
    $path = $projectRoot . '/' . $wrapper;
    $contents = is_file($path) ? (string) file_get_contents($path) : '';
    $report(is_file($path) && !is_link($path), 'logout wrapper is a regular file: ' . $wrapper);
    $report(str_contains($contents, $sharedTarget), 'logout wrapper uses shared target: ' . $wrapper);
    $report(
        str_contains($contents, '\\OneId\\App\\Auth\\LogoutEndpoint::run();'),
        'logout wrapper runs shared endpoint: ' . $wrapper
    );
}

$endpointPath = $projectRoot . '/app/Auth/LogoutEndpoint.php';
$endpoint = is_file($endpointPath) ? (string) file_get_contents($endpointPath) : '';
$report(is_file($endpointPath), 'shared LogoutEndpoint exists');
$report(str_contains($endpoint, "'/lib/session_security.php'"), 'endpoint loads secure session bootstrap');
$report(str_contains($endpoint, "'/lib/config.php'"), 'endpoint loads application configuration');
$report(str_contains($endpoint, "'/lib/SSO_IDP_INC.php'"), 'endpoint loads SSO compatibility operation');
$report(str_contains($endpoint, "'/LogoutHandler.php'"), 'endpoint loads extracted logout handler');
$report(str_contains($endpoint, 'LogoutHandler::handle('), 'endpoint delegates logout behavior');

foreach (['admin/logout.php', 'page/logout.php'] as $retiredImplementation) {
    $report(
        !is_file($projectRoot . '/' . $retiredImplementation),
        'duplicate implementation absent: ' . $retiredImplementation
    );
}

$remainingWrappers = [
    'public/index.php' => "'/index.php'",
    'public/api.php' => "'/api.php'",
    'public/idms.php' => "'/idms.php'",
    'public/skp_api.php' => "'/skp_api.php'",
    'public/admin/dashboard.php' => "'/admin/dashboard.php'",
    'public/admin/user_list.php' => "'/admin/user_list.php'",
    'public/page/dashboard.php' => "'/page/dashboard.php'",
    'public/lib/q_func.php' => "'/lib/q_func.php'",
    'public/lib/sso_IDP_index.php' => "'/lib/sso_IDP_index.php'",
    'public/lib/sso_IDP_sub.php' => "'/lib/sso_IDP_sub.php'",
];
foreach ($remainingWrappers as $wrapper => $targetFragment) {
    $contents = is_file($projectRoot . '/' . $wrapper)
        ? (string) file_get_contents($projectRoot . '/' . $wrapper)
        : '';
    $report(
        str_contains($contents, $targetFragment),
        'non-logout compatibility wrapper unchanged: ' . $wrapper
    );
}

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
