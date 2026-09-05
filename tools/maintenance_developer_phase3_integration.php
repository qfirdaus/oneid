<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

putenv('ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED=true');
putenv('ONEID_MAINTENANCE_DEVELOPER_STAGING_APPROVED=true');
define('ONEID_MAINTENANCE_BYPASS', true);
require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use OneId\App\Maintenance\MaintenanceDeveloperAccessException;
use OneId\App\Maintenance\MaintenanceDeveloperAccessService;
use OneId\App\Maintenance\PdoMaintenanceDeveloperAccessRepository;

$root = dirname(__DIR__);
$database = 'oneid_md3_' . bin2hex(random_bytes(6));
if (preg_match('/\Aoneid_md3_[a-f0-9]{12}\z/', $database) !== 1) {
    throw new RuntimeException('MD3_REHEARSAL_NAME_INVALID');
}
$quoted = '`' . $database . '`';
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$split = static fn(string $sql): array => array_values(array_filter(
    array_map('trim', preg_split('/;\s*(?:\R|$)/', $sql) ?: []),
    static fn(string $statement): bool => $statement !== ''
));
$failed = 0;
$checks = 0;
$report = static function (bool $passed, string $label) use (&$failed, &$checks): void {
    $checks++;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    $failed += $passed ? 0 : 1;
};
$created = false;

try {
    $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $created = true;
    $pdo->exec("USE {$quoted}");
    $pdo->exec(
        "CREATE TABLE user_tbl (
            u_id VARCHAR(20) NOT NULL,
            u_type TINYINT NOT NULL DEFAULT 0,
            avail_status TINYINT NOT NULL DEFAULT 1,
            PRIMARY KEY (u_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "INSERT INTO user_tbl(u_id,u_type,avail_status) VALUES
         ('DEV1',0,1),('DEV2',0,0),('ADMIN1',1,1),('ADMIN2',1,0)"
    );
    foreach ($split((string) file_get_contents(
        $root . '/docs/migrations/20260904_maintenance_developer_access_up.sql'
    )) as $statement) {
        $pdo->exec($statement);
    }

    $clock = static fn(): DateTimeImmutable => new DateTimeImmutable(
        '2026-09-04 12:00:00.000000',
        new DateTimeZone('UTC')
    );
    $repository = new PdoMaintenanceDeveloperAccessRepository($pdo);
    $service = new MaintenanceDeveloperAccessService($repository, $clock);
    $result = $service->grant(
        'DEV1', '2026-09-04 11:00:00.000000', '2026-09-04 14:00:00.000000',
        'Approved developer verification window', 'ONEID-MD3-TEST-01',
        'ADMIN1', '127.0.0.1', true
    );
    $report(
        $result['code'] === 'MAINTENANCE_ACCESS_GRANTED'
        && (int) $result['configuration_version'] === 1,
        'eligible u_type=0 account receives a versioned grant'
    );
    putenv('ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED=false');
    $report(
        $service->revalidate('DEV1')['code'] === 'MAINTENANCE_DEVELOPER_FEATURE_DISABLED',
        'runtime access remains fail-closed while feature flag is off'
    );
    putenv('ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED=true');
    $decision = $service->revalidate('DEV1');
    $report(
        $decision['allowed'] === true
        && $decision['code'] === 'MAINTENANCE_ACCESS_ALLOWED'
        && $decision['grant_id'] === $result['grant_id'],
        'active grant revalidates from server-side persistence'
    );

    $duplicate = '';
    try {
        $service->grant(
            'DEV1', '2026-09-04 12:00:00.000000', '2026-09-04 15:00:00.000000',
            'Duplicate active developer grant attempt', 'ONEID-MD3-TEST-02',
            'ADMIN1', '127.0.0.1', true
        );
    } catch (MaintenanceDeveloperAccessException $exception) {
        $duplicate = $exception->reason;
    }
    $report($duplicate === 'MAINTENANCE_ACCESS_ALREADY_ACTIVE', 'duplicate active grant is rejected');

    $adminSubject = '';
    try {
        $service->grant(
            'ADMIN1', '2026-09-04 12:00:00.000000', '2026-09-04 15:00:00.000000',
            'Administrator must not become developer subject', 'ONEID-MD3-TEST-03',
            'ADMIN1', '127.0.0.1', true
        );
    } catch (MaintenanceDeveloperAccessException $exception) {
        $adminSubject = $exception->reason;
    }
    $report(
        $adminSubject === 'MAINTENANCE_ACCESS_USER_TYPE_FORBIDDEN',
        'u_type=1 subject is rejected by domain policy'
    );

    $stepUp = '';
    try {
        $service->grant(
            'DEV2', '2026-09-04 12:00:00.000000', '2026-09-04 15:00:00.000000',
            'Inactive user test with missing step up', 'ONEID-MD3-TEST-04',
            'ADMIN1', '127.0.0.1', false
        );
    } catch (MaintenanceDeveloperAccessException $exception) {
        $stepUp = $exception->reason;
    }
    $report(
        $stepUp === 'MAINTENANCE_ACCESS_ADMIN_STEP_UP_REQUIRED',
        'grant mutation requires explicit Admin Step-Up authorization'
    );

    $stale = '';
    try {
        $service->revoke(
            (int) $result['grant_id'], 2, 'Revoke using stale configuration version',
            'ONEID-MD3-TEST-05', 'ADMIN1', '127.0.0.1', true
        );
    } catch (MaintenanceDeveloperAccessException $exception) {
        $stale = $exception->reason;
    }
    $report(
        $stale === 'MAINTENANCE_ACCESS_CONFIGURATION_STALE'
        && $service->revalidate('DEV1')['allowed'] === true,
        'stale revoke rolls back without changing active grant'
    );

    $revoked = $service->revoke(
        (int) $result['grant_id'], 1, 'Revoke after completed developer verification',
        'ONEID-MD3-TEST-06', 'ADMIN1', '127.0.0.1', true
    );
    $report(
        $revoked['code'] === 'MAINTENANCE_ACCESS_REVOKED'
        && (int) $revoked['configuration_version'] === 2
        && $service->revalidate('DEV1')['allowed'] === false,
        'versioned revoke immediately removes effective access'
    );
    $report(
        (int) $pdo->query('SELECT COUNT(*) FROM maintenance_developer_access_history')->fetchColumn() === 2,
        'grant and revoke each commit one lifecycle audit event'
    );
} finally {
    if ($created) {
        $pdo->exec('USE information_schema');
        $pdo->exec("DROP DATABASE {$quoted}");
    }
    putenv('ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED');
    putenv('ONEID_MAINTENANCE_DEVELOPER_STAGING_APPROVED');
}

$leftovers = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'oneid_md3_%'"
)->fetchColumn();
$report($leftovers === 0, 'isolated integration database is removed');
printf(
    "RESULT checks=%d failed=%d live_schema_mutations=0 rehearsal_database_removed=%s\n",
    $checks,
    $failed,
    $leftovers === 0 ? 'yes' : 'no'
);
exit($failed === 0 ? 0 : 1);
