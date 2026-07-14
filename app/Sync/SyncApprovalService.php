<?php

namespace OneId\App\Sync;

use OneId\App\Sync\Contracts\SyncApprovalStoreInterface;
use OneId\App\Sync\Contracts\SyncPlanApprovalGateInterface;
use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\DTO\SyncApprovalReceipt;
use OneId\App\Sync\DTO\SyncPlan;
use RuntimeException;

/** Dormant S4B one-time approval service. Contains no persistence writer. */
final class SyncApprovalService implements SyncPlanApprovalGateInterface
{
    public function __construct(
        private SyncApprovalStoreInterface $store,
        private SyncPlanFingerprinter $fingerprinter,
        private int $ttlSeconds = 300
    ) {
        if ($this->ttlSeconds < 1 || $this->ttlSeconds > 300) {
            throw new RuntimeException('SYNC_APPROVAL_TTL_INVALID');
        }
    }

    public function issue(
        string $adminId,
        SyncPlan $plan,
        int $acceptedBaseline,
        ?int $now = null
    ): SyncApprovalReceipt {
        $adminId = trim($adminId);
        if ($adminId === '') {
            throw new RuntimeException('SYNC_APPROVAL_ADMIN_INVALID');
        }
        if ($plan->sourceRows < 1 || $acceptedBaseline < 1) {
            throw new RuntimeException('SYNC_APPROVAL_BASELINE_INVALID');
        }

        $issuedAt = $now ?? time();
        $approval = new SyncApproval(
            bin2hex(random_bytes(32)),
            bin2hex(random_bytes(8)),
            $adminId,
            $this->fingerprinter->fingerprint($plan),
            $plan->legacyCounts(),
            $plan->sourceRows,
            $acceptedBaseline,
            $issuedAt,
            $issuedAt + $this->ttlSeconds
        );
        $this->store->save($approval);

        return new SyncApprovalReceipt(
            $approval->approvalId,
            $approval->correlationId,
            $approval->planFingerprint,
            $approval->counts,
            $approval->sourceRows,
            $approval->issuedAt,
            $approval->expiresAt
        );
    }

    /**
     * Consume first, then validate. Any invalid attempt burns the approval and
     * therefore cannot be retried or raced as a second writer authorization.
     */
    public function consumeAndValidate(
        string $approvalId,
        string $adminId,
        SyncPlan $currentPlan,
        ?int $now = null
    ): SyncApproval {
        if (preg_match('/^[a-f0-9]{64}$/', $approvalId) !== 1) {
            throw new RuntimeException('SYNC_APPROVAL_INVALID');
        }

        $approval = $this->store->consume($approvalId);
        if ($approval === null) {
            throw new RuntimeException('SYNC_APPROVAL_NOT_AVAILABLE');
        }

        $currentTime = $now ?? time();
        if ($currentTime >= $approval->expiresAt) {
            throw new RuntimeException('SYNC_APPROVAL_EXPIRED');
        }
        if (!hash_equals($approval->adminId, trim($adminId))) {
            throw new RuntimeException('SYNC_APPROVAL_ADMIN_MISMATCH');
        }

        $fingerprint = $this->fingerprinter->fingerprint($currentPlan);
        if (!hash_equals($approval->planFingerprint, $fingerprint)
            || $approval->counts !== $currentPlan->legacyCounts()
            || $approval->sourceRows !== $currentPlan->sourceRows
        ) {
            throw new RuntimeException('SYNC_APPROVAL_PLAN_MISMATCH');
        }

        return $approval;
    }
}
