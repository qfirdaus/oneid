<?php

namespace OneId\App\Sync\Contracts;

interface SyncPolicyInterface
{
    /** @return list<string> */
    public function excludedUserIds(): array;

    public function categoryIdFor(string $sourceCategory): int;
}
