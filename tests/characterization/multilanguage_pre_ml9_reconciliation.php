<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Documentation/DocumentInventory.php';

$root = dirname(__DIR__, 2);
$audit = file_get_contents($root . '/docs/AUDIT_DAN_PELAN_PELAKSANAAN_MULTILANGUAGE_BM_ENGLISH.md');
$ml1 = file_get_contents($root . '/docs/ML1_UAT_MIGRATION_AND_PILOT_GATE.md');
$ml8c = file_get_contents($root . '/docs/ML8C_BILINGUAL_CONTENT_PREVIEW.md');
$releaseDraft = file_get_contents($root . '/docs/ML8C_RELEASE_ENGLISH_DRAFT_REVIEW.md');
$manual = file_get_contents($root . '/docs/MANUAL_SALAM_ENGLISH_DRAFT_REVIEW.md');
$ms = require $root . '/config/locales/ms.php';
$en = require $root . '/config/locales/en.php';
$inventory = (new \OneId\App\Documentation\DocumentInventory($root))->preview();

$checks = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$condition) {
        $failed++;
    }
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
};

$assert(array_keys($ms) === array_keys($en), 'BM and English catalogue ordered parity');
$assert(count($ms) >= 700, 'catalogue retains complete approved surface coverage');
$assert(
    str_contains($audit, '**Administrator Multilingual Completeness:** PASS / CLOSED')
    && str_contains($audit, '**Baki belum dilaksanakan:** ML9, staging dan Production'),
    'authoritative audit status is reconciled before ML9'
);
$assert(
    str_contains($ml1, '**Status semasa:** LOCAL MIGRATION PASS'),
    'ML1 pre-activation gate is clearly marked historical'
);
$assert(
    str_contains($ml8c, '**Status semasa:** VERSION RELEASE ACTIVATION PASS / CLOSED'),
    'ML8C Preview gate is reconciled with activation closure'
);
$assert(
    str_contains($releaseDraft, 'SUPERSEDED BY APPROVED 217/217 CHANGELOG CATALOGUE'),
    'release draft review is marked superseded'
);
$assert(
    str_contains($manual, 'DEFERRED BY OWNER / NOT IN CURRENT SCOPE / NOT PUBLISHED'),
    'English User Manual remains explicitly deferred'
);
$assert(
    $inventory['status'] === 1
    && $inventory['blocking_codes'] === []
    && $inventory['manifest']['total_items'] === 218
    && $inventory['manifest']['duplicate_identity_count'] === 0
    && $inventory['manifest']['missing_target_count'] === 0,
    'current document inventory is deterministic and unblocked'
);
$assert(
    str_contains($audit, '`ONEID-ML-EXTSYNC-LOCAL-20260726-01`')
    && str_contains($audit, '`ONEID-ML-STEPUP-LOCAL-20260726-01`')
    && str_contains($audit, '`ONEID-ML-ADMIN-COMPLETE-LOCAL-20260726-01`'),
    'all post-ML8 multilingual closure evidence is referenced'
);
$assert(
    str_contains($audit, '| ML9 | FUTURE WORK |'),
    'ML9 remains a separate authorization boundary'
);

echo "RESULT checks={$checks} failed={$failed}" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
