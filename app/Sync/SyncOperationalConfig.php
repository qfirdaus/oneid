<?php

namespace OneId\App\Sync;

use RuntimeException;

/** Persistent safe-mode switch and typed confirmation policy for routine sync. */
final class SyncOperationalConfig
{
    private function __construct(public readonly bool $enabled)
    {
    }

    public static function fromEnvironment(): self
    {
        return self::fromValue((string) oneid_config('ONEID_SYNC_OPERATIONAL_ENABLED', 'false'));
    }

    public static function fromValue(string $enabled): self
    {
        if (!in_array($enabled, ['false', 'true'], true)) {
            throw new RuntimeException('SYNC_OPERATIONAL_FLAG_INVALID');
        }

        return new self($enabled === 'true');
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

        $prefix = strtoupper(substr($planHash, 0, 12));
        $deactivate = max(0, (int) ($counts['Deactivate'] ?? 0));

        return $deactivate > 0
            ? sprintf('APPLY SYNC DEACTIVATE %d %s', $deactivate, $prefix)
            : 'APPLY SYNC ' . $prefix;
    }
}
