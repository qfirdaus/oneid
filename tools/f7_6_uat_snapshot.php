<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$finalGate = ($argv[1] ?? '') === '--final';
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = [];
$record = static function (string $name, bool $passed, string $detail) use (&$checks): void {
    $checks[$name] = ['passed' => $passed, 'detail' => $detail];
};

$pilot = $pdo->prepare(
    "SELECT c.admin_2fa_enabled,c.configuration_version,u.u_type,u.avail_status,u.data5 email,
        (SELECT COUNT(*) FROM admin_mfa_factors f WHERE f.admin_user_id=u.u_id AND f.factor_type='TOTP' AND f.factor_status='ACTIVE') active_totp,
        (SELECT COUNT(*) FROM admin_mfa_factors f WHERE f.admin_user_id=u.u_id AND f.factor_type='TOTP' AND f.factor_status='PENDING') pending_totp,
        COALESCE((SELECT preferred_factor FROM admin_mfa_preferences p WHERE p.admin_user_id=u.u_id),'NONE') preference
     FROM user_tbl u CROSS JOIN sys_config c
     WHERE u.data3=:staff_reference AND c.singleton_key=1"
);
$pilot->execute([':staff_reference' => '0530-09']);
$pilotRows = $pilot->fetchAll(PDO::FETCH_ASSOC);
$pilotRow = $pilotRows[0] ?? [];
$record('pilot_identity', count($pilotRows) === 1
    && (int) ($pilotRow['u_type'] ?? 0) === 1
    && (int) ($pilotRow['avail_status'] ?? 0) === 1,
    'single active admin pilot staff_reference=0530-09');
$record('feature_enabled', (int) ($pilotRow['admin_2fa_enabled'] ?? 0) === 1,
    'admin_2fa_enabled=' . (int) ($pilotRow['admin_2fa_enabled'] ?? -1)
    . ' configuration_version=' . (int) ($pilotRow['configuration_version'] ?? -1));
$record('factors_ready', filter_var((string) ($pilotRow['email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false
    && (int) ($pilotRow['active_totp'] ?? 0) === 1
    && (int) ($pilotRow['pending_totp'] ?? -1) === 0,
    'email_valid=' . (filter_var((string) ($pilotRow['email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false ? 'yes' : 'no')
    . ' active_totp=' . (int) ($pilotRow['active_totp'] ?? -1)
    . ' pending_totp=' . (int) ($pilotRow['pending_totp'] ?? -1));
$record('preference_valid', in_array((string) ($pilotRow['preference'] ?? ''), ['EMAIL_OTP', 'TOTP'], true),
    'preference=' . (string) ($pilotRow['preference'] ?? 'NONE'));

$schemaTables = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
     AND TABLE_NAME IN ('admin_mfa_factors','admin_mfa_preferences','admin_step_up_challenges','admin_step_up_grants')"
)->fetchColumn();
$auditEvents = (int) $pdo->query(
    'SELECT COUNT(*) FROM syslog_event_conf WHERE syslog_event_id BETWEEN 37 AND 53'
)->fetchColumn();
$record('schema_and_audit_dictionary', $schemaTables === 4 && $auditEvents === 17,
    "tables={$schemaTables}/4 events={$auditEvents}/17");

$evidence = [];
foreach ([39, 40, 42, 45, 46, 48, 50, 51] as $event) {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM syslog WHERE log_type=:event');
    $statement->execute([':event' => $event]);
    $evidence[$event] = (int) $statement->fetchColumn();
}
$evidenceReady = true;
foreach ($evidence as $count) {
    $evidenceReady = $evidenceReady && $count > 0;
}
$record('uat_audit_coverage', $evidenceReady,
    'verified_email=' . $evidence[39]
    . ' rejected=' . $evidence[40]
    . ' rate_limited=' . $evidence[42]
    . ' totp_confirmed=' . $evidence[45]
    . ' totp_verified=' . $evidence[46]
    . ' totp_revoked=' . $evidence[48]
    . ' preference_changed=' . $evidence[50]
    . ' bootstrap=' . $evidence[51]);

$orphanFactors = (int) $pdo->query(
    "SELECT COUNT(*) FROM admin_mfa_factors f LEFT JOIN user_tbl u ON u.u_id=f.admin_user_id
     WHERE f.factor_status IN ('ACTIVE','PENDING') AND (u.u_id IS NULL OR u.u_type<>1 OR u.avail_status<>1)"
)->fetchColumn();
$orphanGrants = (int) $pdo->query(
    'SELECT COUNT(*) FROM admin_step_up_grants g LEFT JOIN user_tbl u ON u.u_id=g.admin_user_id
     WHERE g.revoked_at IS NULL AND g.expires_at>NOW() AND (u.u_id IS NULL OR u.u_type<>1 OR u.avail_status<>1)'
)->fetchColumn();
$record('no_active_orphans', $orphanFactors === 0 && $orphanGrants === 0,
    "factor_orphans={$orphanFactors} grant_orphans={$orphanGrants}");

$secretLeakRows = (int) $pdo->query(
    "SELECT COUNT(*) FROM syslog WHERE log_type BETWEEN 37 AND 53
     AND (log_detail LIKE '%otpauth://%' OR log_detail LIKE '%secret=%')"
)->fetchColumn();
$record('audit_secret_hygiene', $secretLeakRows === 0, "secret_material_rows={$secretLeakRows}");

$keyringPath = (string) oneid_config('ONEID_TOTP_KEYRING_PATH', '');
$projectRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$realKeyring = $keyringPath !== '' ? realpath($keyringPath) : false;
$keyMode = $realKeyring !== false ? (fileperms($realKeyring) & 0777) : 0;
$keyOutsideProject = $realKeyring !== false && !str_starts_with($realKeyring, $projectRoot . DIRECTORY_SEPARATOR);
$record('keyring_runtime_custody', $realKeyring !== false && is_readable($realKeyring)
    && $keyOutsideProject && ($keyMode & 0007) === 0 && ($keyMode & 0020) === 0,
    'exists=' . ($realKeyring !== false ? 'yes' : 'no')
    . ' outside_project=' . ($keyOutsideProject ? 'yes' : 'no')
    . ' mode=' . sprintf('%04o', $keyMode));

$activation = $pdo->query("SELECT MIN(datetime) FROM syslog WHERE log_type=51")->fetchColumn();
$elapsedSeconds = is_string($activation) && $activation !== '' ? max(0, time() - strtotime($activation)) : 0;
$observationComplete = $elapsedSeconds >= 86400;
$record('observation_24h', $observationComplete,
    'activation=' . ($activation ?: 'not-recorded')
    . ' elapsed_hours=' . number_format($elapsedSeconds / 3600, 2, '.', '')
    . ' required_hours=24.00');

$failedFunctional = array_filter(
    $checks,
    static fn(array $result, string $name): bool => $name !== 'observation_24h' && !$result['passed'],
    ARRAY_FILTER_USE_BOTH
);
foreach ($checks as $name => $result) {
    $expectedPending = $name === 'observation_24h' && !$result['passed'] && !$finalGate;
    printf(
        "%s %-28s %s\n",
        $expectedPending ? 'WAIT' : ($result['passed'] ? 'PASS' : 'FAIL'),
        $name,
        $result['detail']
    );
}

$status = $failedFunctional === []
    ? ($observationComplete ? 'ACCEPT_READY' : 'FUNCTIONAL_PASS_OBSERVATION_ACTIVE')
    : 'NO_GO';
printf("F7.6_STATUS %s final_gate=%s\n", $status, $finalGate ? 'yes' : 'no');

exit($failedFunctional !== [] || ($finalGate && !$observationComplete) ? 1 : 0);
