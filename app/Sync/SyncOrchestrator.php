<?php

namespace OneId\App\Sync;

use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\Contracts\InitialPasswordFactoryInterface;
use OneId\App\Sync\Contracts\SyncPersistenceInterface;
use OneId\App\Sync\DTO\SyncPlan;
use OneId\App\Sync\DTO\SyncRunSummary;

/** Legacy-compatible production orchestration, dormant until a later wiring phase. */
final class SyncOrchestrator
{
    public function __construct(
        private ExternalUserSourceInterface $source,
        private SyncPersistenceInterface $persistence,
        private SyncPlanner $planner,
        private InitialPasswordFactoryInterface $passwordFactory
    ) {
    }

    public function run(string $triggeredBy): SyncRunSummary
    {
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        $this->persistence->begin();
        $headerId = $this->persistence->createHeader(0);

        // Compatibility-first D4 decision: retain the characterized legacy
        // boundary. Upstream/planning failure is corrected in a separate change.
        $externalRows = $this->source->fetchAll();

        if (empty($externalRows)) {
            $this->persistence->updateHeaderStatus($headerId, 3, 'ext_head_initial_sourcedata', 0);
            $this->persistence->updateSummary($headerId, 0, 0, 0, 0, $triggeredBy);
            $this->persistence->commit();

            return new SyncRunSummary(
                $headerId,
                0,
                0,
                0,
                0,
                $this->persistence->header($headerId)
            );
        }

        $plan = $this->planner->plan(
            $externalRows,
            $this->persistence->activeUsers(),
            $this->persistence->inactiveUserIds()
        );

        try {
            return $this->executePlan($headerId, $plan, $triggeredBy);
        } catch (\Exception $exception) {
            $this->persistence->rollback();
            throw $exception;
        }
    }

    private function executePlan(int $headerId, SyncPlan $plan, string $triggeredBy): SyncRunSummary
    {
        $logBuffer = [];
        $pending = [];

        foreach ($plan->actions as $index => $action) {
            if ($action['action'] === 'DEACTIVATE') {
                $this->persistence->deactivateUser($action['u_id']);
                $logBuffer[] = $this->logRow($headerId, $action);
                continue;
            }

            if ($action['action'] === 'UPDATE') {
                $this->persistence->updateUser(
                    $action['u_id'],
                    $action['row'],
                    $action['change_hash']
                );
                $logBuffer[] = $this->logRow($headerId, $action);
                continue;
            }

            if (in_array($action['action'], ['NEW', 'REACTIVATE'], true)) {
                $pending[$index] = $action;
            }
        }

        $retainedSourceRows = $plan->sourceRows
            - $plan->discardedInvalid
            - $plan->discardedExcluded;
        $this->persistence->updateHeaderStatus(
            $headerId,
            1,
            'ext_head_initial_sourcedata',
            $retainedSourceRows
        );

        foreach ($pending as $index => $action) {
            $pending[$index]['body_id'] = $this->persistence->stageExternalUser(
                $headerId,
                $action['row']
            );
        }

        foreach ($pending as $action) {
            $this->persistence->insertExternalUser(
                $action['row'],
                $action['category_id'],
                $this->passwordFactory->createHash(),
                $action['change_hash']
            );
            $this->persistence->markStagedUser($headerId, $action['body_id'], 2);
            $logBuffer[] = $this->logRow($headerId, $action);
        }

        $pendingCount = count($pending);
        $this->persistence->updateHeaderStatus(
            $headerId,
            $pendingCount > 0 ? 2 : 4,
            'ext_head_uploaded_data',
            $pendingCount
        );

        $counts = $plan->legacyCounts();
        $this->persistence->appendChanges($logBuffer);
        $this->persistence->updateSummary(
            $headerId,
            $counts['New'],
            $counts['Update'],
            $counts['Deactivate'],
            $counts['Reactivate'],
            $triggeredBy
        );
        $this->persistence->commit();

        return new SyncRunSummary(
            $headerId,
            $counts['New'],
            $counts['Update'],
            $counts['Deactivate'],
            $counts['Reactivate'],
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
