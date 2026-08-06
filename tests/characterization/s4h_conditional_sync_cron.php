<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/sync_runtime.php';

use OneId\App\Sync\Adapters\InMemorySyncApprovalStore;
use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\Odl\OdlStudentSource;
use OneId\App\Sync\Odl\StaffSource;
use OneId\App\Sync\Odl\UgStudentSource;
use OneId\App\Sync\SyncCronConfig;

$checks = [];
$limits = [
    StaffSource::SOURCE_CODE => ['New'=>'50','Update'=>'250','Reactivate'=>'20','Total'=>'300'],
    UgStudentSource::SOURCE_CODE => ['New'=>'50','Update'=>'250','Reactivate'=>'20','Total'=>'300'],
    OdlStudentSource::SOURCE_CODE => ['New'=>'20','Update'=>'100','Reactivate'=>'10','Total'=>'120'],
];
$config = SyncCronConfig::fromValues(
    'true', 'true', implode(',', SyncCronConfig::SOURCE_CODES), '0', $limits
);
$checks['strict_source_order'] = $config->sources === SyncCronConfig::SOURCE_CODES;
$checks['zero_counts_allowed'] = $config->blockingCode(
    UgStudentSource::SOURCE_CODE,
    ['New'=>0,'Update'=>0,'Deactivate'=>0,'Reactivate'=>0]
) === null;
$checks['threshold_boundary_allowed'] = $config->blockingCode(
    UgStudentSource::SOURCE_CODE,
    ['New'=>50,'Update'=>250,'Deactivate'=>0,'Reactivate'=>0]
) === null;
$checks['deactivation_blocked'] = $config->blockingCode(
    UgStudentSource::SOURCE_CODE,
    ['New'=>0,'Update'=>0,'Deactivate'=>1,'Reactivate'=>0]
) === 'SYNC_CRON_DEACTIVATION_NOT_ALLOWED';
$checks['over_limit_blocked'] = $config->blockingCode(
    OdlStudentSource::SOURCE_CODE,
    ['New'=>21,'Update'=>0,'Deactivate'=>0,'Reactivate'=>0]
) === 'SYNC_CRON_NEW_LIMIT_EXCEEDED';

$unrestricted = SyncCronConfig::fromValues(
    'true', 'false', implode(',', SyncCronConfig::SOURCE_CODES), '0', $limits,
    'ONEID-CRON', 'true'
);
$checks['all_safe_changes_allows_deactivation_and_volume'] = $unrestricted->blockingCode(
    OdlStudentSource::SOURCE_CODE,
    ['New'=>99999,'Update'=>99999,'Deactivate'=>99999,'Reactivate'=>99999]
) === null;

$invalid = static function (callable $call, string $code): bool {
    try { $call(); } catch (RuntimeException $exception) {
        return $exception->getMessage() === $code;
    }
    return false;
};
$checks['nonzero_deactivate_config_rejected'] = $invalid(
    fn() => SyncCronConfig::fromValues('true','true','STUDENT_UG','1',$limits),
    'SYNC_CRON_DEACTIVATE_LIMIT_INVALID'
);
$checks['duplicate_source_rejected'] = $invalid(
    fn() => SyncCronConfig::fromValues('true','true','STUDENT_UG,STUDENT_UG','0',$limits),
    'SYNC_CRON_SOURCES_INVALID'
);
$checks['oversized_service_identity_rejected'] = $invalid(
    fn() => SyncCronConfig::fromValues(
        'true','true','STUDENT_UG','0',$limits,str_repeat('A', 21)
    ),
    'SYNC_CRON_IDENTITY_INVALID'
);

$store = new InMemorySyncApprovalStore();
$approval = new SyncApproval(
    str_repeat('a',64), str_repeat('b',16), 'cron', str_repeat('c',64),
    ['New'=>1,'Update'=>0,'Deactivate'=>0,'Reactivate'=>0], 10, 10, 1, 2
);
$store->save($approval);
$checks['approval_one_use'] = $store->consume($approval->approvalId) === $approval
    && $store->consume($approval->approvalId) === null;

foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
