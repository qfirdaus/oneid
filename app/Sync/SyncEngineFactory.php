<?php

namespace OneId\App\Sync;

use OneId\App\Sync\Adapters\DatabaseSyncPersistenceAdapter;
use OneId\App\Sync\Adapters\DatabaseSyncReconciliationReader;
use OneId\App\Sync\Adapters\DatabaseSyncRunLock;
use OneId\App\Sync\Adapters\ExternalApiUserSource;
use OneId\App\Sync\Adapters\LegacySyncPolicy;
use OneId\App\Sync\Adapters\SecureInitialPasswordFactory;
use OneId\App\Sync\Adapters\SourceScopedSyncPersistenceAdapter;
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

    public function createPilotCoordinator(
        SyncApprovalStoreInterface $approvalStore,
        SyncPilotConfig $pilotConfig,
        ?string $sourceCode = null
    ): ApprovedSyncCoordinator {
        if (!$this->config->canApply()) {
            throw new RuntimeException('SYNC_APPLY_DISABLED');
        }
        if (!$pilotConfig->enabled) {
            throw new RuntimeException('SYNC_PILOT_DISABLED');
        }
        $selector = new SyncPlanSubsetSelector($pilotConfig);

        return new ApprovedSyncCoordinator(
            $this->buildSafeOrchestrator($selector, $sourceCode),
            new SyncApprovalService($approvalStore, new SyncPlanFingerprinter())
        );
    }

    public function createFullCoordinator(
        SyncApprovalStoreInterface $approvalStore,
        SyncFullConfig $fullConfig,
        ?string $sourceCode = null
    ): ApprovedSyncCoordinator {
        if (!$this->config->canApply()) {
            throw new RuntimeException('SYNC_APPLY_DISABLED');
        }
        if (!$fullConfig->enabled) {
            throw new RuntimeException('SYNC_FULL_DISABLED');
        }
        $fingerprinter = new SyncPlanFingerprinter();
        $approvalService = new SyncApprovalService($approvalStore, $fingerprinter);

        return new ApprovedSyncCoordinator(
            $this->buildSafeOrchestrator(null, $sourceCode),
            new FullSyncApprovalGate($approvalService, $fullConfig, $fingerprinter)
        );
    }

    public function createOperationalCoordinator(
        SyncApprovalStoreInterface $approvalStore,
        SyncOperationalConfig $operationalConfig,
        string $confirmation,
        ?string $sourceCode = null
    ): ApprovedSyncCoordinator {
        if (!$this->config->canApply()) {
            throw new RuntimeException('SYNC_APPLY_DISABLED');
        }
        if (!$operationalConfig->enabled) {
            throw new RuntimeException('SYNC_OPERATIONAL_DISABLED');
        }
        $fingerprinter = new SyncPlanFingerprinter();
        $approvalService = new SyncApprovalService($approvalStore, $fingerprinter);

        return new ApprovedSyncCoordinator(
            $this->buildSafeOrchestrator(null, $sourceCode),
            new OperationalSyncApprovalGate(
                $approvalService,
                $operationalConfig,
                $confirmation
            )
        );
    }

    private function buildSafeOrchestrator(
        ?SyncPlanSubsetSelector $selector = null,
        ?string $sourceCode = null
    ): SafeSyncOrchestrator
    {
        [$source, $persistence] = $this->sourceScope($sourceCode);
        return new SafeSyncOrchestrator(
            $source,
            $persistence,
            new DatabaseSyncReconciliationReader($this->operation),
            new DatabaseSyncRunLock($this->operation),
            new SyncPlanner(new LegacySyncPolicy()),
            new SyncSafetyPolicy(
                requiredSourceCode: $sourceCode
            ),
            new SyncReconciler(),
            new SecureInitialPasswordFactory(),
            $selector
        );
    }

    /** @return array{Contracts\ExternalUserSourceInterface,Contracts\SyncPersistenceInterface} */
    private function sourceScope(?string $sourceCode): array
    {
        $persistence = new DatabaseSyncPersistenceAdapter($this->operation);
        if ($sourceCode === null) {
            return [new ExternalApiUserSource(), $persistence];
        }
        $scope = SyncSourceScope::fromCode($sourceCode);
        return [
            $scope->source,
            new SourceScopedSyncPersistenceAdapter(
                $persistence,
                $scope->categoryIds,
                $scope->sourceCode === \OneId\App\Sync\Odl\UgStudentSource::SOURCE_CODE
                    ? fn(): array =>
                        $this->operation->sync_get_active_user_ids_by_source(
                            $scope->sourceCode
                        )
                    : null
            ),
        ];
    }
}
