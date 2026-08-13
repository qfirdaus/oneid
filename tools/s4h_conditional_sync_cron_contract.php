<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'app/Sync/SyncCronConfig.php',
    'app/Sync/ConditionalSyncCronRunner.php',
    'app/Sync/Adapters/InMemorySyncApprovalStore.php',
    'app/Sync/SyncSourceScope.php',
    'app/Sync/SyncEngineFactory.php',
    'cron/run_conditional_external_sync.php',
    'config/runtime.php',
    'lib/q_func.php',
    'lib/Database.php',
];
$source = [];
$failed = 0;
$report = static function (bool $passed, string $name) use (&$failed): void {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
    if (!$passed) $failed++;
};
foreach ($files as $file) {
    $source[$file] = (string) file_get_contents($root . '/' . $file);
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $file), $out, $code);
    $report($code === 0, 'PHP lint ' . $file);
}
$config = $source['config/runtime.php'];
$runner = $source['app/Sync/ConditionalSyncCronRunner.php'];
$cli = $source['cron/run_conditional_external_sync.php'];
$factory = $source['app/Sync/SyncEngineFactory.php'];
$scope = $source['app/Sync/SyncSourceScope.php'];
$q = $source['lib/q_func.php'];
$database = $source['lib/Database.php'];
$report(
    str_contains($config, "'ONEID_SYNC_CRON_ENABLED' => 'false'")
        && str_contains($config, "'ONEID_SYNC_CRON_DRY_RUN' => 'true'")
        && str_contains($config, "'ONEID_SYNC_CRON_MAX_DEACTIVATE' => '0'"),
    'committed cron defaults are disabled dry-run and no-deactivation'
);
$report(
    strpos($runner, "array_sum(\$counts) === 0")
        < strpos($runner, 'createCronCoordinator'),
    'zero-change exits before writer coordinator construction'
);
$report(
    str_contains($runner, "\$warnings !== []")
        && str_contains($runner, 'SYNC_CRON_STAFF_PROVENANCE_REQUIRED'),
    'warnings and unprovenanced Staff fail closed'
);
$report(
    str_contains($cli, "PHP_SAPI !== 'cli'")
        && !str_contains($cli, '$_POST')
        && !str_contains($cli, '$_SESSION')
        && !str_contains($cli, 'curl'),
    'runner is CLI-only and independent from HTTP Admin authorization'
);
$report(
    str_contains($factory, 'createCronCoordinator')
        && str_contains($scope, 'fromCodeForCron')
        && str_contains($q, "admin_apply_operational_sync"),
    'cron seam is separate while manual operational endpoint remains available'
);
$report(
    str_contains($runner, 'ADMIN_SYNC_CRON_SAFE')
        && str_contains($runner, 'APPLIED_AUDIT_WARNING'),
    'successful mutation has source-specific audit marker and uncertainty outcome'
);
$report(
    str_contains($database, "['STAFF_HR','STUDENT_UG','STUDENT_ODL_PG']")
        && !str_contains($database, "['STAFF_HR','STUDENT_UG_SMP','STUDENT_ODL_PG']"),
    'cron writer accepts the canonical UG source code used by the runner'
);
exec(
    escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg($root . '/tests/characterization/s4h_conditional_sync_cron.php'),
    $output,
    $code
);
foreach ($output as $line) echo $line, PHP_EOL;
$report($code === 0, 'pure cron configuration characterization passes');
printf("RESULT checks=%d failed=%d\n", count($files) + 8, $failed);
exit($failed === 0 ? 0 : 1);
