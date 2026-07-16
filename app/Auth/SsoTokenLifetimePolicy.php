<?php

namespace OneId\App\Auth;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class SsoTokenLifetimePolicy
{
    public const ACTIVE = 'active';
    public const LEGACY_REFRESH = 'legacy_refresh';
    public const EXPIRED = 'expired';
    public const FUTURE_INVALID = 'future_invalid';
    public const LEGACY_REFRESH_SECONDS = 3600;

    public function evaluate(string $issuedAt, string $now, float $lifetimeHours): array
    {
        if ($lifetimeHours <= 0) {
            throw new InvalidArgumentException('Token lifetime must be positive.');
        }
        $timezone = new DateTimeZone(date_default_timezone_get());
        $issued = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $issuedAt, $timezone);
        $current = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $now, $timezone);
        if (!$issued || !$current) {
            throw new InvalidArgumentException('Token timestamps must use Y-m-d H:i:s.');
        }
        $ageSeconds = $current->getTimestamp() - $issued->getTimestamp();
        $lifetimeSeconds = (int) round($lifetimeHours * 3600);
        if ($ageSeconds < 0) {
            $state = self::FUTURE_INVALID;
        } elseif ($ageSeconds <= $lifetimeSeconds) {
            $state = self::ACTIVE;
        } elseif ($ageSeconds < $lifetimeSeconds + self::LEGACY_REFRESH_SECONDS) {
            $state = self::LEGACY_REFRESH;
        } else {
            $state = self::EXPIRED;
        }
        return ['state' => $state, 'age_seconds' => $ageSeconds, 'lifetime_seconds' => $lifetimeSeconds];
    }
}
