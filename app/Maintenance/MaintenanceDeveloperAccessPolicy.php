<?php

declare(strict_types=1);

namespace OneId\App\Maintenance;

final class MaintenanceDeveloperAccessPolicy
{
    /**
     * @param array<string,mixed>|null $account
     * @param array<string,mixed>|null $grant
     * @return array{allowed:bool,code:string,grant_id:?int,configuration_version:?int}
     */
    public static function decide(?array $account, ?array $grant, string $effectiveAtUtc): array
    {
        if (!self::validUtc($effectiveAtUtc)) {
            return self::denied('MAINTENANCE_ACCESS_TIME_INVALID');
        }
        if (!is_array($account) || (int) ($account['avail_status'] ?? 0) !== 1) {
            return self::denied('MAINTENANCE_ACCESS_ACCOUNT_INACTIVE');
        }
        if ((int) ($account['u_type'] ?? -1) !== 0) {
            return self::denied('MAINTENANCE_ACCESS_USER_TYPE_FORBIDDEN');
        }
        if (!is_array($grant)) {
            return self::denied('MAINTENANCE_ACCESS_DENIED');
        }
        $status = (string) ($grant['grant_status'] ?? '');
        if ($status === 'REVOKED') {
            return self::denied('MAINTENANCE_ACCESS_REVOKED');
        }
        if ($status === 'EXPIRED') {
            return self::denied('MAINTENANCE_ACCESS_EXPIRED');
        }
        if ($status !== 'ACTIVE') {
            return self::denied('MAINTENANCE_ACCESS_DENIED');
        }
        $from = (string) ($grant['valid_from'] ?? '');
        $until = (string) ($grant['valid_until'] ?? '');
        if (!self::validUtc($from) || !self::validUtc($until)) {
            return self::denied('MAINTENANCE_ACCESS_GRANT_INVALID');
        }
        if ($effectiveAtUtc < $from) {
            return self::denied('MAINTENANCE_ACCESS_NOT_YET_VALID');
        }
        if ($effectiveAtUtc >= $until) {
            return self::denied('MAINTENANCE_ACCESS_EXPIRED');
        }
        $grantId = filter_var($grant['grant_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $version = filter_var($grant['configuration_version'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($grantId === false || $version === false
            || (string) ($grant['u_id'] ?? '') !== (string) ($account['u_id'] ?? '')
        ) {
            return self::denied('MAINTENANCE_ACCESS_GRANT_INVALID');
        }
        return [
            'allowed' => true,
            'code' => 'MAINTENANCE_ACCESS_ALLOWED',
            'grant_id' => $grantId,
            'configuration_version' => $version,
        ];
    }

    /** @return array{allowed:false,code:string,grant_id:null,configuration_version:null} */
    private static function denied(string $code): array
    {
        return ['allowed' => false, 'code' => $code, 'grant_id' => null, 'configuration_version' => null];
    }

    private static function validUtc(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $value, new \DateTimeZone('UTC'));
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d H:i:s.u') === $value;
    }
}
