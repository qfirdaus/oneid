<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$root = dirname(__DIR__);
$database = 'oneid_ml1_rehearsal_' . strtolower(bin2hex(random_bytes(6)));
if (preg_match('/\Aoneid_ml1_rehearsal_[a-f0-9]{12}\z/', $database) !== 1) {
    fwrite(STDERR, "FAIL invalid rehearsal database name\n");
    exit(1);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$quotedDatabase = '`' . $database . '`';
$created = false;

try {
    $pdo->exec("CREATE DATABASE {$quotedDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $created = true;
    $pdo->exec("USE {$quotedDatabase}");
    $pdo->exec(
        "CREATE TABLE user_tbl (
            u_id VARCHAR(20) NOT NULL,
            avail_status INT NOT NULL DEFAULT 1,
            PRIMARY KEY (u_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec("INSERT INTO user_tbl(u_id,avail_status) VALUES('ML1-USER',1)");
    $before = (string) $pdo->query(
        "SELECT CONCAT_WS('|',u_id,avail_status) FROM user_tbl WHERE u_id='ML1-USER'"
    )->fetchColumn();

    $pdo->exec((string) file_get_contents(
        $root . '/docs/migrations/20260725_ml1_locale_preference_up.sql'
    ));
    $pdo->exec(
        "INSERT INTO user_locale_preference(u_id,locale,created_at,updated_at)
         VALUES('ML1-USER','en',NOW(),NOW())"
    );
    $stored = (string) $pdo->query(
        "SELECT locale FROM user_locale_preference WHERE u_id='ML1-USER'"
    )->fetchColumn();
    $invalidRejected = false;
    try {
        $pdo->exec(
            "INSERT INTO user_locale_preference(u_id,locale,created_at,updated_at)
             VALUES('ML1-BAD','fr',NOW(),NOW())"
        );
    } catch (PDOException) {
        $invalidRejected = true;
    }
    $after = (string) $pdo->query(
        "SELECT CONCAT_WS('|',u_id,avail_status) FROM user_tbl WHERE u_id='ML1-USER'"
    )->fetchColumn();
    if ($stored !== 'en' || !$invalidRejected || !hash_equals($before, $after)) {
        throw new RuntimeException('ML1_FORWARD_RECONCILIATION_FAILED');
    }
    echo "PASS forward locale=en invalid_rejected=yes user_unchanged=yes\n";

    $pdo->exec((string) file_get_contents(
        $root . '/docs/migrations/20260725_ml1_locale_preference_down.sql'
    ));
    $remaining = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_locale_preference'"
    )->fetchColumn();
    $afterRollback = (string) $pdo->query(
        "SELECT CONCAT_WS('|',u_id,avail_status) FROM user_tbl WHERE u_id='ML1-USER'"
    )->fetchColumn();
    if ($remaining !== 0 || !hash_equals($before, $afterRollback)) {
        throw new RuntimeException('ML1_ROLLBACK_RECONCILIATION_FAILED');
    }
    echo "PASS rollback table_removed=yes user_unchanged=yes\n";
} finally {
    if ($created) {
        $pdo->exec('USE information_schema');
        $pdo->exec("DROP DATABASE {$quotedDatabase}");
    }
}

echo "RESULT checks=2 failed=0 user_mutations=0 rehearsal_database_removed=yes\n";
