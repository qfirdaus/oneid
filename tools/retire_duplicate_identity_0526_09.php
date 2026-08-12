<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

const LEGACY_USER_ID = '0526-09';
const CANONICAL_USER_ID = '790612146270';
const STAFF_NUMBER = '0526-09';

$apply = in_array('--apply', $argv, true);
$property = new ReflectionProperty(Database::class, 'pdo');
$property->setAccessible(true);
/** @var PDO $pdo */
$pdo = $property->getValue($operation);

$load = static function (PDO $pdo, string $id, bool $lock = false): array|false {
    $sql = 'SELECT u_id,u_category,u_type,avail_status,account_source,sync_protected,data1,data2,data3,data4 '
        . 'FROM user_tbl WHERE u_id=:id' . ($lock ? ' FOR UPDATE' : '');
    $statement = $pdo->prepare($sql);
    $statement->execute([':id' => $id]);
    return $statement->fetch(PDO::FETCH_ASSOC);
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$validate = static function (array $legacy, array $canonical) use ($assert): void {
    $assert((string) $legacy['u_id'] === LEGACY_USER_ID, 'legacy identity not found');
    $assert((int) $legacy['avail_status'] === 0, 'legacy identity must already be inactive');
    $assert((int) $legacy['u_type'] === 0, 'legacy identity unexpectedly has administrator access');
    $assert((string) $canonical['u_id'] === CANONICAL_USER_ID, 'canonical identity not found');
    $assert((int) $canonical['avail_status'] === 1, 'canonical identity is not active');
    $assert((int) $canonical['u_type'] === 1, 'canonical identity does not have administrator access');
    $assert(trim((string) $canonical['data3']) === STAFF_NUMBER, 'canonical staff number does not match');
};

$legacy = $load($pdo, LEGACY_USER_ID);
$canonical = $load($pdo, CANONICAL_USER_ID);
$assert(is_array($legacy) && is_array($canonical), 'required identity pair is unavailable');
$validate($legacy, $canonical);

$count = static function (PDO $pdo, string $sql, array $parameters): int {
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return (int) $statement->fetchColumn();
};

$legacyActiveTokens = $count($pdo, 'SELECT COUNT(*) FROM token_tbl WHERE user_id=:id AND status=1', [':id'=>LEGACY_USER_ID]);
$canonicalTokens = $count($pdo, 'SELECT COUNT(*) FROM token_tbl WHERE user_id=:id', [':id'=>CANONICAL_USER_ID]);

printf("MODE %s\n", $apply ? 'APPLY' : 'AUDIT');
printf("LEGACY user_id=%s active=%d admin=%d source=%s protected=%d active_tokens=%d\n",
    LEGACY_USER_ID, (int)$legacy['avail_status'], (int)$legacy['u_type'],
    (string)$legacy['account_source'], (int)$legacy['sync_protected'], $legacyActiveTokens
);
printf("CANONICAL user_id=%s staff_no=%s active=%d admin=%d tokens=%d\n",
    CANONICAL_USER_ID, (string)$canonical['data3'], (int)$canonical['avail_status'],
    (int)$canonical['u_type'], $canonicalTokens
);

if (!$apply) {
    echo "READY run again with --apply to retire only the duplicate identity\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $legacy = $load($pdo, LEGACY_USER_ID, true);
    $canonical = $load($pdo, CANONICAL_USER_ID, true);
    $assert(is_array($legacy) && is_array($canonical), 'identity changed before lock');
    $validate($legacy, $canonical);

    $password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $update = $pdo->prepare(
        "UPDATE user_tbl SET avail_status=0,u_type=0,account_source='manual',sync_protected=1,"
        . 'password_change_required=1,u_password=:password,u_update_datetime=NOW() '
        . 'WHERE u_id=:legacy AND avail_status=0 AND u_type=0'
    );
    $update->execute([':password'=>$password, ':legacy'=>LEGACY_USER_ID]);
    $assert($update->rowCount() === 1, 'legacy identity was not retired');

    $revoke = $pdo->prepare('UPDATE token_tbl SET status=0 WHERE user_id=:legacy AND status=1');
    $revoke->execute([':legacy'=>LEGACY_USER_ID]);

    $pdo->exec("INSERT IGNORE INTO syslog_event_conf(syslog_event_id,syslog_event_name) VALUES(71,'DUPLICATE_IDENTITY_RETIRED')");
    $log = $pdo->prepare('INSERT INTO syslog(log_type,log_detail,ip_addr,datetime) VALUES(71,:detail,:ip,NOW())');
    $log->execute([
        ':detail'=>'action=duplicate_identity_retired legacy='.LEGACY_USER_ID.' canonical='.CANONICAL_USER_ID.' staff_no='.STAFF_NUMBER,
        ':ip'=>'127.0.0.1',
    ]);
    $assert($log->rowCount() === 1, 'retirement audit could not be recorded');
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

$legacy = $load($pdo, LEGACY_USER_ID);
$canonical = $load($pdo, CANONICAL_USER_ID);
$assert(is_array($legacy) && is_array($canonical), 'post-apply verification failed');
$validate($legacy, $canonical);
$assert((string)$legacy['account_source'] === 'manual' && (int)$legacy['sync_protected'] === 1, 'legacy identity is not sync protected');
$assert($count($pdo, 'SELECT COUNT(*) FROM token_tbl WHERE user_id=:id AND status=1', [':id'=>LEGACY_USER_ID]) === 0, 'legacy identity still has an active token');

echo "PASS duplicate identity retired; canonical administrator remains active\n";
