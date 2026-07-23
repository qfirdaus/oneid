<?php
declare(strict_types=1);
namespace OneId\App\Sync\DTO;

final class OdlFullPlan
{
    /** @param list<array<string,mixed>> $newActions */
    public function __construct(
        public readonly array $newActions,
        public readonly int $sourceRows,
        public readonly int $keepMemberships
    ) {}

    /** @return array<string,int> */
    public function counts(): array
    {
        return [
            'New' => count($this->newActions),
            'Keep' => $this->keepMemberships,
            'Update' => 0,
            'Deactivate' => 0,
            'Reactivate' => 0,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function safeActions(): array
    {
        return array_map(static fn(array $action): array => [
            'action' => 'NEW',
            'uid_digest' => hash('sha256', (string) $action['u_id']),
            'category_id' => 10,
            'change_hash' => (string) $action['change_hash'],
        ], $this->newActions);
    }

    public function planHash(): string
    {
        return hash('sha256', json_encode([
            'source' => 'STUDENT_ODL_PG',
            'source_rows' => $this->sourceRows,
            'counts' => $this->counts(),
            'actions' => $this->safeActions(),
        ], JSON_UNESCAPED_SLASHES) ?: '');
    }
}
