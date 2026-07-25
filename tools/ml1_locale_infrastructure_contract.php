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

$required = [
    'app/Locale/LocaleResolver.php',
    'app/Locale/Translator.php',
    'app/Locale/PdoLocalePreferenceRepository.php',
    'config/locales/ms.php',
    'config/locales/en.php',
    'lib/locale.php',
    'docs/migrations/20260725_ml1_locale_preference_up.sql',
    'docs/migrations/20260725_ml1_locale_preference_down.sql',
    'tests/characterization/ml1_locale_infrastructure.php',
    'tools/ml1_locale_schema_rehearsal.php',
    'tools/ml1_uat_migration_gate.php',
];
foreach ($required as $relative) {
    $path = $root . '/' . $relative;
    $lint = true;
    if (str_ends_with($relative, '.php')) {
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);
        $lint = $status === 0;
    }
    $report(is_file($path) && $lint, "source and lint {$relative}");
}

$localeHelper = file_get_contents($root . '/lib/locale.php') ?: '';
$up = file_get_contents($root . '/docs/migrations/20260725_ml1_locale_preference_up.sql') ?: '';
$down = file_get_contents($root . '/docs/migrations/20260725_ml1_locale_preference_down.sql') ?: '';
$requestSecurity = file_get_contents($root . '/lib/request_security.php') ?: '';
$operationalConfig = file_get_contents($root . '/app/Sync/Odl/OdlOperationalConfig.php') ?: '';
$migrationGate = file_get_contents($root . '/tools/ml1_uat_migration_gate.php') ?: '';

$report(
    str_contains($localeHelper, "'secure' => true")
    && str_contains($localeHelper, "'httponly' => true")
    && str_contains($localeHelper, "'samesite' => 'Lax'")
    && str_contains($localeHelper, '180 * 86400'),
    'guest cookie follows approved secure 180-day policy'
);
$report(
    str_contains($up, 'CREATE TABLE IF NOT EXISTS user_locale_preference')
    && str_contains($up, "CHECK (locale IN ('ms','en'))")
    && !preg_match('/\b(?:UPDATE|DELETE)\s+user_tbl\b/i', $up),
    'up migration is additive and presentation-only'
);
$report(
    trim($down) !== '' && str_contains($down, 'DROP TABLE IF EXISTS user_locale_preference'),
    'down migration removes only locale preference table'
);
$report(
    str_contains($localeHelper, "'translation_key' => \$translationKey")
    && str_contains($localeHelper, "'msg' => \$legacyMessage"),
    'stable translation key seam preserves legacy msg'
);
$report(
    str_contains($requestSecurity, 'STEP_UP_REQUIRED')
    && str_contains($operationalConfig, 'ONEID_ODL_OPERATIONAL_EXPECTED_PLAN_HASH'),
    'security and exact-plan canonical boundaries remain present'
);
$report(
    str_contains($migrationGate, 'ML1_SCHEMA_APPLY_DISABLED')
    && str_contains($migrationGate, "['local', 'uat']")
    && str_contains($migrationGate, 'ML1_OUTSIDE_CHANGE_WINDOW')
    && str_contains($migrationGate, 'ML1_PREFERENCE_COUNT_MISMATCH')
    && str_contains($migrationGate, 'APPLY ML1 LOCALE SCHEMA')
    && str_contains($migrationGate, 'ROLLBACK ML1 LOCALE SCHEMA'),
    'UAT migration gate is exact-plan, window-bound and fail-closed'
);

foreach (['index.php', 'page/dashboard.php', 'admin/dashboard.php', 'admin/user_list.php', 'page/admin_step_up.php'] as $relative) {
    $source = file_get_contents($root . '/' . $relative) ?: '';
    $report(str_contains($source, 'oneid_current_locale()'), "dynamic html lang wired: {$relative}");
}

$command = 'php ' . escapeshellarg($root . '/tests/characterization/ml1_locale_infrastructure.php');
passthru($command, $characterizationStatus);
$report($characterizationStatus === 0, 'locale characterization passes');

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
