<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$root = dirname(__DIR__);
$path = $root . '/tools/s4d_backup_restore_rehearsal.php';
$source = (string) file_get_contents($path);
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$output = [];
$code = 1;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $output, $code);
$report($code === 0, 'backup/restore tool passes PHP lint');
$report(str_contains($source, "in_array('--preflight', \$argv, true)") && str_contains($source, 'mutation_statements=0'), 'preflight is explicit and read-only');
$report(str_contains($source, "in_array('--execute', \$argv, true)"), 'execution requires an explicit mode');
$report(str_contains($source, "ONEID_SYNC_APPLY_ENABLED') !== 'false'") && str_contains($source, "ONEID_SYNC_ENGINE') !== 'disabled'"), 'execution independently requires Apply disabled');
$report(str_contains($source, 'ONEID_REHEARSAL_ALLOWED_SERVER_HOSTNAME') && str_contains($source, 'ONEID_REHEARSAL_ALLOWED_SOURCE_DATABASE'), 'execution requires exact private target allowlists');
$report(str_contains($source, 'stream_isatty(STDIN)') && str_contains($source, "'BACKUP-RESTORE ' . \$sourceDatabase"), 'execution requires interactive database-bound confirmation');
$report(str_contains($source, "\$rehearsalDatabase === \$sourceDatabase"), 'cleanup explicitly rejects the source database');
$report(str_contains($source, "preg_match('/\\Aoneiddb_s4d_"), 'cleanup only permits generated rehearsal names');
$report(!str_contains($source, "'--password=") && str_contains($source, "\$environment['MYSQL_PWD']"), 'password is absent from process arguments');
$report(str_contains($source, "'--single-transaction'") && str_contains($source, "'--skip-lock-tables'"), 'source dump uses non-locking consistent snapshot options');
$report(str_contains($source, 'exact_row_count_reconciliation') && str_contains($source, 'restore_target_dropped'), 'evidence records reconciliation and cleanup');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
