<?php

namespace OneId\App\Sync\DTO;

final class SyncApproval
{
    /** @param array{New:int,Update:int,Deactivate:int,Reactivate:int} $counts */
    public function __construct(
        public readonly string $approvalId,
        public readonly string $correlationId,
        public readonly string $adminId,
        public readonly string $planFingerprint,
        public readonly array $counts,
        public readonly int $sourceRows,
        public readonly int $acceptedBaseline,
        public readonly int $issuedAt,
        public readonly int $expiresAt
    ) {
    }

    /** @return array<string, mixed> */
    public function toStorageArray(): array
    {
        return [
            'approval_id' => $this->approvalId,
            'correlation_id' => $this->correlationId,
            'admin_id' => $this->adminId,
            'plan_fingerprint' => $this->planFingerprint,
            'counts' => $this->counts,
            'source_rows' => $this->sourceRows,
            'accepted_baseline' => $this->acceptedBaseline,
            'issued_at' => $this->issuedAt,
            'expires_at' => $this->expiresAt,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromStorageArray(array $data): self
    {
        return new self(
            (string) ($data['approval_id'] ?? ''),
            (string) ($data['correlation_id'] ?? ''),
            (string) ($data['admin_id'] ?? ''),
            (string) ($data['plan_fingerprint'] ?? ''),
            is_array($data['counts'] ?? null) ? $data['counts'] : [],
            (int) ($data['source_rows'] ?? 0),
            (int) ($data['accepted_baseline'] ?? 0),
            (int) ($data['issued_at'] ?? 0),
            (int) ($data['expires_at'] ?? 0)
        );
    }
}
