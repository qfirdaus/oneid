<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$root = dirname(__DIR__);
$database = 'oneid_ml5_rehearsal_' . strtolower(bin2hex(random_bytes(6)));
if (preg_match('/\Aoneid_ml5_rehearsal_[a-f0-9]{12}\z/', $database) !== 1) {
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
        "CREATE TABLE sys_config (
            id INT NOT NULL,
            configuration_version INT NOT NULL DEFAULT 1,
            admin_step_up_lifetime_minutes INT NOT NULL DEFAULT 15,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE protected_data (
            record_id INT NOT NULL,
            marker VARCHAR(40) NOT NULL,
            PRIMARY KEY (record_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec("INSERT INTO sys_config(id,configuration_version) VALUES(1,7)");
    $pdo->exec("INSERT INTO protected_data(record_id,marker) VALUES(1,'ML5-UNCHANGED')");
    $before = (string) $pdo->query(
        "SELECT CONCAT_WS('|',record_id,marker) FROM protected_data WHERE record_id=1"
    )->fetchColumn();

    $pdo->exec((string) file_get_contents(
        $root . '/docs/migrations/20260725_ml5_system_default_locale_up.sql'
    ));
    $default = (string) $pdo->query("SELECT default_locale FROM sys_config WHERE id=1")->fetchColumn();
    $pdo->exec("UPDATE sys_config SET default_locale='en' WHERE id=1");
    $stored = (string) $pdo->query("SELECT default_locale FROM sys_config WHERE id=1")->fetchColumn();
    $invalidRejected = false;
    try {
        $pdo->exec("UPDATE sys_config SET default_locale='fr' WHERE id=1");
    } catch (PDOException) {
        $invalidRejected = true;
    }
    $after = (string) $pdo->query(
        "SELECT CONCAT_WS('|',record_id,marker) FROM protected_data WHERE record_id=1"
    )->fetchColumn();
    if ($default !== 'ms' || $stored !== 'en' || !$invalidRejected || !hash_equals($before, $after)) {
        throw new RuntimeException('ML5_FORWARD_RECONCILIATION_FAILED');
    }
    echo "PASS forward default=ms stored=en invalid_rejected=yes protected_data_unchanged=yes\n";

    $pdo->exec((string) file_get_contents(
        $root . '/docs/migrations/20260725_ml5_system_default_locale_down.sql'
    ));
    $remaining = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sys_config'
           AND COLUMN_NAME='default_locale'"
    )->fetchColumn();
    $afterRollback = (string) $pdo->query(
        "SELECT CONCAT_WS('|',record_id,marker) FROM protected_data WHERE record_id=1"
    )->fetchColumn();
    if ($remaining !== 0 || !hash_equals($before, $afterRollback)) {
        throw new RuntimeException('ML5_ROLLBACK_RECONCILIATION_FAILED');
    }
    echo "PASS rollback column_removed=yes protected_data_unchanged=yes\n";
} finally {
    if ($created) {
        $pdo->exec('USE information_schema');
        $pdo->exec("DROP DATABASE {$quotedDatabase}");
    }
}

echo "RESULT checks=2 failed=0 data_mutations=0 rehearsal_database_removed=yes\n";
