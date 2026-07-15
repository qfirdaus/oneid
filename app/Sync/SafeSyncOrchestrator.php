<?php

namespace OneId\App\Sync;

use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\Contracts\InitialPasswordFactoryInterface;
use OneId\App\Sync\Contracts\SyncPersistenceInterface;
use OneId\App\Sync\Contracts\SyncReconciliationReaderInterface;
use OneId\App\Sync\Contracts\SyncRunLockInterface;
use OneId\App\Sync\Contracts\SyncPlanApprovalGateInterface;
use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\DTO\SyncPlan;
use OneId\App\Sync\DTO\SyncRunSummary;
use RuntimeException;
use Throwable;

/** S3 fail-closed writer. Dormant until controlled S4 feature-flag wiring. */
final class SafeSyncOrchestrator
{
    public function __construct(
        private ExternalUserSourceInterface $source,
        private SyncPersistenceInterface $persistence,
        private SyncReconciliationReaderInterface $reconciliationReader,
        private SyncRunLockInterface $lock,
        private SyncPlanner $planner,
        private SyncSafetyPolicy $safetyPolicy,
        private SyncReconciler $reconciler,
        private InitialPasswordFactoryInterface $passwordFactory,
        private ?SyncPlanSubsetSelector $subsetSelector = null
    ) {
    }

    public function run(
        string $triggeredBy,
        ?int $previousSourceRows = null,
        int $lockWaitSeconds = 0
    ): SyncRunSummary {
        return $this->runInternal(
            $triggeredBy,
            $previousSourceRows,
            $lockWaitSeconds,
            null,
            null,
            null,
            null
        );
    }

    public function runApproved(
        string $triggeredBy,
        string $approvalId,
        string $adminId,
        SyncPlanApprovalGateInterface $approvalGate,
        int $lockWaitSeconds = 0,
        ?int $now = null
    ): SyncRunSummary {
        return $this->runInternal(
            $triggeredBy,
            null,
            $lockWaitSeconds,
            $approvalGate,
            $approvalId,
            $adminId,
            $now
        );
    }

    private function runInternal(
        string $triggeredBy,
        ?int $previousSourceRows,
        int $lockWaitSeconds,
        ?SyncPlanApprovalGateInterface $approvalGate,
        ?string $approvalId,
        ?string $adminId,
        ?int $now
    ): SyncRunSummary {
        if (!$this->lock->acquire($lockWaitSeconds)) {
            throw new RuntimeException('SYNC_ALREADY_RUNNING');
        }

        $transactionStarted = false;
        try {
            // External I/O and all planning happen before a transaction exists.
            $externalRows = $this->source->fetchAll();
            if ($externalRows === []) {
                $decision = $this->safetyPolicy->assess(
                    [],
                    [],
                    new SyncPlan([], 0, 0, 0),
                    $previousSourceRows
                );
                throw new SyncSafetyViolation($decision);
            }

            $activeUsers = $this->persistence->activeUsers();
            $inactiveUserIds = $this->persistence->inactiveUserIds();
            $plan = $this->planner->plan($externalRows, $activeUsers, $inactiveUserIds);
            if ($this->subsetSelector !== null) {
                $plan = $this->subsetSelector->select($plan);
            }

            $approval = null;
            if ($approvalGate !== null) {
                if ($approvalId === null || $adminId === null) {
                    throw new RuntimeException('SYNC_APPROVAL_CONTEXT_MISSING');
                }
                $approval = $approvalGate->consumeAndValidate(
                    $approvalId,
                    $adminId,
                    $plan,
                    $now
                );
            }

            $decision = $this->safetyPolicy->assess(
                $externalRows,
                $activeUsers,
                $plan,
                $approval instanceof SyncApproval ? $approval->acceptedBaseline : $previousSourceRows
            );
            if (!$decision->allowed) {
                throw new SyncSafetyViolation($decision);
            }

            $this->persistence->begin();
            $transactionStarted = true;
            $headerId = $this->persistence->createHeader(0);
            $result = $this->executePlan($headerId, $plan, $triggeredBy);
            $this->persistence->commit();
            $transactionStarted = false;

            return $result;
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                try {
                    $this->persistence->rollback();
                } catch (Throwable) {
                    // Preserve the original exception for the caller/log.
                }
            }
            throw $exception;
        } finally {
            $this->lock->release();
        }
    }

    private function executePlan(int $headerId, SyncPlan $plan, string $triggeredBy): SyncRunSummary
    {
        $logBuffer = [];
        $pending = [];
        $executed = ['New' => 0, 'Update' => 0, 'Deactivate' => 0, 'Reactivate' => 0];

        foreach ($plan->actions as $index => $action) {
            if ($action['action'] === 'DEACTIVATE') {
                $this->persistence->deactivateUser($action['u_id']);
                $executed['Deactivate']++;
                $logBuffer[] = $this->logRow($headerId, $action);
            } elseif ($action['action'] === 'UPDATE') {
                $this->persistence->updateUser($action['u_id'], $action['row'], $action['change_hash']);
                $executed['Update']++;
                $logBuffer[] = $this->logRow($headerId, $action);
            } elseif (in_array($action['action'], ['NEW', 'REACTIVATE'], true)) {
                $pending[$index] = $action;
            }
        }

        $retainedSourceRows = $plan->sourceRows
            - $plan->discardedInvalid
            - $plan->discardedExcluded
            - $plan->discardedProtectedCollisions;
        $this->persistence->updateHeaderStatus(
            $headerId,
            1,
            'ext_head_initial_sourcedata',
            $retainedSourceRows
        );

        foreach ($pending as $index => $action) {
            $pending[$index]['body_id'] = $this->persistence->stageExternalUser($headerId, $action['row']);
        }
        foreach ($pending as $action) {
            $this->persistence->insertExternalUser(
                $action['row'],
                $action['category_id'],
                $this->passwordFactory->createHash(),
                $action['change_hash']
            );
            $this->persistence->markStagedUser($headerId, $action['body_id'], 2);
            $legacyKey = $action['action'] === 'NEW' ? 'New' : 'Reactivate';
            $executed[$legacyKey]++;
            $logBuffer[] = $this->logRow($headerId, $action);
        }

        $this->persistence->updateHeaderStatus(
            $headerId,
            $pending === [] ? 4 : 2,
            'ext_head_uploaded_data',
            count($pending)
        );

        $planned = $plan->legacyCounts();
        $this->persistence->appendChanges($logBuffer);
        $this->persistence->updateSummary(
            $headerId,
            $planned['New'],
            $planned['Update'],
            $planned['Deactivate'],
            $planned['Reactivate'],
            $triggeredBy
        );

        $audited = $this->reconciliationReader->changeCounts($headerId);
        $this->reconciler->assertMatched($planned, $executed, $audited);

        return new SyncRunSummary(
            $headerId,
            $planned['New'],
            $planned['Update'],
            $planned['Deactivate'],
            $planned['Reactivate'],
            $this->persistence->header($headerId)
        );
    }

    /** @param array<string, mixed> $action */
    private function logRow(int $headerId, array $action): array
    {
        return [
            'ext_head_id' => $headerId,
            'u_id' => $action['u_id'],
            'action' => $action['action'],
            'old_data' => $action['old_data'],
            'new_data' => $action['new_data'],
            'changed_fields' => $action['changed_fields'],
        ];
    }
}
