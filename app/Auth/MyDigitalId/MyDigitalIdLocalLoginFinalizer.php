<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

use Throwable;

final class MyDigitalIdLocalLoginFinalizer implements MyDigitalIdLocalLoginFinalizerInterface
{
    /**
     * @param object $operation Legacy OneID database operation.
     * @param null|callable():string $tokenGenerator
     * @param null|callable(string):void $cookieSetter
     * @param null|callable(array):void $sessionEstablisher
     */
    public function __construct(
        private readonly object $operation,
        private readonly mixed $tokenGenerator = null,
        private readonly mixed $cookieSetter = null,
        private readonly mixed $sessionEstablisher = null
    ) {
    }

    public function finalize(array $user, string $deviceInfo): void
    {
        $userId = (string) ($user['u_id'] ?? '');
        if (
            preg_match('/^[A-Za-z0-9._@-]{1,20}$/D', $userId) !== 1
            || (int) ($user['avail_status'] ?? 0) !== 1
        ) {
            throw new MyDigitalIdPersistenceException('MYDID_FINALIZE_USER_INVALID');
        }
        if (strlen($deviceInfo) > 255) {
            throw new MyDigitalIdPersistenceException('MYDID_DEVICE_INFO_INVALID');
        }
        $configuration = $this->operation->get_system_config();
        if (
            !is_array($configuration)
            || !in_array((int) ($configuration['multi_session'] ?? -1), [0, 1], true)
        ) {
            throw new MyDigitalIdPersistenceException('MYDID_SSO_POLICY_INVALID');
        }

        $generate = $this->tokenGenerator
            ?? static fn(): string => \oneid_generate_sso_token();
        $setCookie = $this->cookieSetter
            ?? static fn(string $token): mixed => \oneid_set_sso_cookie($token);
        $establishSession = $this->sessionEstablisher
            ?? static fn(array $account): mixed => \oneid_establish_authenticated_session($account);
        $token = $generate();
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            throw new MyDigitalIdPersistenceException('MYDID_LOCAL_TOKEN_INVALID');
        }

        $inserted = false;
        try {
            if ((int) $configuration['multi_session'] === 0) {
                $this->operation->update_whole_token_status($userId, 0);
            }
            if ((int) $this->operation->add_new_token($token, $userId, $deviceInfo) !== 1) {
                throw new MyDigitalIdPersistenceException('MYDID_LOCAL_TOKEN_NOT_CREATED');
            }
            $inserted = true;
            $setCookie($token);
            $establishSession($user);
            $_SESSION['auth_method'] = 'mydigitalid';
        } catch (Throwable $exception) {
            if ($inserted) {
                try {
                    $this->operation->update_specific_token_status($userId, $token, 0);
                } catch (Throwable) {
                    // Preserve the original failure; token expiry policy remains a backstop.
                }
            }
            if (function_exists('oneid_clear_local_authenticated_session')) {
                \oneid_clear_local_authenticated_session();
            } else {
                $_SESSION = [];
            }
            if ($exception instanceof MyDigitalIdPersistenceException) {
                throw $exception;
            }
            throw new MyDigitalIdPersistenceException('MYDID_LOCAL_LOGIN_FINALIZE_FAILED');
        } finally {
            $token = '';
        }
    }
}
