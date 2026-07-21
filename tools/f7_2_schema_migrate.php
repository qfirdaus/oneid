<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/f7_2_schema_migrate.php [--check|--apply]\n");
    exit(2);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$column = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_step_up_challenges'
       AND COLUMN_NAME='sent_at'"
)->fetchColumn();
$indexes = (int) $pdo->query(
    "SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_step_up_challenges'
       AND INDEX_NAME IN ('idx_admin_step_up_challenge_rate','idx_admin_step_up_challenge_ip_rate')"
)->fetchColumn();
$events = (int) $pdo->query(
    "SELECT COUNT(*) FROM syslog_event_conf
     WHERE (syslog_event_id=37 AND syslog_event_name='ADMIN_2FA_REQUESTED')
        OR (syslog_event_id=38 AND syslog_event_name='ADMIN_2FA_SENT')
        OR (syslog_event_id=39 AND syslog_event_name='ADMIN_2FA_VERIFIED')
        OR (syslog_event_id=40 AND syslog_event_name='ADMIN_2FA_FAILED')
        OR (syslog_event_id=41 AND syslog_event_name='ADMIN_2FA_EXPIRED')
        OR (syslog_event_id=42 AND syslog_event_name='ADMIN_2FA_RATE_LIMITED')
        OR (syslog_event_id=43 AND syslog_event_name='ADMIN_2FA_DELIVERY_FAILED')"
)->fetchColumn();
$complete = $column === 1 && $indexes === 2 && $events === 7;
printf("F7_2_SCHEMA sent_at=%d indexes=%d/2 events=%d/7 mode=%s\n", $column, $indexes, $events, $mode);

if ($mode === '--check') {
    exit($complete ? 0 : 1);
}
if ($complete) {
    exit(0);
}
if ($column !== 0 || $indexes !== 0 || $events !== 0) {
    fwrite(STDERR, "FAIL F7_2_PARTIAL_SCHEMA_REQUIRES_MANUAL_RECONCILIATION\n");
    exit(1);
}
if ((int) $pdo->query('SELECT admin_2fa_enabled FROM sys_config WHERE singleton_key=1')->fetchColumn() !== 0) {
    fwrite(STDERR, "FAIL F7_2_REQUIRES_FEATURE_OFF\n");
    exit(1);
}
if ((getenv('ONEID_F7_CHANGE_ID') ?: '') !== 'ONEID-F7-2FA-20260720-01') {
    fwrite(STDERR, "FAIL F7_CHANGE_ID_REQUIRED\n");
    exit(1);
}

$evidencePath = getenv('ONEID_F7_BACKUP_EVIDENCE') ?: '';
if ($evidencePath === '' || !is_file($evidencePath) || !is_readable($evidencePath)) {
    fwrite(STDERR, "FAIL F7_BACKUP_EVIDENCE_REQUIRED\n");
    exit(1);
}
$evidence = parse_ini_file($evidencePath, false, INI_SCANNER_RAW);
$backupFile = is_array($evidence) ? (string) ($evidence['backup_file'] ?? '') : '';
$expectedHash = is_array($evidence) ? (string) ($evidence['backup_sha256'] ?? '') : '';
if (!is_array($evidence)
    || ($evidence['source_modified'] ?? '') !== 'no'
    || ($evidence['restore_completed'] ?? '') !== 'yes'
    || ($evidence['exact_row_count_reconciliation'] ?? '') !== 'pass'
    || ($evidence['restore_target_dropped'] ?? '') !== 'yes'
    || preg_match('/\A[a-f0-9]{64}\z/', $expectedHash) !== 1
    || !is_file($backupFile)
    || !hash_equals($expectedHash, (string) hash_file('sha256', $backupFile))
) {
    fwrite(STDERR, "FAIL F7_BACKUP_EVIDENCE_INVALID\n");
    exit(1);
}

$migration = (string) file_get_contents(
    dirname(__DIR__) . '/docs/migrations/20260720_f7_2_email_otp_up.sql'
);
$pdo->exec($migration);
echo "PASS F7.2 email OTP schema installed feature_off=1\n";
