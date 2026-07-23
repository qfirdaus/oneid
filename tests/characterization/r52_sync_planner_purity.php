<?php

/** Validate R5.2D6 production-grade pure SyncPlanner extraction. */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$projectRoot = dirname(__DIR__, 2);
foreach ([
    'app/Sync/Contracts/SyncPolicyInterface.php',
    'app/Sync/DTO/SyncPlan.php',
    'app/Sync/SyncDataTransformer.php',
    'app/Sync/SyncPlanner.php',
] as $file) {
    require_once $projectRoot . '/' . $file;
}

use OneId\App\Sync\Contracts\SyncPolicyInterface;
use OneId\App\Sync\DTO\SyncPlan;
use OneId\App\Sync\SyncPlanner;

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $item, string $detail = '') use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %-60s%s\n", $ok ? 'PASS' : 'FAIL', $item, $detail === '' ? '' : ' ' . $detail);
};

$plannerFile = $projectRoot . '/app/Sync/SyncPlanner.php';
$lintOutput = [];
$lintCode = 1;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($plannerFile) . ' 2>&1', $lintOutput, $lintCode);
$report($lintCode === 0, 'SyncPlanner PHP lint');
$report(class_exists(SyncPlanner::class), 'SyncPlanner class available');

$reflection = new ReflectionClass(SyncPlanner::class);
$report($reflection->isFinal(), 'SyncPlanner is final');
$report($reflection->getNamespaceName() === 'OneId\App\Sync', 'application namespace contract');

$constructor = $reflection->getConstructor();
$constructorParameters = $constructor?->getParameters() ?? [];
$constructorType = isset($constructorParameters[0])
    ? (string) $constructorParameters[0]->getType()
    : '';
$report(
    count($constructorParameters) === 1
        && $constructorParameters[0]->getName() === 'policy'
        && $constructorType === SyncPolicyInterface::class,
    'constructor depends only on SyncPolicyInterface'
);

$planMethod = $reflection->getMethod('plan');
$planParameters = $planMethod->getParameters();
$report(
    array_map(static fn(ReflectionParameter $parameter): string => $parameter->getName(), $planParameters)
        === ['externalRows', 'activeUsers', 'inactiveUserIds'],
    'plan input snapshot contract'
);
$report(
    count($planParameters) === 3
        && array_reduce(
            $planParameters,
            static fn(bool $valid, ReflectionParameter $parameter): bool =>
                $valid && (string) $parameter->getType() === 'array',
            true
        ),
    'plan inputs are arrays'
);
$report((string) $planMethod->getReturnType() === SyncPlan::class, 'plan returns SyncPlan');

$publicMethods = array_map(
    static fn(ReflectionMethod $method): string => $method->getName(),
    $reflection->getMethods(ReflectionMethod::IS_PUBLIC)
);
sort($publicMethods);
$report($publicMethods === ['__construct', 'plan'], 'minimal public API');

$source = (string) file_get_contents($plannerFile);
$forbiddenSymbols = [
    'SyncPersistenceInterface',
    'ExternalUserSourceInterface',
    'InitialPasswordFactoryInterface',
    'Database',
    'PDO',
    'getenv(',
    '$_GET',
    '$_POST',
    '$_SESSION',
    '$_COOKIE',
    'file_put_contents(',
    'fopen(',
    'curl_',
    'header(',
    'setcookie(',
];
$foundForbidden = array_values(array_filter(
    $forbiddenSymbols,
    static fn(string $symbol): bool => str_contains($source, $symbol)
));
$report(
    $foundForbidden === [],
    'no I/O, persistence, HTTP, session or environment symbol',
    implode(',', $foundForbidden)
);

$requiredPureDependencies = [
    'SyncPolicyInterface',
    'SyncPlan',
    'SyncDataTransformer',
];
$missingDependencies = array_values(array_filter(
    $requiredPureDependencies,
    static fn(string $symbol): bool => !str_contains($source, $symbol)
));
$report(
    $missingDependencies === [],
    'pure dependency set present',
    implode(',', $missingDependencies)
);

$report(
    !is_file($projectRoot . '/tests/Support/Sync/TestSyncPlanner.php'),
    'duplicate test planner removed'
);

$productionFiles = [
    'lib/sync_user_runner.php',
    'lib/Database.php',
    'lib/q_func.php',
    'page/dashboard.php',
    'admin/dashboard.php',
];
$productionReferences = [];
foreach ($productionFiles as $file) {
    if (str_contains((string) file_get_contents($projectRoot . '/' . $file), 'SyncPlanner')) {
        $productionReferences[] = $file;
    }
}
$report(
    $productionReferences === ['lib/q_func.php'],
    'production caller limited to S2 preview controller',
    implode(',', $productionReferences)
);

$runtimeHashes = [
    'lib/sync_user_runner.php' => '965fd187492e1f120b074601746b031474405480f234412e458f64189108c8bb',
    'lib/Database.php' => '71b51b7a9443bc3b83361be8b80c2ea464694af5454bbb38bfb80ad6ab3a1cce',
    'lib/q_func.php' => '9f53ef34248c9a8f93f26757d75b4fa1563882b8b4a59a785637c7de8e1d2ee9',
];
foreach ($runtimeHashes as $file => $expectedHash) {
    $actualHash = hash_file('sha256', $projectRoot . '/' . $file);
    $report($actualHash === $expectedHash, 'S4D runtime checkpoint: ' . $file, 'sha256=' . $actualHash);
}
$report(!is_file($projectRoot . '/cron/run_sync.php'), 'retired cron absent from runtime');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
