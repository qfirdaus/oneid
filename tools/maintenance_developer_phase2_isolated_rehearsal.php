<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

define('ONEID_MAINTENANCE_BYPASS', true);
require_once dirname(__DIR__) . '/lib/config.php';

$root = dirname(__DIR__);
$database = 'oneid_md2_' . bin2hex(random_bytes(6));
if (preg_match('/\Aoneid_md2_[a-f0-9]{12}\z/', $database) !== 1) {
    throw new RuntimeException('MD2_REHEARSAL_NAME_INVALID');
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
    $pdo->exec("INSERT INTO user_tbl(u_id,u_type) VALUES ('DEV1',0),('ADMIN1',1)");
    $before = (string) $pdo->query('SHOW CREATE TABLE user_tbl')->fetch()['Create Table'];
    foreach ($split((string) file_get_contents(
        $root . '/docs/migrations/20260904_maintenance_developer_access_up.sql'
    )) as $statement) {
        $pdo->exec($statement);
    }

    $tables = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_NAME IN ('maintenance_developer_access_grants',
                             'maintenance_developer_access_history')"
    )->fetchColumn();
    $report($tables === 2, 'two additive maintenance developer tables are created');
    $after = (string) $pdo->query('SHOW CREATE TABLE user_tbl')->fetch()['Create Table'];
    $report(hash_equals(hash('sha256', $before), hash('sha256', $after)), 'user_tbl structure is unchanged');

    $insert = $pdo->prepare(
        "INSERT INTO maintenance_developer_access_grants(
            u_id,valid_from,valid_until,approved_by,change_reason,
            change_reference,correlation_id
         ) VALUES(?,NOW(6),DATE_ADD(NOW(6),INTERVAL 2 HOUR),?,?,?,?)"
    );
    $insert->execute([
        'DEV1', 'ADMIN1', 'Approved UAT maintenance verification',
        'ONEID-MD2-TEST-01', str_repeat('a', 32),
    ]);
    $grantId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO maintenance_developer_access_history(
            grant_id,u_id,action_name,actor_user_id,
            configuration_version_before,configuration_version_after,
            change_reason,change_reference,correlation_id,source_ip
         ) VALUES(?,?,'GRANTED',?,NULL,1,?,?,?,?)"
    )->execute([
        $grantId, 'DEV1', 'ADMIN1', 'Approved UAT maintenance verification',
        'ONEID-MD2-TEST-01', str_repeat('b', 32), '127.0.0.1',
    ]);
    $report(
        (int) $pdo->query('SELECT COUNT(*) FROM maintenance_developer_access_history')->fetchColumn() === 1,
        'valid grant and immutable lifecycle event satisfy constraints'
    );

    $duplicateBlocked = false;
    try {
        $insert->execute([
            'DEV1', 'ADMIN1', 'Second active grant must be rejected',
            'ONEID-MD2-TEST-02', str_repeat('c', 32),
        ]);
    } catch (PDOException) {
        $duplicateBlocked = true;
    }
    $report($duplicateBlocked, 'database blocks a second active grant for the same user');

    $oversizedWindowBlocked = false;
    try {
        $pdo->exec(
            "INSERT INTO maintenance_developer_access_grants(
                u_id,valid_from,valid_until,approved_by,change_reason,
                change_reference,correlation_id
             VALUES('ADMIN1',NOW(6),DATE_ADD(NOW(6),INTERVAL 31 DAY),'ADMIN1',
                    'Window longer than policy maximum','ONEID-MD2-TEST-03','dddddddddddddddddddddddddddddddd')"
        );
    } catch (PDOException) {
        $oversizedWindowBlocked = true;
    }
    $report($oversizedWindowBlocked, 'database blocks grant windows longer than 30 days');

    $forbidden = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_NAME LIKE 'maintenance_developer_access_%'
          AND LOWER(COLUMN_NAME) IN ('password','otp','totp','secret','token','cookie','session_id')"
    )->fetchColumn();
    $report($forbidden === 0, 'schema contains no credential or session material');

    foreach ($split((string) file_get_contents(
        $root . '/docs/migrations/20260904_maintenance_developer_access_down.sql'
    )) as $statement) {
        $pdo->exec($statement);
    }
    $remaining = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_NAME LIKE 'maintenance_developer_access_%'"
    )->fetchColumn();
    $report($remaining === 0, 'down migration removes history before grants');
} finally {
    if ($created) {
        $pdo->exec('USE information_schema');
        $pdo->exec("DROP DATABASE {$quoted}");
    }
}

$leftovers = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'oneid_md2_%'"
)->fetchColumn();
$report($leftovers === 0, 'isolated rehearsal database is removed');
printf(
    "RESULT checks=%d failed=%d live_schema_mutations=0 rehearsal_database_removed=%s\n",
    $checks,
    $failed,
    $leftovers === 0 ? 'yes' : 'no'
);
exit($failed === 0 ? 0 : 1);
