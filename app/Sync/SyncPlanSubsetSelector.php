<?php

namespace OneId\App\Sync;

use OneId\App\Sync\DTO\SyncPlan;
use RuntimeException;

/** Pure deterministic selector. It never exposes raw identities. */
final class SyncPlanSubsetSelector
{
    public function __construct(private SyncPilotConfig $config)
    {
        if (!$config->enabled) {
            throw new RuntimeException('SYNC_PILOT_DISABLED');
        }
    }

    public function select(SyncPlan $fullPlan): SyncPlan
    {
        $groups = ['NEW' => [], 'UPDATE' => []];
        foreach ($fullPlan->actions as $action) {
            $type = (string) ($action['action'] ?? '');
            if (isset($groups[$type])) {
                $groups[$type][] = $action;
            }
        }
        foreach ($groups as &$actions) {
            usort($actions, static fn(array $left, array $right): int => strcmp(
                hash('sha256', (string) ($left['u_id'] ?? '')),
                hash('sha256', (string) ($right['u_id'] ?? ''))
            ));
        }
        unset($actions);

        if (count($groups['NEW']) < $this->config->newLimit
            || count($groups['UPDATE']) < $this->config->updateLimit
        ) {
            throw new RuntimeException('SYNC_PILOT_SUBSET_UNAVAILABLE');
        }

        $actions = array_merge(
            array_slice($groups['NEW'], 0, $this->config->newLimit),
            array_slice($groups['UPDATE'], 0, $this->config->updateLimit)
        );

        return new SyncPlan(
            $actions,
            $fullPlan->sourceRows,
            $fullPlan->discardedInvalid,
            $fullPlan->discardedExcluded,
            $fullPlan->warnings,
            $fullPlan->protectedManualUsers,
            $fullPlan->discardedProtectedCollisions
        );
    }
}
