<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/user_portal_session_schema.php [--check|--apply]\n");
    exit(2);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$statement = $pdo->query(
    "SELECT COUNT(*) FROM syslog_event_conf
     WHERE (syslog_event_id=68 AND syslog_event_name='USER_PORTAL_SESSION_EXPIRED')
        OR (syslog_event_id=69 AND syslog_event_name='USER_PORTAL_SESSION_RENEWED')
        OR (syslog_event_id=70 AND syslog_event_name='USER_PORTAL_SESSION_ENDED')"
);
$installed = (int) $statement->fetchColumn() === 3;

if ($mode === '--apply' && !$installed) {
    $sql = (string) file_get_contents(
        dirname(__DIR__) . '/docs/migrations/20260807_user_portal_session_audit_up.sql'
    );
    if ($sql === '') {
        throw new RuntimeException('USER_PORTAL_SESSION_MIGRATION_UNAVAILABLE');
    }
    $pdo->exec($sql);
    $statement->execute();
    $installed = (int) $statement->fetchColumn() === 3;
}

echo ($installed ? 'PASS' : 'FAIL') . ' user_portal_session_audit_events=68,69,70 mode=' . $mode . PHP_EOL;
exit($installed ? 0 : 1);
