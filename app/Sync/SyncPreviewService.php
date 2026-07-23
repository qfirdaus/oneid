<?php

namespace OneId\App\Sync;

use DateTimeImmutable;
use DateTimeZone;
use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\Contracts\SyncPersistenceInterface;
use OneId\App\Sync\DTO\SyncPlan;
use RuntimeException;

/**
 * Read-only external sync preview.
 *
 * This service deliberately exposes no apply method and only calls the two
 * persistence reads required by SyncPlanner.
 */
final class SyncPreviewService
{
    private SyncSafetyPolicy $safetyPolicy;

    public function __construct(
        private ExternalUserSourceInterface $source,
        private SyncPersistenceInterface $persistence,
        private SyncPlanner $planner,
        private int $expirySeconds = 300,
        private float $deactivationThresholdPercent = 5.0,
        ?SyncSafetyPolicy $safetyPolicy = null,
        private ?\Closure $externalRowsValidator = null
    ) {
        $this->safetyPolicy = $safetyPolicy
            ?? new SyncSafetyPolicy($this->deactivationThresholdPercent);
    }

    /** @return array<string, mixed> */
    public function preview(): array
    {
        [$externalRows, $activeUsers, $plan] = $this->buildPlan();
        return $this->projectPreview($externalRows, $activeUsers, $plan);
    }

    /**
     * Build one read-only snapshot and issue an approval only when the full S3
     * safety policy and authoritative previous-source baseline both pass.
     *
     * @return array<string, mixed>
     */
    public function previewForApproval(
        string $adminId,
        ?int $acceptedBaseline,
        SyncApprovalService $approvalService,
        ?SyncPlanSubsetSelector $subsetSelector = null
    ): array {
        [$externalRows, $activeUsers, $plan] = $this->buildPlan();
        $response = $this->projectPreview($externalRows, $activeUsers, $plan);
        $decision = $this->safetyPolicy->assess(
            $externalRows,
            $activeUsers,
            $plan,
            $acceptedBaseline
        );

        $blockingCodes = $decision->blockingCodes;
        if ($acceptedBaseline === null || $acceptedBaseline < 1) {
            $blockingCodes[] = 'SOURCE_BASELINE_UNAVAILABLE';
        }
        $blockingCodes = array_values(array_unique($blockingCodes));

        $response['safety_metrics'] = $decision->metrics;
        $response['blocking_codes'] = $blockingCodes;
        $response['approval_ready'] = false;
        $response['can_apply'] = false;
        $response['risk_level'] = $blockingCodes === [] ? 'normal' : 'blocked';
        $response['warnings'] = array_values(array_unique(array_merge(
            $response['warnings'],
            $decision->warnings,
            $blockingCodes
        )));

        if ($blockingCodes === [] && $response['warnings'] !== []) {
            $blockingCodes[] = 'PREVIEW_WARNING_REQUIRES_REVIEW';
            $response['blocking_codes'] = $blockingCodes;
            $response['warnings'][] = 'PREVIEW_WARNING_REQUIRES_REVIEW';
            $response['risk_level'] = 'blocked';
        }

        if ($blockingCodes !== []) {
            return $response;
        }

        $approvedPlan = $subsetSelector === null ? $plan : $subsetSelector->select($plan);
        $receipt = $approvalService->issue(
            $adminId,
            $approvedPlan,
            $acceptedBaseline,
            time()
        );
        $timezone = new DateTimeZone('Asia/Kuala_Lumpur');
        $response['approval_ready'] = true;
        $response['pilot_mode'] = $subsetSelector !== null;
        $response['pilot_counts'] = $approvedPlan->legacyCounts();
        $response['approval_id'] = $receipt->approvalId;
        $response['correlation_id'] = $receipt->correlationId;
        $response['plan_hash'] = $receipt->planFingerprint;
        $response['generated_at'] = (new DateTimeImmutable('@' . $receipt->issuedAt))
            ->setTimezone($timezone)->format(DATE_ATOM);
        $response['expires_at'] = (new DateTimeImmutable('@' . $receipt->expiresAt))
            ->setTimezone($timezone)->format(DATE_ATOM);

        return $response;
    }

    /** @return array{array, array, \OneId\App\Sync\DTO\SyncPlan} */
    private function buildPlan(): array
    {
        $externalRows = $this->source->fetchAll();
        if ($externalRows === []) {
            throw new RuntimeException('EMPTY_EXTERNAL_SNAPSHOT');
        }
        if ($this->externalRowsValidator !== null) {
            ($this->externalRowsValidator)($externalRows);
        }

        $activeUsers = $this->persistence->activeUsers();
        $inactiveUserIds = $this->persistence->inactiveUserIds();
        $plan = $this->planner->plan($externalRows, $activeUsers, $inactiveUserIds);

        return [$externalRows, $activeUsers, $plan];
    }

    /** @return array<string, mixed> */
    private function projectPreview(array $externalRows, array $activeUsers, SyncPlan $plan): array
    {
        $counts = $plan->legacyCounts();

        $warnings = $plan->warnings;
        if ($plan->discardedInvalid > 0) {
            $warnings[] = 'Invalid external rows were excluded from the preview.';
        }
        if ($plan->discardedExcluded > 0) {
            $warnings[] = 'Policy-excluded identities were excluded from the preview.';
        }

        $activeSyncScope = max(1, count($activeUsers) - $plan->protectedManualUsers);
        $deactivationPercent = round(($counts['Deactivate'] / $activeSyncScope) * 100, 2);
        $riskLevel = 'normal';
        if ($deactivationPercent > $this->deactivationThresholdPercent) {
            $riskLevel = 'blocked';
            $warnings[] = 'Deactivation threshold exceeded; apply must remain blocked.';
        } elseif ($warnings !== []) {
            $riskLevel = 'warning';
        }

        $timezone = new DateTimeZone('Asia/Kuala_Lumpur');
        $generatedAt = new DateTimeImmutable('now', $timezone);
        $expiresAt = $generatedAt->modify('+' . max(1, $this->expirySeconds) . ' seconds');

        return [
            'status' => 1,
            'mode' => 'preview',
            'can_apply' => false,
            'risk_level' => $riskLevel,
            'counts' => $counts,
            'source_rows' => $plan->sourceRows,
            'active_sync_scope' => $activeSyncScope,
            'discarded_invalid' => $plan->discardedInvalid,
            'discarded_excluded' => $plan->discardedExcluded,
            'protected_manual_users' => $plan->protectedManualUsers,
            'discarded_protected_collisions' => $plan->discardedProtectedCollisions,
            'deactivation_percent' => $deactivationPercent,
            'plan_hash' => $plan->planHash(),
            'generated_at' => $generatedAt->format(DATE_ATOM),
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'warnings' => array_values(array_unique($warnings)),
            // Digests only. Raw IDs, names, emails and source rows are never returned.
            'sample' => array_slice($plan->safeProjection(), 0, 20),
        ];
    }
}
