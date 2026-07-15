<?php

/**
 * S4D full OneID backup and isolated restore rehearsal.
 *
 * The source database is never modified. The dump is restored into a newly
 * generated rehearsal database, reconciled by exact table row counts, and the
 * rehearsal database is dropped in a finally block.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$root = dirname(__DIR__);
require_once $root . '/lib/config.php';

function s4d_fail(string $message): never
{
    fwrite(STDERR, "FAIL " . $message . "\n");
    exit(1);
}

function s4d_parse_mysql_dsn(string $dsn): array
{
    if (!str_starts_with($dsn, 'mysql:')) {
        s4d_fail('Only a MySQL DSN is supported.');
    }

    $parts = [];
    foreach (explode(';', substr($dsn, 6)) as $part) {
        if (!str_contains($part, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $part, 2));
        $parts[strtolower($key)] = $value;
    }

    return $parts;
}

function s4d_run(array $command, array $descriptors, array $environment): array
{
    $pipes = [];
    $process = proc_open($command, $descriptors, $pipes, null, $environment);
    if (!is_resource($process)) {
        return [1, 'Unable to start database client.'];
    }

    $stderr = '';
    if (isset($pipes[2]) && is_resource($pipes[2])) {
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);
    }
    foreach ($pipes as $index => $pipe) {
        if ($index !== 2 && is_resource($pipe)) {
            fclose($pipe);
        }
    }

    return [proc_close($process), trim($stderr)];
}

function s4d_quote_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function s4d_table_counts(PDO $pdo, string $database): array
{
    $query = $pdo->prepare(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = :database AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME"
    );
    $query->execute([':database' => $database]);
    $tables = $query->fetchAll(PDO::FETCH_COLUMN);
    $counts = [];
    foreach ($tables as $table) {
        $table = (string) $table;
        $counts[$table] = (int) $pdo->query(
            'SELECT COUNT(*) FROM ' . s4d_quote_identifier($database) . '.' . s4d_quote_identifier($table)
        )->fetchColumn();
    }
    return $counts;
}

$dsn = s4d_parse_mysql_dsn(DB_DSN);
$host = (string) ($dsn['host'] ?? '');
$port = (string) ($dsn['port'] ?? '3306');
$sourceDatabase = (string) ($dsn['dbname'] ?? '');

$reflection = new ReflectionProperty(Database::class, 'pdo');
$reflection->setAccessible(true);
/** @var PDO $pdo */
$pdo = $reflection->getValue($operation);
$actualDatabase = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$serverHostname = (string) $pdo->query('SELECT @@hostname')->fetchColumn();
$databaseBytesQuery = $pdo->prepare(
    'SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0)
     FROM information_schema.TABLES WHERE TABLE_SCHEMA = :database'
);
$databaseBytesQuery->execute([':database' => $actualDatabase]);
$estimatedDatabaseBytes = (int) $databaseBytesQuery->fetchColumn();
$freeBytes = (int) disk_free_space($root . '/storage');
$preflight = in_array('--preflight', $argv, true);

if ($preflight) {
    printf("PREFLIGHT source_host=%s server_hostname=%s database=%s estimated_bytes=%d free_bytes=%d\n", $host, $serverHostname, $actualDatabase, $estimatedDatabaseBytes, $freeBytes);
    printf("PREFLIGHT mysqldump=%s mysql=%s apply_enabled=%s engine=%s\n",
        is_executable('/usr/bin/mysqldump') ? 'yes' : 'no',
        is_executable('/usr/bin/mysql') ? 'yes' : 'no',
        (string) oneid_config('ONEID_SYNC_APPLY_ENABLED'),
        (string) oneid_config('ONEID_SYNC_ENGINE')
    );
    $preflightOk = $sourceDatabase !== ''
        && $actualDatabase === $sourceDatabase
        && $estimatedDatabaseBytes > 0
        && $freeBytes > ($estimatedDatabaseBytes * 3)
        && is_executable('/usr/bin/mysqldump')
        && is_executable('/usr/bin/mysql')
        && (string) oneid_config('ONEID_SYNC_APPLY_ENABLED') === 'false'
        && (string) oneid_config('ONEID_SYNC_ENGINE') === 'disabled';
    printf("RESULT preflight=%s mutation_statements=0\n", $preflightOk ? 'pass' : 'fail');
    exit($preflightOk ? 0 : 1);
}

if (!in_array('--execute', $argv, true)) {
    s4d_fail('Choose --preflight or --execute explicitly.');
}

if ((string) oneid_config('ONEID_SYNC_APPLY_ENABLED') !== 'false'
    || (string) oneid_config('ONEID_SYNC_ENGINE') !== 'disabled'
) {
    s4d_fail('Rehearsal requires sync Apply to remain false and the engine disabled.');
}

$allowedServerHostname = trim((string) oneid_config('ONEID_REHEARSAL_ALLOWED_SERVER_HOSTNAME', ''));
$allowedSourceDatabase = trim((string) oneid_config('ONEID_REHEARSAL_ALLOWED_SOURCE_DATABASE', ''));
if ($allowedServerHostname === ''
    || $allowedSourceDatabase === ''
    || $sourceDatabase !== 'oneiddb'
    || !hash_equals(strtolower($allowedServerHostname), strtolower($serverHostname))
    || !hash_equals($allowedSourceDatabase, $sourceDatabase)
    || !hash_equals($sourceDatabase, $actualDatabase)
) {
    s4d_fail('Fail-closed target allowlist rejected the server hostname or source database.');
}
if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) {
    s4d_fail('Execution requires an interactive terminal.');
}
fwrite(STDERR, sprintf(
    "Source %s/%s will be dumped. A generated rehearsal DB will be created and dropped.\nType BACKUP-RESTORE %s to continue: ",
    $serverHostname,
    $sourceDatabase,
    $sourceDatabase
));
$confirmation = trim((string) fgets(STDIN));
if (!hash_equals('BACKUP-RESTORE ' . $sourceDatabase, $confirmation)) {
    s4d_fail('Operator confirmation did not match.');
}

$schemaQuery = $pdo->prepare(
    'SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
     FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :database'
);
$schemaQuery->execute([':database' => $sourceDatabase]);
$schema = $schemaQuery->fetch(PDO::FETCH_ASSOC);
if (!is_array($schema)) {
    s4d_fail('Unable to read source schema metadata.');
}

$changeId = 'S4D-' . date('Ymd-His');
$backupDirectory = $root . '/storage/backups/' . $changeId;
if (!mkdir($backupDirectory, 0700, true) && !is_dir($backupDirectory)) {
    s4d_fail('Unable to create the private backup directory.');
}
chmod($backupDirectory, 0700);

$dumpPath = $backupDirectory . '/oneiddb.full.sql';
$checksumPath = $backupDirectory . '/SHA256SUMS';
$evidencePath = $backupDirectory . '/EVIDENCE.txt';
$rehearsalDatabase = 'oneiddb_s4d_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2));
$created = false;
$restored = false;
$reconciled = false;
$dropped = false;
$sourceCounts = [];
$restoreCounts = [];
$checksum = '';

$environment = [];
foreach (getenv() as $key => $value) {
    if (is_string($key) && is_string($value)) {
        $environment[$key] = $value;
    }
}
$environment['MYSQL_PWD'] = DB_PASSWORD;

try {
    $dumpCommand = [
        '/usr/bin/mysqldump',
        '--single-transaction',
        '--skip-lock-tables',
        '--no-tablespaces',
        '--set-gtid-purged=OFF',
        '--default-character-set=' . DB_CHARACSET,
        '--host=' . $host,
        '--port=' . $port,
        '--user=' . DB_USERNAME,
        $sourceDatabase,
    ];
    [$dumpExit, $dumpError] = s4d_run(
        $dumpCommand,
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', $dumpPath, 'w'], 2 => ['pipe', 'w']],
        $environment
    );
    if ($dumpExit !== 0 || !is_file($dumpPath) || filesize($dumpPath) === 0) {
        throw new RuntimeException('Backup command failed: ' . ($dumpError === '' ? 'no diagnostic' : $dumpError));
    }
    chmod($dumpPath, 0600);
    $checksum = hash_file('sha256', $dumpPath);
    if (!is_string($checksum) || $checksum === '') {
        throw new RuntimeException('Unable to calculate the backup checksum.');
    }
    file_put_contents($checksumPath, $checksum . '  oneiddb.full.sql' . PHP_EOL, LOCK_EX);
    chmod($checksumPath, 0600);

    $characterSet = preg_replace('/[^A-Za-z0-9_]/', '', (string) $schema['DEFAULT_CHARACTER_SET_NAME']);
    $collation = preg_replace('/[^A-Za-z0-9_]/', '', (string) $schema['DEFAULT_COLLATION_NAME']);
    if ($characterSet === '' || $collation === '') {
        throw new RuntimeException('Unsafe source schema metadata.');
    }
    $pdo->exec(
        'CREATE DATABASE ' . s4d_quote_identifier($rehearsalDatabase)
        . ' CHARACTER SET ' . $characterSet . ' COLLATE ' . $collation
    );
    $created = true;

    $restoreCommand = [
        '/usr/bin/mysql',
        '--host=' . $host,
        '--port=' . $port,
        '--user=' . DB_USERNAME,
        '--default-character-set=' . DB_CHARACSET,
        $rehearsalDatabase,
    ];
    [$restoreExit, $restoreError] = s4d_run(
        $restoreCommand,
        [0 => ['file', $dumpPath, 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']],
        $environment
    );
    if ($restoreExit !== 0) {
        throw new RuntimeException('Restore command failed: ' . ($restoreError === '' ? 'no diagnostic' : $restoreError));
    }
    $restored = true;

    $sourceCounts = s4d_table_counts($pdo, $sourceDatabase);
    $restoreCounts = s4d_table_counts($pdo, $rehearsalDatabase);
    $reconciled = $sourceCounts === $restoreCounts && $sourceCounts !== [];
    if (!$reconciled) {
        throw new RuntimeException('Exact table row-count reconciliation failed.');
    }
} catch (Throwable $exception) {
    error_log('[S4D_BACKUP_REHEARSAL] ' . get_class($exception) . ': ' . $exception->getMessage());
    fwrite(STDERR, "FAIL Backup or restore rehearsal failed; inspect the private PHP error log.\n");
} finally {
    if ($created) {
        try {
            if ($rehearsalDatabase === $sourceDatabase
                || preg_match('/\Aoneiddb_s4d_[0-9]{8}_[0-9]{6}_[a-f0-9]{4}\z/', $rehearsalDatabase) !== 1
            ) {
                throw new RuntimeException('Unsafe rehearsal cleanup target rejected.');
            }
            $pdo->exec('DROP DATABASE IF EXISTS ' . s4d_quote_identifier($rehearsalDatabase));
            $dropped = true;
        } catch (Throwable $dropException) {
            error_log('[S4D_BACKUP_REHEARSAL] rehearsal cleanup failed: ' . $dropException->getMessage());
        }
    }

    $sourceDigest = $sourceCounts === [] ? '-' : hash('sha256', json_encode($sourceCounts, JSON_THROW_ON_ERROR));
    $restoreDigest = $restoreCounts === [] ? '-' : hash('sha256', json_encode($restoreCounts, JSON_THROW_ON_ERROR));
    $evidence = [
        'change_id=' . $changeId,
        'generated_at=' . date(DATE_ATOM),
        'source_host=' . $host,
        'source_server_hostname=' . $serverHostname,
        'source_database=' . $sourceDatabase,
        'source_modified=no',
        'backup_file=' . $dumpPath,
        'backup_bytes=' . (is_file($dumpPath) ? (string) filesize($dumpPath) : '0'),
        'backup_sha256=' . ($checksum === '' ? '-' : $checksum),
        'restore_target=' . $rehearsalDatabase,
        'restore_completed=' . ($restored ? 'yes' : 'no'),
        'source_table_count=' . count($sourceCounts),
        'restore_table_count=' . count($restoreCounts),
        'source_row_count_digest=' . $sourceDigest,
        'restore_row_count_digest=' . $restoreDigest,
        'exact_row_count_reconciliation=' . ($reconciled ? 'pass' : 'fail'),
        'restore_target_dropped=' . ($dropped ? 'yes' : 'no'),
    ];
    file_put_contents($evidencePath, implode(PHP_EOL, $evidence) . PHP_EOL, LOCK_EX);
    chmod($evidencePath, 0600);
}

$ok = $checksum !== '' && $restored && $reconciled && $dropped;
printf("%s backup=%s bytes=%d sha256=%s...\n", $ok ? 'PASS' : 'FAIL', $dumpPath, is_file($dumpPath) ? filesize($dumpPath) : 0, substr($checksum, 0, 12));
printf("%s restore_rehearsal tables=%d row_digest_match=%s target_dropped=%s\n", $ok ? 'PASS' : 'FAIL', count($sourceCounts), $reconciled ? 'yes' : 'no', $dropped ? 'yes' : 'no');
printf("EVIDENCE %s\n", $evidencePath);
exit($ok ? 0 : 1);
