<?php

/**
 * Read-only characterization for deterministic sync transformation helpers.
 * No database, session, HTTP or external integration is used.
 *
 * Usage:
 *   php tools/r52_pure_helpers.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$projectRoot = dirname(__DIR__);
$contracts = require $projectRoot . '/tests/characterization/r52_pure_helper_contracts.php';
require_once $projectRoot . '/lib/sync_user_runner.php';

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $item) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $item);
};

$requiredFunctions = [
    'sync_compute_hash',
    'sync_log_field_names',
    'sync_build_log_snapshot',
    'sync_pick_log_fields',
    'sync_get_changed_fields',
    'sync_remove_duplicateKeys',
    'sync_get_exclude_uids',
];
foreach ($requiredFunctions as $function) {
    $report(function_exists($function), 'function available: ' . $function);
}
$transformerClass = \OneId\App\Sync\SyncDataTransformer::class;
$report(class_exists($transformerClass), 'transformer class available');

$hashInput = $contracts['hash_input'];
$hash = sync_compute_hash(...$hashInput);
$report($hash === $contracts['hash_expected'], 'hash trims and preserves field order');

$differentCategory = $hashInput;
$differentCategory[12] = 'Student';
$report(sync_compute_hash(...$differentCategory) !== $hash, 'hash includes category');

$report(sync_log_field_names() === $contracts['field_names'], 'log field order contract');

$snapshot = sync_build_log_snapshot($contracts['snapshot_input']);
$report($snapshot === $contracts['snapshot_expected'], 'snapshot trim/default/filter contract');

$picked = sync_pick_log_fields($contracts['snapshot_input'], $contracts['pick_fields']);
$report($picked === $contracts['pick_expected'], 'picked fields append category');

$pickedWithCategory = sync_pick_log_fields(
    $contracts['snapshot_input'],
    ['data2', 'ext_data_source_category']
);
$report(
    $pickedWithCategory === ['data2' => '123', 'ext_data_source_category' => 'Staff'],
    'picked fields do not duplicate category'
);

$changed = sync_get_changed_fields($contracts['changed_old'], $contracts['changed_new']);
$report($changed === $contracts['changed_expected'], 'changed fields order and trim contract');
$report(
    sync_get_changed_fields(['data1' => ' Same '], ['data1' => 'Same']) === '',
    'whitespace-only change ignored'
);

$uniqueRows = [
    ['u_changes_hash' => 'a', 'value' => 1],
    ['u_changes_hash' => 'b', 'value' => 2],
];
$report(sync_remove_duplicateKeys($uniqueRows) === $uniqueRows, 'unique hashes retained');

$pairRows = [
    ['u_changes_hash' => 'a', 'value' => 1],
    ['u_changes_hash' => 'a', 'value' => 2],
];
$report(sync_remove_duplicateKeys($pairRows) === [], 'duplicate pair removes both rows');

$tripleRows = [
    ['u_changes_hash' => 'a', 'value' => 1],
    ['u_changes_hash' => 'a', 'value' => 2],
    ['u_changes_hash' => 'a', 'value' => 3],
];
$report(
    sync_remove_duplicateKeys($tripleRows) === [['u_changes_hash' => 'a', 'value' => 3]],
    'third duplicate reappears as current legacy behavior'
);

$report(sync_get_exclude_uids() === ['10'], 'hardcoded sync exclusion policy contract');

$delimiterLeft = array_fill(0, 13, '');
$delimiterRight = array_fill(0, 13, '');
$delimiterLeft[0] = 'ab';
$delimiterLeft[1] = 'c';
$delimiterRight[0] = 'a';
$delimiterRight[1] = 'bc';
$report(
    sync_compute_hash(...$delimiterLeft) === sync_compute_hash(...$delimiterRight),
    'legacy delimiter-free hash ambiguity characterized'
);

$report(
    sync_get_changed_fields(['data8' => 'old'], ['data8' => 'new']) === '',
    'legacy changed-field scope ignores data8-data12'
);

$report(
    $transformerClass::computeHash(...$hashInput) === $hash,
    'computeHash class/wrapper parity'
);
$report(
    $transformerClass::logFieldNames() === sync_log_field_names(),
    'logFieldNames class/wrapper parity'
);
$report(
    $transformerClass::buildLogSnapshot($contracts['snapshot_input']) === $snapshot,
    'buildLogSnapshot class/wrapper parity'
);
$report(
    $transformerClass::pickLogFields($contracts['snapshot_input'], $contracts['pick_fields']) === $picked,
    'pickLogFields class/wrapper parity'
);
$report(
    $transformerClass::getChangedFields($contracts['changed_old'], $contracts['changed_new']) === $changed,
    'getChangedFields class/wrapper parity'
);
$report(
    $transformerClass::removeDuplicateKeys($tripleRows) === sync_remove_duplicateKeys($tripleRows),
    'removeDuplicateKeys class/wrapper parity'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
