<?php

namespace OneId\Tests\Support\Sync;

use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\Contracts\SyncPersistenceInterface;
use OneId\App\Sync\DTO\SyncPlan;
use OneId\App\Sync\SyncPlanner;

/** Test-only read facade. No persistence mutation is permitted here. */
final class TestSyncDryRun
{
    public function __construct(
        private ExternalUserSourceInterface $source,
        private SyncPersistenceInterface $persistence,
        private SyncPlanner $planner
    ) {
    }

    public function run(): SyncPlan
    {
        $externalRows = $this->source->fetchAll();
        if (empty($externalRows)) {
            return $this->planner->plan([], [], []);
        }

        return $this->planner->plan(
            $externalRows,
            $this->persistence->activeUsers(),
            $this->persistence->inactiveUserIds()
        );
    }
}
