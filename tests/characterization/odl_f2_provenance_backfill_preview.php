<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__, 2)
    . '/app/Sync/Provenance/ProvenanceBackfillPreview.php';

use OneId\App\Sync\Provenance\ProvenanceBackfillPreview;

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$row = static fn(string $matric, string $ic): array => [
    'data2' => $ic,
    'data4' => $matric,
    'ext_data_source_category' => 'Pelajar',
];
$user = static fn(
    string $matric,
    string $ic,
    int $active = 1,
    string $source = 'external',
    int $protected = 0
): array => [
    'u_id' => $matric,
    'data2' => $ic,
    'avail_status' => $active,
    'account_source' => $source,
    'sync_protected' => $protected,
];

$previewer = new ProvenanceBackfillPreview();
$result = $previewer->preview(
    'STUDENT_ASIS_ACTIVE',
    [
        $row('A-001', '900101-01-1234'),
        $row('A002', '900202021234'),
        $row('A003', '900303031234'),
        $row('', '900404041234'),
        $row('PROTECTED', '900505051234'),
        $row('DUP', '900606061234'),
        $row('DUP', '900606061234'),
    ],
    [
        $user('A001', '900101011234'),
        $user('A002', '900202021234', 0),
        $user('PROTECTED', '900505051234', 1, 'manual', 1),
        $user('DUP', '900606061234'),
    ],
    []
);

$report($result['mode'] === 'preview' && $result['can_apply'] === false, 'preview can never apply');
$report($result['source_rows'] === 7, 'all source rows counted');
$report($result['valid_student_identities'] === 5, 'valid identities are canonical and deduplicated');
$report($result['invalid_identity_rows'] === 1, 'blank identity is invalid');
$report($result['duplicate_pair_groups'] === 1 && $result['duplicate_pair_rows'] === 2, 'duplicate pairs are counted');
$report($result['exact_duplicate_pair_groups'] === 1, 'exact duplicate pairs are safely deduplicated');
$report($result['profile_variant_duplicate_groups'] === 0, 'exact duplicates have no profile ambiguity');
$report($result['matched_active_users'] === 2, 'active matches include canonical identities');
$report($result['matched_inactive_users'] === 1, 'inactive match is reported without mutation');
$report($result['protected_collisions'] === 1, 'protected manual collision is blocked');
$report($result['unmatched_external'] === 1, 'unmatched external identity is reported');
$report($result['candidate_memberships'] === 3, 'only safe exact matches become candidates');
$report($result['status'] === 'blocked' && $result['blocking_findings'] === 2, 'blocking findings fail closed');
$report(is_string($result['plan_digest']) && strlen($result['plan_digest']) === 64, 'aggregate plan digest is stable');
$report(!str_contains(json_encode($result), '900101011234'), 'result contains no raw identity');

$existing = $previewer->preview(
    'STUDENT_ASIS_ACTIVE',
    [$row('A001', '900101011234')],
    [$user('A001', '900101011234')],
    [[
        'u_id' => 'A001',
        'source_code' => 'STUDENT_ASIS_ACTIVE',
        'external_user_id' => 'A001',
    ]]
);
$report($existing['existing_memberships'] === 1, 'existing exact membership is idempotent');
$report($existing['candidate_memberships'] === 0, 'existing membership is not planned again');

$conflict = $previewer->preview(
    'STUDENT_ASIS_ACTIVE',
    [$row('A001', '900101011234')],
    [$user('A001', '900101011234')],
    [[
        'u_id' => 'OTHER',
        'source_code' => 'STUDENT_ASIS_ACTIVE',
        'external_user_id' => 'A001',
    ]]
);
$report($conflict['membership_conflicts'] === 1 && $conflict['status'] === 'blocked', 'membership conflict fails closed');

$profileVariant = $previewer->preview(
    'STUDENT_ASIS_ACTIVE',
    [
        $row('VARIANT', '901010101234'),
        array_merge($row('VARIANT', '901010101234'), ['data5' => 'different@example.test']),
    ],
    [$user('VARIANT', '901010101234')],
    []
);
$report(
    $profileVariant['profile_variant_duplicate_groups'] === 1
        && $profileVariant['profile_variant_field_groups']['data5'] === 1
        && $profileVariant['candidate_memberships'] === 1
        && $profileVariant['status'] === 'review',
    'profile variant is reviewed without changing exact membership identity'
);

$invalidCodeRejected = false;
try {
    $previewer->preview('student source', [], [], []);
} catch (InvalidArgumentException $exception) {
    $invalidCodeRejected = $exception->getMessage() === 'PROVENANCE_SOURCE_CODE_INVALID';
}
$report($invalidCodeRejected, 'source code is allowlisted');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
