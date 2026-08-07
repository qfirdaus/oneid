<?php

declare(strict_types=1);

namespace OneId\App\Auth;

use InvalidArgumentException;

final class UserSessionTimeoutPolicy
{
    public const DEFAULT_IDLE_SECONDS = 1800;
    public const ABSOLUTE_SECONDS = 28800;

    private const ALLOWED_HOURS = ['0.5', '1', '2', '12', '24', '48', '72', '168'];

    public static function idleSeconds(mixed $hours): int
    {
        if (!is_scalar($hours)) {
            throw new InvalidArgumentException('User session timeout must be scalar.');
        }

        $normalized = trim((string) $hours);
        if (!in_array($normalized, self::ALLOWED_HOURS, true)) {
            throw new InvalidArgumentException('User session timeout is not an allowed Administrator setting.');
        }

        return (int) round((float) $normalized * 3600);
    }

    public static function isExpired(
        int $now,
        int $createdAt,
        int $lastActivity,
        int $idleSeconds
    ): bool {
        if ($idleSeconds < 1) {
            throw new InvalidArgumentException('User session idle timeout must be positive.');
        }

        return ($now - $lastActivity) > $idleSeconds
            || ($now - $createdAt) > self::ABSOLUTE_SECONDS;
    }
}
