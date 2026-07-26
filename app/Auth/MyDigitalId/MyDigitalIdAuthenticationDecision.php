<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdAuthenticationDecision
{
    /** @param null|array<string,mixed> $user */
    public function __construct(
        public readonly bool $allowed,
        public readonly string $reason,
        public readonly ?array $user,
        public readonly ?int $identityId
    ) {
        if (
            !in_array($reason, [
                'MYDID_LOGIN_SUCCESS',
                'MYDID_USER_NOT_FOUND',
                'MYDID_USER_INACTIVE',
                'MYDID_IDENTITY_AMBIGUOUS',
                'MYDID_IDENTITY_MISMATCH',
            ], true)
            || ($allowed && ($reason !== 'MYDID_LOGIN_SUCCESS' || $user === null || $identityId === null))
            || (!$allowed && ($user !== null || $identityId !== null))
        ) {
            throw new MyDigitalIdPersistenceException('MYDID_DECISION_INVALID');
        }
    }
}
