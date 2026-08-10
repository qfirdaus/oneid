<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/config.php';
require_once dirname(__DIR__, 2) . '/app/Metadata/BilingualMetadataRepository.php';

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};

$ms = require dirname(__DIR__, 2) . '/config/locales/ms.php';
$en = require dirname(__DIR__, 2) . '/config/locales/en.php';
$report(array_keys($ms) === array_keys($en), 'BM and English catalogue ordered parity');
$report(count(array_filter(
    array_keys($ms),
    static fn(string $key): bool => str_starts_with($key, 'admin.metadata.')
)) >= 16, 'ML7 Administrator catalogue coverage');

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$repository = new \OneId\App\Metadata\BilingualMetadataRepository($pdo);
$preview = $repository->preview();
$report(
    $preview['schema_available'] === true
    && $preview['can_apply_migration'] === false
    && $preview['translations']['applications'] === array_sum(array_column($preview['translations']['by_locale'], 'applications'))
    && $preview['translations']['categories'] === array_sum(array_column($preview['translations']['by_locale'], 'categories'))
    && $preview['translations']['by_locale']['en']['applications'] <= $preview['source']['applications']
    && $preview['translations']['by_locale']['ms']['applications'] <= $preview['source']['applications']
    && $preview['translations']['by_locale']['en']['categories'] <= $preview['source']['categories']
    && $preview['translations']['by_locale']['ms']['categories'] <= $preview['source']['categories']
    && $preview['mutation_statements'] === 0,
    'active ML7 coverage is locale-scoped and Preview has zero mutation'
);
$groups = [[
    'sp_group_id' => 1,
    'sp_group_name' => 'Original Category',
    'data' => [[
        'sp_id' => 'APP1',
        'sp_name' => 'Original App',
        'sp_description' => 'Original Description',
        'sp_domain' => 'https://example.invalid',
    ]],
]];
$fallback = $repository->localizeGroups($groups, 'en');
$report(
    $fallback[0]['sp_group_name'] === 'Original Category'
    && $fallback[0]['data'][0]['sp_name'] === 'Original App'
    && $fallback[0]['metadata_fallback'] === 1
    && $fallback[0]['data'][0]['metadata_fallback'] === 1,
    'missing-translation read path falls back to original metadata'
);
$report(
    $fallback[0]['data'][0]['sp_domain'] === 'https://example.invalid',
    'URL and technical metadata remain invariant'
);

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
