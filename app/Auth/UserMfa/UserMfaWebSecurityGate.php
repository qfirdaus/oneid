<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use RuntimeException;

final class UserMfaWebSecurityGate
{
    public static function assertStateChangingRequest(
        string $method,
        string $sessionCsrfToken,
        string $submittedCsrfToken
    ): void {
        if (strtoupper($method) !== 'POST'
            || strlen($sessionCsrfToken) < 32
            || strlen($submittedCsrfToken) < 32
            || !hash_equals($sessionCsrfToken, $submittedCsrfToken)
        ) {
            throw new RuntimeException('USER_MFA_CSRF_REJECTED');
        }
    }

    public static function assertSessionRotated(string $preAuthenticationId, string $authenticatedId): void
    {
        if ($preAuthenticationId === ''
            || $authenticatedId === ''
            || hash_equals($preAuthenticationId, $authenticatedId)
        ) {
            throw new RuntimeException('USER_MFA_SESSION_FIXATION_REJECTED');
        }
    }
}
