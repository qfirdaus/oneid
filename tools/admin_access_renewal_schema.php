<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/admin_access_renewal_schema.php [--check|--apply]\n");
    exit(2);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$statement = $pdo->prepare(
    "SELECT COUNT(*) FROM syslog_event_conf
     WHERE syslog_event_id=67 AND syslog_event_name='ADMIN_ACCESS_RENEW'"
);
$statement->execute();
$installed = (int) $statement->fetchColumn() === 1;

if ($mode === '--apply' && !$installed) {
    $sql = (string) file_get_contents(
        dirname(__DIR__) . '/docs/migrations/20260806_admin_access_renewal_audit_up.sql'
    );
    if ($sql === '') {
        throw new RuntimeException('ADMIN_ACCESS_RENEWAL_MIGRATION_UNAVAILABLE');
    }
    $pdo->exec($sql);
    $statement->execute();
    $installed = (int) $statement->fetchColumn() === 1;
}

echo ($installed ? 'PASS' : 'FAIL') . ' admin_access_renewal_audit_event=67 mode=' . $mode . PHP_EOL;
exit($installed ? 0 : 1);
