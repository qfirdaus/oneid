<?php

namespace OneId\App\Sync;

use OneId\App\Sync\DTO\SyncPlan;
use OneId\App\Sync\DTO\SyncSafetyDecision;

/** Pure source-completeness and blast-radius policy. */
final class SyncSafetyPolicy
{
    public function __construct(
        private float $maxDeactivationPercent = 5.0,
        private float $maxSourceShrinkPercent = 20.0,
        private float $maxInvalidPercent = 1.0
    ) {
    }

    /**
     * @param list<array<string, mixed>> $externalRows
     * @param list<array<string, mixed>> $activeUsers
     */
    public function assess(
        array $externalRows,
        array $activeUsers,
        SyncPlan $plan,
        ?int $previousSourceRows = null
    ): SyncSafetyDecision {
        $blocking = [];
        $warnings = [];
        $sourceRows = count($externalRows);
        $counts = $plan->legacyCounts();
        $activeScope = max(1, count($activeUsers) - $plan->protectedManualUsers);
        $deactivationPercent = ($counts['Deactivate'] / $activeScope) * 100;
        $invalidPercent = $sourceRows > 0 ? ($plan->discardedInvalid / $sourceRows) * 100 : 100.0;

        $staffRows = 0;
        $studentRows = 0;
        $knownCategories = [
            'Akademik',
            'Pentadbiran',
            'Pelajar',
            'PelajarPelajar',
            'PentadbiranPelajar',
            'AkademikPelajar',
        ];
        foreach ($externalRows as $row) {
            $category = trim((string) ($row['ext_data_source_category'] ?? ''));
            if (!in_array($category, $knownCategories, true)) {
                $blocking[] = 'UNKNOWN_SOURCE_CATEGORY';
                continue;
            }
            if (in_array($category, ['Pelajar', 'PelajarPelajar', 'PentadbiranPelajar', 'AkademikPelajar'], true)) {
                $studentRows++;
            }
            if (in_array($category, ['Akademik', 'Pentadbiran', 'PentadbiranPelajar', 'AkademikPelajar'], true)) {
                $staffRows++;
            }
        }

        if ($sourceRows === 0) $blocking[] = 'EMPTY_SOURCE';
        if ($staffRows === 0) $blocking[] = 'STAFF_SOURCE_MISSING';
        if ($studentRows === 0) $blocking[] = 'STUDENT_SOURCE_MISSING';
        if ($deactivationPercent > $this->maxDeactivationPercent) {
            $blocking[] = 'DEACTIVATION_THRESHOLD_EXCEEDED';
        }
        if ($invalidPercent > $this->maxInvalidPercent) {
            $blocking[] = 'INVALID_SOURCE_THRESHOLD_EXCEEDED';
        }
        if ($plan->discardedProtectedCollisions > 0) {
            $blocking[] = 'PROTECTED_IDENTITY_COLLISION';
        }
        foreach ($plan->actions as $action) {
            if (in_array($action['action'] ?? '', ['NEW', 'REACTIVATE'], true)
                && (int) ($action['category_id'] ?? 0) === 0
            ) {
                $blocking[] = 'UNKNOWN_SOURCE_CATEGORY';
                break;
            }
        }

        $shrinkPercent = null;
        if ($previousSourceRows !== null && $previousSourceRows > 0) {
            $shrinkPercent = max(0.0, (($previousSourceRows - $sourceRows) / $previousSourceRows) * 100);
            if ($shrinkPercent > $this->maxSourceShrinkPercent) {
                $blocking[] = 'SOURCE_SHRINK_THRESHOLD_EXCEEDED';
            }
        } else {
            $warnings[] = 'SOURCE_BASELINE_UNAVAILABLE';
        }

        $blocking = array_values(array_unique($blocking));
        return new SyncSafetyDecision(
            $blocking === [],
            $blocking,
            $warnings,
            [
                'source_rows' => $sourceRows,
                'staff_rows' => $staffRows,
                'student_rows' => $studentRows,
                'previous_source_rows' => $previousSourceRows,
                'source_shrink_percent' => $shrinkPercent,
                'active_sync_scope' => $activeScope,
                'deactivation_percent' => round($deactivationPercent, 2),
                'invalid_percent' => round($invalidPercent, 2),
            ]
        );
    }
}
