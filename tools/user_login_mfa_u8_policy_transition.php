<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }
require_once dirname(__DIR__) . '/lib/config.php';

$target = strtoupper(trim((string) ($argv[1] ?? '')));
if (!in_array($target, ['OFF','ENROLLMENT','PILOT_ENFORCED'], true)) {
    fwrite(STDERR, "Usage: php tools/user_login_mfa_u8_policy_transition.php OFF|ENROLLMENT|PILOT_ENFORCED\n");
    exit(2);
}
$actor = trim((string) (getenv('ONEID_USER_MFA_U8_ACTOR') ?: ''));
$verifier = trim((string) (getenv('ONEID_USER_MFA_U8_VERIFIER') ?: ''));
$reference = trim((string) (getenv('ONEID_USER_MFA_U8_POLICY_REFERENCE') ?: ''));
$reason = trim((string) (getenv('ONEID_USER_MFA_U8_POLICY_REASON') ?: ''));
$confirmation = getenv('ONEID_USER_MFA_U8_POLICY_CONFIRMATION') ?: '';
$runtime = strtoupper((string) oneid_config('ONEID_USER_MFA_MODE', ''));
$authorized = filter_var(
    oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED', false),
    FILTER_VALIDATE_BOOLEAN
);
$totpEnabled = $target !== 'OFF' && filter_var(
    oneid_config('ONEID_USER_MFA_TOTP_ENABLED', false),
    FILTER_VALIDATE_BOOLEAN
);
$expectedConfirmation = "SET USER MFA POLICY {$target}";
if (preg_match('/\A[A-Za-z0-9._@-]{1,20}\z/', $actor) !== 1
    || preg_match('/\A[A-Za-z0-9._@-]{1,20}\z/', $verifier) !== 1
    || hash_equals($actor, $verifier)
    || preg_match('/\A[A-Za-z0-9._-]{8,100}\z/', $reference) !== 1
    || strlen($reason) < 10 || strlen($reason) > 500
    || $confirmation !== $expectedConfirmation
) {
    fwrite(STDERR, "FAIL USER_MFA_U8_POLICY_INPUT_INVALID\n");
    exit(1);
}
if ($runtime !== $target || ($target === 'OFF' ? $authorized : !$authorized)) {
    fwrite(STDERR, "FAIL USER_MFA_U8_POLICY_RUNTIME_TARGET_MISMATCH\n");
    exit(1);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$exactAdmin = $pdo->prepare(
    'SELECT u_id,u_type,avail_status FROM user_tbl WHERE u_id=:identifier'
);
$aliasAdmin = $pdo->prepare(
    'SELECT u_id,u_type,avail_status FROM user_tbl
      WHERE data2=:identifier2 OR data3=:identifier3 OR data8=:identifier4'
);
$resolveAdmin = static function (string $identifier) use ($exactAdmin, $aliasAdmin): array|false {
    $exactAdmin->execute([':identifier' => $identifier]);
    $rows = $exactAdmin->fetchAll();
    if ($rows !== []) {
        return count($rows) === 1 ? $rows[0] : false;
    }
    $aliasAdmin->execute([
        ':identifier2' => $identifier,
        ':identifier3' => $identifier,
        ':identifier4' => $identifier,
    ]);
    $rows = $aliasAdmin->fetchAll();
    return count($rows) === 1 ? $rows[0] : false;
};
$actorRow = $resolveAdmin($actor);
$verifierRow = $resolveAdmin($verifier);
if (!is_array($actorRow) || !is_array($verifierRow)
    || hash_equals((string) $actorRow['u_id'], (string) $verifierRow['u_id'])
    || (int) $actorRow['u_type'] !== 1 || (int) $actorRow['avail_status'] !== 1
    || (int) $verifierRow['u_type'] !== 1 || (int) $verifierRow['avail_status'] !== 1
) {
    fwrite(STDERR, "FAIL USER_MFA_U8_POLICY_ADMIN_VERIFIER_INVALID\n");
    exit(1);
}
$pilotCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM user_login_mfa_pilot_users WHERE pilot_status='ACTIVE'"
)->fetchColumn();
if ($target === 'PILOT_ENFORCED' && ($pilotCount < 5 || $pilotCount > 10)) {
    fwrite(STDERR, "FAIL USER_MFA_U8_POLICY_PILOT_COUNT_INVALID\n");
    exit(1);
}

$correlation = bin2hex(random_bytes(16));
try {
    $pdo->beginTransaction();
    $current = $pdo->query(
        'SELECT * FROM user_login_mfa_policy WHERE singleton_key=1 FOR UPDATE'
    )->fetch();
    if (!is_array($current)) {
        throw new RuntimeException('USER_MFA_POLICY_UNAVAILABLE');
    }
    $from = (string) $current['policy_mode'];
    $allowed = [
        'OFF' => ['OFF','ENROLLMENT'],
        'ENROLLMENT' => ['OFF','ENROLLMENT','PILOT_ENFORCED'],
        'PILOT_ENFORCED' => ['OFF','ENROLLMENT','PILOT_ENFORCED'],
    ];
    if (!in_array($target, $allowed[$from] ?? [], true)) {
        throw new RuntimeException('USER_MFA_POLICY_TRANSITION_INVALID');
    }
    $nextVersion = (int) $current['configuration_version'] + 1;
    $resulting = $current;
    $resulting['policy_mode'] = $target;
    $resulting['configuration_version'] = $nextVersion;
    $resulting['email_enabled'] = 1;
    $resulting['totp_enabled'] = $totpEnabled ? 1 : 0;
    $update = $pdo->prepare(
        'UPDATE user_login_mfa_policy
            SET policy_mode=:target,email_enabled=1,totp_enabled=:totp_enabled,
                configuration_version=:version,readiness_reference=:reference,
                updated_by=:actor
          WHERE singleton_key=1 AND configuration_version=:previous_version'
    );
    $update->execute([
        ':target' => $target, ':version' => $nextVersion,
        ':totp_enabled' => $totpEnabled ? 1 : 0,
        ':reference' => $reference, ':actor' => (string) $actorRow['u_id'],
        ':previous_version' => (int) $current['configuration_version'],
    ]);
    $history = $pdo->prepare(
        'INSERT INTO user_login_mfa_policy_history(
            configuration_version,previous_policy,resulting_policy,changed_by,
            change_reason,change_reference,correlation_id
         ) VALUES(:version,:previous,:resulting,:actor,:reason,:reference,:correlation)'
    );
    $history->execute([
        ':version' => $nextVersion,
        ':previous' => json_encode($current, JSON_THROW_ON_ERROR),
        ':resulting' => json_encode($resulting, JSON_THROW_ON_ERROR),
        ':actor' => (string) $actorRow['u_id'], ':reason' => $reason,
        ':reference' => $reference, ':correlation' => $correlation,
    ]);
    $detail = sprintf(
        'event=USER_MFA_POLICY_CHANGE actor=%s verifier=%s outcome=success from=%s to=%s reference=%s correlation=%s',
        (string) $actorRow['u_id'],
        (string) $verifierRow['u_id'],
        $from,
        $target,
        $reference,
        $correlation
    );
    $audit = $pdo->prepare(
        "INSERT INTO syslog(log_type,log_detail,ip_addr,datetime)
         VALUES(64,:detail,'127.0.0.1',NOW())"
    );
    $audit->execute([':detail' => $detail]);
    if ($update->rowCount() !== 1 || $history->rowCount() !== 1 || $audit->rowCount() !== 1) {
        throw new RuntimeException('USER_MFA_POLICY_AUDIT_ATOMICITY_FAILED');
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, "FAIL USER_MFA_U8_POLICY_TRANSITION_COMPENSATED\n");
    exit(1);
}
printf(
    "PASS USER MFA policy transition target=%s active_pilots=%d correlation=%s\n",
    $target,
    $pilotCount,
    $correlation
);
