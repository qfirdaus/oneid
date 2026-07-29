<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/config.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaAuditWriterInterface.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaEmailSenderInterface.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaEmailOtpException.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaEmailOtpPersistenceInterface.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaOtp.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaRateLimitConfig.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaRequestBinding.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaEmailOtpService.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/PdoUserMfaEmailOtpPersistence.php';

use OneId\App\Auth\UserMfa\PdoUserMfaEmailOtpPersistence;
use OneId\App\Auth\UserMfa\UserMfaAuditWriterInterface;
use OneId\App\Auth\UserMfa\UserMfaEmailOtpService;
use OneId\App\Auth\UserMfa\UserMfaEmailSenderInterface;

final class PdoEmailAudit implements UserMfaAuditWriterInterface
{
    public array $rows = [];
    public function write(
        string $event,
        string $targetUserId,
        string $actorUserId,
        string $outcome,
        string $reason,
        string $reference,
        string $correlationId,
        string $ipAddress
    ): int {
        $this->rows[] = compact(
            'event', 'targetUserId', 'actorUserId', 'outcome', 'reason',
            'reference', 'correlationId', 'ipAddress'
        );
        return 1;
    }
}
final class PdoEmailSender implements UserMfaEmailSenderInterface
{
    public string $otp = '';
    public function send(string $otp, string $email, string $displayName, string $locale): bool
    {
        $this->otp = $otp;
        return true;
    }
}

$root = dirname(__DIR__, 2);
$database = 'oneid_user_mfa_pdo_' . bin2hex(random_bytes(6));
$quoted = '`' . $database . '`';
$admin = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$split = static fn(string $sql): array => array_values(array_filter(
    array_map('trim', preg_split('/;\s*(?:\R|$)/', $sql) ?: []),
    static fn(string $statement): bool => $statement !== ''
));
$created = false;
$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    $failed += $passed ? 0 : 1;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};

try {
    $admin->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $created = true;
    $admin->exec("USE {$quoted}");
    $admin->exec(
        'CREATE TABLE user_tbl(
            u_id VARCHAR(20) NOT NULL PRIMARY KEY,
            data1 VARCHAR(100) NULL,
            data5 VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
    );
    $admin->exec(
        "INSERT INTO user_tbl(u_id,data1,data5)
         VALUES('PDOTEST','PDO Test','pdo.test@example.test')"
    );
    foreach ($split((string) file_get_contents(
        $root . '/docs/migrations/20260729_user_login_mfa_u1_up.sql'
    )) as $statement) {
        $admin->exec($statement);
    }
    $admin->exec((string) file_get_contents(
        $root . '/docs/migrations/20260730_user_login_mfa_terminal_otp_purge_up.sql'
    ));
    $admin->exec(
        "UPDATE user_login_mfa_policy SET policy_mode='ENFORCED',email_enabled=1
          WHERE singleton_key=1"
    );
    $transactionId = str_repeat('a', 64);
    $sessionHash = hash('sha256', 'pdo-session');
    $browserHash = hash('sha256', 'PDO Browser/1');
    $statement = $admin->prepare(
        'INSERT INTO user_login_mfa_transactions(
            transaction_id,u_id,primary_method,transaction_status,
            session_binding_hash,browser_digest,requesting_ip,policy_version,
            expires_at,correlation_id
         ) VALUES(
            :transaction_id,:user_id,\'PASSWORD\',\'PENDING\',
            :session_hash,:browser_hash,\'127.0.0.1\',1,
            DATE_ADD(NOW(6),INTERVAL 5 MINUTE),:correlation_id
         )'
    );
    $statement->execute([
        ':transaction_id' => $transactionId,
        ':user_id' => 'PDOTEST',
        ':session_hash' => $sessionHash,
        ':browser_hash' => $browserHash,
        ':correlation_id' => str_repeat('b', 32),
    ]);

    $audit = new PdoEmailAudit();
    $sender = new PdoEmailSender();
    $persistence = new PdoUserMfaEmailOtpPersistence($admin, $audit);
    $service = new UserMfaEmailOtpService($persistence, $sender);
    $challenge = $service->request(
        $transactionId,
        'PDOTEST',
        'pdo-session',
        'PDO Browser/1',
        '127.0.0.1',
        'en'
    );
    $stored = $admin->query(
        'SELECT otp_hash,destination_hmac,sent_at,consumed_at
           FROM user_login_mfa_challenges'
    )->fetch(PDO::FETCH_ASSOC);
    $report(
        is_array($stored)
        && str_starts_with((string) $stored['otp_hash'], '$argon2id$')
        && $stored['destination_hmac'] !== null
        && $stored['sent_at'] !== null
        && $stored['consumed_at'] === null,
        'PDO request stores hash/HMAC and marks delivery without raw OTP'
    );

    $verified = $service->verify(
        $transactionId,
        (string) $challenge['challenge_id'],
        $sender->otp,
        'pdo-session',
        'PDO Browser/1',
        '127.0.0.1'
    );
    $terminal = $admin->query(
        'SELECT c.otp_hash,c.consumed_at,t.transaction_status
           FROM user_login_mfa_challenges c
           JOIN user_login_mfa_transactions t ON t.transaction_id=c.transaction_id'
    )->fetch(PDO::FETCH_ASSOC);
    $report(
        $verified['code'] === 'USER_MFA_FACTOR_VERIFIED'
        && is_array($terminal)
        && $terminal['otp_hash'] === null
        && $terminal['consumed_at'] !== null
        && $terminal['transaction_status'] === 'VERIFIED',
        'PDO verification atomically consumes/purges OTP and verifies pending login'
    );
    $report(
        count($audit->rows) === 3
        && !str_contains(json_encode($audit->rows, JSON_THROW_ON_ERROR), $sender->otp)
        && !str_contains(
            json_encode($audit->rows, JSON_THROW_ON_ERROR),
            'pdo.test@example.test'
        ),
        'PDO service audit remains OTP and e-mail free'
    );
} finally {
    if ($created) {
        $admin->exec('USE information_schema');
        $admin->exec("DROP DATABASE {$quoted}");
    }
}

$leftovers = (int) $admin->query(
    "SELECT COUNT(*) FROM information_schema.schemata
      WHERE schema_name LIKE 'oneid_user_mfa_pdo_%'"
)->fetchColumn();
$report($leftovers === 0, 'isolated PDO integration database is removed');
printf(
    "RESULT checks=%d failures=%d shared_database_mutations=0 smtp_calls=0 runtime_activation=0\n",
    $checks,
    $failed
);
exit($failed === 0 ? 0 : 1);
