<?php

namespace OneId\App\Sync;

use OneId\App\Sync\Adapters\DatabaseSyncPersistenceAdapter;
use OneId\App\Sync\Adapters\DatabaseSyncReconciliationReader;
use OneId\App\Sync\Adapters\DatabaseSyncRunLock;
use OneId\App\Sync\Adapters\ExternalApiUserSource;
use OneId\App\Sync\Adapters\LegacySyncPolicy;
use OneId\App\Sync\Adapters\SecureInitialPasswordFactory;
use OneId\App\Sync\Contracts\SyncApprovalStoreInterface;
use RuntimeException;

/** S4A dormant dependency factory. Construction performs no I/O. */
final class SyncEngineFactory
{
    public function __construct(
        private object $operation,
        private SyncRuntimeConfig $config
    ) {
    }

    public function createApprovedCoordinator(
        SyncApprovalStoreInterface $approvalStore
    ): ApprovedSyncCoordinator
    {
        if (!$this->config->canApply()) {
            throw new RuntimeException('SYNC_APPLY_DISABLED');
        }

        return new ApprovedSyncCoordinator(
            $this->buildSafeOrchestrator(),
            new SyncApprovalService($approvalStore, new SyncPlanFingerprinter())
        );
    }

    private function buildSafeOrchestrator(): SafeSyncOrchestrator
    {
        return new SafeSyncOrchestrator(
            new ExternalApiUserSource(),
            new DatabaseSyncPersistenceAdapter($this->operation),
            new DatabaseSyncReconciliationReader($this->operation),
            new DatabaseSyncRunLock($this->operation),
            new SyncPlanner(new LegacySyncPolicy()),
            new SyncSafetyPolicy(),
            new SyncReconciler(),
            new SecureInitialPasswordFactory()
        );
    }
}
