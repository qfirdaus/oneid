<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/u1_user_app_favourites_migrate.php [--check|--apply]\n");
    exit(2);
}

if ($operation->supportsUserAppFavourites()) {
    echo "PASS user_app_favourite table is present\n";
    exit(0);
}

if ($mode === '--check') {
    fwrite(STDERR, "FAIL user_app_favourite table is missing\n");
    exit(1);
}

$migrationPath = dirname(__DIR__) . '/docs/migrations/U1_USER_APP_FAVOURITES_UP.sql';
$sql = file_get_contents($migrationPath);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "FAIL migration file could not be read\n");
    exit(1);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec($sql);

$verify = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_app_favourite'"
);
if ((int) $verify->fetchColumn() !== 1) {
    fwrite(STDERR, "FAIL migration did not create user_app_favourite\n");
    exit(1);
}

echo "PASS U1 user_app_favourite migration applied\n";
