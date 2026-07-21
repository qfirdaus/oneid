<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$passed) {
        $failed++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};
$scalar = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();

$tables = $scalar(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE()
       AND TABLE_NAME IN ('admin_mfa_factors','admin_mfa_preferences',
                          'admin_step_up_challenges','admin_step_up_grants')"
);
$report($tables === 4, 'all four F7.1 stores are installed');

$foreignKeys = $scalar(
    "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA=DATABASE()
       AND TABLE_NAME IN ('admin_mfa_factors','admin_mfa_preferences',
                          'admin_step_up_challenges','admin_step_up_grants')"
);
$report($foreignKeys === 4, 'each F7.1 store binds to canonical user_tbl identity');

$checksInstalled = $scalar(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='CHECK'
       AND (TABLE_NAME LIKE 'admin_%'
            OR (TABLE_NAME='sys_config'
                AND CONSTRAINT_NAME='chk_sys_config_admin_2fa_enabled'))"
);
$report($checksInstalled === 9, 'all nine F7.1 value and safety constraints are installed');

$flag = $scalar('SELECT admin_2fa_enabled FROM sys_config WHERE singleton_key=1');
$report($flag === 0, 'Admin 2FA remains fail-closed OFF after migration');

$rows = 0;
foreach (['admin_mfa_factors', 'admin_mfa_preferences', 'admin_step_up_challenges', 'admin_step_up_grants'] as $table) {
    $rows += $scalar('SELECT COUNT(*) FROM `' . $table . '`');
}
$report($rows === 0, 'new F7.1 stores contain no implicit enrollment, challenge or grant');

printf("RESULT checks=%d failed=%d mutation_statements=0\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
