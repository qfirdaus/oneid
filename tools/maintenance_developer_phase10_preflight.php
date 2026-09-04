<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }
define('ONEID_MAINTENANCE_BYPASS', true);
require_once dirname(__DIR__) . '/lib/config.php';

$value = static fn(string $key): string => trim((string) oneid_config($key, ''));
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$tables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('maintenance_developer_access_grants','maintenance_developer_access_history')")->fetchColumn();
$activeGrants = $tables === 2 ? (int) $pdo->query(
    "SELECT COUNT(*) FROM maintenance_developer_access_grants WHERE grant_status='ACTIVE'"
)->fetchColumn() : -1;
$pilotId = $value('ONEID_MAINTENANCE_DEVELOPER_PILOT_USER_ID');
$pilotEligible = false;
if (preg_match('/\A[A-Za-z0-9._@-]{1,20}\z/', $pilotId) === 1) {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM user_tbl WHERE u_id=? AND u_type=0 AND avail_status=1');
    $statement->execute([$pilotId]);
    $pilotEligible = (int) $statement->fetchColumn() === 1;
}
$startRaw = $value('ONEID_MAINTENANCE_DEVELOPER_PILOT_WINDOW_START');
$endRaw = $value('ONEID_MAINTENANCE_DEVELOPER_PILOT_WINDOW_END');
$start = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $startRaw);
$end = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $endRaw);
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur'));
$window = $start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable
    && $start->format(DateTimeInterface::ATOM) === $startRaw
    && $end->format(DateTimeInterface::ATOM) === $endRaw
    && $end > $start && ($end->getTimestamp() - $start->getTimestamp()) <= 7200
    && $now >= $start && $now <= $end;
$checks = [
    'environment_is_staging' => $value('ONEID_ENVIRONMENT') === 'staging',
    'exact_database_is_oneiddb' => $database === 'oneiddb',
    'maintenance_schema_is_complete' => $tables === 2,
    'schema_apply_is_closed' => $value('ONEID_MAINTENANCE_DEVELOPER_SCHEMA_APPLY_ENABLED') === 'false',
    'feature_is_dormant_before_activation' => $value('ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED') === 'false',
    'no_existing_active_grants' => $activeGrants === 0,
    'user_mfa_is_enforced' => $value('ONEID_USER_MFA_MODE') === 'ENFORCED',
    'user_mfa_email_is_enabled' => filter_var($value('ONEID_USER_MFA_EMAIL_ENABLED'), FILTER_VALIDATE_BOOLEAN),
    'pilot_is_approved' => filter_var($value('ONEID_MAINTENANCE_DEVELOPER_PILOT_APPROVED'), FILTER_VALIDATE_BOOLEAN),
    'pilot_change_reference_is_valid' => preg_match('/^[A-Z0-9][A-Z0-9._-]{7,127}$/D',
        $value('ONEID_MAINTENANCE_DEVELOPER_PILOT_CHANGE_REFERENCE')) === 1,
    'pilot_account_is_active_u_type_zero' => $pilotEligible,
    'active_pilot_window_is_valid' => $window,
];
foreach ($checks as $name => $passed) { printf("%s %s\n", $passed ? 'PASS' : 'BLOCKED', $name); }
$ready = !in_array(false, $checks, true);
printf("DECISION=%s live_mutations=0 active_grants=%d feature_enabled=false\n", $ready ? 'GO' : 'NO_GO', $activeGrants);
exit($ready ? 0 : 1);
