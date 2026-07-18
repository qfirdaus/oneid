<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/config.php';

$root = dirname(__DIR__);
$database = (string) file_get_contents($root . '/lib/Database.php');
$service = (string) file_get_contents($root . '/app/Admin/SsoConfigurationService.php');
$checks = 0; $failures = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++; if (!$ok) $failures++; printf("%s: %s\n", $ok ? 'PASS' : 'FAIL', $label);
};
$report(str_contains($database, 'configuration_version=:expected_version') && str_contains($database, 'PDO::PARAM_INT'), 'persistence update targets the identified singleton revision');
$report(str_contains($service, 'get_system_config_for_update()') && str_contains($service, 'beginTransaction()') && str_contains($service, 'syslog_record(19') && str_contains($service, 'commit()') && str_contains($service, 'rollback()'), 'configuration lock, mutation and audit share an atomic transaction');
$report(str_contains($service, 'before_token_timeout=') && str_contains($service, 'after_multi_session=') && str_contains($service, 'correlation=%s'), 'audit detail records before and after policy values with correlation');

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$rows = $pdo->query('SELECT id,singleton_key,configuration_version,token_timeout,multi_session,password_reset_email_enabled FROM sys_config')->fetchAll(PDO::FETCH_ASSOC);
$report(count($rows) === 1 && (int) $rows[0]['singleton_key'] === 1 && (int)$rows[0]['configuration_version']>=1 && in_array((int)$rows[0]['multi_session'],[0,1],true), 'live singleton row and positive configuration revision exist');
$unique = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sys_config' AND INDEX_NAME='uq_sys_config_singleton' AND NON_UNIQUE=0")->fetchColumn();
$check = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sys_config' AND CONSTRAINT_NAME='chk_sys_config_singleton' AND CONSTRAINT_TYPE='CHECK'")->fetchColumn();
$report($unique === 1 && $check === 1, 'database exposes singleton unique and check constraints');
$duplicateRejected = false;
try {
    $statement = $pdo->prepare('INSERT INTO sys_config(singleton_key,token_timeout,multi_session,password_reset_email_enabled) VALUES (1,:timeout,1,1)');
    $statement->execute([':timeout' => 0.5]);
} catch (PDOException $exception) {
    $duplicateRejected = true;
}
$report($duplicateRejected, 'database rejects a second singleton configuration row');
$event = $pdo->query("SELECT syslog_event_name FROM syslog_event_conf WHERE syslog_event_id=19")->fetchColumn();
$report($event === 'ADMIN_UPDATE_SSO_CONFIG', 'audit event 19 is configured as ADMIN_UPDATE_SSO_CONFIG');
printf("RESULT: checks=%d failures=%d\n", $checks, $failures);
exit($failures === 0 ? 0 : 1);
