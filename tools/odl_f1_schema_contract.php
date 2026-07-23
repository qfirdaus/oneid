<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$upPath = $root . '/docs/migrations/20260723_odl_f1_provenance_up.sql';
$downPath = $root . '/docs/migrations/20260723_odl_f1_provenance_down.sql';
$runnerPath = $root . '/tools/odl_f1_schema_migrate.php';
$up = is_file($upPath) ? (string) file_get_contents($upPath) : '';
$down = is_file($downPath) ? (string) file_get_contents($downPath) : '';
$runner = is_file($runnerPath) ? (string) file_get_contents($runnerPath) : '';
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$report($up !== '' && $down !== '', 'up and down migrations exist');
$report(
    str_contains($up, 'CREATE TABLE external_source')
        && str_contains($up, 'CREATE TABLE user_external_identity'),
    'provenance tables are additive'
);
$report(
    str_contains($up, 'VARCHAR(20) NOT NULL')
        && str_contains($up, 'utf8mb4_0900_ai_ci')
        && str_contains($up, 'ENGINE=InnoDB'),
    'identity type engine and collation match live user_tbl'
);
$report(
    str_contains($up, 'fk_external_identity_user')
        && str_contains($up, 'fk_external_identity_source')
        && str_contains($up, 'ON DELETE RESTRICT'),
    'foreign keys preserve users and source registry'
);
$report(
    str_contains($up, 'uq_external_identity_source_user')
        && str_contains($up, 'uq_external_identity_user_source'),
    'source identity and user membership are unique'
);
$report(
    str_contains($up, "'STUDENT_ODL_PG'")
        && str_contains($up, "'dormant'")
        && str_contains($up, "'student'"),
    'STUDENT_ODL_PG is registered dormant'
);
$report(
    !str_contains($up, 'user_tbl SET')
        && !str_contains($up, 'UPDATE user_tbl')
        && !str_contains($up, 'INSERT INTO user_tbl')
        && !str_contains($up, 'DELETE FROM user_tbl'),
    'migration cannot mutate user_tbl rows'
);
$report(
    !str_contains($up, 'student_basic_info')
        && !str_contains($up, '172.16.2.224')
        && !str_contains($up, 'ONEID_ODL_DB_'),
    'migration has no datasource or runtime wiring'
);
$report(
    str_contains($down, 'DROP TABLE user_external_identity')
        && str_contains($down, 'DROP TABLE external_source'),
    'rollback order respects foreign keys'
);
$report(
    str_contains($runner, 'ODL_F1_LIVE_USER_SCHEMA_INCOMPATIBLE')
        && str_contains($runner, 'ODL_F1_PARTIAL_SCHEMA_REQUIRES_RECONCILIATION'),
    'live apply fails closed on incompatible or partial schema'
);
$report(
    str_contains($runner, '$memberships !== 0')
        && str_contains($runner, '$nonDormant !== 0')
        && str_contains($runner, '$sourceCount !== 1'),
    'live rollback requires one dormant empty source registry'
);
$report(
    str_contains($runner, 'ONEID-ODL-F1-20260723-01')
        && str_contains($runner, 'ODL_F1_CHANGE_ID_REQUIRED'),
    'live mutation modes require exact change ID'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
