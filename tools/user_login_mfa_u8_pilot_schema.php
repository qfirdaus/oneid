<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply', '--rollback'], true)) {
    fwrite(STDERR, "Usage: php tools/user_login_mfa_u8_pilot_schema.php [--check|--apply|--rollback]\n");
    exit(2);
}
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$exists = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name='user_login_mfa_pilot_users'"
)->fetchColumn() === 1;
$rows = $exists
    ? (int) $pdo->query('SELECT COUNT(*) FROM user_login_mfa_pilot_users')->fetchColumn()
    : 0;
printf(
    "USER_MFA_U8_PILOT_SCHEMA installed=%s pilot_rows=%d mode=%s runtime=%s authorized=%s\n",
    $exists ? 'yes' : 'no',
    $rows,
    $mode,
    (string) oneid_config('ONEID_USER_MFA_MODE', ''),
    filter_var(
        oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED', false),
        FILTER_VALIDATE_BOOLEAN
    ) ? 'yes' : 'no'
);
if ($mode === '--check') {
    exit($exists ? 0 : 1);
}

$authorized = (getenv('ONEID_USER_MFA_U8_CHANGE_REFERENCE') ?: '')
        === 'ONEID-USER-MFA-U8-20260730'
    && (getenv('ONEID_USER_MFA_U8_CONFIRMATION') ?: '')
        === 'APPLY USER MFA U8 PILOT SCHEMA WITH MODE OFF'
    && (string) oneid_config('ONEID_USER_MFA_MODE', '') === 'OFF'
    && !filter_var(
        oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED', false),
        FILTER_VALIDATE_BOOLEAN
    );
if (!$authorized) {
    fwrite(STDERR, "FAIL USER_MFA_U8_SCHEMA_AUTHORIZATION_INVALID\n");
    exit(1);
}

if ($mode === '--apply') {
    if (!$exists) {
        $pdo->exec((string) file_get_contents(
            dirname(__DIR__) . '/docs/migrations/20260730_user_login_mfa_u8_pilot_up.sql'
        ));
    }
    echo "PASS USER MFA U8 pilot schema installed mode_off=yes pilot_rows=0\n";
    exit(0);
}
if ($rows !== 0) {
    fwrite(STDERR, "FAIL USER_MFA_U8_ROLLBACK_REQUIRES_EMPTY_PILOT_TABLE\n");
    exit(1);
}
if ($exists) {
    $pdo->exec((string) file_get_contents(
        dirname(__DIR__) . '/docs/migrations/20260730_user_login_mfa_u8_pilot_down.sql'
    ));
}
echo "PASS USER MFA U8 pilot schema rolled back mode_off=yes\n";
