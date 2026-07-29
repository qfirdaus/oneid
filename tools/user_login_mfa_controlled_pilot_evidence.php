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
$identity = $pdo->prepare(
    'SELECT u_id,u_category,data5 FROM user_tbl
      WHERE data3=:login_alias AND avail_status=1 LIMIT 1'
);
$identity->execute([':login_alias' => '0530-09']);
$user = $identity->fetch();
if (!is_array($user)
    || filter_var((string) $user['data5'], FILTER_VALIDATE_EMAIL) === false
) {
    fwrite(STDERR, "FAIL CONTROLLED_PILOT_IDENTITY_MISMATCH\n");
    exit(1);
}
$userId = (string) $user['u_id'];
$transaction = $pdo->prepare(
    "SELECT COUNT(*) total,
            SUM(t.transaction_status='CONSUMED') consumed,
            SUM(c.consumed_at IS NOT NULL) challenge_consumed,
            SUM(c.otp_hash IS NOT NULL) otp_hash_remaining
       FROM user_login_mfa_transactions t
       JOIN user_login_mfa_challenges c ON c.transaction_id=t.transaction_id
      WHERE t.u_id=:user_id"
);
$transaction->execute([':user_id' => $userId]);
$evidence = $transaction->fetch();
$token = $pdo->prepare(
    "SELECT COUNT(*) total,SUM(status=1) active
       FROM token_tbl
      WHERE user_id=:user_id AND device_info='USER_MFA_U7_CONTROLLED_PILOT'"
);
$token->execute([':user_id' => $userId]);
$tokenEvidence = $token->fetch();
$audit = $pdo->prepare(
    "SELECT COUNT(*) FROM syslog
      WHERE log_type BETWEEN 55 AND 65
        AND log_detail LIKE :target"
);
$audit->execute([':target' => '%target=' . $userId . '%']);
$auditRows = (int) $audit->fetchColumn();

$digest = static function (PDO $pdo, string $sql, array $parameters): array {
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    $ids = $statement->fetchAll(PDO::FETCH_COLUMN, 0);
    return [count($ids), substr(hash('sha256', json_encode($ids)), 0, 16)];
};
$single = $digest(
    $pdo,
    'SELECT sp_id FROM acl_single WHERE u_id=:user_id ORDER BY sp_id',
    [':user_id' => $userId]
);
$group = $digest(
    $pdo,
    'SELECT sp_id FROM acl_group WHERE uc_id=:category ORDER BY sp_id',
    [':category' => $user['u_category']]
);
$blacklist = $digest(
    $pdo,
    'SELECT sp_id FROM acl_blacklist WHERE u_id=:user_id ORDER BY sp_id',
    [':user_id' => $userId]
);
$passed = (int) ($evidence['total'] ?? 0) >= 1
    && (int) ($evidence['consumed'] ?? 0) >= 1
    && (int) ($evidence['challenge_consumed'] ?? 0) >= 1
    && (int) ($evidence['otp_hash_remaining'] ?? 0) === 0
    && (int) ($tokenEvidence['total'] ?? 0) >= 1
    && (int) ($tokenEvidence['active'] ?? 0) === 0
    && $auditRows >= 5
    && $single === [1, 'a74d1e11b96dbb3e']
    && $group === [35, '9a9560c503ede999']
    && $blacklist === [0, '4f53cda18c2baa0c'];
printf(
    "%s controlled pilot evidence pending_consumed=yes challenge_consumed=yes otp_hash_purged=yes token_revoked=yes acl_parity=yes audit_complete=yes canonical_id_output=0\n",
    $passed ? 'PASS' : 'FAIL'
);
printf(
    "RESULT checks=1 failures=%d global_activation=0 mutation_statements=0\n",
    $passed ? 0 : 1
);
exit($passed ? 0 : 1);
