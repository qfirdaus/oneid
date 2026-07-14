<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/w0_reconcile_orphan_app_categories.php [--check|--apply]\n");
    exit(2);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$orphanSql = "SELECT s.sp_id,s.sp_group_id,s.avail_status
              FROM sp_list s
              LEFT JOIN sp_group g ON g.sp_group_id=s.sp_group_id
              WHERE g.sp_group_id IS NULL
              ORDER BY s.sp_id";
$orphans = $pdo->query($orphanSql, PDO::FETCH_ASSOC)->fetchAll();
$activeOrphans = array_values(array_filter($orphans, static fn(array $row): bool => (int) $row['avail_status'] === 1));

printf("W0 orphan rows=%d active=%d mode=%s\n", count($orphans), count($activeOrphans), $mode);
if ($activeOrphans !== []) {
    fwrite(STDERR, "FAIL active orphan application detected; no mutation permitted\n");
    exit(1);
}
if ((int) $pdo->query("SELECT COUNT(*) FROM sp_group WHERE sp_group_id=0")->fetchColumn() !== 1) {
    fwrite(STDERR, "FAIL default category ID 0 is unavailable\n");
    exit(1);
}
if ($mode === '--check') {
    exit($orphans === [] ? 0 : 1);
}
if ($orphans === []) {
    echo "PASS no reconciliation required\n";
    exit(0);
}

$backupDir = dirname(__DIR__) . '/storage/backups/W0-' . date('Ymd-His');
if (!mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
    throw new RuntimeException('Unable to create W0 backup directory.');
}
$snapshotPath = $backupDir . '/orphan-app-groups.json';
$payload = json_encode([
    'created_at' => date(DATE_ATOM),
    'target_group_id' => 0,
    'rows' => $orphans,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($payload === false || file_put_contents($snapshotPath, $payload . PHP_EOL, LOCK_EX) === false) {
    throw new RuntimeException('Unable to write W0 snapshot.');
}
chmod($snapshotPath, 0600);
$hash = hash_file('sha256', $snapshotPath);
file_put_contents($backupDir . '/SHA256SUMS', $hash . '  orphan-app-groups.json' . PHP_EOL, LOCK_EX);
chmod($backupDir . '/SHA256SUMS', 0600);

$pdo->beginTransaction();
try {
    $lock = $pdo->query("SELECT s.sp_id,s.sp_group_id,s.avail_status
                         FROM sp_list s
                         LEFT JOIN sp_group g ON g.sp_group_id=s.sp_group_id
                         WHERE g.sp_group_id IS NULL
                         ORDER BY s.sp_id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
    if ($lock !== $orphans) {
        throw new RuntimeException('Orphan snapshot changed before reconciliation.');
    }
    $update = $pdo->prepare("UPDATE sp_list SET sp_group_id=0 WHERE sp_id=:sp_id AND avail_status=0");
    foreach ($orphans as $row) {
        $update->execute([':sp_id' => $row['sp_id']]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Expected orphan row was not reconciled.');
        }
    }
    $remaining = (int) $pdo->query("SELECT COUNT(*) FROM sp_list s LEFT JOIN sp_group g ON g.sp_group_id=s.sp_group_id WHERE g.sp_group_id IS NULL")->fetchColumn();
    if ($remaining !== 0) {
        throw new RuntimeException('Orphan references remain after reconciliation.');
    }
    $pdo->commit();
    printf("PASS reconciled=%d snapshot=%s sha256=%s\n", count($orphans), $snapshotPath, $hash);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "FAIL reconciliation rolled back: " . $exception->getMessage() . "\n");
    exit(1);
}
