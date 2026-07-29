<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

interface UserMfaLoginFinalizerInterface
{
    /** @return array<string, mixed> Opaque compensation handle; no cookie is emitted here. */
    public function prepare(string $userId, string $correlationId): array;

    /** @param array<string, mixed> $handle */
    public function compensate(array $handle): void;
}
