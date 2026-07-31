<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}
require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/user_login_mfa_temporary_exemption_schema.php --check|--apply\n");
    exit(2);
}
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$exists = static function () use ($pdo): bool {
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema=DATABASE()
            AND table_name='user_login_mfa_exemptions'"
    )->fetchColumn() === 1;
};

$installed = $exists();
if ($mode === '--apply' && !$installed) {
    $reference = (string) getenv('ONEID_USER_MFA_EXEMPTION_SCHEMA_REFERENCE');
    if (getenv('ONEID_USER_MFA_EXEMPTION_SCHEMA_CONFIRMATION')
            !== 'APPLY USER MFA TEMPORARY EXEMPTION SCHEMA'
        || preg_match('/\A[A-Za-z0-9._-]{8,100}\z/', $reference) !== 1
    ) {
        fwrite(STDERR, "FAIL USER_MFA_EXEMPTION_SCHEMA_AUTHORIZATION\n");
        exit(1);
    }
    $sql = (string) file_get_contents(
        dirname(__DIR__) . '/docs/migrations/20260731_user_login_mfa_temporary_exemption_up.sql'
    );
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
    $installed = $exists();
}

$columns = $installed ? (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema=DATABASE() AND table_name='user_login_mfa_exemptions'"
)->fetchColumn() : 0;
$active = $installed ? (int) $pdo->query(
    "SELECT COUNT(*) FROM user_login_mfa_exemptions
      WHERE exemption_status='ACTIVE' AND starts_at<=NOW(6) AND expires_at>NOW(6)"
)->fetchColumn() : 0;
printf(
    "USER_MFA_EXEMPTION_SCHEMA installed=%s columns=%d active=%d mode=%s\n",
    $installed ? 'yes' : 'no',
    $columns,
    $active,
    $mode
);
exit($installed && $columns === 15 ? 0 : 1);
