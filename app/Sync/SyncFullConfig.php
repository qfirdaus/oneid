<?php

namespace OneId\App\Sync;

use OneId\App\Sync\DTO\SyncPlan;
use RuntimeException;

/** Exact, owner-approved boundary for one full synchronization plan. */
final class SyncFullConfig
{
    private function __construct(
        public readonly bool $enabled,
        public readonly array $expectedCounts,
        public readonly string $expectedPlanHash
    ) {
    }

    public static function fromEnvironment(): self
    {
        return self::fromValues(
            (string) oneid_config('ONEID_SYNC_FULL_ENABLED', 'false'),
            (string) oneid_config('ONEID_SYNC_FULL_EXPECTED_NEW', '0'),
            (string) oneid_config('ONEID_SYNC_FULL_EXPECTED_UPDATE', '0'),
            (string) oneid_config('ONEID_SYNC_FULL_EXPECTED_DEACTIVATE', '0'),
            (string) oneid_config('ONEID_SYNC_FULL_EXPECTED_REACTIVATE', '0'),
            (string) oneid_config('ONEID_SYNC_FULL_EXPECTED_PLAN_HASH', '')
        );
    }

    public static function fromValues(
        string $enabled,
        string $new,
        string $update,
        string $deactivate,
        string $reactivate,
        string $planHash
    ): self {
        if (!in_array($enabled, ['false', 'true'], true)) {
            throw new RuntimeException('SYNC_FULL_FLAG_INVALID');
        }
        foreach ([$new, $update, $deactivate, $reactivate] as $count) {
            if (preg_match('/^(0|[1-9][0-9]{0,4})$/', $count) !== 1) {
                throw new RuntimeException('SYNC_FULL_COUNT_INVALID');
            }
        }

        $hash = strtolower(trim($planHash));
        if ($enabled === 'true' && preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw new RuntimeException('SYNC_FULL_PLAN_HASH_INVALID');
        }

        $counts = [
            'New' => (int) $new,
            'Update' => (int) $update,
            'Deactivate' => (int) $deactivate,
            'Reactivate' => (int) $reactivate,
        ];
        if ($enabled === 'true' && array_sum($counts) < 1) {
            throw new RuntimeException('SYNC_FULL_EMPTY_SCOPE');
        }

        return new self($enabled === 'true', $counts, $hash);
    }

    public function assertPlan(SyncPlan $plan, SyncPlanFingerprinter $fingerprinter): void
    {
        if (!$this->enabled) {
            throw new RuntimeException('SYNC_FULL_DISABLED');
        }
        if ($plan->legacyCounts() !== $this->expectedCounts) {
            throw new RuntimeException('SYNC_FULL_COUNT_MISMATCH');
        }
        if (!hash_equals($this->expectedPlanHash, $fingerprinter->fingerprint($plan))) {
            throw new RuntimeException('SYNC_FULL_PLAN_MISMATCH');
        }
    }

    public function confirmationText(): string
    {
        return 'FULL SYNC ' . strtoupper(substr($this->expectedPlanHash, 0, 12));
    }
}
