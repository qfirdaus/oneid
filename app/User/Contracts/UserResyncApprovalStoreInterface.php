<?php

namespace OneId\App\User\Contracts;

interface UserResyncApprovalStoreInterface
{
    /** @param array<string, mixed> $approval */
    public function save(array $approval): void;

    /** @return array<string, mixed>|null */
    public function consume(string $approvalId): ?array;
}
