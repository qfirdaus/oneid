<?php

declare(strict_types=1);

namespace OneId\App\Maintenance;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

final class MaintenanceDeveloperAccessService
{
    private readonly \Closure $clock;

    public function __construct(
        private readonly MaintenanceDeveloperAccessRepositoryInterface $repository,
        ?callable $clock = null
    ) {
        $this->clock = $clock === null
            ? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'))
            : \Closure::fromCallable($clock);
    }

    public function grant(
        string $userId,
        string $validFromUtc,
        string $validUntilUtc,
        string $reason,
        string $reference,
        string $admin,
        string $ip,
        bool $adminStepUpAuthorized
    ): array {
        $correlation = bin2hex(random_bytes(16));
        $this->assertSchemaAvailable($correlation);
        $this->assertAdminInput($admin, $ip, $adminStepUpAuthorized, $correlation);
        $this->assertUserId($userId, $correlation);
        $this->assertText($reason, 10, 500, 'MAINTENANCE_ACCESS_REASON_INVALID', $correlation);
        $this->assertText($reference, 8, 100, 'MAINTENANCE_ACCESS_REFERENCE_INVALID', $correlation);
        $from = $this->parseUtc($validFromUtc, $correlation);
        $until = $this->parseUtc($validUntilUtc, $correlation);
        $now = ($this->clock)();
        if ($until <= $from || $until > $from->modify('+30 days') || $until <= $now) {
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_WINDOW_INVALID', $correlation);
        }

        try {
            return $this->repository->transactional(function () use (
                $userId, $from, $until, $reason, $reference, $admin, $ip, $correlation, $now
            ): array {
                $adminAccount = $this->repository->account($admin, true);
                if (!is_array($adminAccount) || (int) ($adminAccount['u_type'] ?? 0) !== 1
                    || (int) ($adminAccount['avail_status'] ?? 0) !== 1
                ) {
                    throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_ADMIN_FORBIDDEN', $correlation);
                }
                $account = $this->repository->account($userId, true);
                $eligibilityAt = $now >= $from ? $now : $from;
                $eligibility = MaintenanceDeveloperAccessPolicy::decide(
                    $account,
                    ['grant_status' => 'ACTIVE', 'valid_from' => $from->format('Y-m-d H:i:s.u'),
                     'valid_until' => $until->format('Y-m-d H:i:s.u'), 'grant_id' => 1,
                     'configuration_version' => 1, 'u_id' => $userId],
                    $eligibilityAt->format('Y-m-d H:i:s.u')
                );
                if (!$eligibility['allowed']) {
                    throw new MaintenanceDeveloperAccessException($eligibility['code'], $correlation);
                }
                $active = $this->repository->activeGrant($userId, true);
                if (is_array($active) && (string) $active['valid_until'] <= $now->format('Y-m-d H:i:s.u')) {
                    $this->expireLocked($active, $now, $reference, $ip);
                    $active = null;
                }
                if (is_array($active)) {
                    throw new MaintenanceDeveloperAccessException(
                        'MAINTENANCE_ACCESS_ALREADY_ACTIVE',
                        $correlation
                    );
                }
                $grantId = $this->repository->insertGrant([
                    'u_id' => $userId,
                    'valid_from' => $from->format('Y-m-d H:i:s.u'),
                    'valid_until' => $until->format('Y-m-d H:i:s.u'),
                    'approved_by' => $admin,
                    'change_reason' => trim($reason),
                    'change_reference' => trim($reference),
                    'correlation_id' => $correlation,
                ]);
                if ($this->repository->recordHistory([
                    'grant_id' => $grantId, 'u_id' => $userId, 'action_name' => 'GRANTED',
                    'actor_user_id' => $admin, 'configuration_version_before' => null,
                    'configuration_version_after' => 1, 'change_reason' => trim($reason),
                    'change_reference' => trim($reference), 'correlation_id' => $correlation,
                    'source_ip' => $ip,
                ]) !== 1) {
                    throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_AUDIT_FAILED', $correlation);
                }
                return [
                    'status' => 1, 'code' => 'MAINTENANCE_ACCESS_GRANTED',
                    'grant_id' => $grantId, 'configuration_version' => 1,
                    'correlation_id' => $correlation,
                ];
            });
        } catch (MaintenanceDeveloperAccessException $exception) {
            throw $exception;
        } catch (Throwable) {
            error_log('Maintenance developer grant failed correlation=' . $correlation);
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_GRANT_FAILED', $correlation);
        }
    }

    public function searchCandidates(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2 || mb_strlen($query) > 100
            || preg_match('/[\x00-\x1F\x7F]/u', $query) === 1
        ) {
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_SEARCH_INVALID');
        }
        $this->assertSchemaAvailable('');
        $rows = $this->repository->searchCandidates($query);
        foreach ($rows as &$row) {
            $row['eligible'] = ($row['eligibility'] ?? '') === 'ELIGIBLE';
        }
        return ['status' => 1, 'code' => 'MAINTENANCE_ACCESS_CANDIDATES_LOADED', 'data' => $rows];
    }

    public function list(string $query = ''): array
    {
        $query = trim($query);
        if (mb_strlen($query) > 100 || preg_match('/[\x00-\x1F\x7F]/u', $query) === 1) {
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_SEARCH_INVALID');
        }
        $this->assertSchemaAvailable('');
        $now = ($this->clock)()->format('Y-m-d H:i:s.u');
        $rows = $this->repository->listGrants($query);
        foreach ($rows as &$row) {
            $row['grant_id'] = (int) $row['grant_id'];
            $row['configuration_version'] = (int) $row['configuration_version'];
            $row['effective_status'] = (string) $row['grant_status'];
            if ($row['grant_status'] === 'ACTIVE') {
                $row['effective_status'] = $now < (string) $row['valid_from']
                    ? 'SCHEDULED'
                    : ($now >= (string) $row['valid_until'] ? 'EXPIRED' : 'ACTIVE');
            }
        }
        return [
            'status' => 1,
            'code' => 'MAINTENANCE_ACCESS_GRANTS_LOADED',
            'feature_enabled' => oneid_maintenance_developer_access_enabled(),
            'data' => $rows,
        ];
    }

    public function revoke(
        int $grantId,
        int $expectedVersion,
        string $reason,
        string $reference,
        string $admin,
        string $ip,
        bool $adminStepUpAuthorized
    ): array {
        $correlation = bin2hex(random_bytes(16));
        $this->assertSchemaAvailable($correlation);
        $this->assertAdminInput($admin, $ip, $adminStepUpAuthorized, $correlation);
        if ($grantId < 1 || $expectedVersion < 1) {
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_VERSION_INVALID', $correlation);
        }
        $this->assertText($reason, 10, 500, 'MAINTENANCE_ACCESS_REASON_INVALID', $correlation);
        $this->assertText($reference, 8, 100, 'MAINTENANCE_ACCESS_REFERENCE_INVALID', $correlation);
        $now = ($this->clock)();

        try {
            return $this->repository->transactional(function () use (
                $grantId, $expectedVersion, $reason, $reference, $admin, $ip, $correlation, $now
            ): array {
                $adminAccount = $this->repository->account($admin, true);
                if (!is_array($adminAccount) || (int) ($adminAccount['u_type'] ?? 0) !== 1
                    || (int) ($adminAccount['avail_status'] ?? 0) !== 1
                ) {
                    throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_ADMIN_FORBIDDEN', $correlation);
                }
                $grant = $this->repository->grant($grantId, true);
                if (!is_array($grant) || (string) ($grant['grant_status'] ?? '') !== 'ACTIVE') {
                    throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_NOT_ACTIVE', $correlation);
                }
                if ((int) $grant['configuration_version'] !== $expectedVersion) {
                    throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_CONFIGURATION_STALE', $correlation);
                }
                if ($this->repository->revokeGrantVersioned(
                    $grantId, $expectedVersion, $admin, $now->format('Y-m-d H:i:s.u'), trim($reason)
                ) !== 1) {
                    throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_CONFIGURATION_STALE', $correlation);
                }
                $next = $expectedVersion + 1;
                if ($this->repository->recordHistory([
                    'grant_id' => $grantId, 'u_id' => $grant['u_id'], 'action_name' => 'REVOKED',
                    'actor_user_id' => $admin, 'configuration_version_before' => $expectedVersion,
                    'configuration_version_after' => $next, 'change_reason' => trim($reason),
                    'change_reference' => trim($reference), 'correlation_id' => $correlation,
                    'source_ip' => $ip,
                ]) !== 1) {
                    throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_AUDIT_FAILED', $correlation);
                }
                return [
                    'status' => 1, 'code' => 'MAINTENANCE_ACCESS_REVOKED',
                    'grant_id' => $grantId, 'configuration_version' => $next,
                    'correlation_id' => $correlation,
                ];
            });
        } catch (MaintenanceDeveloperAccessException $exception) {
            throw $exception;
        } catch (Throwable) {
            error_log('Maintenance developer revoke failed correlation=' . $correlation);
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_REVOKE_FAILED', $correlation);
        }
    }

    /** @return array{allowed:bool,code:string,grant_id:?int,configuration_version:?int} */
    public function revalidate(string $userId): array
    {
        if (!oneid_maintenance_developer_access_enabled()) {
            return ['allowed' => false, 'code' => 'MAINTENANCE_DEVELOPER_FEATURE_DISABLED',
                'grant_id' => null, 'configuration_version' => null];
        }
        if (preg_match('/\A[A-Za-z0-9._@-]{1,20}\z/', $userId) !== 1) {
            return ['allowed' => false, 'code' => 'MAINTENANCE_ACCESS_DENIED',
                'grant_id' => null, 'configuration_version' => null];
        }
        try {
            $schema = $this->repository->schemaStatus();
            if (!$schema['available']) {
                return ['allowed' => false, 'code' => 'MAINTENANCE_ACCESS_SCHEMA_UNAVAILABLE',
                    'grant_id' => null, 'configuration_version' => null];
            }
            return MaintenanceDeveloperAccessPolicy::decide(
                $this->repository->account($userId),
                $this->repository->activeGrant($userId),
                ($this->clock)()->format('Y-m-d H:i:s.u')
            );
        } catch (Throwable) {
            return ['allowed' => false, 'code' => 'MAINTENANCE_ACCESS_REVALIDATION_FAILED',
                'grant_id' => null, 'configuration_version' => null];
        }
    }

    /** @param array<string,mixed> $grant */
    private function expireLocked(array $grant, DateTimeImmutable $now, string $reference, string $ip): void
    {
        $version = (int) $grant['configuration_version'];
        if ($this->repository->expireGrantVersioned(
            (int) $grant['grant_id'], $version, $now->format('Y-m-d H:i:s.u')
        ) !== 1 || $this->repository->recordHistory([
            'grant_id' => (int) $grant['grant_id'], 'u_id' => $grant['u_id'],
            'action_name' => 'EXPIRED', 'actor_user_id' => null,
            'configuration_version_before' => $version,
            'configuration_version_after' => $version + 1,
            'change_reason' => 'Grant expired automatically before replacement',
            'change_reference' => $reference, 'correlation_id' => bin2hex(random_bytes(16)),
            'source_ip' => $ip,
        ]) !== 1) {
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_EXPIRY_FAILED');
        }
    }

    private function assertSchemaAvailable(string $correlation): void
    {
        $schema = $this->repository->schemaStatus();
        if (!$schema['available']) {
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_SCHEMA_UNAVAILABLE', $correlation);
        }
    }

    private function assertAdminInput(string $admin, string $ip, bool $authorized, string $correlation): void
    {
        if (!$authorized || preg_match('/\A[A-Za-z0-9._@-]{1,20}\z/', $admin) !== 1
            || filter_var($ip, FILTER_VALIDATE_IP) === false
        ) {
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_ADMIN_STEP_UP_REQUIRED', $correlation);
        }
    }

    private function assertUserId(string $userId, string $correlation): void
    {
        if (preg_match('/\A[A-Za-z0-9._@-]{1,20}\z/', $userId) !== 1) {
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_USER_INVALID', $correlation);
        }
    }

    private function assertText(string $value, int $min, int $max, string $code, string $correlation): void
    {
        $value = trim($value);
        if (mb_strlen($value) < $min || mb_strlen($value) > $max
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1
        ) {
            throw new MaintenanceDeveloperAccessException($code, $correlation);
        }
    }

    private function parseUtc(string $value, string $correlation): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $value, new DateTimeZone('UTC'));
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d H:i:s.u') !== $value) {
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_TIME_INVALID', $correlation);
        }
        return $date;
    }
}
