<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdAuthorizationTransactionStore
{
    public const SESSION_KEY = 'oneid_mydid_authorization';
    public const TTL_SECONDS = 300;

    /**
     * @param array<string,mixed> $session
     * @param null|callable(int):string $randomBytes
     */
    public function create(
        array &$session,
        int $now,
        string $returnPath = '/page/dashboard',
        ?callable $randomBytes = null
    ): MyDigitalIdAuthorizationTransaction {
        $randomBytes ??= static fn(int $length): string => random_bytes($length);
        $transaction = new MyDigitalIdAuthorizationTransaction(
            bin2hex($randomBytes(32)),
            bin2hex($randomBytes(32)),
            rtrim(strtr(base64_encode($randomBytes(64)), '+/', '-_'), '='),
            $now,
            $this->safeReturnPath($returnPath)
        );
        $session[self::SESSION_KEY] = $transaction->toSessionValue();

        return $transaction;
    }

    /**
     * Validate and remove the transaction before any token exchange.
     *
     * @param array<string,mixed> $session
     */
    public function consume(array &$session, string $receivedState, int $now): MyDigitalIdAuthorizationTransaction
    {
        $stored = $session[self::SESSION_KEY] ?? null;
        unset($session[self::SESSION_KEY]);

        if (!is_array($stored)) {
            throw new MyDigitalIdConfigurationException('MYDID_AUTH_TRANSACTION_MISSING');
        }

        try {
            $transaction = new MyDigitalIdAuthorizationTransaction(
                is_string($stored['state'] ?? null) ? $stored['state'] : '',
                is_string($stored['nonce'] ?? null) ? $stored['nonce'] : '',
                is_string($stored['code_verifier'] ?? null) ? $stored['code_verifier'] : '',
                is_int($stored['created_at'] ?? null) ? $stored['created_at'] : 0,
                is_string($stored['return_path'] ?? null) ? $stored['return_path'] : ''
            );
        } catch (MyDigitalIdConfigurationException) {
            throw new MyDigitalIdConfigurationException('MYDID_AUTH_TRANSACTION_INVALID');
        }

        if (
            preg_match('/^[a-f0-9]{64}$/D', $receivedState) !== 1
            || !hash_equals($transaction->state, $receivedState)
        ) {
            throw new MyDigitalIdConfigurationException('MYDID_STATE_MISMATCH');
        }
        if ($now < $transaction->createdAt || ($now - $transaction->createdAt) > self::TTL_SECONDS) {
            throw new MyDigitalIdConfigurationException('MYDID_AUTH_TRANSACTION_EXPIRED');
        }

        return $transaction;
    }

    private function safeReturnPath(string $returnPath): string
    {
        return $returnPath === '/page/dashboard' ? $returnPath : '/page/dashboard';
    }
}
