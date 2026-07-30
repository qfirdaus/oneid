<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}
require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check','--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/user_login_mfa_category_schema.php --check|--apply\n");
    exit(2);
}
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$exists = static function (string $table) use ($pdo): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema=DATABASE() AND table_name=:table'
    );
    $statement->execute([':table' => $table]);
    return (int) $statement->fetchColumn() === 1;
};
$policy = $exists('user_login_mfa_category_policy');
$history = $exists('user_login_mfa_category_policy_history');
if ($mode === '--apply' && (!$policy || !$history)) {
    if ($policy || $history
        || getenv('ONEID_USER_MFA_CATEGORY_SCHEMA_CONFIRMATION')
            !== 'APPLY USER MFA CATEGORY POLICY SCHEMA'
        || preg_match(
            '/\A[A-Za-z0-9._-]{8,100}\z/',
            (string) getenv('ONEID_USER_MFA_CATEGORY_SCHEMA_REFERENCE')
        ) !== 1
    ) {
        fwrite(STDERR, "FAIL USER_MFA_CATEGORY_SCHEMA_AUTHORIZATION\n");
        exit(1);
    }
    $sql = (string) file_get_contents(
        dirname(__DIR__) . '/docs/migrations/20260730_user_login_mfa_category_policy_up.sql'
    );
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
    $policy = $exists('user_login_mfa_category_policy');
    $history = $exists('user_login_mfa_category_policy_history');
}
$rows = $policy ? $pdo->query(
    'SELECT COUNT(*) FROM user_login_mfa_category_policy
      WHERE category_code IN (\'STAFF\',\'STUDENT\') AND enforcement_enabled=1'
)->fetchColumn() : 0;
printf(
    "USER_MFA_CATEGORY_SCHEMA policy=%s history=%s default_enabled=%d mode=%s\n",
    $policy ? 'yes' : 'no',
    $history ? 'yes' : 'no',
    (int) $rows,
    $mode
);
exit($policy && $history && (int) $rows === 2 ? 0 : 1);
