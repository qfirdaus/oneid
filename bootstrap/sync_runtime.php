<?php

/** S4D runtime class map. Loading these definitions performs no I/O. */

$oneidSyncRoot = dirname(__DIR__);
foreach ([
    'app/Sync/Contracts/ExternalUserSourceInterface.php',
    'app/Sync/Contracts/InitialPasswordFactoryInterface.php',
    'app/Sync/Contracts/SyncPersistenceInterface.php',
    'app/Sync/Contracts/SyncPolicyInterface.php',
    'app/Sync/Contracts/SyncReconciliationReaderInterface.php',
    'app/Sync/Contracts/SyncRunLockInterface.php',
    'app/Sync/Contracts/SyncApprovalStoreInterface.php',
    'app/Sync/Contracts/SyncPlanApprovalGateInterface.php',
    'app/Sync/DTO/SyncPlan.php',
    'app/Sync/DTO/SyncRunSummary.php',
    'app/Sync/DTO/SyncSafetyDecision.php',
    'app/Sync/DTO/SyncApproval.php',
    'app/Sync/DTO/SyncApprovalReceipt.php',
    'app/Sync/SyncDataTransformer.php',
    'app/Sync/SyncPlanner.php',
    'app/Sync/SyncSafetyPolicy.php',
    'app/Sync/SyncSafetyViolation.php',
    'app/Sync/SyncDatabaseStageException.php',
    'app/Sync/SyncReconciler.php',
    'app/Sync/SyncPlanFingerprinter.php',
    'app/Sync/SyncApprovalService.php',
    'app/Sync/SyncPreviewService.php',
    'app/Sync/SafeSyncOrchestrator.php',
    'app/Sync/ApprovedSyncCoordinator.php',
    'app/Sync/SyncRuntimeConfig.php',
    'app/Sync/SyncPilotConfig.php',
    'app/Sync/SyncFullConfig.php',
    'app/Sync/FullSyncApprovalGate.php',
    'app/Sync/SyncOperationalConfig.php',
    'app/Sync/OperationalSyncApprovalGate.php',
    'app/Sync/SyncPlanSubsetSelector.php',
    'app/Sync/Adapters/ExternalApiUserSource.php',
    'app/Sync/Adapters/DatabaseSyncPersistenceAdapter.php',
    'app/Sync/Adapters/DatabaseSyncReconciliationReader.php',
    'app/Sync/Adapters/DatabaseSyncRunLock.php',
    'app/Sync/Adapters/LegacySyncPolicy.php',
    'app/Sync/Adapters/SecureInitialPasswordFactory.php',
    'app/Sync/Adapters/SessionSyncApprovalStore.php',
    'app/Sync/ExternalRowNormalizer.php',
    'app/Sync/Provenance/ProvenanceBackfillPreview.php',
    'app/Sync/Odl/OdlDataQualityAudit.php',
    'app/Sync/Odl/OdlSourceConfig.php',
    'app/Sync/Odl/OdlStudentSource.php',
    'app/Sync/SyncEngineFactory.php',
] as $oneidSyncFile) {
    require_once $oneidSyncRoot . '/' . $oneidSyncFile;
}

unset($oneidSyncRoot, $oneidSyncFile);
