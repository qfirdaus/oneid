<?php

/** R5.2D7 dormant production adapter contract fixture. */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$projectRoot = dirname(__DIR__, 2);
$r52d7ExternalRows = [];
$r52d7ExternalFailure = false;

function EXTERNAL_DATA_SOURCE_GET_ALL_USER(): array
{
    global $r52d7ExternalRows, $r52d7ExternalFailure;
    if ($r52d7ExternalFailure) {
        throw new RuntimeException('D7 external fixture failure');
    }
    return $r52d7ExternalRows;
}

require_once $projectRoot . '/lib/auth_security.php';
foreach ([
    'app/Sync/Contracts/ExternalUserSourceInterface.php',
    'app/Sync/Contracts/InitialPasswordFactoryInterface.php',
    'app/Sync/Contracts/SyncPolicyInterface.php',
    'app/Sync/Contracts/SyncPersistenceInterface.php',
    'app/Sync/Adapters/ExternalApiUserSource.php',
    'app/Sync/Adapters/SecureInitialPasswordFactory.php',
    'app/Sync/Adapters/LegacySyncPolicy.php',
    'app/Sync/Adapters/DatabaseSyncPersistenceAdapter.php',
] as $file) {
    require_once $projectRoot . '/' . $file;
}

use OneId\App\Sync\Adapters\DatabaseSyncPersistenceAdapter;
use OneId\App\Sync\Adapters\ExternalApiUserSource;
use OneId\App\Sync\Adapters\LegacySyncPolicy;
use OneId\App\Sync\Adapters\SecureInitialPasswordFactory;
use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\Contracts\InitialPasswordFactoryInterface;
use OneId\App\Sync\Contracts\SyncPersistenceInterface;
use OneId\App\Sync\Contracts\SyncPolicyInterface;

final class R52D7LegacyOperationSpy
{
    /** @var list<array{0:string,1:array<int,mixed>}> */
    public array $calls = [];

    public function __call(string $name, array $arguments): mixed
    {
        $this->calls[] = [$name, $arguments];

        return match ($name) {
            'action_add_new_ext_header' => 77,
            'sync_get_all_sso_user' => [['u_id' => 'A01']],
            'sync_get_inactive_user_ids' => ['I01'],
            'action_add_external_temp_body' => 101,
            'action_get_ext_header' => ['ext_head_id' => 77, 'ext_head_status' => 2],
            default => null,
        };
    }
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $item, string $detail = '') use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %-62s%s\n", $ok ? 'PASS' : 'FAIL', $item, $detail === '' ? '' : ' ' . $detail);
};

$adapterContracts = [
    ExternalApiUserSource::class => ExternalUserSourceInterface::class,
    SecureInitialPasswordFactory::class => InitialPasswordFactoryInterface::class,
    LegacySyncPolicy::class => SyncPolicyInterface::class,
    DatabaseSyncPersistenceAdapter::class => SyncPersistenceInterface::class,
];
foreach ($adapterContracts as $adapter => $contract) {
    $reflection = new ReflectionClass($adapter);
    $report($reflection->isFinal(), 'adapter is final: ' . $adapter);
    $report($reflection->implementsInterface($contract), 'adapter contract: ' . $adapter);
}

$r52d7ExternalRows = [['data4' => 'U01']];
$source = new ExternalApiUserSource();
$report($source->fetchAll() === $r52d7ExternalRows, 'external source return parity');
$r52d7ExternalFailure = true;
$externalException = false;
try {
    $source->fetchAll();
} catch (RuntimeException $exception) {
    $externalException = $exception->getMessage() === 'D7 external fixture failure';
}
$report($externalException, 'external source exception propagation');
$r52d7ExternalFailure = false;

$passwordFactory = new SecureInitialPasswordFactory();
$passwordHashA = $passwordFactory->createHash();
$passwordHashB = $passwordFactory->createHash();
$passwordInfo = password_get_info($passwordHashA);
$report($passwordHashA !== '' && ($passwordInfo['algo'] ?? null) !== null, 'secure password hash produced');
$report(!hash_equals($passwordHashA, $passwordHashB), 'initial password hashes are non-deterministic');

$policy = new LegacySyncPolicy();
$report($policy->excludedUserIds() === ['10'], 'legacy exclusion parity');
foreach ([
    'Akademik' => 2,
    'Pentadbiran' => 3,
    'Pelajar' => 10,
    'PelajarPelajar' => 10,
    'PentadbiranPelajar' => 11,
    'AkademikPelajar' => 12,
    'TidakDikenali' => 0,
] as $category => $expected) {
    $report($policy->categoryIdFor($category) === $expected, 'category parity: ' . $category);
}

$row = [];
for ($index = 1; $index <= 12; $index++) {
    $row['data' . $index] = 'D' . $index;
}
$dataValues = array_values($row);
$changes = [['u_id' => 'U01', 'action' => 'UPDATE']];
$spy = new R52D7LegacyOperationSpy();
$persistence = new DatabaseSyncPersistenceAdapter($spy);

$persistence->begin();
$headerId = $persistence->createHeader(0);
$active = $persistence->activeUsers();
$inactive = $persistence->inactiveUserIds();
$persistence->deactivateUser('U02');
$persistence->updateUser('U01', $row, 'change-hash');
$persistence->updateHeaderStatus(77, 1, 'ext_head_initial_sourcedata', 2);
$bodyId = $persistence->stageExternalUser(77, $row);
$persistence->insertExternalUser($row, 10, 'password-hash', 'insert-hash');
$persistence->markStagedUser(77, 101, 2);
$persistence->appendChanges($changes);
$persistence->updateSummary(77, 1, 2, 3, 4, 'admin-user');
$header = $persistence->header(77);
$persistence->commit();
$persistence->rollback();

$expectedCalls = [
    ['beginTransaction', []],
    ['action_add_new_ext_header', [0]],
    ['sync_get_all_sso_user', []],
    ['sync_get_inactive_user_ids', []],
    ['admin_update_user_status', ['U02', 0]],
    ['admin_update_specific_user_info_all_data', array_merge(['U01'], $dataValues, ['change-hash'])],
    ['admin_update_ext_header_status', [77, 1, 'ext_head_initial_sourcedata', 2]],
    ['action_add_external_temp_body', array_merge([77], $dataValues)],
    ['action_add_new_user_from_external_source', array_merge(['D4', 10, 'password-hash'], $dataValues, ['insert-hash'])],
    ['admin_update_ext_body_status', [77, 101, 2]],
    ['sync_log_change_batch', [$changes]],
    ['sync_update_header_summary', [77, 1, 2, 3, 4, 'admin-user']],
    ['action_get_ext_header', [77]],
    ['commit', []],
    ['rollback', []],
];
$report($spy->calls === $expectedCalls, 'persistence exact method/argument parity');
$report($headerId === 77, 'createHeader return parity');
$report($active === [['u_id' => 'A01']], 'activeUsers return parity');
$report($inactive === ['I01'], 'inactiveUserIds return parity');
$report($bodyId === 101, 'stageExternalUser return parity');
$report($header === ['ext_head_id' => 77, 'ext_head_status' => 2], 'header return parity');

$adapterFiles = [
    'app/Sync/Adapters/ExternalApiUserSource.php',
    'app/Sync/Adapters/SecureInitialPasswordFactory.php',
    'app/Sync/Adapters/LegacySyncPolicy.php',
    'app/Sync/Adapters/DatabaseSyncPersistenceAdapter.php',
];
$testNamespaceReferences = [];
foreach ($adapterFiles as $file) {
    if (str_contains((string) file_get_contents($projectRoot . '/' . $file), 'OneId\\Tests')) {
        $testNamespaceReferences[] = $file;
    }
}
$report($testNamespaceReferences === [], 'production adapters do not depend on tests', implode(',', $testNamespaceReferences));

$productionFiles = [
    'lib/sync_user_runner.php',
    'lib/Database.php',
    'lib/q_func.php',
    'page/dashboard.php',
    'admin/dashboard.php',
];
$adapterNames = array_map(
    static fn(string $class): string => substr($class, (int) strrpos($class, '\\') + 1),
    array_keys($adapterContracts)
);
$productionReferences = [];
foreach ($productionFiles as $file) {
    $contents = (string) file_get_contents($projectRoot . '/' . $file);
    foreach ($adapterNames as $adapterName) {
        if (str_contains($contents, $adapterName)) {
            $productionReferences[] = $file . ':' . $adapterName;
        }
    }
}
$report(
    $productionReferences === [
        'lib/q_func.php:ExternalApiUserSource',
        'lib/q_func.php:LegacySyncPolicy',
        'lib/q_func.php:DatabaseSyncPersistenceAdapter',
    ],
    'production adapters limited to S2 preview controller',
    implode(',', $productionReferences)
);

$runtimeHashes = [
    'lib/sync_user_runner.php' => '965fd187492e1f120b074601746b031474405480f234412e458f64189108c8bb',
    'lib/Database.php' => 'ef82c7ac8d3898e8ead942bb0991007b3fe6b475bd3b697a6b00f0643e0cfb4e',
    'lib/q_func.php' => '6715f149be5a22aca57ca31eb74a2c445fc104b30cb7422fb0f8d693efc60e7a',
];
foreach ($runtimeHashes as $file => $expectedHash) {
    $actualHash = hash_file('sha256', $projectRoot . '/' . $file);
    $report($actualHash === $expectedHash, 'runtime unchanged: ' . $file, 'sha256=' . $actualHash);
}
$report(!is_file($projectRoot . '/cron/run_sync.php'), 'retired cron absent from runtime');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
