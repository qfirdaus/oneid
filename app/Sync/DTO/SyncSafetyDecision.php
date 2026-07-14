<?php

namespace OneId\App\Sync\DTO;

final class SyncSafetyDecision
{
    /** @param list<string> $blockingCodes @param list<string> $warnings */
    public function __construct(
        public readonly bool $allowed,
        public readonly array $blockingCodes,
        public readonly array $warnings,
        public readonly array $metrics
    ) {
    }
}
