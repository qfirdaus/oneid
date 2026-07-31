<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}
require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/user_mfa_totp_email_recovery_schema.php --check|--apply\n");
    exit(2);
}
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$exists = static fn(): bool => (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name='user_mfa_recovery_challenges'"
)->fetchColumn() === 1;

$installed = $exists();
if ($mode === '--apply' && !$installed) {
    $reference = (string) getenv('ONEID_USER_MFA_RECOVERY_SCHEMA_REFERENCE');
    if (getenv('ONEID_USER_MFA_RECOVERY_SCHEMA_CONFIRMATION')
            !== 'APPLY USER MFA TOTP EMAIL RECOVERY SCHEMA'
        || preg_match('/\A[A-Za-z0-9._-]{8,100}\z/', $reference) !== 1
    ) {
        fwrite(STDERR, "FAIL USER_MFA_RECOVERY_SCHEMA_AUTHORIZATION\n");
        exit(1);
    }
    $pdo->exec((string) file_get_contents(
        dirname(__DIR__) . '/docs/migrations/20260731_user_mfa_totp_email_recovery_up.sql'
    ));
    $installed = $exists();
}
$columns = $installed ? (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema=DATABASE() AND table_name='user_mfa_recovery_challenges'"
)->fetchColumn() : 0;
$open = $installed ? (int) $pdo->query(
    "SELECT COUNT(*) FROM user_mfa_recovery_challenges
      WHERE consumed_at IS NULL AND revoked_at IS NULL AND expires_at>NOW(6)"
)->fetchColumn() : 0;
printf(
    "USER_MFA_TOTP_EMAIL_RECOVERY_SCHEMA installed=%s columns=%d open=%d mode=%s\n",
    $installed ? 'yes' : 'no',
    $columns,
    $open,
    $mode
);
exit($installed && $columns === 16 ? 0 : 1);
