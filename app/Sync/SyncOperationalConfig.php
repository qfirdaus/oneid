<?php

namespace OneId\App\Sync;

use RuntimeException;

/** Persistent safe-mode switch and typed confirmation policy for routine sync. */
final class SyncOperationalConfig
{
    private function __construct(
        public readonly bool $enabled,
        public readonly int $warnNew,
        public readonly int $warnUpdate,
        public readonly int $warnReactivate,
        public readonly int $warnTotal,
        public readonly int $maxDeactivate
    ) {
    }

    public static function fromEnvironment(): self
    {
        return self::fromValues(
            (string) oneid_config('ONEID_SYNC_OPERATIONAL_ENABLED', 'false'),
            (string) oneid_config('ONEID_SYNC_OPERATIONAL_WARN_NEW', '500'),
            (string) oneid_config('ONEID_SYNC_OPERATIONAL_WARN_UPDATE', '1000'),
            (string) oneid_config('ONEID_SYNC_OPERATIONAL_WARN_REACTIVATE', '100'),
            (string) oneid_config('ONEID_SYNC_OPERATIONAL_WARN_TOTAL', '1500'),
            (string) oneid_config('ONEID_SYNC_OPERATIONAL_MAX_DEACTIVATE', '50')
        );
    }

    public static function fromValue(string $enabled): self
    {
        return self::fromValues($enabled, '500', '1000', '100', '1500', '50');
    }

    public static function fromValues(
        string $enabled,
        string $warnNew,
        string $warnUpdate,
        string $warnReactivate,
        string $warnTotal,
        string $maxDeactivate
    ): self
    {
        if (!in_array($enabled, ['false', 'true'], true)) {
            throw new RuntimeException('SYNC_OPERATIONAL_FLAG_INVALID');
        }
        foreach ([$warnNew, $warnUpdate, $warnReactivate, $warnTotal] as $threshold) {
            if (preg_match('/^[1-9][0-9]{0,4}$/', $threshold) !== 1) {
                throw new RuntimeException('SYNC_OPERATIONAL_WARNING_THRESHOLD_INVALID');
            }
        }
        if (preg_match('/^(0|[1-9][0-9]{0,3})$/', $maxDeactivate) !== 1) {
            throw new RuntimeException('SYNC_OPERATIONAL_DEACTIVATE_LIMIT_INVALID');
        }

        return new self(
            $enabled === 'true',
            (int) $warnNew,
            (int) $warnUpdate,
            (int) $warnReactivate,
            (int) $warnTotal,
            (int) $maxDeactivate
        );
    }

    /** @param array{New:int,Update:int,Deactivate:int,Reactivate:int} $counts */
    public function confirmationText(string $planHash, array $counts): string
    {
        if (!$this->enabled) {
            throw new RuntimeException('SYNC_OPERATIONAL_DISABLED');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $planHash) !== 1) {
            throw new RuntimeException('SYNC_OPERATIONAL_PLAN_HASH_INVALID');
        }
        $this->assertWithinHardLimits($counts);

        $prefix = strtoupper(substr($planHash, 0, 12));
        $normalized = $this->normalizedCounts($counts);
        if ($this->isLargeBatch($normalized)) {
            return sprintf(
                'APPLY LARGE SYNC N%d U%d D%d R%d %s',
                $normalized['New'],
                $normalized['Update'],
                $normalized['Deactivate'],
                $normalized['Reactivate'],
                $prefix
            );
        }

        return $normalized['Deactivate'] > 0
            ? sprintf('APPLY SYNC DEACTIVATE %d %s', $normalized['Deactivate'], $prefix)
            : 'APPLY SYNC ' . $prefix;
    }

    /** @param array{New:int,Update:int,Deactivate:int,Reactivate:int} $counts */
    public function isLargeBatch(array $counts): bool
    {
        $counts = $this->normalizedCounts($counts);
        return $counts['New'] > $this->warnNew
            || $counts['Update'] > $this->warnUpdate
            || $counts['Reactivate'] > $this->warnReactivate
            || array_sum($counts) > $this->warnTotal;
    }

    /** @param array{New:int,Update:int,Deactivate:int,Reactivate:int} $counts */
    public function assertWithinHardLimits(array $counts): void
    {
        $counts = $this->normalizedCounts($counts);
        if ($counts['Deactivate'] > $this->maxDeactivate) {
            throw new RuntimeException('SYNC_OPERATIONAL_DEACTIVATE_LIMIT_EXCEEDED');
        }
    }

    /** @param array<string, int> $counts @return array{New:int,Update:int,Deactivate:int,Reactivate:int} */
    private function normalizedCounts(array $counts): array
    {
        $normalized = [];
        foreach (['New', 'Update', 'Deactivate', 'Reactivate'] as $action) {
            $value = $counts[$action] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new RuntimeException('SYNC_OPERATIONAL_COUNTS_INVALID');
            }
            $normalized[$action] = $value;
        }
        return $normalized;
    }
}
