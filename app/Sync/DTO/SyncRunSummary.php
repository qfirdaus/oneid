<?php

namespace OneId\App\Sync\DTO;

final class SyncRunSummary
{
    /** @param array<string, mixed> $header */
    public function __construct(
        public readonly int $headerId,
        public readonly int $new,
        public readonly int $updated,
        public readonly int $deactivated,
        public readonly int $reactivated,
        public readonly array $header = []
    ) {
    }

    /** @return array<string, mixed> */
    public function toLegacyArray(): array
    {
        return array_merge($this->header, [
            'ext_head_id' => $this->headerId,
            'Deactivate' => $this->deactivated,
            'Update' => $this->updated,
            'New' => $this->new,
            'Reactivate' => $this->reactivated,
        ]);
    }
}
