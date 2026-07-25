<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/config.php';
require_once dirname(__DIR__, 2) . '/app/Metadata/MetadataContentInventory.php';

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
$result = (new \OneId\App\Metadata\MetadataContentInventory($pdo))->preview();
$manifest = $result['manifest'];
$items = $manifest['items'];
$byIdentity = [];
foreach ($items as $item) {
    $byIdentity[$item['entity_type'] . ':' . $item['entity_id']] = $item;
}

$report(
    $manifest['source'] === ['applications' => 77, 'categories' => 7]
    && count($items) === 84,
    'all applications and categories are inventoried'
);
$report(
    $manifest['approved_items'] === 84
    && $manifest['pending_owner_review'] === 0
    && (float) $manifest['completion_percent'] === 100.0,
    'all approved review decisions are reconciled'
);
$report(
    array_sum($manifest['classification_counts']) === 84
    && isset(
        $manifest['classification_counts']['EXISTING_TRANSLATION_APPROVED'],
        $manifest['classification_counts']['PROPER_NOUN_INVARIANT'],
        $manifest['classification_counts']['REVIEW_REQUIRED'],
        $manifest['classification_counts']['TRANSLATION_REQUIRED']
    ),
    'every item has one approved classification'
);
$report(
    $byIdentity['application:0Y4IIXKILT']['review_decision'] === 'ACCEPT_EXISTING'
    && $byIdentity['category:2']['review_decision'] === 'ACCEPT_EXISTING',
    'controlled ML7 translations remain present after local metadata edits'
);
$report(
    $byIdentity['application:2WJ4USYRS9']['classification'] === 'REVIEW_REQUIRED'
    && $byIdentity['application:2WJ4USYRS9']['review_decision'] === 'EXCLUDE_QUARANTINE'
    && $byIdentity['category:6']['draft_en_name'] === 'Student'
    && $byIdentity['category:6']['review_decision'] === 'ACCEPT_EXISTING',
    'test data remains quarantined while the reviewed category translation is retained'
);
$report(
    $manifest['duplicate_identity_count'] === 0
    && $manifest['unresolved_identity_count'] === 0
    && $manifest['stale_source_count'] === 0,
    'identity and source snapshot integrity checks pass'
);
$report(
    $result['can_apply'] === false
    && $result['automatic_approval'] === false
    && $result['bulk_apply_ready'] === true
    && $result['blocking_codes'] === []
    && $result['mutation_statements'] === 0,
    'completed owner review is reported without exposing Apply'
);
$report(
    preg_match('/\A[a-f0-9]{64}\z/', $result['manifest_digest']) === 1,
    'review manifest has a deterministic approval digest'
);

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
