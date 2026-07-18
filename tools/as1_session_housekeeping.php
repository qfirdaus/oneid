<?php

if (PHP_SAPI !== 'cli') { exit(2); }

use OneId\App\Admin\SessionHousekeepingPolicy;

$root = dirname(__DIR__);
require_once $root . '/lib/config.php';
require_once $root . '/app/Admin/SessionHousekeepingPolicy.php';

$apply = in_array('--apply', $argv, true);
$check = in_array('--check', $argv, true);
if ($apply === $check) {
    fwrite(STDERR, "Usage: php tools/as1_session_housekeeping.php --check | --apply --change-id=ID\n");
    exit(2);
}

$changeId = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--change-id=')) {
        $changeId = substr($argument, strlen('--change-id='));
    }
}
if ($apply && !preg_match('/\A[A-Za-z0-9._-]{6,64}\z/', $changeId)) {
    fwrite(STDERR, "FAIL change_id_invalid\n");
    exit(1);
}

$property = new ReflectionProperty(Database::class, 'pdo');
$property->setAccessible(true);
/** @var PDO $pdo */
$pdo = $property->getValue($operation);
$config = $operation->get_system_config();
$lifetime = (float) ($config['token_timeout'] ?? 0);
$now = date('Y-m-d H:i:s');
try {
    $expiryCutoff = SessionHousekeepingPolicy::expiryCutoff($now, $lifetime);
} catch (Throwable $exception) {
    printf("RESULT housekeeping=fail code=%s mutation_statements=0\n", $exception->getMessage());
    exit(1);
}

$reasonSql = "status=1 AND (token_issued_at>:now_value OR token_issued_at<=:expiry_cutoff OR (policy_revoke_at IS NOT NULL AND policy_revoke_at<=:now_due))";
$params = [':now_value'=>$now, ':expiry_cutoff'=>$expiryCutoff, ':now_due'=>$now];
$countStatement = $pdo->prepare("SELECT COUNT(*) FROM token_tbl WHERE {$reasonSql}");
$countStatement->execute($params);
$candidateCount = (int) $countStatement->fetchColumn();
$breakdownStatement = $pdo->prepare(
    "SELECT
       SUM(token_issued_at>:future_now) AS future_invalid,
       SUM(token_issued_at<=:natural_cutoff) AS natural_expired,
       SUM(policy_revoke_at IS NOT NULL AND policy_revoke_at<=:due_now) AS policy_due
     FROM token_tbl WHERE status=1"
);
$breakdownStatement->execute([':future_now'=>$now, ':natural_cutoff'=>$expiryCutoff, ':due_now'=>$now]);
$breakdown = $breakdownStatement->fetch(PDO::FETCH_ASSOC) ?: [];
$excessive = $pdo->prepare(
    'SELECT COUNT(*) FROM (SELECT user_id FROM token_tbl WHERE status=1 GROUP BY user_id HAVING COUNT(*)>:session_threshold) X'
);
$excessive->execute([':session_threshold'=>SessionHousekeepingPolicy::EXCESSIVE_SESSION_THRESHOLD]);
$excessiveUsers = (int) $excessive->fetchColumn();

printf(
    "CHECK lifetime_hours=%s cutoff=%s candidates=%d natural=%d future_invalid=%d policy_due=%d excessive_users=%d batch_limit=%d\n",
    rtrim(rtrim(sprintf('%.4F', $lifetime), '0'), '.'),
    $expiryCutoff,
    $candidateCount,
    (int) ($breakdown['natural_expired'] ?? 0),
    (int) ($breakdown['future_invalid'] ?? 0),
    (int) ($breakdown['policy_due'] ?? 0),
    $excessiveUsers,
    SessionHousekeepingPolicy::BATCH_SIZE
);

if ($check) {
    echo "RESULT housekeeping=check_pass mutation_statements=0\n";
    exit(0);
}

if (getenv('ONEID_SESSION_HOUSEKEEPING_APPLY_ENABLED') !== 'true') {
    echo "RESULT housekeeping=blocked code=AS1_APPLY_DISABLED mutation_statements=0\n";
    exit(1);
}
if ($candidateCount === 0) {
    echo "RESULT housekeeping=no_changes mutation_statements=0\n";
    exit(0);
}

$phrase = SessionHousekeepingPolicy::confirmationPhrase($candidateCount);
printf("Type %s to continue: ", $phrase);
$provided = trim((string) fgets(STDIN));
if (!hash_equals($phrase, $provided)) {
    echo "RESULT housekeeping=blocked code=AS1_CONFIRMATION_MISMATCH mutation_statements=0\n";
    exit(1);
}

$lockName = 'oneid_session_housekeeping';
$lock = $pdo->prepare('SELECT GET_LOCK(:lock_name,0)');
$lock->execute([':lock_name'=>$lockName]);
if ((int) $lock->fetchColumn() !== 1) {
    echo "RESULT housekeeping=blocked code=AS1_LOCK_UNAVAILABLE mutation_statements=0\n";
    exit(1);
}

$updated = 0;
try {
    $pdo->beginTransaction();
    $select = $pdo->prepare(
        "SELECT token_id FROM token_tbl WHERE {$reasonSql} ORDER BY token_issued_at ASC,token_id ASC LIMIT "
        . SessionHousekeepingPolicy::BATCH_SIZE . ' FOR UPDATE'
    );
    $select->execute($params);
    $tokenIds = array_column($select->fetchAll(PDO::FETCH_ASSOC), 'token_id');
    if ($tokenIds !== []) {
        $placeholders = implode(',', array_fill(0, count($tokenIds), '?'));
        $update = $pdo->prepare("UPDATE token_tbl SET status=0 WHERE status=1 AND token_id IN ({$placeholders})");
        $update->execute($tokenIds);
        $updated = $update->rowCount();
        if ($updated !== count($tokenIds)) {
            throw new RuntimeException('AS1_RECONCILIATION_FAILED');
        }
    }
    $detail = sprintf('action=session_housekeeping change_id=%s candidates=%d selected=%d updated=%d', $changeId, $candidateCount, count($tokenIds), $updated);
    if ($operation->syslog_record(7, $detail, 'CLI') !== 1) {
        throw new RuntimeException('AS1_AUDIT_FAILED');
    }
    $pdo->commit();
    printf("RESULT housekeeping=applied change_id=%s selected=%d updated=%d reconciliation=pass\n", $changeId, count($tokenIds), $updated);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    printf("RESULT housekeeping=fail code=%s updated=0\n", $exception->getMessage());
    exit(1);
} finally {
    $release = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
    $release->execute([':lock_name'=>$lockName]);
}
