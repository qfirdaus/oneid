<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdAccountMatch
{
    /**
     * @param null|array{
     *   u_id:string,u_type:int,avail_status:int,password_change_required:int,
     *   data1:string,normalized_nric:string
     * } $user
     */
    private function __construct(
        public readonly string $status,
        public readonly ?array $user
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function matched(array $row): self
    {
        $userId = (string) ($row['u_id'] ?? '');
        $nric = (string) ($row['normalized_nric'] ?? '');
        if (
            preg_match('/^[A-Za-z0-9._@-]{1,20}$/D', $userId) !== 1
            || preg_match('/^\d{12}$/D', $nric) !== 1
            || (int) ($row['avail_status'] ?? 0) !== 1
        ) {
            throw new MyDigitalIdPersistenceException('MYDID_MATCHED_ACCOUNT_INVALID');
        }
        return new self('MATCHED', [
            'u_id' => $userId,
            'u_type' => (int) ($row['u_type'] ?? 0),
            'avail_status' => 1,
            'password_change_required' => (int) ($row['password_change_required'] ?? 0),
            'data1' => (string) ($row['data1'] ?? ''),
            'normalized_nric' => $nric,
        ]);
    }

    public static function rejected(string $status, ?array $user = null): self
    {
        if (!in_array($status, ['NOT_FOUND', 'INACTIVE', 'AMBIGUOUS'], true)) {
            throw new MyDigitalIdPersistenceException('MYDID_MATCH_STATUS_INVALID');
        }
        return new self($status, $user);
    }
}
