<?php

namespace OneId\Tests\Support\Sync;

use OneId\App\Sync\Contracts\SyncPolicyInterface;

final class LegacySyncPolicyAdapter implements SyncPolicyInterface
{
    /** @param list<string> $excludedUserIds */
    public function __construct(private array $excludedUserIds = ['10'])
    {
    }

    public function excludedUserIds(): array
    {
        return $this->excludedUserIds;
    }

    public function categoryIdFor(string $sourceCategory): int
    {
        return match ($sourceCategory) {
            'Akademik' => 2,
            'Pentadbiran' => 3,
            'Pelajar', 'PelajarPelajar' => 10,
            'PentadbiranPelajar' => 11,
            'AkademikPelajar' => 12,
            default => 0,
        };
    }
}
