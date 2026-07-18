<?php

namespace OneId\App\Admin;

use InvalidArgumentException;

final class SessionHousekeepingPolicy
{
    public const REFRESH_WINDOW_SECONDS = 3600;
    public const BATCH_SIZE = 500;
    public const EXCESSIVE_SESSION_THRESHOLD = 5;

    public static function expiryCutoff(string $now, float $lifetimeHours): string
    {
        if ($lifetimeHours <= 0 || $lifetimeHours > 720) {
            throw new InvalidArgumentException('AS1_TOKEN_LIFETIME_INVALID');
        }

        $timestamp = strtotime($now);
        if ($timestamp === false) {
            throw new InvalidArgumentException('AS1_NOW_INVALID');
        }

        $seconds = (int) round($lifetimeHours * 3600) + self::REFRESH_WINDOW_SECONDS;
        return date('Y-m-d H:i:s', $timestamp - $seconds);
    }

    public static function confirmationPhrase(int $candidateCount): string
    {
        return 'HOUSEKEEP SESSIONS ' . max(0, $candidateCount);
    }
}
