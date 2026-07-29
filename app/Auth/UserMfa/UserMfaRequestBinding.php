<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use InvalidArgumentException;

final class UserMfaRequestBinding
{
    /** @return array{session_hash:string,browser_digest:string,ip_address:string} */
    public static function fromRequest(string $sessionId, string $userAgent, string $ipAddress): array
    {
        if ($sessionId === '' || strlen($sessionId) > 256) {
            throw new InvalidArgumentException('USER_MFA_SESSION_INVALID');
        }
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('USER_MFA_IP_INVALID');
        }
        return [
            'session_hash' => hash('sha256', $sessionId),
            'browser_digest' => hash('sha256', substr($userAgent, 0, 1000)),
            'ip_address' => $ipAddress,
        ];
    }
}
