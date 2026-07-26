<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/bootstrap/app.php';
require_once dirname(__DIR__) . '/lib/secrets.php';
require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use OneId\App\Auth\MyDigitalId\MyDigitalIdIdentityProtector;

$checks = [];
$checks['environment_staging'] = oneid_config('ONEID_ENVIRONMENT', '') === 'staging';
$checks['auth_flag_false'] = oneid_config('ONEID_MYDID_ENABLED', '') === 'false';
$checks['schema_approval'] = oneid_config('ONEID_MYDID_SCHEMA_APPLY_ENABLED', '') === 'true';
foreach ([
    'change_reference' => 'ONEID_MYDID_SCHEMA_CHANGE_REFERENCE',
    'backup_reference' => 'ONEID_MYDID_SCHEMA_BACKUP_REFERENCE',
    'retention_reference' => 'ONEID_MYDID_AUDIT_RETENTION_REFERENCE',
] as $name => $key) {
    $checks[$name] = preg_match(
        '/^[A-Z0-9][A-Z0-9._-]{7,127}$/D',
        trim((string) oneid_config($key, ''))
    ) === 1;
}
$startRaw = trim((string) oneid_config('ONEID_MYDID_SCHEMA_WINDOW_START', ''));
$endRaw = trim((string) oneid_config('ONEID_MYDID_SCHEMA_WINDOW_END', ''));
$start = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $startRaw);
$end = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $endRaw);
$checks['change_window'] = $start instanceof DateTimeImmutable
    && $end instanceof DateTimeImmutable
    && $start->format(DateTimeInterface::ATOM) === $startRaw
    && $end->format(DateTimeInterface::ATOM) === $endRaw
    && $end > $start
    && ($end->getTimestamp() - $start->getTimestamp()) <= 7200;
try {
    MyDigitalIdIdentityProtector::fromRuntime();
    $checks['hmac_key'] = true;
} catch (Throwable) {
    $checks['hmac_key'] = false;
}
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD);
$checks['database'] = $pdo->query('SELECT DATABASE()')->fetchColumn() === 'oneiddb';
$checks['baseline_empty'] = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE()
       AND TABLE_NAME IN ('user_federated_identity','federated_auth_event')"
)->fetchColumn() === 0;

foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'BLOCKED', $name);
}
$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
printf("RESULT checks=%d blocked=%d mutations=0 secret_output=0\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
