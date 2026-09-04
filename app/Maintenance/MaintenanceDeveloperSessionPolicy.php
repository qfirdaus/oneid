<?php

declare(strict_types=1);

namespace OneId\App\Maintenance;

final class MaintenanceDeveloperSessionPolicy
{
    /**
     * @param array<string,mixed> $session
     * @param array<string,mixed> $serverDecision
     * @return array{allowed:bool,code:string}
     */
    public static function decide(array $session, array $serverDecision, bool $tokenActive): array
    {
        if (($session['login_status'] ?? '') !== 'true'
            || (string) ($session['login_user_type'] ?? '') !== '0'
            || trim((string) ($session['login_user'] ?? '')) === ''
        ) {
            return ['allowed' => false, 'code' => 'MAINTENANCE_DEVELOPER_SESSION_INVALID'];
        }
        $sessionGrant = filter_var($session['oneid_maintenance_developer_grant_id'] ?? null,
            FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $sessionVersion = filter_var($session['oneid_maintenance_developer_grant_version'] ?? null,
            FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($sessionGrant === false || $sessionVersion === false) {
            return ['allowed' => false, 'code' => 'MAINTENANCE_DEVELOPER_SESSION_INVALID'];
        }
        if (!$tokenActive) {
            return ['allowed' => false, 'code' => 'SSO_TOKEN_REVOKED'];
        }
        if (!($serverDecision['allowed'] ?? false)
            || (int) ($serverDecision['grant_id'] ?? 0) !== $sessionGrant
            || (int) ($serverDecision['configuration_version'] ?? 0) !== $sessionVersion
        ) {
            return ['allowed' => false, 'code' => 'MAINTENANCE_ACCESS_REVALIDATION_FAILED'];
        }
        return ['allowed' => true, 'code' => 'MAINTENANCE_DEVELOPER_SESSION_ALLOWED'];
    }
}
