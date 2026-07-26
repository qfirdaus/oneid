<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdRejectedLogoutState
{
    public const ID_TOKEN_SESSION_KEY = 'oneid_mydid_rejected_id_token';
    public const REJECTED_AT_SESSION_KEY = 'oneid_mydid_rejected_at';
    public const TTL_SECONDS = 300;

    /** @param array<string,mixed> $session */
    public static function retain(array &$session, string $idToken, int $now): void
    {
        if (
            $now < 1
            || strlen($idToken) > 8192
            || substr_count($idToken, '.') !== 2
            || preg_match('/^[A-Za-z0-9._-]+$/D', $idToken) !== 1
        ) {
            throw new MyDigitalIdConfigurationException('MYDID_REJECTED_LOGOUT_STATE_INVALID');
        }
        $session[self::ID_TOKEN_SESSION_KEY] = $idToken;
        $session[self::REJECTED_AT_SESSION_KEY] = $now;
    }

    /** @param array<string,mixed> $session */
    public static function isAvailable(array $session, int $now): bool
    {
        $idToken = $session[self::ID_TOKEN_SESSION_KEY] ?? null;
        $rejectedAt = $session[self::REJECTED_AT_SESSION_KEY] ?? null;
        return is_string($idToken)
            && strlen($idToken) <= 8192
            && substr_count($idToken, '.') === 2
            && preg_match('/^[A-Za-z0-9._-]+$/D', $idToken) === 1
            && is_int($rejectedAt)
            && $rejectedAt <= $now
            && ($now - $rejectedAt) <= self::TTL_SECONDS;
    }

    /** @param array<string,mixed> $session */
    public static function consume(array &$session, int $now): ?string
    {
        $available = self::isAvailable($session, $now);
        $idToken = $session[self::ID_TOKEN_SESSION_KEY] ?? null;
        unset(
            $session[self::ID_TOKEN_SESSION_KEY],
            $session[self::REJECTED_AT_SESSION_KEY]
        );
        return $available && is_string($idToken) ? $idToken : null;
    }

    /** @param array<string,mixed> $session */
    public static function clear(array &$session): void
    {
        unset(
            $session[self::ID_TOKEN_SESSION_KEY],
            $session[self::REJECTED_AT_SESSION_KEY]
        );
    }
}
