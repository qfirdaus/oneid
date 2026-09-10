<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Maintenance/MaintenanceDeveloperAccessException.php';
require_once dirname(__DIR__, 2) . '/app/Maintenance/MaintenanceDeveloperAccessRepositoryInterface.php';
require_once dirname(__DIR__, 2) . '/app/Maintenance/MaintenanceDeveloperAccessPolicy.php';
require_once dirname(__DIR__, 2) . '/app/Maintenance/MaintenanceDeveloperAccessService.php';

use OneId\App\Maintenance\MaintenanceDeveloperAccessException;
use OneId\App\Maintenance\MaintenanceDeveloperAccessRepositoryInterface;
use OneId\App\Maintenance\MaintenanceDeveloperAccessService;

final class MaintenanceLifetimeRepository implements MaintenanceDeveloperAccessRepositoryInterface
{
    public int $inserts = 0;

    public function __construct(private readonly array $maintenance) {}
    public function schemaStatus(): array { return ['available' => true, 'tables' => []]; }
    public function transactional(callable $operation): mixed { return $operation($this); }
    public function maintenanceConfiguration(bool $forUpdate = false): ?array { return $this->maintenance; }
    public function searchCandidates(string $query): array { return []; }
    public function listGrants(string $query): array { return []; }
    public function account(string $userId, bool $forUpdate = false): ?array
    {
        return ['u_id' => $userId, 'u_type' => $userId === 'admin' ? 1 : 0, 'avail_status' => 1];
    }
    public function activeGrant(string $userId, bool $forUpdate = false): ?array { return null; }
    public function grant(int $grantId, bool $forUpdate = false): ?array { return null; }
    public function insertGrant(array $grant): int { $this->inserts++; return 1; }
    public function revokeGrantVersioned(int $grantId, int $expectedVersion, string $revokedBy, string $revokedAtUtc, string $reason): int { return 0; }
    public function expireGrantVersioned(int $grantId, int $expectedVersion, string $expiredAtUtc): int { return 0; }
    public function recordHistory(array $event): int { return 1; }
}

$window = [
    'maintenance_mode' => 'SCHEDULED',
    'maintenance_starts_at' => '2026-09-10 01:00:00',
    'maintenance_ends_at' => '2026-09-10 05:00:00',
];
$clock = static fn(): DateTimeImmutable => new DateTimeImmutable('2026-09-10 02:00:00', new DateTimeZone('UTC'));
$validRepository = new MaintenanceLifetimeRepository($window);
$valid = (new MaintenanceDeveloperAccessService($validRepository, $clock))->grant(
    'developer', '2026-09-10 02:00:00.000000', '2026-09-10 04:00:00.000000',
    'Approved maintenance verification', 'ONEID-MD-WINDOW-01', 'admin', '127.0.0.1', true
);

$invalidRepository = new MaintenanceLifetimeRepository($window);
$reason = '';
try {
    (new MaintenanceDeveloperAccessService($invalidRepository, $clock))->grant(
        'developer', '2026-09-10 02:00:00.000000', '2026-09-10 06:00:00.000000',
        'Approved maintenance verification', 'ONEID-MD-WINDOW-02', 'admin', '127.0.0.1', true
    );
} catch (MaintenanceDeveloperAccessException $exception) {
    $reason = $exception->reason;
}

$api = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/q_func.php');
$checks = [
    'developer grant inside scheduled maintenance window is accepted' => ($valid['status'] ?? 0) === 1
        && $validRepository->inserts === 1,
    'developer grant beyond maintenance end is rejected without insert' => $reason === 'MAINTENANCE_ACCESS_EXCEEDS_MAINTENANCE_WINDOW'
        && $invalidRepository->inserts === 0,
    'maintenance Administrator lifetime reads approved configuration' => str_contains($api, "['admin_step_up_lifetime_minutes']")
        && str_contains($api, "'lifetime_minutes'=>\$maintenanceAdminLifetime"),
    'successful renewal extends only an existing maintenance marker' => str_contains(
        $api,
        "if(isset(\$_SESSION['oneid_maintenance_admin_verified_until']))"
    ) && str_contains($api, "(int)\$results['grant_remaining_seconds']"),
    'developer countdown is bounded by the grant and maintenance deadline' => str_contains(
        (string) file_get_contents(dirname(__DIR__, 2) . '/lib/session_security.php'),
        "['oneid_maintenance_developer_valid_until']"
    ) && str_contains(
        (string) file_get_contents(dirname(__DIR__, 2) . '/lib/session_security.php'),
        "['maintenance_access_remaining_seconds']"
    ),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $failed += $passed ? 0 : 1;
}
printf('RESULT checks=%d failed=%d' . PHP_EOL, count($checks), $failed);
exit($failed === 0 ? 0 : 1);
