<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}
require_once dirname(__DIR__) . '/lib/config.php';
$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply', '--rollback'], true)) {
    exit(2);
}
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$expected = [
    55 => 'USER_MFA_PRIMARY_AUTH_PENDING',
    56 => 'USER_MFA_EMAIL_CHALLENGE',
    57 => 'USER_MFA_EMAIL_VERIFY',
    58 => 'USER_MFA_TOTP_VERIFY',
    59 => 'USER_MFA_LOGIN_COMPLETE',
    60 => 'USER_MFA_FACTOR_ENROLL',
    61 => 'USER_MFA_FACTOR_REVOKE',
    62 => 'USER_MFA_PREFERENCE_CHANGE',
    63 => 'USER_MFA_ADMIN_RECOVERY',
    64 => 'USER_MFA_POLICY_CHANGE',
    65 => 'USER_MFA_RETENTION_PURGE',
];
$rows = $pdo->query(
    'SELECT syslog_event_id,syslog_event_name FROM syslog_event_conf
      WHERE syslog_event_id BETWEEN 55 AND 65
         OR syslog_event_name LIKE \'USER_MFA_%\''
)->fetchAll(PDO::FETCH_KEY_PAIR);
$complete = $rows === $expected;
$collision = $rows !== [] && !$complete;
printf(
    "USER_MFA_AUDIT_EVENTS events=%d/11 complete=%s collision=%s mode=%s\n",
    count($rows),
    $complete ? 'yes' : 'no',
    $collision ? 'yes' : 'no',
    $mode
);
if ($mode === '--check') {
    exit($complete ? 0 : 1);
}
if ((getenv('ONEID_USER_MFA_CHANGE_REFERENCE') ?: '') !== 'ONEID-USER-MFA-U1-20260730'
    || (getenv('ONEID_USER_MFA_MIGRATION_CONFIRMATION') ?: '')
        !== 'APPLY USER MFA AUDIT EVENT CATALOG WITH MODE OFF'
) {
    fwrite(STDERR, "FAIL USER_MFA_AUDIT_EVENT_AUTHORIZATION_INVALID\n");
    exit(1);
}
if ((string) oneid_config('ONEID_USER_MFA_MODE', '') !== 'OFF') {
    fwrite(STDERR, "FAIL USER_MFA_AUDIT_EVENT_REQUIRES_MODE_OFF\n");
    exit(1);
}
if ($mode === '--apply') {
    if ($collision) {
        fwrite(STDERR, "FAIL USER_MFA_AUDIT_EVENT_COLLISION\n");
        exit(1);
    }
    if (!$complete) {
        $pdo->exec((string) file_get_contents(
            dirname(__DIR__) . '/docs/migrations/20260730_user_login_mfa_audit_events_up.sql'
        ));
    }
    echo "PASS USER MFA audit event catalog installed events=11 mode_off=yes\n";
    exit(0);
}
$auditRows = (int) $pdo->query(
    'SELECT COUNT(*) FROM syslog WHERE log_type BETWEEN 55 AND 65'
)->fetchColumn();
if (!$complete || $auditRows !== 0) {
    fwrite(STDERR, "FAIL USER_MFA_AUDIT_EVENT_ROLLBACK_BLOCKED\n");
    exit(1);
}
$pdo->exec((string) file_get_contents(
    dirname(__DIR__) . '/docs/migrations/20260730_user_login_mfa_audit_events_down.sql'
));
echo "PASS USER MFA audit event catalog rolled back audit_rows=0\n";
