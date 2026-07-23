<?php
declare(strict_types=1);
namespace OneId\App\Sync\SourceAware;

use OneId\App\Sync\DTO\SourceSnapshot;
use OneId\App\Sync\DTO\SyncSafetyDecision;

final class SourceAwareSafetyPolicy
{
    public function __construct(
        private readonly float $maxShrinkPercent = 20.0,
        private readonly float $maxInvalidPercent = 1.0
    ) {}

    /** @param list<SourceSnapshot> $snapshots */
    public function assess(array $snapshots): SyncSafetyDecision
    {
        $blocking = [];
        $warnings = [];
        $metrics = [];
        $seen = [];
        foreach ($snapshots as $snapshot) {
            if (!$snapshot instanceof SourceSnapshot) {
                throw new \InvalidArgumentException('SOURCE_SNAPSHOT_INVALID');
            }
            if (isset($seen[$snapshot->sourceCode])) {
                throw new \InvalidArgumentException('SOURCE_SNAPSHOT_DUPLICATE');
            }
            $seen[$snapshot->sourceCode] = true;
            $prefix = $snapshot->sourceCode === 'STUDENT_ODL_PG'
                ? 'ODL' : $snapshot->sourceCode;
            $rows = count($snapshot->rows);
            $invalidPercent = $rows > 0
                ? ($snapshot->invalidIdentityRows / $rows) * 100 : 100.0;
            $shrinkPercent = null;
            if ($snapshot->baselineRows !== null && $snapshot->baselineRows > 0) {
                $shrinkPercent = max(
                    0.0,
                    (($snapshot->baselineRows - $rows) / $snapshot->baselineRows) * 100
                );
            } else {
                $warnings[] = $prefix . '_BASELINE_UNAVAILABLE';
            }
            if ($snapshot->required && $snapshot->status !== 'success') {
                $blocking[] = $prefix . '_' . strtoupper($snapshot->status);
            }
            if ($snapshot->required && $snapshot->status === 'success' && $rows === 0) {
                $blocking[] = $prefix . '_EMPTY_SOURCE';
            }
            if ($shrinkPercent !== null && $shrinkPercent > $this->maxShrinkPercent) {
                $blocking[] = $prefix . '_SOURCE_SHRINK_EXCEEDED';
            }
            if ($invalidPercent > $this->maxInvalidPercent) {
                $blocking[] = $prefix . '_INVALID_IDENTITY_THRESHOLD_EXCEEDED';
            }
            $metrics[$snapshot->sourceCode] = [
                'status' => $snapshot->status,
                'rows' => $rows,
                'baseline_rows' => $snapshot->baselineRows,
                'shrink_percent' => $shrinkPercent === null ? null : round($shrinkPercent, 2),
                'invalid_identity_rows' => $snapshot->invalidIdentityRows,
                'invalid_percent' => round($invalidPercent, 2),
            ];
        }
        $blocking = array_values(array_unique($blocking));
        return new SyncSafetyDecision(
            $blocking === [],
            $blocking,
            array_values(array_unique($warnings)),
            ['sources' => $metrics]
        );
    }
}
