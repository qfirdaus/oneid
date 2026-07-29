<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/config.php';
foreach ([
    'UserLoginMfaPolicy.php',
    'UserMfaAuditWriterInterface.php',
    'UserMfaPendingLoginException.php',
    'UserMfaPendingLoginPersistenceInterface.php',
    'UserMfaRequestBinding.php',
    'UserMfaPendingLoginCoordinator.php',
    'PdoUserMfaPendingLoginPersistence.php',
    'PdoUserMfaPolicyReader.php',
    'UserMfaPrimaryAuthDecision.php',
] as $file) {
    require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/' . $file;
}

use OneId\App\Auth\UserMfa\PdoUserMfaPendingLoginPersistence;
use OneId\App\Auth\UserMfa\PdoUserMfaPolicyReader;
use OneId\App\Auth\UserMfa\UserMfaAuditWriterInterface;
use OneId\App\Auth\UserMfa\UserMfaPendingLoginCoordinator;
use OneId\App\Auth\UserMfa\UserMfaPrimaryAuthDecision;

final class U8Audit implements UserMfaAuditWriterInterface
{
    public array $events = [];
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
        $this->events[] = $event;
        return 1;
    }
}

$admin = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'oneid_user_mfa_u8_' . bin2hex(random_bytes(6));
$quoted = '`' . $database . '`';
$created = false;
$checks = 0;
$failures = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++;
    $failures += $ok ? 0 : 1;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};
$split = static fn(string $sql): array => array_values(array_filter(
    array_map('trim', preg_split('/;\s*(?:\R|$)/', $sql) ?: []),
    static fn(string $statement): bool => $statement !== ''
));

try {
    $admin->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $created = true;
    $admin->exec("USE {$quoted}");
    $admin->exec(
        'CREATE TABLE user_tbl(u_id VARCHAR(20) NOT NULL PRIMARY KEY)
         ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
    );
    $admin->exec("INSERT INTO user_tbl(u_id) VALUES('PILOT1'),('NORMAL1'),('ADMIN1')");
    foreach ($split((string) file_get_contents(
        dirname(__DIR__, 2) . '/docs/migrations/20260729_user_login_mfa_u1_up.sql'
    )) as $statement) {
        $admin->exec($statement);
    }
    $admin->exec((string) file_get_contents(
        dirname(__DIR__, 2) . '/docs/migrations/20260730_user_login_mfa_u8_pilot_up.sql'
    ));
    $admin->exec(
        "INSERT INTO user_login_mfa_pilot_users(
            u_id,pilot_category,enrolled_by,change_reference
         ) VALUES('PILOT1','STAFF','ADMIN1','U8-TEST')"
    );
    $audit = new U8Audit();
    $decision = new UserMfaPrimaryAuthDecision(
        new PdoUserMfaPolicyReader($admin),
        new UserMfaPendingLoginCoordinator(
            new PdoUserMfaPendingLoginPersistence($admin, $audit)
        )
    );

    $off = $decision->afterPasswordAccepted(
        'PILOT1', 'u8-session', 'U8 Browser', '127.0.0.1', 'OFF'
    );
    $report(
        $off['code'] === 'USER_MFA_NOT_REQUIRED'
        && (int) $admin->query('SELECT COUNT(*) FROM user_login_mfa_transactions')->fetchColumn() === 0,
        'OFF preserves direct login and creates no pending transaction'
    );

    $admin->exec(
        "UPDATE user_login_mfa_policy
            SET policy_mode='ENROLLMENT',configuration_version=configuration_version+1"
    );
    $enrollment = $decision->afterPasswordAccepted(
        'PILOT1', 'u8-session', 'U8 Browser', '127.0.0.1', 'ENROLLMENT'
    );
    $report(
        $enrollment['reason'] === 'ENROLLMENT_ONLY'
        && (int) $admin->query('SELECT COUNT(*) FROM user_login_mfa_transactions')->fetchColumn() === 0,
        'ENROLLMENT preserves direct login for self-service setup'
    );

    $admin->exec(
        "UPDATE user_login_mfa_policy
            SET policy_mode='PILOT_ENFORCED',configuration_version=configuration_version+1"
    );
    $normal = $decision->afterPasswordAccepted(
        'NORMAL1', 'normal-session', 'U8 Browser', '127.0.0.2', 'PILOT_ENFORCED'
    );
    $pilot = $decision->afterPasswordAccepted(
        'PILOT1', 'pilot-session', 'U8 Browser', '127.0.0.3', 'PILOT_ENFORCED'
    );
    $report(
        $normal['code'] === 'USER_MFA_NOT_REQUIRED'
        && $pilot['code'] === 'USER_MFA_REQUIRED'
        && (int) $admin->query('SELECT COUNT(*) FROM user_login_mfa_transactions')->fetchColumn() === 1,
        'PILOT_ENFORCED challenges allowlisted pilot only'
    );

    try {
        $decision->afterPasswordAccepted(
            'PILOT1', 'mismatch-session', 'U8 Browser', '127.0.0.4', 'OFF'
        );
        $mismatch = false;
    } catch (RuntimeException $exception) {
        $mismatch = $exception->getMessage() === 'USER_MFA_RUNTIME_DATABASE_POLICY_MISMATCH';
    }
    $report($mismatch, 'runtime and database mode mismatch fails closed before token issue');
    $report(
        $audit->events === ['USER_MFA_PRIMARY_AUTH_PENDING'],
        'only enforced pilot decision writes pending-login audit'
    );
} finally {
    if ($created) {
        $admin->exec('USE information_schema');
        $admin->exec("DROP DATABASE {$quoted}");
    }
}

printf(
    "RESULT checks=%d failures=%d shared_database_mutations=0 runtime_activation=0\n",
    $checks,
    $failures
);
exit($failures === 0 ? 0 : 1);
