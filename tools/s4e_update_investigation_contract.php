<?php

if (PHP_SAPI !== 'cli') { exit(2); }
$root = dirname(__DIR__);
$path = $root . '/tools/s4e_update_investigation.php';
$source = is_file($path) ? (string) file_get_contents($path) : '';
$checks = 0; $failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++; if (!$ok) $failed++; printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $output, $code);
$report($source !== '' && $code === 0, 'update investigation exists and passes PHP lint');
$report(str_contains($source, "PHP_SAPI !== 'cli'"), 'tool is CLI-only');
$report(str_contains($source, "in_array('--reveal', \$argv, true)"), 'raw reveal requires explicit flag');
$report(str_contains($source, 'stream_isatty(STDIN)') && str_contains($source, "hash_equals('REVEAL-UPDATES'"), 'raw reveal requires interactive confirmation');
$report(str_contains($source, "hash('sha256', \$userId)") && str_contains($source, 'change_fingerprint='), 'default identities and changes are fingerprinted');
$report(str_contains($source, 'EXTERNAL_DATA_SOURCE_GET_ALL_USER()') && str_contains($source, 'sync_get_all_sso_user()'), 'tool reads production planner inputs');
$report(str_contains($source, 'SyncPlanner') && str_contains($source, "=== 'UPDATE'"), 'tool uses production planner and selects UPDATE only');
$report(str_contains($source, 'MANIFEST digest=') && str_contains($source, 'sort($manifest'), 'stable redacted manifest supports future comparison');
$report(str_contains($source, 'pilot_selected=%s') && str_contains($source, "\$index === 0 ? 'yes' : 'no'"), 'first deterministic UPDATE is identified as pilot selection');
$forbidden = ['beginTransaction(', '->commit(', '->rollback(', 'createPilotCoordinator(', 'admin_add_sync_user', 'sync_update_header_summary('];
$report(array_filter($forbidden, static fn(string $call): bool => str_contains($source, $call)) === [], 'tool contains no transaction mutation approval or Apply caller');
$report(str_contains($source, 'mutation_statements=0'), 'tool emits explicit zero-mutation marker');
printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
