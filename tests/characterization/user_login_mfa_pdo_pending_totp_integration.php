<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/config.php';
foreach ([
    'app/Auth/Totp.php',
    'app/Auth/TotpKeyring.php',
    'app/Auth/TotpSecretCipher.php',
    'app/Auth/UserMfa/UserLoginMfaPolicy.php',
    'app/Auth/UserMfa/UserMfaRequestBinding.php',
    'app/Auth/UserMfa/UserMfaAuditWriterInterface.php',
    'app/Auth/UserMfa/UserMfaSessionRevokerInterface.php',
    'app/Auth/UserMfa/UserMfaPendingLoginException.php',
    'app/Auth/UserMfa/UserMfaPendingLoginPersistenceInterface.php',
    'app/Auth/UserMfa/UserMfaLoginFinalizerInterface.php',
    'app/Auth/UserMfa/UserMfaPendingLoginCoordinator.php',
    'app/Auth/UserMfa/UserMfaTotpException.php',
    'app/Auth/UserMfa/UserMfaTotpPersistenceInterface.php',
    'app/Auth/UserMfa/UserMfaTotpPrimitive.php',
    'app/Auth/UserMfa/UserMfaTotpService.php',
    'app/Auth/UserMfa/PdoUserMfaPendingLoginPersistence.php',
    'app/Auth/UserMfa/PdoUserMfaTotpPersistence.php',
] as $file) {
    require_once dirname(__DIR__, 2) . '/' . $file;
}

use OneId\App\Auth\Totp;
use OneId\App\Auth\TotpKeyring;
use OneId\App\Auth\TotpSecretCipher;
use OneId\App\Auth\UserMfa\PdoUserMfaPendingLoginPersistence;
use OneId\App\Auth\UserMfa\PdoUserMfaTotpPersistence;
use OneId\App\Auth\UserMfa\UserLoginMfaPolicy;
use OneId\App\Auth\UserMfa\UserMfaAuditWriterInterface;
use OneId\App\Auth\UserMfa\UserMfaLoginFinalizerInterface;
use OneId\App\Auth\UserMfa\UserMfaPendingLoginCoordinator;
use OneId\App\Auth\UserMfa\UserMfaSessionRevokerInterface;
use OneId\App\Auth\UserMfa\UserMfaTotpPrimitive;
use OneId\App\Auth\UserMfa\UserMfaTotpService;

final class PdoMfaAudit implements UserMfaAuditWriterInterface
{
    public array $rows = [];
    public function write(
        string $event, string $targetUserId, string $actorUserId,
        string $outcome, string $reason, string $reference,
        string $correlationId, string $ipAddress
    ): int {
        $this->rows[] = compact(
            'event', 'targetUserId', 'actorUserId', 'outcome', 'reason',
            'reference', 'correlationId', 'ipAddress'
        );
        return 1;
    }
}
final class PdoMfaSessions implements UserMfaSessionRevokerInterface
{
    public array $users = [];
    public function revokeAll(string $userId, string $reason): int
    {
        $this->users[] = $userId;
        return 2;
    }
}
final class PdoMfaFinalizer implements UserMfaLoginFinalizerInterface
{
    public function prepare(string $userId, string $correlationId): array
    {
        return ['token_ref' => 'isolated-token', 'u_id' => $userId];
    }
    public function compensate(array $handle): void {}
}

$root = dirname(__DIR__, 2);
$database = 'oneid_user_mfa_pt_' . bin2hex(random_bytes(6));
$quoted = '`' . $database . '`';
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$split = static fn(string $sql): array => array_values(array_filter(
    array_map('trim', preg_split('/;\s*(?:\R|$)/', $sql) ?: []),
    static fn(string $statement): bool => $statement !== ''
));
$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    $failed += $passed ? 0 : 1;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};
$created = false;
$keyringPath = tempnam(sys_get_temp_dir(), 'oneid-pdo-pt-');
if ($keyringPath === false) {
    throw new RuntimeException('TEMP_KEYRING_FAILED');
}
file_put_contents(
    $keyringPath,
    "<?php\nreturn ['active_version'=>'pdo-test','keys'=>['pdo-test'=>'"
    . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)) . "']];\n",
    LOCK_EX
);
chmod($keyringPath, 0600);

try {
    $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $created = true;
    $pdo->exec("USE {$quoted}");
    $pdo->exec(
        'CREATE TABLE user_tbl(u_id VARCHAR(20) NOT NULL PRIMARY KEY)
         ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
    );
    $pdo->exec("INSERT INTO user_tbl(u_id) VALUES('PTTEST')");
    foreach ($split((string) file_get_contents(
        $root . '/docs/migrations/20260729_user_login_mfa_u1_up.sql'
    )) as $statement) {
        $pdo->exec($statement);
    }
    $pdo->exec((string) file_get_contents(
        $root . '/docs/migrations/20260730_user_login_mfa_terminal_otp_purge_up.sql'
    ));

    $audit = new PdoMfaAudit();
    $pending = new PdoUserMfaPendingLoginPersistence($pdo, $audit);
    $clock = 1_800_000_000;
    $coordinator = new UserMfaPendingLoginCoordinator($pending, static fn(): int => $clock);
    $policy = new UserLoginMfaPolicy(
        'ENFORCED', 'PASSWORD_ONLY', true, true, 300, 300, 5, 60, 10
    );
    $login = $coordinator->begin(
        'PTTEST', 'PASSWORD', 'pt-session', 'PT Browser/1', '127.0.0.1', $policy
    );
    $report(
        $login['code'] === 'USER_MFA_REQUIRED'
        && (int) $pdo->query(
            "SELECT COUNT(*) FROM user_login_mfa_transactions
              WHERE transaction_status='PENDING'"
        )->fetchColumn() === 1,
        'PDO pending adapter persists enforced password login without token'
    );
    $coordinator->markVerified(
        $login['transaction_id'], 'EMAIL_OTP',
        'pt-session', 'PT Browser/1', '127.0.0.1'
    );
    $authorized = $coordinator->finalize(
        $login['transaction_id'], 'pt-session', 'PT Browser/1', '127.0.0.1',
        new PdoMfaFinalizer()
    );
    $report(
        $authorized['code'] === 'USER_MFA_LOGIN_AUTHORIZED'
        && $pdo->query(
            "SELECT transaction_status FROM user_login_mfa_transactions
              WHERE transaction_id=" . $pdo->quote($login['transaction_id'])
        )->fetchColumn() === 'CONSUMED',
        'PDO pending adapter verifies then consumes one-use login'
    );

    $pdo->exec(
        "UPDATE user_login_mfa_policy
            SET policy_mode='ENROLLMENT',totp_enabled=1 WHERE singleton_key=1"
    );
    $sessions = new PdoMfaSessions();
    $totpPersistence = new PdoUserMfaTotpPersistence($pdo, $audit, $sessions);
    $primitive = new UserMfaTotpPrimitive(
        new TotpSecretCipher(TotpKeyring::fromFile($keyringPath)),
        static fn(): int => $clock
    );
    $service = new UserMfaTotpService($totpPersistence, $primitive);
    $enrollment = $service->beginEnrollment(
        'PTTEST', 'pttest@upnm.edu.my', 'totp-session', 'PT Browser/2'
    );
    parse_str((string) parse_url($enrollment['provisioning_uri'], PHP_URL_QUERY), $query);
    $secret = (string) ($query['secret'] ?? '');
    $service->confirmEnrollment(
        'PTTEST',
        $enrollment['factor_id'],
        Totp::codeAt($secret, $clock),
        'totp-session',
        'PT Browser/2'
    );
    $report(
        $pdo->query(
            "SELECT factor_status FROM user_mfa_factors
              WHERE factor_id=" . (int) $enrollment['factor_id']
        )->fetchColumn() === 'ACTIVE'
        && $pdo->query(
            "SELECT preferred_factor FROM user_mfa_preferences WHERE u_id='PTTEST'"
        )->fetchColumn() === 'TOTP',
        'PDO TOTP adapter atomically confirms encrypted factor and preference'
    );

    $nextClock = $clock + 30;
    $nextService = new UserMfaTotpService(
        $totpPersistence,
        new UserMfaTotpPrimitive(
            new TotpSecretCipher(TotpKeyring::fromFile($keyringPath)),
            static fn(): int => $nextClock
        )
    );
    $nextService->verify('PTTEST', Totp::codeAt($secret, $nextClock));
    $nextService->selfRevoke('PTTEST', true, 'isolated integration');
    $report(
        $pdo->query(
            "SELECT factor_status FROM user_mfa_factors
              WHERE factor_id=" . (int) $enrollment['factor_id']
        )->fetchColumn() === 'REVOKED'
        && $pdo->query(
            "SELECT preferred_factor FROM user_mfa_preferences WHERE u_id='PTTEST'"
        )->fetchColumn() === 'EMAIL_OTP'
        && $sessions->users === ['PTTEST'],
        'PDO TOTP revoke resets preference and invokes all-session revoker'
    );
} finally {
    @unlink($keyringPath);
    if ($created) {
        $pdo->exec('USE information_schema');
        $pdo->exec("DROP DATABASE {$quoted}");
    }
}

$leftovers = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.schemata
      WHERE schema_name LIKE 'oneid_user_mfa_pt_%'"
)->fetchColumn();
$report($leftovers === 0, 'isolated pending/TOTP integration database is removed');
printf(
    "RESULT checks=%d failures=%d shared_database_mutations=0 live_sessions_revoked=0 runtime_activation=0\n",
    $checks,
    $failed
);
exit($failed === 0 ? 0 : 1);
