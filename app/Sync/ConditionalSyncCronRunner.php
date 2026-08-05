<?php

declare(strict_types=1);

namespace OneId\App\Sync;

use OneId\App\Sync\Adapters\DatabaseSyncPersistenceAdapter;
use OneId\App\Sync\Adapters\InMemorySyncApprovalStore;
use OneId\App\Sync\Adapters\LegacySyncPolicy;
use OneId\App\Sync\Adapters\SourceScopedSyncPersistenceAdapter;
use OneId\App\Sync\Odl\OdlStudentSource;
use OneId\App\Sync\Odl\StaffSource;
use RuntimeException;

final class ConditionalSyncCronRunner
{
    public function __construct(
        private readonly object $operation,
        private readonly SyncCronConfig $config,
        private readonly SyncRuntimeConfig $runtime
    ) {}

    /** @return array<string,mixed> */
    public function runSource(string $sourceCode): array
    {
        if (!$this->config->enabled) {
            throw new RuntimeException('SYNC_CRON_DISABLED');
        }
        if (!in_array($sourceCode, $this->config->sources, true)) {
            throw new RuntimeException('SYNC_CRON_SOURCE_NOT_ENABLED');
        }
        if ($sourceCode === StaffSource::SOURCE_CODE
            && (string) \oneid_config('ONEID_SYNC_STAFF_PROVENANCE_ENABLED', 'false') !== 'true'
        ) {
            throw new RuntimeException('SYNC_CRON_STAFF_PROVENANCE_REQUIRED');
        }

        $scope = SyncSourceScope::fromCodeForCron($sourceCode);
        $persistence = new SourceScopedSyncPersistenceAdapter(
            new DatabaseSyncPersistenceAdapter($this->operation),
            $scope->categoryIds,
            $scope->provenanceEnforced
                ? fn(): array => $this->operation->sync_get_active_user_ids_by_source($sourceCode)
                : null,
            $scope->provenanceEnforced
                ? fn(): array => $this->operation->sync_get_inactive_user_ids_by_source($sourceCode)
                : null
        );
        $store = new InMemorySyncApprovalStore();
        $approvalService = new SyncApprovalService($store, new SyncPlanFingerprinter());
        $preview = (new SyncPreviewService(
            $scope->source,
            $persistence,
            new SyncPlanner(
                new LegacySyncPolicy(),
                $scope->preserveExistingEmailOnBlank,
                $sourceCode === OdlStudentSource::SOURCE_CODE
            ),
            300,
            5.0,
            new SyncSafetyPolicy(requiredSourceCode: $sourceCode),
            $sourceCode === OdlStudentSource::SOURCE_CODE
                ? fn(array $rows) => $this->operation->sync_assert_source_snapshot_isolated($rows, $sourceCode)
                : null
        ))->previewForApproval(
            $this->config->serviceIdentity,
            $scope->baselineRows,
            $approvalService
        );

        $counts = $preview['counts'];
        $base = [
            'source' => $sourceCode,
            'counts' => $counts,
            'source_rows' => (int) $preview['source_rows'],
            'correlation_id' => (string) ($preview['correlation_id'] ?? bin2hex(random_bytes(8))),
        ];
        if (array_sum($counts) === 0) {
            return $base + ['outcome' => 'SKIP_NO_CHANGES', 'mutation_statements' => 0];
        }
        $blocking = $preview['blocking_codes'] ?? [];
        $warnings = $preview['warnings'] ?? [];
        $limitCode = $this->config->blockingCode($sourceCode, $counts);
        if ($blocking !== [] || $warnings !== [] || $limitCode !== null) {
            return $base + [
                'outcome' => 'BLOCKED_REQUIRES_ADMIN',
                'code' => $limitCode ?? (string) ($blocking[0] ?? $warnings[0]),
                'mutation_statements' => 0,
            ];
        }
        if ($this->config->dryRun) {
            return $base + ['outcome' => 'DRY_RUN_CHANGES_FOUND', 'mutation_statements' => 0];
        }
        if (!$this->runtime->canApply()) {
            throw new RuntimeException('SYNC_APPLY_DISABLED');
        }
        $approvalId = (string) ($preview['approval_id'] ?? '');
        try {
            $summary = (new SyncEngineFactory($this->operation, $this->runtime))
                ->createCronCoordinator($store, $this->config, $sourceCode)
                ->run(
                    $approvalId,
                    $this->config->serviceIdentity,
                    $this->config->serviceIdentity
                );
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'SYNC_APPROVAL_PLAN_MISMATCH') {
                return $base + [
                    'outcome' => 'BLOCKED_PLAN_DRIFT',
                    'code' => 'SYNC_APPROVAL_PLAN_MISMATCH',
                    'mutation_statements' => 0,
                ];
            }
            if ($exception->getMessage() === 'SYNC_ALREADY_RUNNING') {
                return $base + [
                    'outcome' => 'SKIP_ALREADY_RUNNING',
                    'code' => 'SYNC_ALREADY_RUNNING',
                    'mutation_statements' => 0,
                ];
            }
            throw $exception;
        }
        $auditRecorded = true;
        try {
            $auditRecorded = $this->operation->syslog_record(
                22,
                sprintf(
                    'ADMIN_SYNC_CRON_SAFE source=%s header=%d new=%d updated=%d deactivated=%d reactivated=%d correlation=%s',
                    $sourceCode,
                    $summary->headerId,
                    $summary->new,
                    $summary->updated,
                    $summary->deactivated,
                    $summary->reactivated,
                    $base['correlation_id']
                ),
                '127.0.0.1'
            ) === 1;
        } catch (\Throwable) {
            $auditRecorded = false;
        }
        return $base + [
            'outcome' => $auditRecorded ? 'APPLIED' : 'APPLIED_AUDIT_WARNING',
            'header_id' => $summary->headerId,
            'audit_marker_recorded' => $auditRecorded,
        ];
    }
}
