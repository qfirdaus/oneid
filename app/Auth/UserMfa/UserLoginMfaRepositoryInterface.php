<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

interface UserLoginMfaRepositoryInterface
{
    /** @return array<string, mixed>|false */
    public function policyForUpdate(): array|false;

    /** @return array<string, mixed>|false */
    public function activeFactorForUpdate(string $userId, string $factorType): array|false;

    /** @return array<string, mixed>|false */
    public function pendingLoginForUpdate(string $transactionId): array|false;

    /** @return array<string, mixed>|false */
    public function challengeForUpdate(string $challengeId): array|false;

    /** @param array<string, mixed> $entry */
    public function createPendingLogin(array $entry): int;

    /** @param array<string, mixed> $entry */
    public function createChallenge(array $entry): int;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;
}
