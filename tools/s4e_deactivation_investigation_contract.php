<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$root = dirname(__DIR__);
$path = $root . '/tools/s4e_deactivation_investigation.php';
$source = is_file($path) ? (string) file_get_contents($path) : '';
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$output = [];
$lintCode = 1;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $output, $lintCode);
$report($source !== '' && $lintCode === 0, 'investigation tool exists and passes PHP lint');
$report(str_contains($source, "PHP_SAPI !== 'cli'"), 'tool is CLI-only');
$report(str_contains($source, "in_array('--reveal', \$argv, true)"), 'identity reveal requires explicit flag');
$report(str_contains($source, "stream_isatty(STDIN)") && str_contains($source, "hash_equals('REVEAL'"), 'identity reveal requires interactive confirmation');
$report(str_contains($source, "hash('sha256', \$userId)"), 'default candidate identity is digested');
$report(str_contains($source, 'EXTERNAL_DATA_SOURCE_GET_ALL_USER()'), 'tool reads the external snapshot');
$report(str_contains($source, 'sync_get_all_sso_user()') && str_contains($source, 'sync_get_inactive_user_ids()'), 'tool performs only planner input reads');
$report(str_contains($source, 'SyncPlanner') && str_contains($source, "=== 'DEACTIVATE'"), 'tool uses the production planner and selects deactivation actions');

$forbiddenCalls = [
    'beginTransaction(',
    '->commit(',
    '->rollback(',
    'admin_update_user_status(',
    'admin_update_specific_user_info_all_data(',
    'action_add_new_user_from_external_source(',
    'action_add_new_ext_header(',
    'action_add_external_temp_body(',
    'sync_log_change_batch(',
    'sync_update_header_summary(',
    'admin_add_sync_user',
];
$found = array_values(array_filter(
    $forbiddenCalls,
    static fn(string $call): bool => str_contains($source, $call)
));
$report($found === [], 'tool contains no transaction, mutation or Apply caller');
$report(str_contains($source, 'mutation_statements=0'), 'tool emits an explicit zero-mutation marker');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);

