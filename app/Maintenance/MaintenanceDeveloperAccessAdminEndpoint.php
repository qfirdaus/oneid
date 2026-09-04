<?php

declare(strict_types=1);

namespace OneId\App\Maintenance;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class MaintenanceDeveloperAccessAdminEndpoint
{
    public function __construct(
        private readonly MaintenanceDeveloperAccessService $service,
        private readonly string $timezone = 'Asia/Kuala_Lumpur'
    ) {}

    public function handle(string $action, array $post, string $admin, string $ip): array
    {
        return match ($action) {
            'admin_search_maintenance_developer_candidates' => $this->service->searchCandidates(
                (string) ($post['query'] ?? '')
            ),
            'admin_list_maintenance_developer_access' => $this->service->list(
                (string) ($post['query'] ?? '')
            ),
            'admin_grant_maintenance_developer_access' => $this->grant($post, $admin, $ip),
            'admin_revoke_maintenance_developer_access' => $this->revoke($post, $admin, $ip),
            default => throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_ACTION_INVALID'),
        };
    }

    private function grant(array $post, string $admin, string $ip): array
    {
        $user = trim((string) ($post['user_id'] ?? ''));
        if (!hash_equals('GRANT MAINTENANCE ACCESS ' . $user, trim((string) ($post['confirmation'] ?? '')))) {
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_CONFIRMATION_INVALID');
        }
        return $this->service->grant(
            $user,
            $this->toUtc((string) ($post['valid_from'] ?? '')),
            $this->toUtc((string) ($post['valid_until'] ?? '')),
            (string) ($post['change_reason'] ?? ''),
            (string) ($post['change_reference'] ?? ''),
            $admin,
            $ip,
            true
        );
    }

    private function revoke(array $post, string $admin, string $ip): array
    {
        $grantId = filter_var($post['grant_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $version = filter_var($post['configuration_version'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($grantId === false || $version === false
            || !hash_equals('REVOKE MAINTENANCE ACCESS ' . (string) $grantId, trim((string) ($post['confirmation'] ?? '')))
        ) {
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_CONFIRMATION_INVALID');
        }
        return $this->service->revoke(
            $grantId,
            $version,
            (string) ($post['change_reason'] ?? ''),
            (string) ($post['change_reference'] ?? ''),
            $admin,
            $ip,
            true
        );
    }

    private function toUtc(string $value): string
    {
        $local = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', trim($value), new DateTimeZone($this->timezone));
        if (!$local instanceof DateTimeImmutable || $local->format('Y-m-d\TH:i') !== trim($value)) {
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_TIME_INVALID');
        }
        return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
