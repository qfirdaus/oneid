<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/config.php';
require_once dirname(__DIR__, 2) . '/app/Metadata/MetadataContentInventory.php';
require_once dirname(__DIR__, 2) . '/app/Metadata/MetadataBulkContentPlanner.php';

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$result = (new \OneId\App\Metadata\MetadataBulkContentPlanner(
    new \OneId\App\Metadata\MetadataContentInventory($pdo)
))->preview();

$report($result['can_apply'] === false && $result['live_apply_authorized'] === false, 'Preview exposes no live Apply capability');
$report($result['mutation_statements'] === 0, 'Preview executes zero mutation statements');
$report(
    $result['proposed_mutations']['review_decision_inserts'] === 84
    && $result['proposed_mutations']['translation_history_inserts']
        === $result['proposed_mutations']['translation_inserts']
    && $result['proposed_mutations']['original_metadata_updates'] === 0
    && $result['proposed_mutations']['quarantine_translation_inserts'] === 0,
    'all decisions are planned without original or quarantine mutation'
);
$report(
    array_sum($result['plan']['decision_counts']) === 84
    && ($result['plan']['decision_counts']['EXCLUDE_QUARANTINE'] ?? 0) >= 1,
    'every source identity receives one explicit decision'
);
$report(
    preg_match('/\A[a-f0-9]{64}\z/', $result['plan_hash']) === 1,
    'deterministic exact plan hash is generated'
);
$report(
    $result['approved_manifest_match'] === false
    && in_array('ML7A_APPROVED_MANIFEST_DIGEST_MISMATCH', $result['blocking_codes'], true),
    'committed Apply invalidates replay of the pre-Apply manifest'
);
$report(
    $result['status'] === 0
    && $result['code'] === 'ML7A_BULK_PREVIEW_BLOCKED',
    'pre-Apply plan cannot be replayed after commit'
);

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
