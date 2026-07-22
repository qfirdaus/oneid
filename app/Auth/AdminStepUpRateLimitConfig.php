<?php

declare(strict_types=1);

namespace OneId\App\Auth;

use InvalidArgumentException;

final class AdminStepUpRateLimitConfig
{
    public function __construct(
        public readonly int $adminHourly,
        public readonly int $adminDaily,
        public readonly int $sessionHourly,
        public readonly int $ipHourly
    ) {
        if ($adminHourly < 1 || $adminHourly > 30
            || $adminDaily < $adminHourly || $adminDaily > 100
            || $sessionHourly < 1 || $sessionHourly > 30
            || $ipHourly < $sessionHourly || $ipHourly > 200
        ) {
            throw new InvalidArgumentException('STEP_UP_RATE_LIMIT_CONFIGURATION_INVALID');
        }
    }

    public static function fromRuntime(): self
    {
        return new self(
            self::runtimeInt('ONEID_STEP_UP_EMAIL_ADMIN_HOURLY_LIMIT', 10),
            self::runtimeInt('ONEID_STEP_UP_EMAIL_ADMIN_DAILY_LIMIT', 30),
            self::runtimeInt('ONEID_STEP_UP_EMAIL_SESSION_HOURLY_LIMIT', 10),
            self::runtimeInt('ONEID_STEP_UP_EMAIL_IP_HOURLY_LIMIT', 50)
        );
    }

    /** @param array<string, mixed> $stats */
    public function exceeded(array $stats): bool
    {
        return (int) ($stats['admin_hour'] ?? 0) >= $this->adminHourly
            || (int) ($stats['admin_day'] ?? 0) >= $this->adminDaily
            || (int) ($stats['session_hour'] ?? 0) >= $this->sessionHourly
            || (int) ($stats['ip_hour'] ?? 0) >= $this->ipHourly;
    }

    private static function runtimeInt(string $key, int $fallback): int
    {
        $value = function_exists('oneid_config') ? \oneid_config($key, (string) $fallback) : $fallback;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', trim($value)) !== 1) {
            throw new InvalidArgumentException('STEP_UP_RATE_LIMIT_CONFIGURATION_INVALID');
        }
        return (int) trim($value);
    }
}
