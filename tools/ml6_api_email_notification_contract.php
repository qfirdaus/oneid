<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};

foreach ([
    'app/Locale/ApiResponseLocalizer.php',
    'app/Mail/OneIdEmailTemplate.php',
    'lib/q_func.php',
    'admin/dashboard.php',
    'page/dashboard.php',
    'tests/characterization/ml6_api_email_notifications.php',
    'tools/ml6_active_response_inventory.php',
] as $relative) {
    exec('php -l ' . escapeshellarg($root . '/' . $relative) . ' 2>&1', $output, $status);
    $report($status === 0, "source and lint {$relative}");
}

$localizer = file_get_contents($root . '/app/Locale/ApiResponseLocalizer.php') ?: '';
$endpoint = file_get_contents($root . '/lib/q_func.php') ?: '';
$admin = file_get_contents($root . '/admin/dashboard.php') ?: '';
$user = file_get_contents($root . '/page/dashboard.php') ?: '';
$mail = file_get_contents($root . '/app/Mail/OneIdEmailTemplate.php') ?: '';

$report(
    str_contains($endpoint, 'ApiResponseLocalizer::enrich')
    && str_contains($localizer, "\$response['localized_msg']")
    && str_contains($localizer, "\$response['translation_key']"),
    'scoped JSON compatibility enrichment is wired'
);
$report(
    str_contains($localizer, "'SYNC_'")
    && str_contains($localizer, "'ODL_'")
    && str_contains($localizer, "'RESYNC_'")
    && str_contains($localizer, "'STEP_UP_'")
    && str_contains($localizer, "'ADMIN_2FA_'"),
    'External Sync and Admin Step-Up boundaries are excluded'
);
$report(
    !str_contains($localizer, "unset(\$response['msg'])")
    && !str_contains($localizer, "unset(\$response['message'])"),
    'legacy msg and message fields are retained'
);
$report(
    str_contains($admin, 'localizedResponseMessage(response')
    && str_contains($user, 'response&&response.localized_msg'),
    'new frontend paths consume localized presentation'
);
$report(
    str_contains($mail, "oneid_translate('email.test.headline'")
    && str_contains($mail, "oneid_translate('email.test.plain'"),
    'transactional delivery-test e-mail is catalogue driven'
);
$report(
    !str_contains($localizer, 'correlation_id] =')
    && !str_contains($localizer, 'exact_confirmation'),
    'technical identifiers and exact confirmation remain invariant'
);

passthru(
    'php ' . escapeshellarg($root . '/tests/characterization/ml6_api_email_notifications.php'),
    $status
);
$report($status === 0, 'ML6 characterization passes');
passthru(
    'php ' . escapeshellarg($root . '/tools/ml6_active_response_inventory.php'),
    $status
);
$report($status === 0, 'ML6 active response inventory has zero unresolved codes');

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
