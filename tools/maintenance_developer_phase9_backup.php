<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }
define('ONEID_MAINTENANCE_BYPASS', true);
require_once dirname(__DIR__) . '/lib/config.php';

if ((string) oneid_config('ONEID_ENVIRONMENT', '') !== 'staging') {
    fwrite(STDERR, "BLOCKED staging environment required\n"); exit(1);
}
$parts = [];
foreach (explode(';', substr(DB_DSN, 6)) as $part) {
    if (str_contains($part, '=')) { [$key, $value] = explode('=', $part, 2); $parts[$key] = $value; }
}
$host = (string) ($parts['host'] ?? '127.0.0.1');
$port = (string) ($parts['port'] ?? '3306');
$source = (string) ($parts['dbname'] ?? '');
if ($source !== 'oneiddb' || preg_match('/\A[\w.-]+\z/', $host) !== 1 || preg_match('/\A\d{1,5}\z/', $port) !== 1) {
    fwrite(STDERR, "BLOCKED exact source database configuration required\n"); exit(1);
}
$stamp = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))->format('Ymd-His');
$reference = 'ONEID-MD9-BACKUP-' . $stamp;
$directory = dirname(__DIR__) . '/storage/backups/' . $reference;
if (!mkdir($directory, 0700, true) && !is_dir($directory)) { throw new RuntimeException('BACKUP_DIRECTORY_FAILED'); }
$dump = $directory . '/oneiddb.full.sql';
$evidence = $directory . '/EVIDENCE.txt';
$credentials = tempnam(sys_get_temp_dir(), 'oneid-md9-');
if ($credentials === false) { throw new RuntimeException('TEMPORARY_CREDENTIAL_FILE_FAILED'); }
chmod($credentials, 0600);
file_put_contents($credentials, "[client]\nuser=" . DB_USERNAME . "\npassword=" . DB_PASSWORD
    . "\nhost={$host}\nport={$port}\nprotocol=tcp\n", LOCK_EX);
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$restore = 'oneid_md9_restore_' . bin2hex(random_bytes(5));
$quotedRestore = '`' . $restore . '`';
$created = false;
$run = static function (array $arguments, ?string $stdout = null, ?string $stdin = null): void {
    $command = implode(' ', array_map('escapeshellarg', $arguments));
    if ($stdin !== null) { $command .= ' < ' . escapeshellarg($stdin); }
    if ($stdout !== null) { $command .= ' > ' . escapeshellarg($stdout); }
    passthru($command, $status);
    if ($status !== 0) { throw new RuntimeException('DATABASE_COMMAND_FAILED'); }
};
$counts = static function (PDO $connection, string $schema): array {
    $statement = $connection->prepare("SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME");
    $statement->execute([$schema]);
    $result = [];
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (preg_match('/\A[A-Za-z0-9_]+\z/', (string) $table) !== 1) { throw new RuntimeException('TABLE_NAME_INVALID'); }
        $result[(string) $table] = (int) $connection->query("SELECT COUNT(*) FROM `{$schema}`.`{$table}`")->fetchColumn();
    }
    return $result;
};

try {
    $sourceBefore = $counts($pdo, $source);
    $run(['/usr/bin/mysqldump', '--defaults-extra-file=' . $credentials, '--single-transaction',
        '--quick', '--routines', '--triggers', '--events', '--hex-blob', '--no-tablespaces',
        '--set-gtid-purged=OFF', $source], $dump);
    chmod($dump, 0600);
    $pdo->exec("CREATE DATABASE {$quotedRestore} CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $created = true;
    $run(['/usr/bin/mysql', '--defaults-extra-file=' . $credentials, $restore], null, $dump);
    $restored = $counts($pdo, $restore);
    $matched = $sourceBefore === $restored;
    $lines = [
        'contract=ONEID_MAINTENANCE_DEVELOPER_BACKUP_V1', 'backup_reference=' . $reference,
        'generated_at=' . (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        'source_database=oneiddb', 'source_modified=no', 'backup_file=' . $dump,
        'backup_bytes=' . filesize($dump), 'backup_sha256=' . hash_file('sha256', $dump),
        'restore_target=' . $restore, 'restore_completed=yes',
        'source_table_count=' . count($sourceBefore), 'restore_table_count=' . count($restored),
        'source_row_count_digest=' . hash('sha256', json_encode($sourceBefore, JSON_THROW_ON_ERROR)),
        'restore_row_count_digest=' . hash('sha256', json_encode($restored, JSON_THROW_ON_ERROR)),
        'exact_row_count_reconciliation=' . ($matched ? 'pass' : 'fail'),
        'restore_target_dropped=pending',
    ];
    file_put_contents($evidence, implode("\n", $lines) . "\n", LOCK_EX); chmod($evidence, 0600);
    if (!$matched) { throw new RuntimeException('RESTORE_RECONCILIATION_FAILED'); }
} finally {
    if ($created) { $pdo->exec("DROP DATABASE IF EXISTS {$quotedRestore}"); }
    @unlink($credentials);
}
$content = (string) file_get_contents($evidence);
$content = str_replace('restore_target_dropped=pending', 'restore_target_dropped=yes', $content);
file_put_contents($evidence, $content, LOCK_EX); chmod($evidence, 0600);
printf("PASS backup_reference=%s bytes=%d sha256=%s tables=%d restore_reconciliation=pass restore_target_dropped=yes source_mutations=0\n",
    $reference, filesize($dump), hash_file('sha256', $dump), count($sourceBefore));
