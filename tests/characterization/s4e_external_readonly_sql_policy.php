<?php

require_once dirname(__DIR__, 2) . '/lib/readonly_odbc.php';

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};
$code = static function (string $sql): string {
    try {
        oneid_assert_readonly_select($sql);
        return 'ALLOWED';
    } catch (RuntimeException $exception) {
        return $exception->getMessage();
    }
};

$report($code('SELECT COUNT(*) FROM staff_view') === 'ALLOWED', 'plain SELECT is allowed');
$report($code("  select id FROM student_view WHERE id='A'  ") === 'ALLOWED', 'case-insensitive parameterizable SELECT is allowed');

$blocked = [
    'INSERT INTO staff_view VALUES (1)',
    'UPDATE staff_view SET name = 1',
    'DELETE FROM staff_view',
    'CREATE TABLE x (id INT)',
    'ALTER TABLE x ADD y INT',
    'DROP TABLE x',
    'TRUNCATE TABLE x',
    'MERGE INTO x USING y ON 1=1 WHEN MATCHED THEN DELETE',
    'EXEC dangerous_writer',
    'GRANT UPDATE ON x TO y',
    'REVOKE SELECT ON x FROM y',
    'SELECT * INTO copied_table FROM staff_view',
    'SELECT * FROM staff_view; DELETE FROM staff_view',
    'SELECT * FROM staff_view -- hidden statement',
    'SELECT * FROM staff_view /* hidden statement */',
    "WITH x AS (SELECT * FROM staff_view) SELECT * FROM x",
];
foreach ($blocked as $sql) {
    $report($code($sql) !== 'ALLOWED', 'blocked: ' . strtok($sql, " \t"));
}

$report($code('') === 'EXTERNAL_READONLY_SQL_INVALID', 'empty SQL is rejected');
$report($code("SELECT\0 1") === 'EXTERNAL_READONLY_SQL_INVALID', 'NUL byte is rejected');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);

