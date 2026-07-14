<?php

/**
 * Backup-aware S1 provenance migration runner.
 *
 * Usage:
 *   php tools/s1_provenance_migrate.php --status
 *   php tools/s1_provenance_migrate.php --apply
 *   php tools/s1_provenance_migrate.php --rollback
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$mode = $argv[1] ?? '--status';
if (!in_array($mode, ['--status', '--apply', '--rollback'], true)) {
    fwrite(STDERR, "Usage: php tools/s1_provenance_migrate.php [--status|--apply|--rollback]\n");
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$reflection = new ReflectionProperty(Database::class, 'pdo');
$reflection->setAccessible(true);
/** @var PDO $pdo */
$pdo = $reflection->getValue($operation);

$columnCount = static function () use ($pdo): int {
    $statement = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_tbl'
           AND COLUMN_NAME IN ('account_source','sync_protected')"
    );
    return (int) $statement->fetchColumn();
};

$indexCount = static function () use ($pdo): int {
    $statement = $pdo->query(
        "SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_tbl'
           AND INDEX_NAME='idx_user_sync_scope'"
    );
    return (int) $statement->fetchColumn();
};

$printStatus = static function () use ($columnCount, $indexCount): void {
    printf(
        "S1 provenance columns=%d/2 index=%d/1 status=%s\n",
        $columnCount(),
        $indexCount(),
        $columnCount() === 2 && $indexCount() === 1 ? 'ACTIVE' : 'INACTIVE'
    );
};

if ($mode === '--status') {
    $printStatus();
    exit(0);
}

$parseDsn = static function (string $dsn): array {
    if (!str_starts_with($dsn, 'mysql:')) {
        throw new RuntimeException('Only a MySQL DSN is supported by this migration runner.');
    }
    $parts = [];
    foreach (explode(';', substr($dsn, 6)) as $pair) {
        if (!str_contains($pair, '=')) continue;
        [$key, $value] = explode('=', $pair, 2);
        $parts[trim($key)] = trim($value);
    }
    if (($parts['dbname'] ?? '') === '') {
        throw new RuntimeException('Database name is missing from ONEID_DB_DSN.');
    }
    return $parts;
};

$backupUserTable = static function () use ($parseDsn): array {
    $dsn = $parseDsn(DB_DSN);
    $backupRoot = oneid_storage_path('backups');
    if (!is_dir($backupRoot) && !mkdir($backupRoot, 0700, true) && !is_dir($backupRoot)) {
        throw new RuntimeException('Unable to create the private backup directory.');
    }
    chmod($backupRoot, 0700);

    $changeId = 'S1-' . date('Ymd-His');
    $changeDir = $backupRoot . DIRECTORY_SEPARATOR . $changeId;
    if (!mkdir($changeDir, 0700, true) && !is_dir($changeDir)) {
        throw new RuntimeException('Unable to create the S1 backup directory.');
    }

    $backupPath = $changeDir . DIRECTORY_SEPARATOR . 'user_tbl.before.sql';
    $command = [
        '/usr/bin/mysqldump',
        '--single-transaction',
        '--skip-lock-tables',
        '--no-tablespaces',
        '--set-gtid-purged=OFF',
        '--default-character-set=latin1',
        '--user=' . DB_USERNAME,
    ];
    if (($dsn['unix_socket'] ?? '') !== '') {
        $command[] = '--socket=' . $dsn['unix_socket'];
    } else {
        $command[] = '--host=' . ($dsn['host'] ?? '127.0.0.1');
        $command[] = '--port=' . ($dsn['port'] ?? '3306');
    }
    $command[] = $dsn['dbname'];
    $command[] = 'user_tbl';

    $environment = getenv();
    $environment['MYSQL_PWD'] = DB_PASSWORD;
    $process = proc_open(
        $command,
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', $backupPath, 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        $environment
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start mysqldump.');
    }
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    unset($environment['MYSQL_PWD']);
    chmod($backupPath, 0600);

    if ($exitCode !== 0 || !is_file($backupPath) || filesize($backupPath) === 0) {
        @unlink($backupPath);
        throw new RuntimeException('Database backup failed: ' . trim((string) $error));
    }

    $checksum = hash_file('sha256', $backupPath);
    file_put_contents($changeDir . DIRECTORY_SEPARATOR . 'SHA256SUMS', $checksum . "  user_tbl.before.sql\n");
    chmod($changeDir . DIRECTORY_SEPARATOR . 'SHA256SUMS', 0600);
    return [$changeId, $backupPath, $checksum];
};

$expectedBefore = $mode === '--apply' ? 0 : 2;
if ($columnCount() !== $expectedBefore) {
    fwrite(STDERR, sprintf("Refusing %s: unexpected provenance column state.\n", $mode));
    $printStatus();
    exit(1);
}

[$changeId, $backupPath, $checksum] = $backupUserTable();
$migrationPath = dirname(__DIR__) . '/docs/migrations/'
    . ($mode === '--apply' ? 'S1_USER_PROVENANCE_UP.sql' : 'S1_USER_PROVENANCE_DOWN.sql');
$sql = (string) file_get_contents($migrationPath);
$sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;
$statements = array_values(array_filter(array_map('trim', explode(';', $sql))));

try {
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
} catch (Throwable $exception) {
    error_log(sprintf('S1 migration failed change_id=%s exception=%s', $changeId, get_class($exception)));
    fwrite(STDERR, "Migration failed. Restore using the private backup and documented rollback runbook.\n");
    exit(1);
}

$expectedAfter = $mode === '--apply' ? 2 : 0;
if ($columnCount() !== $expectedAfter) {
    fwrite(STDERR, "Migration verification failed.\n");
    exit(1);
}

printf("PASS mode=%s change_id=%s\n", ltrim($mode, '-'), $changeId);
printf("BACKUP %s\n", $backupPath);
printf("SHA256 %s\n", $checksum);
$printStatus();

