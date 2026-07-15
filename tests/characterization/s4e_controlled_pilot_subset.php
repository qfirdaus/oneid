<?php

require_once dirname(__DIR__, 2) . '/app/Sync/DTO/SyncPlan.php';
require_once dirname(__DIR__, 2) . '/app/Sync/SyncPilotConfig.php';
require_once dirname(__DIR__, 2) . '/app/Sync/SyncPlanSubsetSelector.php';

use OneId\App\Sync\DTO\SyncPlan;
use OneId\App\Sync\SyncPilotConfig;
use OneId\App\Sync\SyncPlanSubsetSelector;

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};
$reason = static function (callable $call): string {
    try { $call(); } catch (Throwable $e) { return $e->getMessage(); }
    return '';
};

$report($reason(static fn() => SyncPilotConfig::fromValues('yes', '2', '1', '0', '0')) === 'SYNC_PILOT_FLAG_INVALID', 'non-canonical pilot flag rejected');
$report($reason(static fn() => SyncPilotConfig::fromValues('true', '3', '1', '0', '0')) === 'SYNC_PILOT_SCOPE_INVALID', 'expanded pilot scope rejected');
$report($reason(static fn() => SyncPilotConfig::fromValues('true', '2', '1', '1', '0')) === 'SYNC_PILOT_DESTRUCTIVE_ACTION_FORBIDDEN', 'deactivation pilot rejected');
$report($reason(static fn() => new SyncPlanSubsetSelector(SyncPilotConfig::fromValues('false', '2', '1', '0', '0'))) === 'SYNC_PILOT_DISABLED', 'disabled pilot cannot construct selector');

$actions = [];
foreach ([['NEW', 'N3'], ['DEACTIVATE', 'D1'], ['UPDATE', 'U2'], ['NEW', 'N1'], ['REACTIVATE', 'R1'], ['UPDATE', 'U1'], ['NEW', 'N2']] as [$type, $uid]) {
    $actions[] = ['action' => $type, 'u_id' => $uid, 'row' => [], 'change_hash' => hash('sha256', $uid)];
}
$full = new SyncPlan($actions, 6485, 0, 0, [], 1, 0);
$selector = new SyncPlanSubsetSelector(SyncPilotConfig::fromValues('true', '2', '1', '0', '0'));
$selected = $selector->select($full);
$report($selected->legacyCounts() === ['New' => 2, 'Update' => 1, 'Deactivate' => 0, 'Reactivate' => 0], 'subset is exactly 2 new and 1 update');
$report($selected->sourceRows === 6485 && $selected->protectedManualUsers === 1, 'full snapshot safety metadata retained');
$report($selected->planHash() === $selector->select(new SyncPlan(array_reverse($actions), 6485, 0, 0, [], 1, 0))->planHash(), 'selection is independent of upstream action order');
$report($reason(static fn() => $selector->select(new SyncPlan([['action' => 'NEW', 'u_id' => 'only']], 1, 0, 0))) === 'SYNC_PILOT_SUBSET_UNAVAILABLE', 'insufficient exact subset fails closed');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
