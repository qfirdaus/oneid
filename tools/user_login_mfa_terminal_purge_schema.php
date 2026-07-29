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
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$clause = (string) $pdo->query(
    "SELECT check_clause FROM information_schema.check_constraints
      WHERE constraint_schema=DATABASE()
        AND constraint_name='chk_user_mfa_challenge_material'"
)->fetchColumn();
$normalizedClause = strtolower(str_replace('`', '', $clause));
$ready = str_contains($normalizedClause, 'consumed_at')
    && str_contains($normalizedClause, 'revoked_at')
    && str_contains($normalizedClause, 'otp_hash is null');
printf("USER_MFA_TERMINAL_PURGE_SCHEMA ready=%s mode=%s\n", $ready ? 'yes' : 'no', $mode);
if ($mode === '--check') {
    exit($ready ? 0 : 1);
}
if ((getenv('ONEID_USER_MFA_CHANGE_REFERENCE') ?: '') !== 'ONEID-USER-MFA-U1-20260730'
    || (getenv('ONEID_USER_MFA_MIGRATION_CONFIRMATION') ?: '')
        !== 'APPLY USER MFA TERMINAL OTP PURGE CONSTRAINT WITH MODE OFF'
) {
    fwrite(STDERR, "FAIL USER_MFA_TERMINAL_PURGE_AUTHORIZATION_INVALID\n");
    exit(1);
}
if ((string) oneid_config('ONEID_USER_MFA_MODE', '') !== 'OFF'
    || filter_var(
        oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED', 'false'),
        FILTER_VALIDATE_BOOLEAN
    )
) {
    fwrite(STDERR, "FAIL USER_MFA_TERMINAL_PURGE_REQUIRES_MODE_OFF\n");
    exit(1);
}
$challengeRows = (int) $pdo->query(
    'SELECT COUNT(*) FROM user_login_mfa_challenges'
)->fetchColumn();
if ($challengeRows !== 0) {
    fwrite(STDERR, "FAIL USER_MFA_TERMINAL_PURGE_REQUIRES_EMPTY_CHALLENGES\n");
    exit(1);
}
if ($mode === '--apply') {
    if (!$ready) {
        $pdo->exec((string) file_get_contents(
            dirname(__DIR__) . '/docs/migrations/20260730_user_login_mfa_terminal_otp_purge_up.sql'
        ));
    }
    echo "PASS USER MFA terminal OTP purge constraint installed challenge_rows=0 mode_off=yes\n";
    exit(0);
}
if (!$ready) {
    fwrite(STDERR, "FAIL USER_MFA_TERMINAL_PURGE_ROLLBACK_NOT_APPLIED\n");
    exit(1);
}
$pdo->exec((string) file_get_contents(
    dirname(__DIR__) . '/docs/migrations/20260730_user_login_mfa_terminal_otp_purge_down.sql'
));
echo "PASS USER MFA terminal OTP purge constraint rolled back challenge_rows=0\n";
