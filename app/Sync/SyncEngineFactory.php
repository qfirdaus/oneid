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

    public function createCronCoordinator(
        SyncApprovalStoreInterface $approvalStore,
        SyncCronConfig $cronConfig,
        string $sourceCode
    ): ApprovedSyncCoordinator {
        if (!$this->config->canApply()) {
            throw new RuntimeException('SYNC_APPLY_DISABLED');
        }
        if (!$cronConfig->enabled || $cronConfig->dryRun) {
            throw new RuntimeException('SYNC_CRON_APPLY_DISABLED');
        }
        if (!in_array($sourceCode, $cronConfig->sources, true)) {
            throw new RuntimeException('SYNC_CRON_SOURCE_NOT_ENABLED');
        }
        return new ApprovedSyncCoordinator(
            $this->buildSafeOrchestrator(null, $sourceCode, true),
            new SyncApprovalService($approvalStore, new SyncPlanFingerprinter())
        );
    }

    private function buildSafeOrchestrator(
        ?SyncPlanSubsetSelector $selector = null,
        ?string $sourceCode = null,
        bool $cron = false
    ): SafeSyncOrchestrator
    {
        [$source, $persistence] = $this->sourceScope($sourceCode, $cron);
        return new SafeSyncOrchestrator(
            $source,
            $persistence,
            new DatabaseSyncReconciliationReader($this->operation),
            new DatabaseSyncRunLock($this->operation),
            new SyncPlanner(
                new LegacySyncPolicy(),
                $sourceCode === \OneId\App\Sync\Odl\OdlStudentSource::SOURCE_CODE,
                $sourceCode === \OneId\App\Sync\Odl\OdlStudentSource::SOURCE_CODE
            ),
            new SyncSafetyPolicy(
                requiredSourceCode: $sourceCode
            ),
            new SyncReconciler(),
            new SecureInitialPasswordFactory(),
            $selector
        );
    }

    /** @return array{Contracts\ExternalUserSourceInterface,Contracts\SyncPersistenceInterface} */
    private function sourceScope(?string $sourceCode, bool $cron = false): array
    {
        $persistence = new DatabaseSyncPersistenceAdapter($this->operation);
        if ($sourceCode === null) {
            return [new ExternalApiUserSource(), $persistence];
        }
        $scope = $cron
            ? SyncSourceScope::fromCodeForCron($sourceCode)
            : SyncSourceScope::fromCode($sourceCode);
        $provenanceEnforced = $scope->provenanceEnforced;
        return [
            $scope->source,
            new SourceScopedSyncPersistenceAdapter(
                $persistence,
                $scope->categoryIds,
                $provenanceEnforced
                    ? fn(): array =>
                        $this->operation->sync_get_active_user_ids_by_source(
                            $scope->sourceCode
                        )
                    : null,
                $provenanceEnforced
                    ? fn(): array =>
                        $this->operation->sync_get_inactive_user_ids_by_source(
                            $scope->sourceCode
                        )
                    : null,
                $provenanceEnforced
                    ? fn(string $userId,string $identityNumber) =>
                        $this->operation->sync_assert_source_identity_writable(
                            $userId, $scope->sourceCode, $identityNumber
                        )
                    : null,
                $provenanceEnforced
                    ? fn(string $userId,string $externalId,string $hash) =>
                        $this->operation->sync_upsert_source_membership(
                            $userId,$scope->sourceCode,$externalId,$hash
                        )
                    : null,
                $provenanceEnforced
                    ? fn(string $userId) =>
                        $this->operation->sync_deactivate_source_membership(
                            $userId,$scope->sourceCode
                        )
                    : null,
                $provenanceEnforced
                    ? fn(string $userId): bool =>
                        $this->operation->sync_has_other_active_source(
                            $userId,$scope->sourceCode
                        )
                    : null
            ),
        ];
    }
}
