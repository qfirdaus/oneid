<?php

namespace OneId\App\Sync;

use RuntimeException;

/** Strict, fail-closed limits for the first controlled pilot. */
final class SyncPilotConfig
{
    private function __construct(
        public readonly bool $enabled,
        public readonly int $newLimit,
        public readonly int $updateLimit
    ) {
    }

    public static function fromEnvironment(): self
    {
        return self::fromValues(
            (string) oneid_config('ONEID_SYNC_PILOT_ENABLED', 'false'),
            (string) oneid_config('ONEID_SYNC_PILOT_NEW_LIMIT', '2'),
            (string) oneid_config('ONEID_SYNC_PILOT_UPDATE_LIMIT', '1'),
            (string) oneid_config('ONEID_SYNC_PILOT_DEACTIVATE_LIMIT', '0'),
            (string) oneid_config('ONEID_SYNC_PILOT_REACTIVATE_LIMIT', '0')
        );
    }

    public static function fromValues(
        string $enabled,
        string $newLimit,
        string $updateLimit,
        string $deactivateLimit,
        string $reactivateLimit
    ): self {
        if (!in_array($enabled, ['false', 'true'], true)) {
            throw new RuntimeException('SYNC_PILOT_FLAG_INVALID');
        }
        foreach ([$newLimit, $updateLimit, $deactivateLimit, $reactivateLimit] as $limit) {
            if (preg_match('/^(0|[1-9][0-9]?)$/', $limit) !== 1) {
                throw new RuntimeException('SYNC_PILOT_LIMIT_INVALID');
            }
        }

        $new = (int) $newLimit;
        $update = (int) $updateLimit;
        if ($deactivateLimit !== '0' || $reactivateLimit !== '0') {
            throw new RuntimeException('SYNC_PILOT_DESTRUCTIVE_ACTION_FORBIDDEN');
        }
        if ($enabled === 'true' && ($new !== 2 || $update !== 1)) {
            throw new RuntimeException('SYNC_PILOT_SCOPE_INVALID');
        }

        return new self($enabled === 'true', $new, $update);
    }
}
