<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use RuntimeException;

final class UserMfaOperationalModeResolver
{
    private const CEILING = [
        'OFF' => ['OFF'],
        'ENROLLMENT' => ['OFF', 'ENROLLMENT'],
        'PILOT_ENFORCED' => ['OFF', 'ENROLLMENT', 'PILOT_ENFORCED', 'EMERGENCY_BYPASS'],
        'ENFORCED' => UserLoginMfaPolicy::MODES,
    ];

    /**
     * @param array<string,mixed>|null $bypass
     * @return array{stored_mode:string,effective_mode:string,restore_mode:?string,bypass_active:bool,bypass_expired:bool,expires_at:?string,request_id:?int}
     */
    public function resolve(string $storedMode, ?array $bypass, int $databaseEpoch, ?int $storedVersion = null): array
    {
        $storedMode = strtoupper(trim($storedMode));
        if (!in_array($storedMode, UserLoginMfaPolicy::MODES, true)) {
            throw new RuntimeException('USER_MFA_POLICY_MODE_INVALID');
        }
        $result = [
            'stored_mode' => $storedMode,
            'effective_mode' => $storedMode,
            'restore_mode' => null,
            'bypass_active' => false,
            'bypass_expired' => false,
            'expires_at' => null,
            'request_id' => null,
        ];
        if ($storedMode !== 'EMERGENCY_BYPASS') {
            return $result;
        }
        if (!is_array($bypass)
            || strtoupper((string) ($bypass['requested_mode'] ?? '')) !== 'EMERGENCY_BYPASS'
            || strtoupper((string) ($bypass['request_status'] ?? '')) !== 'ACTIVE'
            || ($storedVersion !== null && (int) ($bypass['applied_policy_version'] ?? 0) !== $storedVersion)
        ) {
            // Missing operational evidence can never create an indefinite bypass.
            $result['effective_mode'] = 'ENFORCED';
            $result['bypass_expired'] = true;
            return $result;
        }
        $restore = strtoupper(trim((string) ($bypass['restore_mode'] ?? '')));
        $starts = isset($bypass['starts_at_epoch']) ? (int)$bypass['starts_at_epoch'] : strtotime((string) ($bypass['starts_at'] ?? '') . ' UTC');
        $expires = isset($bypass['expires_at_epoch']) ? (int)$bypass['expires_at_epoch'] : strtotime((string) ($bypass['expires_at'] ?? '') . ' UTC');
        if (!in_array($restore, ['OFF', 'ENROLLMENT', 'PILOT_ENFORCED', 'ENFORCED'], true)
            || $starts === false || $expires === false || $expires <= $starts
            || ($expires - $starts) > 28800
        ) {
            $result['effective_mode'] = 'ENFORCED';
            $result['bypass_expired'] = true;
            return $result;
        }
        $result['restore_mode'] = $restore;
        $result['expires_at'] = (string) $bypass['expires_at'];
        $result['request_id'] = isset($bypass['request_id']) ? (int) $bypass['request_id'] : null;
        if ($databaseEpoch < $starts || $databaseEpoch >= $expires) {
            $result['effective_mode'] = $restore;
            $result['bypass_expired'] = $databaseEpoch >= $expires;
            return $result;
        }
        $result['bypass_active'] = true;
        return $result;
    }

    public function assertWithinRuntimeCeiling(string $effectiveMode, string $runtimeCeiling): void
    {
        $effectiveMode = strtoupper(trim($effectiveMode));
        $runtimeCeiling = strtoupper(trim($runtimeCeiling));
        if (!isset(self::CEILING[$runtimeCeiling])
            || !in_array($effectiveMode, self::CEILING[$runtimeCeiling], true)
        ) {
            throw new RuntimeException('USER_MFA_RUNTIME_DATABASE_POLICY_MISMATCH');
        }
    }
}
