<?php

namespace OneId\App\Sync;

use DateTimeImmutable;
use DateTimeZone;
use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\Contracts\SyncPersistenceInterface;
use RuntimeException;

/**
 * Read-only external sync preview.
 *
 * This service deliberately exposes no apply method and only calls the two
 * persistence reads required by SyncPlanner.
 */
final class SyncPreviewService
{
    public function __construct(
        private ExternalUserSourceInterface $source,
        private SyncPersistenceInterface $persistence,
        private SyncPlanner $planner,
        private int $expirySeconds = 300,
        private float $deactivationThresholdPercent = 5.0
    ) {
    }

    /** @return array<string, mixed> */
    public function preview(): array
    {
        $externalRows = $this->source->fetchAll();
        if ($externalRows === []) {
            throw new RuntimeException('EMPTY_EXTERNAL_SNAPSHOT');
        }

        $activeUsers = $this->persistence->activeUsers();
        $inactiveUserIds = $this->persistence->inactiveUserIds();
        $plan = $this->planner->plan($externalRows, $activeUsers, $inactiveUserIds);
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
