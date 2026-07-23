<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__, 2)
    . '/app/Sync/Provenance/ProvenanceBackfillPreview.php';
require_once dirname(__DIR__, 2)
    . '/app/Sync/Contracts/ExternalUserSourceInterface.php';
require_once dirname(__DIR__, 2) . '/app/Sync/ExternalRowNormalizer.php';
require_once dirname(__DIR__, 2) . '/app/Sync/Odl/OdlSourceConfig.php';
require_once dirname(__DIR__, 2) . '/app/Sync/Odl/OdlStudentSource.php';
require_once dirname(__DIR__, 2) . '/app/Sync/Odl/OdlDataQualityAudit.php';

use OneId\App\Sync\Odl\OdlDataQualityAudit;

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    $failed += $ok ? 0 : 1;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$rows = [
    [
        'data1' => 'Student One',
        'data2' => '900101011234',
        'data4' => 'PG001',
        'data5' => '',
        'data6' => 'Faculty',
        'data7' => 'Programme',
        'ext_data_source_category' => 'Pelajar',
        'external_status_code' => '2',
    ],
    [
        'data1' => 'Student Two',
        'data2' => '900202021234',
        'data4' => 'PG002',
        'data5' => 'invalid-email',
        'data6' => 'Faculty',
        'data7' => 'Programme',
        'ext_data_source_category' => 'Pelajar',
        'external_status_code' => '4',
    ],
];
$users = [[
    'u_id' => 'PG001',
    'data2' => '900101011234',
    'avail_status' => 1,
    'account_source' => 'sync',
    'sync_protected' => 0,
]];
$memberships = [[
    'u_id' => 'PG001',
    'source_code' => 'STUDENT_UG',
    'external_user_id' => 'PG001',
]];

$result = (new OdlDataQualityAudit())->audit($rows, $users, $memberships);
$report($result['mode'] === 'read_only_audit' && $result['can_apply'] === false, 'audit is read-only');
$report($result['source_rows'] === 2, 'source rows counted');
$report($result['status_counts'] === ['2' => 1, '4' => 1], 'eligible status counted');
$report($result['ineligible_status_rows'] === 0, 'eligible fixture has no invalid status');
$report($result['blank_fields']['email'] === 1, 'blank email is informational');
$report($result['invalid_email_rows'] === 1, 'invalid nonblank email detected');
$report($result['valid_student_identities'] === 2, 'valid identities counted');
$report($result['matched_active_users'] === 1, 'existing OneID match counted');
$report($result['unmatched_external'] === 1, 'new external identity counted');
$report($result['ug_membership_overlap'] === 1, 'UG overlap counted for review');
$report($result['mutation_statements'] === 0, 'zero mutation declared');
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES);
$report(
    is_string($encoded)
        && !str_contains($encoded, 'Student One')
        && !str_contains($encoded, '900101011234')
        && !str_contains($encoded, 'PG001'),
    'result contains no raw PII'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
