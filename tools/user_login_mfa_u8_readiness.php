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
$table = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name='user_login_mfa_pilot_users'"
)->fetchColumn() === 1;
$policy = $pdo->query(
    'SELECT policy_mode,email_enabled,totp_enabled,configuration_version
       FROM user_login_mfa_policy WHERE singleton_key=1'
)->fetch();
$pilot = $table ? $pdo->query(
    "SELECT COUNT(*) total,
            SUM(pilot_status='ACTIVE') active,
            COUNT(DISTINCT CASE WHEN pilot_status='ACTIVE' THEN pilot_category END) categories
       FROM user_login_mfa_pilot_users"
)->fetch() : ['total' => 0, 'active' => 0, 'categories' => 0];
$mode = (string) oneid_config('ONEID_USER_MFA_MODE', '');
$authorized = filter_var(
    oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED', false),
    FILTER_VALIDATE_BOOLEAN
);
$runtimeSafe = $mode === 'OFF' && !$authorized;
$dbSafe = is_array($policy)
    && ($policy['policy_mode'] ?? '') === 'OFF'
    && (int) ($policy['email_enabled'] ?? 0) === 1;
printf(
    "USER_MFA_U8_READINESS runtime_off=%s db_off=%s pilot_schema=%s active_pilots=%d categories=%d totp_enabled=%d\n",
    $runtimeSafe ? 'yes' : 'no',
    $dbSafe ? 'yes' : 'no',
    $table ? 'yes' : 'no',
    (int) ($pilot['active'] ?? 0),
    (int) ($pilot['categories'] ?? 0),
    (int) ($policy['totp_enabled'] ?? 0)
);
printf(
    "RESULT development_ready=%s enrollment_activation_authorized=no shared_database_mutations=0\n",
    $runtimeSafe && $dbSafe && $table ? 'yes' : 'no'
);
exit($runtimeSafe && $dbSafe && $table ? 0 : 1);
