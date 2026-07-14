<?php

namespace OneId\App\Sync\DTO;

final class SyncPlan
{
    /**
     * @param list<array<string, mixed>> $actions
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly array $actions,
        public readonly int $sourceRows,
        public readonly int $discardedInvalid,
        public readonly int $discardedExcluded,
        public readonly array $warnings = []
    ) {
    }

    /** @return array{New:int,Update:int,Deactivate:int,Reactivate:int} */
    public function legacyCounts(): array
    {
        $counts = [
            'New' => 0,
            'Update' => 0,
            'Deactivate' => 0,
            'Reactivate' => 0,
        ];
        foreach ($this->actions as $action) {
            $legacyKey = match ($action['action'] ?? '') {
                'NEW' => 'New',
                'UPDATE' => 'Update',
                'DEACTIVATE' => 'Deactivate',
                'REACTIVATE' => 'Reactivate',
                default => null,
            };
            if ($legacyKey !== null) {
                $counts[$legacyKey]++;
            }
        }
        return $counts;
    }

    /**
     * Redacted representation suitable for parity evidence.
     * Full source rows in actions must never be logged directly.
     *
     * @return list<array<string, mixed>>
     */
    public function safeProjection(): array
    {
        return array_map(static function (array $action): array {
            $userId = (string) ($action['u_id'] ?? '');
            return [
                'action' => (string) ($action['action'] ?? ''),
                'uid_digest' => hash('sha256', $userId),
                'category_id' => $action['category_id'] ?? null,
                'changed_fields' => $action['changed_fields'] ?? null,
                'change_hash' => $action['change_hash'] ?? null,
            ];
        }, $this->actions);
    }

    public function planHash(): string
    {
        $payload = [
            'actions' => $this->safeProjection(),
            'counts' => $this->legacyCounts(),
            'source_rows' => $this->sourceRows,
            'discarded_invalid' => $this->discardedInvalid,
            'discarded_excluded' => $this->discardedExcluded,
            'warnings' => $this->warnings,
        ];

        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_SLASHES));
    }
}
