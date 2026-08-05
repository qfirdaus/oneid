<?php

namespace OneId\App\Admin;

final class ActiveSessionRevocationConfig
{
    /** @return list<string> */
    public static function pilotStates(): array
    {
        $raw = strtolower((string) \oneid_config('ONEID_ACTIVE_SESSION_REVOCATION_PILOT_STATES', 'due,expired'));
        $states = array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)))));
        return $states !== [] && array_diff($states, ['due', 'expired']) === [] ? $states : [];
    }

    public static function enabled(): bool
    {
        return filter_var(\oneid_config('ONEID_ACTIVE_SESSION_REVOCATION_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN)
            && self::pilotStates() !== []
            && !filter_var(\oneid_config('ONEID_ACTIVE_SESSION_REVOCATION_ALLOW_ADMIN_TARGET', 'false'), FILTER_VALIDATE_BOOLEAN)
            && !filter_var(\oneid_config('ONEID_ACTIVE_SESSION_REVOCATION_ALLOW_REVOKE_ALL', 'false'), FILTER_VALIDATE_BOOLEAN);
    }
}
