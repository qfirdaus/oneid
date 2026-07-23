<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);

$root = dirname(__DIR__, 2);
foreach ([
    'app/Sync/DTO/SyncSafetyDecision.php',
    'app/Sync/DTO/SourceSnapshot.php',
    'app/Sync/DTO/SourceAwarePlan.php',
    'app/Sync/SourceAware/SourceAwareSafetyPolicy.php',
    'app/Sync/SourceAware/SourceAwareStudentPlanner.php',
] as $file) require_once $root . '/' . $file;

use OneId\App\Sync\DTO\SourceSnapshot;
use OneId\App\Sync\SourceAware\SourceAwareSafetyPolicy;
use OneId\App\Sync\SourceAware\SourceAwareStudentPlanner;

function f5_row(string $source, string $matric, string $ic, string $name): array {
    return [
        'source_code' => $source, 'data1' => $name, 'data2' => $ic,
        'data4' => $matric, 'data5' => '', 'data6' => 'Faculty',
        'data7' => 'Programme', 'ext_data_source_category' => 'Pelajar',
    ];
}
function f5_snapshot(string $code, array $rows, ?int $baseline = null, string $status = 'success'): SourceSnapshot {
    return new SourceSnapshot($code, 'student', $status, $rows, $baseline ?? count($rows));
}

$checks = 0; $failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++; $failed += $ok ? 0 : 1;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};
$planner = new SourceAwareStudentPlanner(new SourceAwareSafetyPolicy());
$users = [
    ['u_id' => 'U1', 'data2' => 'IC1', 'avail_status' => 1],
    ['u_id' => 'U2', 'data2' => 'IC2', 'avail_status' => 1],
    ['u_id' => 'U3', 'data2' => 'IC3', 'avail_status' => 1],
];
$memberships = [
    ['u_id' => 'U1', 'source_code' => 'STUDENT_UG', 'external_user_id' => 'U1', 'source_active' => 1],
    ['u_id' => 'U1', 'source_code' => 'STUDENT_ODL_PG', 'external_user_id' => 'U1', 'source_active' => 1],
    ['u_id' => 'U2', 'source_code' => 'STUDENT_UG', 'external_user_id' => 'U2', 'source_active' => 1],
];
$plan = $planner->plan([
    f5_snapshot('STUDENT_UG', [f5_row('STUDENT_UG', 'U3', 'IC3', 'User Three')]),
    f5_snapshot('STUDENT_ODL_PG', [f5_row('STUDENT_ODL_PG', 'U1', 'IC1', 'User One')]),
], $users, $memberships);
$accountKinds = array_column($plan->accountActions, 'action');
$memberKinds = array_column($plan->membershipActions, 'action');
$report($plan->allowed, 'healthy multi-source snapshot allowed');
$report(in_array('MARK_MEMBERSHIP_INACTIVE', $memberKinds, true), 'missing source membership planned inactive');
$report(in_array('KEEP_ACCOUNT_ACTIVE_OTHER_SOURCE', $accountKinds, true), 'other active student source keeps account active');
$report(in_array('CANDIDATE_DEACTIVATE', $accountKinds, true), 'deactivation is candidate only when all student memberships inactive');
$report(!in_array('DEACTIVATE', $accountKinds, true), 'planner emits no direct legacy deactivation');
$safe = json_encode($plan->safeProjection(), JSON_UNESCAPED_SLASHES);
$report(is_string($safe) && !str_contains($safe, 'IC1') && !str_contains($safe, 'U1'), 'safe projection contains no raw identity');
$report($plan->safeProjection()['can_apply'] === false && $plan->safeProjection()['mutation_statements'] === 0, 'preview is zero mutation');

$odlOutage = $planner->plan([
    f5_snapshot('STUDENT_UG', [f5_row('STUDENT_UG', 'U3', 'IC3', 'User Three')]),
    f5_snapshot('STUDENT_ODL_PG', [], 53, 'connection_failed'),
], $users, $memberships);
$report(!$odlOutage->allowed && in_array('ODL_CONNECTION_FAILED', $odlOutage->blockingCodes, true), 'ODL outage blocks');
$report($odlOutage->membershipActions === [] && $odlOutage->accountActions === [], 'ODL outage produces zero actions');

$ugOutage = $planner->plan([
    f5_snapshot('STUDENT_UG', [], 5423, 'query_failed'),
    f5_snapshot('STUDENT_ODL_PG', [f5_row('STUDENT_ODL_PG', 'U1', 'IC1', 'User One')]),
], $users, $memberships);
$report(!$ugOutage->allowed && in_array('STUDENT_UG_QUERY_FAILED', $ugOutage->blockingCodes, true), 'UG outage blocks');
$report($ugOutage->membershipActions === [] && $ugOutage->accountActions === [], 'UG outage prevents cross-source deactivation');

$shrink = $planner->plan([
    f5_snapshot('STUDENT_ODL_PG', [f5_row('STUDENT_ODL_PG', 'U1', 'IC1', 'User One')], 53),
], $users, $memberships);
$report(!$shrink->allowed && in_array('ODL_SOURCE_SHRINK_EXCEEDED', $shrink->blockingCodes, true), 'ODL shrink over twenty percent blocks');

$mismatch = $planner->plan([
    f5_snapshot('STUDENT_ODL_PG', [f5_row('STUDENT_UG', 'U1', 'IC1', 'User One')]),
], $users, $memberships);
$report(!$mismatch->allowed && $mismatch->blockingCodes === ['SOURCE_ENVELOPE_CODE_MISMATCH'], 'row envelope source mismatch blocks');

$conflict = $planner->plan([
    f5_snapshot('STUDENT_UG', [f5_row('STUDENT_UG', 'U1', 'IC1', 'UG Name')]),
    f5_snapshot('STUDENT_ODL_PG', [f5_row('STUDENT_ODL_PG', 'U1', 'IC1', 'ODL Name')]),
], $users, $memberships);
$report(!$conflict->allowed && $conflict->blockingCodes === ['STUDENT_PROFILE_CONFLICT'], 'cross-source profile conflict blocks manual review');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
