<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }
define('ONEID_MAINTENANCE_BYPASS', true);
require_once dirname(__DIR__) . '/lib/config.php';

$root = dirname(__DIR__);
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$snapshot = static function (PDO $pdo): array {
    return [
        'database' => (string) $pdo->query('SELECT DATABASE()')->fetchColumn(),
        'users' => (int) $pdo->query('SELECT COUNT(*) FROM user_tbl')->fetchColumn(),
        'md_tables' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
            AND TABLE_NAME IN ('maintenance_developer_access_grants','maintenance_developer_access_history')")->fetchColumn(),
    ];
};
$before = $snapshot($pdo);
$scripts = [
    'phase1 contract' => 'tools/maintenance_developer_phase1_contract.php',
    'phase2 isolated schema' => 'tools/maintenance_developer_phase2_isolated_rehearsal.php',
    'phase3 integration' => 'tools/maintenance_developer_phase3_integration.php',
    'phase4 admin contract' => 'tools/maintenance_developer_phase4_contract.php',
    'phase5 login and MFA contract' => 'tools/maintenance_developer_phase5_contract.php',
    'phase6 runtime gate contract' => 'tools/maintenance_developer_phase6_contract.php',
    'phase7 permission isolation' => 'tests/characterization/maintenance_developer_permission_isolation.php',
    'phase8 end-to-end rehearsal' => 'tools/maintenance_developer_phase8_e2e.php',
    'legacy maintenance regression' => 'tools/maintenance_mode_contract.php',
];
$failed = 0;
foreach ($scripts as $label => $relative) {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/' . $relative);
    passthru($command, $status);
    printf("%s suite: %s\n", $status === 0 ? 'PASS' : 'FAIL', $label);
    $failed += $status === 0 ? 0 : 1;
}
$after = $snapshot($pdo);
$unchanged = $before === $after;
printf("%s live database snapshot unchanged\n", $unchanged ? 'PASS' : 'FAIL');
$failed += $unchanged ? 0 : 1;
printf("RESULT suites=%d failed=%d live_mutations=%s database=%s\n", count($scripts) + 1, $failed,
    $unchanged ? '0' : 'detected', $before['database']);
exit($failed === 0 ? 0 : 1);
