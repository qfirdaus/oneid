<?php

namespace OneId\App\Sync\DTO;

final class SyncApprovalReceipt
{
    /** @param array{New:int,Update:int,Deactivate:int,Reactivate:int} $counts */
    public function __construct(
        public readonly string $approvalId,
        public readonly string $correlationId,
        public readonly string $planFingerprint,
        public readonly array $counts,
        public readonly int $sourceRows,
        public readonly int $issuedAt,
        public readonly int $expiresAt
    ) {
    }

    /** Safe response projection; deliberately excludes the bound admin ID. */
    public function toSafeArray(): array
    {
        return [
            'approval_id' => $this->approvalId,
            'correlation_id' => $this->correlationId,
            'plan_fingerprint' => $this->planFingerprint,
            'counts' => $this->counts,
            'source_rows' => $this->sourceRows,
            'issued_at' => $this->issuedAt,
            'expires_at' => $this->expiresAt,
        ];
    }
}
