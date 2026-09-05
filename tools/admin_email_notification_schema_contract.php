<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }

$root = dirname(__DIR__);
$up = (string) file_get_contents($root . '/docs/migrations/20260905_admin_email_notification_outbox_up.sql');
$down = (string) file_get_contents($root . '/docs/migrations/20260905_admin_email_notification_outbox_down.sql');
$plan = (string) file_get_contents($root . '/docs/ADMIN_EMAIL_NOTIFICATION_PLAN.md');
$checks = [
    'outbox and immutable delivery history are additive' => str_contains($up, 'CREATE TABLE admin_email_notification_outbox')
        && str_contains($up, 'CREATE TABLE admin_email_notification_delivery_history'),
    'idempotency and bounded dispatch indexes exist' => str_contains($up, 'uq_admin_email_notification_idempotency')
        && str_contains($up, 'idx_admin_email_notification_dispatch'),
    'delivery lifecycle is constrained' => str_contains($up, "'PENDING','PROCESSING','SENT','FAILED','SUPPRESSED'")
        && str_contains($up, 'attempt_count <= 100'),
    'history cannot orphan a notification' => str_contains($up, 'fk_admin_email_delivery_notification')
        && str_contains($up, 'ON DELETE RESTRICT'),
    'rollback removes child before parent' => strpos($down, 'admin_email_notification_delivery_history')
        < strpos($down, 'admin_email_notification_outbox'),
    'plan separates schema deployment and runtime activation' => str_contains($plan, 'separate controlled changes')
        && str_contains($plan, 'runtime default remains disabled'),
    'plan prohibits sensitive credential delivery' => str_contains($plan, 'API code')
        && str_contains($plan, 'raw session identifier'),
];
$failed = 0;
foreach ($checks as $label => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    $failed += $passed ? 0 : 1;
}
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
