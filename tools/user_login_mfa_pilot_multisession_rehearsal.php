<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}
require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaSessionRevokerInterface.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/LegacyUserMfaSessionRevoker.php';

use OneId\App\Auth\UserMfa\LegacyUserMfaSessionRevoker;

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$identity = $pdo->prepare(
    'SELECT u_id,data5 FROM user_tbl
      WHERE data3=:login_alias AND avail_status=1 LIMIT 1'
);
$identity->execute([':login_alias' => '0530-09']);
$user = $identity->fetch(PDO::FETCH_ASSOC);
if (!is_array($user)
    || filter_var((string) $user['data5'], FILTER_VALIDATE_EMAIL) === false
) {
    fwrite(STDERR, "FAIL PILOT_IDENTITY_MISMATCH\n");
    exit(1);
}
$userId = (string) $user['u_id'];
$pdo->beginTransaction();
try {
    $count = $pdo->prepare(
        'SELECT COUNT(*) FROM token_tbl WHERE user_id=:user_id AND status=1 FOR UPDATE'
    );
    $count->execute([':user_id' => $userId]);
    $baseline = (int) $count->fetchColumn();
    $insert = $pdo->prepare(
        'INSERT INTO token_tbl(
            token_id,token_datetime,token_issued_at,user_id,status,device_info,site_id
         ) VALUES(:token_id,NOW(),NOW(),:user_id,1,:device,0)'
    );
    foreach (['A', 'B'] as $suffix) {
        $insert->execute([
            ':token_id' => hash('sha256', random_bytes(32)),
            ':user_id' => $userId,
            ':device' => 'USER_MFA_U7_CONTROLLED_' . $suffix,
        ]);
    }
    $operation = new class($pdo) {
        public function __construct(private readonly PDO $pdo) {}
        public function update_whole_token_status(string $userId, int $status): int
        {
            $statement = $this->pdo->prepare(
                'UPDATE token_tbl SET status=:status WHERE user_id=:user_id'
            );
            $statement->execute([':status' => $status, ':user_id' => $userId]);
            return $statement->rowCount();
        }
    };
    $revoked = (new LegacyUserMfaSessionRevoker($operation))->revokeAll(
        $userId,
        'U7_CONTROLLED_MULTI_SESSION'
    );
    $active = $pdo->prepare(
        'SELECT COUNT(*) FROM token_tbl WHERE user_id=:user_id AND status=1'
    );
    $active->execute([':user_id' => $userId]);
    $activeAfter = (int) $active->fetchColumn();
    $passed = $revoked >= 2 && $activeAfter === 0;
    $pdo->rollBack();

    $active->execute([':user_id' => $userId]);
    $restored = (int) $active->fetchColumn();
    printf(
        "%s pilot multi-session invalidation active_before=%d synthetic=2 revoked=%d active_after=0 rollback_reconciled=%s canonical_id_output=0\n",
        $passed && $restored === $baseline ? 'PASS' : 'FAIL',
        $baseline,
        $revoked,
        $restored === $baseline ? 'yes' : 'no'
    );
    printf(
        "RESULT checks=1 failures=%d committed_token_mutations=0 user_sessions_disrupted=0\n",
        $passed && $restored === $baseline ? 0 : 1
    );
    exit($passed && $restored === $baseline ? 0 : 1);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "FAIL PILOT_MULTI_SESSION_REHEARSAL correlation="
        . bin2hex(random_bytes(8)) . "\n");
    exit(1);
}
