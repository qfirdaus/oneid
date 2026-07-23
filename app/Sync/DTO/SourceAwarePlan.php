<?php
declare(strict_types=1);
namespace OneId\App\Sync\DTO;

final class SourceAwarePlan
{
    /** @param list<array<string,mixed>> $membershipActions
     * @param list<array<string,mixed>> $accountActions
     * @param array<string,mixed> $metrics
     * @param list<string> $blockingCodes */
    public function __construct(
        public readonly bool $allowed,
        public readonly array $membershipActions,
        public readonly array $accountActions,
        public readonly array $metrics,
        public readonly array $blockingCodes
    ) {}

    /** @return array<string,mixed> */
    public function safeProjection(): array
    {
        $safe = static function (array $action): array {
            $id = (string) ($action['u_id'] ?? $action['external_user_id'] ?? '');
            return [
                'action' => (string) ($action['action'] ?? ''),
                'source_code' => $action['source_code'] ?? null,
                'identity_digest' => hash('sha256', $id),
            ];
        };
        return [
            'mode' => 'preview',
            'can_apply' => false,
            'allowed' => $this->allowed,
            'blocking_codes' => $this->blockingCodes,
            'metrics' => $this->metrics,
            'membership_actions' => array_map($safe, $this->membershipActions),
            'account_actions' => array_map($safe, $this->accountActions),
            'mutation_statements' => 0,
        ];
    }
}
