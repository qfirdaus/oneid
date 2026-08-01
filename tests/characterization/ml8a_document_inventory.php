<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Documentation/DocumentInventory.php';

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};
$result = (new \OneId\App\Documentation\DocumentInventory(dirname(__DIR__, 2)))->preview();
$manifest = $result['manifest'];
$byIdentity = [];
foreach ($manifest['items'] as $item) {
    $byIdentity[$item['identity']] = $item;
}

$report(
    $result['status'] === 1
    && $result['code'] === 'ML8A_DOCUMENT_INVENTORY_READY'
    && $result['blocking_codes'] === [],
    'document inventory is complete and unblocked'
);
$report(
    $manifest['total_items'] === 179
    && $manifest['duplicate_identity_count'] === 0
    && $manifest['missing_target_count'] === 0,
    'all document identities and targets are deterministic'
);
$report(
    $manifest['surface_counts'] === [
        'faq' => 2,
        'internal_document' => 160,
        'policy_document' => 2,
        'public_document' => 1,
        'release_document' => 13,
        'release_ui' => 1,
    ],
    'manual FAQ release policy and internal surfaces are classified'
);
$report(
    $manifest['translation_required_count'] === 19
    && $manifest['classification_counts']['INTERNAL_TECHNICAL_INVARIANT'] === 160,
    'user-facing backlog is separated from canonical technical documents'
);
$report(
    $byIdentity['faq:public_login_faq']['entry_count'] === 8
    && $byIdentity['faq:authenticated_user_faq']['entry_count'] === 8
    && $byIdentity['faq:public_login_faq']['detected_locale'] === 'ms',
    'both active FAQ surfaces and entries are inventoried'
);
$report(
    $byIdentity['public_document:public/public_docs/MANUAL_SALAM.pdf']['classification']
        === 'BM_ONLY_EXPLICIT_FALLBACK_REQUIRED'
    && $byIdentity['public_document:public/public_docs/MANUAL_SALAM.pdf']['target_exists'] === true,
    'BM manual requires explicit fallback until approved English content exists'
);
$report(
    $byIdentity['release_ui:admin_dashboard']['entry_count'] === 42
    && $byIdentity['release_ui:admin_dashboard']['classification']
        === 'MIXED_TRANSLATION_REQUIRED',
    'active Administrator release history is included'
);
$report(
    $result['can_apply'] === false
    && $result['automatic_translation'] === false
    && $result['mutation_statements'] === 0
    && preg_match('/\A[a-f0-9]{64}\z/', $result['manifest_digest']) === 1,
    'ML8A is deterministic zero-mutation inventory only'
);

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
