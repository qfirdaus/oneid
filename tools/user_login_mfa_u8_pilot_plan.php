<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}
require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/user_login_mfa_u8_pilot_plan.php [--check|--apply]\n");
    exit(2);
}
$path = dirname(__DIR__) . '/.private/user_mfa_pilot_plan.json';
if (!is_file($path) || !is_readable($path)) {
    fwrite(STDERR, "FAIL USER_MFA_U8_PRIVATE_PILOT_PLAN_UNAVAILABLE\n");
    exit(1);
}
$permissions = fileperms($path);
if ($permissions === false || ($permissions & 0077) !== 0) {
    fwrite(STDERR, "FAIL USER_MFA_U8_PRIVATE_PILOT_PLAN_PERMISSIONS\n");
    exit(1);
}
try {
    $plan = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    fwrite(STDERR, "FAIL USER_MFA_U8_PRIVATE_PILOT_PLAN_INVALID\n");
    exit(1);
}
$actor = trim((string) ($plan['actor'] ?? ''));
$verifier = trim((string) ($plan['verifier'] ?? ''));
$reference = trim((string) ($plan['reference'] ?? ''));
$pilots = is_array($plan['pilots'] ?? null) ? $plan['pilots'] : [];
$categories = ['STAFF','LECTURER','LOCAL_STUDENT','INTERNATIONAL_STUDENT'];
$validShape = preg_match('/\A[A-Za-z0-9._@-]{1,20}\z/', $actor) === 1
    && preg_match('/\A[A-Za-z0-9._@-]{1,20}\z/', $verifier) === 1
    && !hash_equals($actor, $verifier)
    && preg_match('/\A[A-Za-z0-9._-]{8,100}\z/', $reference) === 1
    && count($pilots) >= 5 && count($pilots) <= 10;
$normalized = [];
foreach ($pilots as $pilot) {
    $user = trim((string) ($pilot['u_id'] ?? ''));
    $category = strtoupper(trim((string) ($pilot['category'] ?? '')));
    if (preg_match('/\A[A-Za-z0-9._@-]{1,20}\z/', $user) !== 1
        || !in_array($category, $categories, true)
        || isset($normalized[$user])
    ) {
        $validShape = false;
        continue;
    }
    $normalized[$user] = $category;
}
$validShape = $validShape
    && count(array_unique(array_values($normalized))) === count($categories);
if (!$validShape) {
    fwrite(STDERR, "FAIL USER_MFA_U8_PILOT_PLAN_SHAPE_INVALID\n");
    exit(1);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$resolver = $pdo->prepare(
    'SELECT u_id,u_type,avail_status,data5 email
       FROM user_tbl
      WHERE u_id=:identifier OR data2=:identifier2
         OR data3=:identifier3 OR data8=:identifier4'
);
$resolve = static function (string $identifier) use ($resolver): array {
    $resolver->execute([
        ':identifier' => $identifier,
        ':identifier2' => $identifier,
        ':identifier3' => $identifier,
        ':identifier4' => $identifier,
    ]);
    return $resolver->fetchAll();
};
$diagnostics = [
    'not_found' => 0,
    'ambiguous' => 0,
    'inactive' => 0,
    'invalid_email' => 0,
    'not_admin' => 0,
    'duplicate_canonical' => 0,
];
$resolveOne = static function (string $identifier) use ($resolve, &$diagnostics): array|false {
    $matches = $resolve($identifier);
    if (count($matches) === 0) {
        $diagnostics['not_found']++;
        return false;
    }
    if (count($matches) !== 1) {
        $diagnostics['ambiguous']++;
        return false;
    }
    return $matches[0];
};
$actorRow = $resolveOne($actor);
$verifierRow = $resolveOne($verifier);
$adminsReady = is_array($actorRow) && is_array($verifierRow);
foreach ([$actorRow, $verifierRow] as $row) {
    if (!is_array($row)) {
        continue;
    }
    if ((int) $row['avail_status'] !== 1) {
        $diagnostics['inactive']++;
        $adminsReady = false;
    }
    if ((int) $row['u_type'] !== 1) {
        $diagnostics['not_admin']++;
        $adminsReady = false;
    }
}
if ($adminsReady && hash_equals((string) $actorRow['u_id'], (string) $verifierRow['u_id'])) {
    $diagnostics['duplicate_canonical']++;
    $adminsReady = false;
}
$resolvedPilots = [];
$pilotsReady = true;
foreach ($normalized as $identifier => $category) {
    // PHP converts numeric-string array keys to integers. Cast again at the
    // resolver boundary so student identifiers containing digits only remain
    // valid login identifiers rather than causing a strict-types TypeError.
    $row = $resolveOne((string) $identifier);
    if (!is_array($row)) {
        $pilotsReady = false;
        continue;
    }
    $canonical = (string) $row['u_id'];
    if (isset($resolvedPilots[$canonical])) {
        $diagnostics['duplicate_canonical']++;
        $pilotsReady = false;
        continue;
    }
    if ((int) $row['avail_status'] !== 1) {
        $diagnostics['inactive']++;
        $pilotsReady = false;
    }
    if (filter_var((string) $row['email'], FILTER_VALIDATE_EMAIL) === false) {
        $diagnostics['invalid_email']++;
        $pilotsReady = false;
    }
    $resolvedPilots[$canonical] = $category;
}
$existing = (int) $pdo->query(
    "SELECT COUNT(*) FROM user_login_mfa_pilot_users WHERE pilot_status='ACTIVE'"
)->fetchColumn();
printf(
    "USER_MFA_U8_PILOT_PLAN count=%d categories=%d admins_ready=%s accounts_ready=%s existing_active=%d not_found=%d ambiguous=%d inactive=%d invalid_email=%d not_admin=%d duplicate_canonical=%d mode=%s pii_output=0\n",
    count($normalized),
    count(array_unique(array_values($normalized))),
    $adminsReady ? 'yes' : 'no',
    $pilotsReady ? 'yes' : 'no',
    $existing,
    $diagnostics['not_found'],
    $diagnostics['ambiguous'],
    $diagnostics['inactive'],
    $diagnostics['invalid_email'],
    $diagnostics['not_admin'],
    $diagnostics['duplicate_canonical'],
    $mode
);
if (!$adminsReady || !$pilotsReady || ($existing !== 0 && $mode === '--apply')) {
    exit(1);
}
if ($mode === '--check') {
    exit(0);
}
$confirmation = getenv('ONEID_USER_MFA_U8_PILOT_CONFIRMATION') ?: '';
if ($confirmation !== 'APPLY PRIVATE USER MFA PILOT PLAN WITH MODE OFF'
    || (string) oneid_config('ONEID_USER_MFA_MODE', '') !== 'OFF'
    || filter_var(oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED', false), FILTER_VALIDATE_BOOLEAN)
) {
    fwrite(STDERR, "FAIL USER_MFA_U8_PILOT_APPLY_NOT_AUTHORIZED\n");
    exit(1);
}
$correlation = bin2hex(random_bytes(16));
try {
    $pdo->beginTransaction();
    $insert = $pdo->prepare(
        "INSERT INTO user_login_mfa_pilot_users(
            u_id,pilot_category,pilot_status,enrolled_by,change_reference
         ) VALUES(:user,:category,'ACTIVE',:actor,:reference)"
    );
    foreach ($resolvedPilots as $user => $category) {
        $insert->execute([
            ':user' => $user, ':category' => $category,
            ':actor' => (string) $actorRow['u_id'], ':reference' => $reference,
        ]);
    }
    $detail = sprintf(
        'event=USER_MFA_POLICY_CHANGE actor=%s verifier=%s outcome=pilot_plan_applied count=%d reference=%s correlation=%s',
        (string) $actorRow['u_id'],
        (string) $verifierRow['u_id'],
        count($resolvedPilots),
        $reference,
        $correlation
    );
    $audit = $pdo->prepare(
        "INSERT INTO syslog(log_type,log_detail,ip_addr,datetime)
         VALUES(64,:detail,'127.0.0.1',NOW())"
    );
    $audit->execute([':detail' => $detail]);
    if ($audit->rowCount() !== 1) {
        throw new RuntimeException('USER_MFA_U8_PILOT_AUDIT_FAILED');
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, "FAIL USER_MFA_U8_PILOT_APPLY_COMPENSATED\n");
    exit(1);
}
printf("PASS USER MFA U8 private pilot plan applied count=%d pii_output=0\n", count($resolvedPilots));
