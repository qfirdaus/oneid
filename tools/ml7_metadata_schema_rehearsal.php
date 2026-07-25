<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/app/Metadata/BilingualMetadataRepository.php';

$root = dirname(__DIR__);
$database = 'oneid_ml7_rehearsal_' . strtolower(bin2hex(random_bytes(6)));
if (preg_match('/\Aoneid_ml7_rehearsal_[a-f0-9]{12}\z/', $database) !== 1) {
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
        "CREATE TABLE sp_group (
            sp_group_id BIGINT NOT NULL,
            sp_group_name VARCHAR(100) NOT NULL,
            sp_group_seq INT NULL,
            PRIMARY KEY(sp_group_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE sp_list (
            sp_id VARCHAR(20) NOT NULL,
            sp_name TEXT NOT NULL,
            sp_description TEXT NOT NULL,
            sp_domain TEXT NOT NULL,
            sp_image TEXT NOT NULL,
            avail_status INT NOT NULL,
            sp_sso_support INT NOT NULL,
            sp_group_id BIGINT NOT NULL,
            PRIMARY KEY(sp_id),
            CONSTRAINT fk_rehearsal_group FOREIGN KEY(sp_group_id) REFERENCES sp_group(sp_group_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec("INSERT INTO sp_group VALUES(1,'Original Category',1)");
    $pdo->exec(
        "INSERT INTO sp_list VALUES(
            'APP1','Original App','Original Description','https://example.invalid',
            '',1,0,1
        )"
    );
    $before = hash('sha256', json_encode([
        $pdo->query('SELECT * FROM sp_group')->fetchAll(PDO::FETCH_ASSOC),
        $pdo->query('SELECT * FROM sp_list')->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_THROW_ON_ERROR));

    $pdo->exec((string) file_get_contents(
        $root . '/docs/migrations/20260725_ml7_metadata_translation_up.sql'
    ));
    $repository = new \OneId\App\Metadata\BilingualMetadataRepository($pdo);
    if (!$repository->schemaStatus()['available']) {
        throw new RuntimeException('ML7_REHEARSAL_SCHEMA_NOT_READY');
    }
    $appSave = $repository->save(
        'application',
        'APP1',
        'en',
        ['name' => 'English App', 'description' => 'English Description'],
        0,
        'ML7-ADMIN',
        'Approved ML7 application translation rehearsal'
    );
    $categorySave = $repository->save(
        'category',
        '1',
        'en',
        ['name' => 'English Category', 'description' => ''],
        0,
        'ML7-ADMIN',
        'Approved ML7 category translation rehearsal'
    );
    $localized = $repository->localizeGroups([[
        'sp_group_id' => 1,
        'sp_group_name' => 'Original Category',
        'data' => [[
            'sp_id' => 'APP1',
            'sp_name' => 'Original App',
            'sp_description' => 'Original Description',
        ]],
    ]], 'en');
    $staleRejected = false;
    try {
        $repository->save(
            'application',
            'APP1',
            'en',
            ['name' => 'Stale App', 'description' => 'Stale Description'],
            0,
            'ML7-ADMIN',
            'Approved stale translation rehearsal'
        );
    } catch (RuntimeException $exception) {
        $staleRejected = $exception->getMessage() === 'ML7_METADATA_STALE';
    }
    $history = (int) $pdo->query('SELECT COUNT(*) FROM metadata_translation_history')->fetchColumn();
    if (
        $appSave['translation_version'] !== 1
        || $categorySave['translation_version'] !== 1
        || $localized[0]['sp_group_name'] !== 'English Category'
        || $localized[0]['data'][0]['sp_name'] !== 'English App'
        || $localized[0]['data'][0]['sp_description'] !== 'English Description'
        || !$staleRejected
        || $history !== 2
    ) {
        throw new RuntimeException('ML7_FORWARD_RECONCILIATION_FAILED');
    }
    echo "PASS forward localized=yes audit=2 stale_rejected=yes originals_unchanged=yes\n";

    $pdo->exec((string) file_get_contents(
        $root . '/docs/migrations/20260725_ml7_metadata_translation_down.sql'
    ));
    $remaining = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME IN (
             'sp_app_translation','sp_group_translation','metadata_translation_history'
           )"
    )->fetchColumn();
    $after = hash('sha256', json_encode([
        $pdo->query('SELECT * FROM sp_group')->fetchAll(PDO::FETCH_ASSOC),
        $pdo->query('SELECT * FROM sp_list')->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_THROW_ON_ERROR));
    if ($remaining !== 0 || !hash_equals($before, $after)) {
        throw new RuntimeException('ML7_ROLLBACK_RECONCILIATION_FAILED');
    }
    echo "PASS rollback translation_tables_removed=yes original_metadata_unchanged=yes\n";
} finally {
    if ($created) {
        $pdo->exec('USE information_schema');
        $pdo->exec("DROP DATABASE {$quotedDatabase}");
    }
}

echo "RESULT checks=2 failed=0 original_mutations=0 rehearsal_database_removed=yes\n";
