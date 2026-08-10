<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/lib/config.php';
require_once $root . '/app/Audit/AuditIdentityResolver.php';

use OneId\App\Audit\AuditIdentityResolver;

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$resolver = new AuditIdentityResolver($pdo);
$staff = $pdo->query(
    "SELECT u_id,data3 FROM user_tbl
      WHERE u_id REGEXP '^[0-9]{12}$' AND data3 REGEXP '^[0-9]{4}-[0-9]{2}$'
      LIMIT 1"
)->fetch();

$checks = [
    'known IC resolves to staff number' => is_array($staff)
        && $resolver->resolve((string) $staff['u_id']) === (string) $staff['data3'],
    'known IC is absent after detail sanitization' => is_array($staff)
        && !str_contains($resolver->sanitizeDetail('admin=' . $staff['u_id']), (string) $staff['u_id']),
    'unknown IC is redacted' => $resolver->resolve('991399991234') === '[ID_REDACTED]',
    'staff number remains stable' => $resolver->resolve('0530-09') === '0530-09',
    'matric number remains stable' => $resolver->resolve('1234567') === '1234567',
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    $failed += $passed ? 0 : 1;
}
exit($failed === 0 ? 0 : 1);
