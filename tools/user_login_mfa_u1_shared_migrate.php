<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply', '--rollback'], true)) {
    fwrite(STDERR, "Usage: php tools/user_login_mfa_u1_shared_migrate.php [--check|--apply|--rollback]\n");
    exit(2);
}

$expectedTables = [
    'user_login_mfa_policy',
    'user_login_mfa_policy_history',
    'user_mfa_factors',
    'user_mfa_preferences',
    'user_login_mfa_transactions',
    'user_login_mfa_challenges',
];
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$tableState = static function () use ($pdo, $expectedTables): array {
    $placeholders = implode(',', array_fill(0, count($expectedTables), '?'));
    $statement = $pdo->prepare(
        "SELECT table_name FROM information_schema.tables
          WHERE table_schema=DATABASE() AND table_name IN ({$placeholders})
          ORDER BY table_name"
    );
    $statement->execute($expectedTables);
    return $statement->fetchAll(PDO::FETCH_COLUMN, 0);
};
$splitSql = static function (string $sql): array {
    return array_values(array_filter(
        array_map('trim', preg_split('/;\s*(?:\R|$)/', $sql) ?: []),
        static fn(string $statement): bool => $statement !== ''
    ));
};

$present = $tableState();
$policy = false;
if (count($present) === count($expectedTables)) {
    $policy = $pdo->query(
        'SELECT policy_mode,login_scope,email_enabled,totp_enabled
           FROM user_login_mfa_policy WHERE singleton_key=1'
    )->fetch();
}
$complete = count($present) === count($expectedTables)
    && is_array($policy)
    && $policy['policy_mode'] === 'OFF'
    && $policy['login_scope'] === 'PASSWORD_ONLY'
    && (int) $policy['email_enabled'] === 1
    && (int) $policy['totp_enabled'] === 0;
$partial = count($present) > 0 && count($present) < count($expectedTables);
printf(
    "USER_MFA_U1_SHARED tables=%d/6 complete_off=%s partial=%s mode=%s\n",
    count($present),
    $complete ? 'yes' : 'no',
    $partial ? 'yes' : 'no',
    $mode
);

if ($mode === '--check') {
    exit($complete ? 0 : 1);
}

$changeReference = getenv('ONEID_USER_MFA_CHANGE_REFERENCE') ?: '';
$backupReference = getenv('ONEID_USER_MFA_BACKUP_REFERENCE') ?: '';
$confirmation = getenv('ONEID_USER_MFA_MIGRATION_CONFIRMATION') ?: '';
if ($changeReference !== 'ONEID-USER-MFA-U1-20260730'
    || $backupReference !== 'ONEID-DB-BACKUP-20260730-U1'
    || $confirmation !== 'APPLY USER MFA U1 SHARED SCHEMA WITH MODE OFF'
) {
    fwrite(STDERR, "FAIL USER_MFA_U1_EXECUTION_AUTHORIZATION_INVALID\n");
    exit(1);
}
if ((string) oneid_config('ONEID_USER_MFA_MODE', '') !== 'OFF'
    || filter_var(
        oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED', 'false'),
        FILTER_VALIDATE_BOOLEAN
    )
) {
    fwrite(STDERR, "FAIL USER_MFA_U1_REQUIRES_MODE_OFF_UNAUTHORIZED\n");
    exit(1);
}

if ($mode === '--apply') {
    if ($complete) {
        echo "PASS USER MFA U1 shared schema already installed mode_off=yes\n";
        exit(0);
    }
    if ($partial) {
        fwrite(STDERR, "FAIL USER_MFA_U1_PARTIAL_SCHEMA_MANUAL_RECONCILIATION_REQUIRED\n");
        exit(1);
    }

    $userRowsBefore = (int) $pdo->query('SELECT COUNT(*) FROM user_tbl')->fetchColumn();
    $userDefinitionBefore = (string) $pdo->query('SHOW CREATE TABLE user_tbl')->fetch()['Create Table'];
    $created = [];
    try {
        foreach ($splitSql((string) file_get_contents(
            dirname(__DIR__) . '/docs/migrations/20260729_user_login_mfa_u1_up.sql'
        )) as $statement) {
            $pdo->exec($statement);
            $created = $tableState();
        }
    } catch (Throwable $exception) {
        foreach ($splitSql((string) file_get_contents(
            dirname(__DIR__) . '/docs/migrations/20260729_user_login_mfa_u1_down.sql'
        )) as $statement) {
            $pdo->exec($statement);
        }
        fwrite(STDERR, "FAIL USER_MFA_U1_APPLY_COMPENSATED correlation="
            . bin2hex(random_bytes(8)) . "\n");
        exit(1);
    }

    $userRowsAfter = (int) $pdo->query('SELECT COUNT(*) FROM user_tbl')->fetchColumn();
    $userDefinitionAfter = (string) $pdo->query('SHOW CREATE TABLE user_tbl')->fetch()['Create Table'];
    $present = $tableState();
    $policy = $pdo->query(
        'SELECT policy_mode,login_scope,email_enabled,totp_enabled
           FROM user_login_mfa_policy WHERE singleton_key=1'
    )->fetch();
    $verified = count($present) === 6
        && is_array($policy)
        && $policy['policy_mode'] === 'OFF'
        && $policy['login_scope'] === 'PASSWORD_ONLY'
        && (int) $policy['email_enabled'] === 1
        && (int) $policy['totp_enabled'] === 0
        && $userRowsBefore === $userRowsAfter
        && hash_equals(hash('sha256', $userDefinitionBefore), hash('sha256', $userDefinitionAfter));
    if (!$verified) {
        fwrite(STDERR, "FAIL USER_MFA_U1_POST_APPLY_RECONCILIATION_REQUIRED\n");
        exit(1);
    }
    printf(
        "PASS USER MFA U1 shared schema installed tables=6 mode_off=yes user_tbl_rows_unchanged=yes user_tbl_definition_unchanged=yes change_reference=%s backup_reference=%s\n",
        $changeReference,
        $backupReference
    );
    exit(0);
}

if (!$complete) {
    fwrite(STDERR, "FAIL USER_MFA_U1_ROLLBACK_REQUIRES_COMPLETE_SCHEMA\n");
    exit(1);
}
$dataRows = 0;
foreach ([
    'user_login_mfa_policy_history',
    'user_mfa_factors',
    'user_mfa_preferences',
    'user_login_mfa_transactions',
    'user_login_mfa_challenges',
] as $table) {
    $dataRows += (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}
if ($dataRows !== 0) {
    fwrite(STDERR, "FAIL USER_MFA_U1_ROLLBACK_BLOCKED_NONDEFAULT_DATA\n");
    exit(1);
}
foreach ($splitSql((string) file_get_contents(
    dirname(__DIR__) . '/docs/migrations/20260729_user_login_mfa_u1_down.sql'
)) as $statement) {
    $pdo->exec($statement);
}
echo "PASS USER MFA U1 shared schema rolled back data_rows=0\n";
