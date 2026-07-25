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
    'tests/characterization/ml2_pilot_locale.php',
    'tools/ml2_local_preference_rehearsal.php',
    'index.php',
    'lib/locale.php',
    'lib/q_func.php',
    'app/Mail/OneIdEmailTemplate.php',
    'app/Auth/AdminStepUpPhpMailerSender.php',
] as $relative) {
    exec('php -l ' . escapeshellarg($root . '/' . $relative) . ' 2>&1', $output, $status);
    $report($status === 0, "source and lint {$relative}");
}

$login = file_get_contents($root . '/index.php') ?: '';
$response = file_get_contents($root . '/lib/q_func.php') ?: '';
$locale = file_get_contents($root . '/lib/locale.php') ?: '';
$userDashboard = file_get_contents($root . '/page/dashboard.php') ?: '';
$adminDashboard = file_get_contents($root . '/admin/dashboard.php') ?: '';

$report(
    str_contains($login, 'href="?locale=ms"')
    && str_contains($login, 'href="?locale=en"')
    && str_contains($login, 'hreflang="ms"')
    && str_contains($login, 'hreflang="en"'),
    'accessible BM and English selector is limited to Login'
);
$report(
    str_contains($locale, "'secure' => true")
    && str_contains($locale, "'httponly' => true")
    && str_contains($locale, "'samesite' => 'Lax'"),
    'guest locale cookie remains Secure HttpOnly SameSite Lax'
);
$report(
    str_contains($locale, 'PdoLocalePreferenceRepository')
    && str_contains($locale, 'oneid_authenticated_locale'),
    'authenticated preference is promoted into session'
);
$report(
    str_contains($response, "'translation_key' => 'recovery.accepted_generic'")
    && str_contains($response, "'msg' => oneid_translate('recovery.accepted_generic')"),
    'Password Recovery emits stable translation key and legacy msg'
);
$report(
    str_contains($userDashboard, "oneid_translate('dashboard.")
    && !str_contains($adminDashboard, "oneid_translate('dashboard.")
    && str_contains($adminDashboard, "oneid_translate('login.language_label')"),
    'User Dashboard is translated while Administrator only exposes the locale selector'
);
$report(
    str_contains($response, 'SC6_RECOVERY_REQUEST_ACCEPTED')
    && str_contains($response, 'hash(\'sha256\'')
    && str_contains($response, 'correlation_id'),
    'recovery response and audit identifiers remain canonical'
);

passthru('php ' . escapeshellarg($root . '/tests/characterization/ml2_pilot_locale.php'), $characterization);
$report($characterization === 0, 'ML2 Pilot characterization passes');

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
