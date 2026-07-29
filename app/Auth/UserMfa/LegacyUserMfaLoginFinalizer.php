<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use RuntimeException;

final class LegacyUserMfaLoginFinalizer implements UserMfaLoginFinalizerInterface
{
    public function __construct(
        private readonly object $operation,
        private readonly string $deviceInfo
    ) {
    }

    public function prepare(string $userId, string $correlationId): array
    {
        if (!method_exists($this->operation, 'add_new_token')) {
            throw new RuntimeException('USER_MFA_TOKEN_WRITER_UNAVAILABLE');
        }
        $token = \oneid_generate_sso_token();
        if ((int) $this->operation->add_new_token($token, $userId, $this->deviceInfo) !== 1) {
            unset($token);
            throw new RuntimeException('USER_MFA_TOKEN_CREATE_FAILED');
        }
        return ['user_id' => $userId, 'token' => $token];
    }

    public function compensate(array $handle): void
    {
        $userId = (string) ($handle['user_id'] ?? '');
        $token = (string) ($handle['token'] ?? '');
        if ($userId !== '' && $token !== ''
            && method_exists($this->operation, 'update_specific_token_status')
        ) {
            $this->operation->update_specific_token_status($userId, $token, 0);
        }
    }
}
