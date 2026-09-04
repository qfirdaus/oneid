<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }
define('ONEID_MAINTENANCE_BYPASS', true);
require_once dirname(__DIR__) . '/lib/config.php';

$value = static fn(string $key): string => trim((string) oneid_config($key, ''));
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$tables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('maintenance_developer_access_grants','maintenance_developer_access_history')")->fetchColumn();
$startRaw = $value('ONEID_MAINTENANCE_DEVELOPER_SCHEMA_WINDOW_START');
$endRaw = $value('ONEID_MAINTENANCE_DEVELOPER_SCHEMA_WINDOW_END');
$start = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $startRaw);
$end = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $endRaw);
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur'));
$window = $start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable
    && $start->format(DateTimeInterface::ATOM) === $startRaw
    && $end->format(DateTimeInterface::ATOM) === $endRaw
    && $end > $start && ($end->getTimestamp() - $start->getTimestamp()) <= 7200
    && $now >= $start && $now <= $end;
$reference = static fn(string $input): bool => preg_match('/^[A-Z0-9][A-Z0-9._-]{7,127}$/D', $input) === 1;
$checks = [
    'environment_is_staging' => $value('ONEID_ENVIRONMENT') === 'staging',
    'exact_database_is_oneiddb' => $database === 'oneiddb',
    'feature_remains_disabled' => $value('ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED') === 'false',
    'schema_baseline_is_empty' => $tables === 0,
    'schema_apply_is_approved' => $value('ONEID_MAINTENANCE_DEVELOPER_SCHEMA_APPLY_ENABLED') === 'true',
    'change_reference_is_valid' => $reference($value('ONEID_MAINTENANCE_DEVELOPER_SCHEMA_CHANGE_REFERENCE')),
    'backup_reference_is_valid' => $reference($value('ONEID_MAINTENANCE_DEVELOPER_SCHEMA_BACKUP_REFERENCE')),
    'active_change_window_is_valid' => $window,
];
foreach ($checks as $name => $passed) { printf("%s %s\n", $passed ? 'PASS' : 'BLOCKED', $name); }
$ready = !in_array(false, $checks, true);
printf("DECISION=%s live_mutations=0 schema_tables=%d/2\n", $ready ? 'GO' : 'NO_GO', $tables);
exit($ready ? 0 : 1);
