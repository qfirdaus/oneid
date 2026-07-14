<?php

namespace OneId\App\Sync;

use JsonException;
use OneId\App\Sync\DTO\SyncPlan;

/** Canonical, PII-redacted fingerprint independent of upstream row order. */
final class SyncPlanFingerprinter
{
    /** @throws JsonException */
    public function fingerprint(SyncPlan $plan): string
    {
        $actions = $plan->safeProjection();
        foreach ($actions as &$action) {
            ksort($action, SORT_STRING);
        }
        unset($action);
        usort($actions, static function (array $left, array $right): int {
            return strcmp(
                json_encode($left, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                json_encode($right, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            );
        });

        $warnings = $plan->warnings;
        sort($warnings, SORT_STRING);
        $payload = [
            'actions' => $actions,
            'counts' => $plan->legacyCounts(),
            'source_rows' => $plan->sourceRows,
            'discarded_invalid' => $plan->discardedInvalid,
            'discarded_excluded' => $plan->discardedExcluded,
            'protected_manual_users' => $plan->protectedManualUsers,
            'discarded_protected_collisions' => $plan->discardedProtectedCollisions,
            'warnings' => $warnings,
        ];

        return hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
    }
}
