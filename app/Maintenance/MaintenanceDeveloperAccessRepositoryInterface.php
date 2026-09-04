<?php

declare(strict_types=1);

namespace OneId\App\Maintenance;

interface MaintenanceDeveloperAccessRepositoryInterface
{
    /** @return array{available:bool,tables:array<string,bool>} */
    public function schemaStatus(): array;

    public function transactional(callable $operation): mixed;

    /** @return list<array<string,mixed>> */
    public function searchCandidates(string $query): array;

    /** @return list<array<string,mixed>> */
    public function listGrants(string $query): array;

    /** @return array<string,mixed>|null */
    public function account(string $userId, bool $forUpdate = false): ?array;

    /** @return array<string,mixed>|null */
    public function activeGrant(string $userId, bool $forUpdate = false): ?array;

    /** @return array<string,mixed>|null */
    public function grant(int $grantId, bool $forUpdate = false): ?array;

    /** @param array<string,mixed> $grant */
    public function insertGrant(array $grant): int;

    public function revokeGrantVersioned(
        int $grantId,
        int $expectedVersion,
        string $revokedBy,
        string $revokedAtUtc,
        string $reason
    ): int;

    public function expireGrantVersioned(int $grantId, int $expectedVersion, string $expiredAtUtc): int;

    /** @param array<string,mixed> $event */
    public function recordHistory(array $event): int;
}
