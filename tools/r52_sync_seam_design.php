<?php

/**
 * Validate R5.2D1 sync contracts without production wiring.
 *
 * Usage:
 *   php tools/r52_sync_seam_design.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$projectRoot = dirname(__DIR__);
$contractFiles = [
    'app/Sync/Contracts/ExternalUserSourceInterface.php',
    'app/Sync/Contracts/InitialPasswordFactoryInterface.php',
    'app/Sync/Contracts/SyncPolicyInterface.php',
    'app/Sync/Contracts/SyncPersistenceInterface.php',
    'app/Sync/DTO/SyncRunSummary.php',
];
foreach ($contractFiles as $file) {
    require_once $projectRoot . '/' . $file;
}
$contracts = require $projectRoot . '/tests/characterization/r52_sync_seam_contracts.php';

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $item, string $detail = '') use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %-52s%s\n", $ok ? 'PASS' : 'FAIL', $item, $detail === '' ? '' : ' ' . $detail);
};

foreach ($contractFiles as $file) {
    $path = $projectRoot . '/' . $file;
    $output = [];
    $exitCode = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
    $report($exitCode === 0, 'PHP lint: ' . $file);
}

foreach ($contracts['interfaces'] as $interface => $expectedMethods) {
    $report(interface_exists($interface), 'interface available: ' . $interface);
    $actualMethods = array_map(
        static fn(ReflectionMethod $method): string => $method->getName(),
        (new ReflectionClass($interface))->getMethods()
    );
    $report($actualMethods === $expectedMethods, 'method contract: ' . $interface);
}

$summaryClass = \OneId\App\Sync\DTO\SyncRunSummary::class;
$summary = new $summaryClass(77, 1, 2, 3, 4, ['ext_head_status' => 2]);
$report(class_exists($summaryClass), 'summary DTO available');
$report(
    $summary->toLegacyArray() === [
        'ext_head_status' => 2,
        'ext_head_id' => 77,
        'Deactivate' => 3,
        'Update' => 2,
        'New' => 1,
        'Reactivate' => 4,
    ],
    'summary legacy projection contract'
);

$summaryReflection = new ReflectionClass($summaryClass);
$readonly = true;
foreach ($summaryReflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
    $readonly = $readonly && $property->isReadOnly();
}
$report($readonly, 'summary public properties are readonly');

$symbols = array_merge(array_keys($contracts['interfaces']), [$summaryClass]);
$shortSymbols = array_map(
    static fn(string $symbol): string => substr($symbol, (int) strrpos($symbol, '\\') + 1),
    $symbols
);
$productionReferences = [];
foreach ($contracts['production_files'] as $file) {
    $contents = (string) file_get_contents($projectRoot . '/' . $file);
    foreach ($shortSymbols as $symbol) {
        if (str_contains($contents, $symbol)) {
            $productionReferences[] = $file . ':' . $symbol;
        }
    }
}
$report(
    $productionReferences === [],
    'no production wiring',
    $productionReferences === [] ? '' : implode(',', $productionReferences)
);

$syncRunnerHash = hash_file('sha256', $projectRoot . '/lib/sync_user_runner.php');
$report(
    $syncRunnerHash === $contracts['legacy_sync_runner_sha256'],
    'legacy sync runner unchanged',
    'sha256=' . $syncRunnerHash
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
