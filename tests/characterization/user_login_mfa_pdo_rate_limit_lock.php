<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/config.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaAuditWriterInterface.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaEmailOtpPersistenceInterface.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/PdoUserMfaEmailOtpPersistence.php';

use OneId\App\Auth\UserMfa\PdoUserMfaEmailOtpPersistence;
use OneId\App\Auth\UserMfa\UserMfaAuditWriterInterface;

final class RateLockAudit implements UserMfaAuditWriterInterface
{
    public function write(
        string $event, string $targetUserId, string $actorUserId,
        string $outcome, string $reason, string $reference,
        string $correlationId, string $ipAddress
    ): int {
        return 1;
    }
}

$database = 'oneid_user_mfa_lock_' . bin2hex(random_bytes(6));
$quoted = '`' . $database . '`';
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
$admin = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, $options);
$first = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, $options);
$second = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, $options);
$created = false;
$failed = 0;

try {
    $admin->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $created = true;
    foreach ([$first, $second] as $connection) {
        $connection->exec("USE {$quoted}");
    }
    $admin->exec("USE {$quoted}");
    $admin->exec(
        'CREATE TABLE user_login_mfa_policy(
            singleton_key TINYINT UNSIGNED NOT NULL PRIMARY KEY
         ) ENGINE=InnoDB'
    );
    $admin->exec('INSERT INTO user_login_mfa_policy(singleton_key) VALUES(1)');
    $admin->exec(
        'CREATE TABLE user_login_mfa_transactions(
            transaction_id CHAR(64) NOT NULL PRIMARY KEY,
            session_binding_hash CHAR(64) NOT NULL,
            requesting_ip VARCHAR(45) NOT NULL
         ) ENGINE=InnoDB'
    );
    $admin->exec(
        'CREATE TABLE user_login_mfa_challenges(
            challenge_id CHAR(64) NOT NULL PRIMARY KEY,
            transaction_id CHAR(64) NOT NULL,
            u_id VARCHAR(20) NOT NULL,
            factor_type VARCHAR(16) NOT NULL,
            destination_hmac CHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
         ) ENGINE=InnoDB'
    );
    $second->exec('SET SESSION innodb_lock_wait_timeout=1');
    $audit = new RateLockAudit();
    $one = new PdoUserMfaEmailOtpPersistence($first, $audit);
    $two = new PdoUserMfaEmailOtpPersistence($second, $audit);

    $one->beginTransaction();
    $one->emailRequestStatsForUpdate(
        'LOCKUSER', str_repeat('a', 64), '127.0.0.1', str_repeat('b', 64)
    );
    $secondBlocked = false;
    $two->beginTransaction();
    try {
        $two->emailRequestStatsForUpdate(
            'LOCKUSER', str_repeat('c', 64), '127.0.0.2', str_repeat('d', 64)
        );
    } catch (PDOException $exception) {
        $secondBlocked = in_array(
            (int) ($exception->errorInfo[1] ?? 0),
            [1205, 1213],
            true
        );
    } finally {
        $two->rollback();
    }
    $one->commit();
    printf(
        "%s concurrent request counter waits on singleton serialization lock\n",
        $secondBlocked ? 'PASS' : 'FAIL'
    );
    $failed += $secondBlocked ? 0 : 1;

    $two->beginTransaction();
    $stats = $two->emailRequestStatsForUpdate(
        'LOCKUSER', str_repeat('c', 64), '127.0.0.2', str_repeat('d', 64)
    );
    $two->commit();
    $released = $stats['user_hour'] === 0;
    printf("%s lock is released after first transaction commit\n", $released ? 'PASS' : 'FAIL');
    $failed += $released ? 0 : 1;
} finally {
    if ($first->inTransaction()) {
        $first->rollBack();
    }
    if ($second->inTransaction()) {
        $second->rollBack();
    }
    if ($created) {
        $admin->exec('USE information_schema');
        $admin->exec("DROP DATABASE {$quoted}");
    }
}

$leftovers = (int) $admin->query(
    "SELECT COUNT(*) FROM information_schema.schemata
      WHERE schema_name LIKE 'oneid_user_mfa_lock_%'"
)->fetchColumn();
printf("%s isolated lock database is removed\n", $leftovers === 0 ? 'PASS' : 'FAIL');
$failed += $leftovers === 0 ? 0 : 1;
printf(
    "RESULT checks=3 failures=%d real_mysql_lock=1 shared_database_mutations=0 runtime_activation=0\n",
    $failed
);
exit($failed === 0 ? 0 : 1);
