<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }

putenv('ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED=true');
putenv('ONEID_MAINTENANCE_DEVELOPER_STAGING_APPROVED=true');
define('ONEID_MAINTENANCE_BYPASS', true);
require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use OneId\App\Maintenance\MaintenanceDeveloperAccessService;
use OneId\App\Maintenance\MaintenanceDeveloperSessionPolicy;
use OneId\App\Maintenance\PdoMaintenanceDeveloperAccessRepository;

$root = dirname(__DIR__);
$database = 'oneid_md8_' . bin2hex(random_bytes(6));
$quoted = '`' . $database . '`';
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$split = static fn(string $sql): array => array_values(array_filter(
    array_map('trim', preg_split('/;\s*(?:\R|$)/', $sql) ?: []),
    static fn(string $statement): bool => $statement !== ''
));
$failed = 0; $checks = 0; $created = false;
$report = static function (bool $passed, string $label) use (&$failed, &$checks): void {
    $checks++; printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label); $failed += $passed ? 0 : 1;
};

try {
    if (preg_match('/\Aoneid_md8_[a-f0-9]{12}\z/', $database) !== 1) {
        throw new RuntimeException('MD8_REHEARSAL_NAME_INVALID');
    }
    $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $created = true;
    $pdo->exec("USE {$quoted}");
    $pdo->exec("CREATE TABLE user_tbl (u_id VARCHAR(20) NOT NULL, u_type TINYINT NOT NULL DEFAULT 0,
        avail_status TINYINT NOT NULL DEFAULT 1, PRIMARY KEY(u_id)) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
    $pdo->exec("INSERT INTO user_tbl VALUES ('DEV1',0,1),('ADMIN1',1,1)");
    foreach ($split((string) file_get_contents($root . '/docs/migrations/20260904_maintenance_developer_access_up.sql')) as $sql) {
        $pdo->exec($sql);
    }
    $now = new DateTimeImmutable('2026-09-04 12:00:00.000000', new DateTimeZone('UTC'));
    $service = new MaintenanceDeveloperAccessService(
        new PdoMaintenanceDeveloperAccessRepository($pdo), static fn(): DateTimeImmutable => $now
    );
    $grant = $service->grant('DEV1', '2026-09-04 11:00:00.000000', '2026-09-04 13:00:00.000000',
        'Approved phase eight end to end rehearsal', 'ONEID-MD8-E2E-01', 'ADMIN1', '127.0.0.1', true);
    $decision = $service->revalidate('DEV1');
    $session = ['login_status' => 'true', 'login_user_type' => '0', 'login_user' => 'DEV1',
        'oneid_maintenance_developer_grant_id' => $grant['grant_id'],
        'oneid_maintenance_developer_grant_version' => $grant['configuration_version']];
    $report(MaintenanceDeveloperSessionPolicy::decide($session, $decision, true)['allowed'],
        'grant plus active token permits the bound developer session');
    $report(!MaintenanceDeveloperSessionPolicy::decide($session, $decision, false)['allowed'],
        'revoked SSO token immediately denies the session');
    $stale = $session; $stale['oneid_maintenance_developer_grant_version'] = 2;
    $report(!MaintenanceDeveloperSessionPolicy::decide($stale, $decision, true)['allowed'],
        'grant version mismatch denies a stale or forged session');
    $service->revoke((int) $grant['grant_id'], 1, 'Completed phase eight end to end rehearsal',
        'ONEID-MD8-E2E-02', 'ADMIN1', '127.0.0.1', true);
    $report(!MaintenanceDeveloperSessionPolicy::decide($session, $service->revalidate('DEV1'), true)['allowed'],
        'server-side revoke removes access without waiting for session expiry');
    $report((int) $pdo->query('SELECT u_type FROM user_tbl WHERE u_id="DEV1"')->fetchColumn() === 0,
        'developer remains an ordinary u_type=0 user throughout lifecycle');
    $report((int) $pdo->query('SELECT COUNT(*) FROM maintenance_developer_access_history')->fetchColumn() === 2,
        'grant and revoke lifecycle is auditable');
} finally {
    if ($created) { $pdo->exec('USE information_schema'); $pdo->exec("DROP DATABASE {$quoted}"); }
    putenv('ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED');
    putenv('ONEID_MAINTENANCE_DEVELOPER_STAGING_APPROVED');
}
$leftovers = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'oneid_md8_%'")->fetchColumn();
$report($leftovers === 0, 'isolated end-to-end database is removed');
printf("RESULT checks=%d failed=%d live_schema_mutations=0 rehearsal_database_removed=%s\n",
    $checks, $failed, $leftovers === 0 ? 'yes' : 'no');
exit($failed === 0 ? 0 : 1);
