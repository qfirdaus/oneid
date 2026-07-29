<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$expectedTables = [
    'user_login_mfa_policy',
    'user_login_mfa_policy_history',
    'user_mfa_factors',
    'user_mfa_preferences',
    'user_login_mfa_transactions',
    'user_login_mfa_challenges',
];
$placeholders = implode(',', array_fill(0, count($expectedTables), '?'));
$statement = $pdo->prepare(
    "SELECT table_name
       FROM information_schema.tables
      WHERE table_schema=DATABASE()
        AND table_name IN ({$placeholders})
      ORDER BY table_name"
);
$statement->execute($expectedTables);
$present = array_map(
    static fn(array $row): string => (string) array_values($row)[0],
    $statement->fetchAll()
);
$missing = array_values(array_diff($expectedTables, $present));

$userTable = $pdo->query(
    "SELECT COUNT(*) count_value
       FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name='user_tbl'"
)->fetch();
$engine = $pdo->query(
    "SELECT engine
       FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name='user_tbl'"
)->fetchColumn();
$version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
$mysqlCompatible = preg_match('/\A(?:8\\.|9\\.)/', $version) === 1;

$runtimeOff = (string) oneid_config('ONEID_USER_MFA_MODE', '') === 'OFF';
$schemaApplyOff = !filter_var(
    oneid_config('ONEID_USER_MFA_SCHEMA_APPLY_ENABLED', 'false'),
    FILTER_VALIDATE_BOOLEAN
);
$activationOff = !filter_var(
    oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED', 'false'),
    FILTER_VALIDATE_BOOLEAN
);

printf(
    "shared_database=yes user_tbl_present=%s user_tbl_engine=%s mysql_8_compatible=%s\n",
    (int) ($userTable['count_value'] ?? 0) === 1 ? 'yes' : 'no',
    preg_replace('/[^A-Za-z0-9_-]/', '', (string) $engine),
    $mysqlCompatible ? 'yes' : 'no'
);
printf(
    "user_mfa_tables_present=%d user_mfa_tables_missing=%d table_names_output=0\n",
    count($present),
    count($missing)
);
printf(
    "mode_off=%s schema_apply_disabled=%s activation_unauthorized=%s\n",
    $runtimeOff ? 'yes' : 'no',
    $schemaApplyOff ? 'yes' : 'no',
    $activationOff ? 'yes' : 'no'
);

$passed = (int) ($userTable['count_value'] ?? 0) === 1
    && strtoupper((string) $engine) === 'INNODB'
    && $mysqlCompatible
    && $runtimeOff
    && $schemaApplyOff
    && $activationOff
    && (count($present) === 0 || count($present) === count($expectedTables));
$partial = count($present) > 0 && count($present) < count($expectedTables);

printf(
    "RESULT status=%s read_only=yes mutation_statements=0 partial_schema=%s migration_required=%s\n",
    $passed ? 'PASS' : 'FAIL',
    $partial ? 'yes' : 'no',
    count($present) === 0 ? 'yes' : 'no'
);
exit($passed ? 0 : 1);
