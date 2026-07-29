<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$root = dirname(__DIR__);
$database = 'oneid_user_mfa_purge_' . bin2hex(random_bytes(6));
$quoted = '`' . $database . '`';
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
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
    $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $created = true;
    $pdo->exec("USE {$quoted}");
    $pdo->exec(
        'CREATE TABLE user_tbl (u_id VARCHAR(20) NOT NULL PRIMARY KEY)
         ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
    );
    $pdo->exec("INSERT INTO user_tbl(u_id) VALUES('PURGETEST')");
    foreach ($split((string) file_get_contents(
        $root . '/docs/migrations/20260729_user_login_mfa_u1_up.sql'
    )) as $statement) {
        $pdo->exec($statement);
    }
    $pdo->exec((string) file_get_contents(
        $root . '/docs/migrations/20260730_user_login_mfa_terminal_otp_purge_up.sql'
    ));
    $pdo->exec(
        "INSERT INTO user_login_mfa_transactions(
            transaction_id,u_id,primary_method,session_binding_hash,browser_digest,
            requesting_ip,policy_version,expires_at,correlation_id
         ) VALUES(
            REPEAT('a',64),'PURGETEST','PASSWORD',REPEAT('b',64),REPEAT('c',64),
            '127.0.0.1',1,DATE_ADD(NOW(),INTERVAL 5 MINUTE),REPEAT('d',32)
         )"
    );
    $pdo->exec(
        "INSERT INTO user_login_mfa_challenges(
            challenge_id,transaction_id,u_id,factor_type,otp_hash,destination_hmac,
            max_attempts,expires_at,correlation_id
         ) VALUES(
            REPEAT('e',64),REPEAT('a',64),'PURGETEST','EMAIL_OTP',
            '\$argon2id\$fixture',REPEAT('f',64),5,
            DATE_ADD(NOW(),INTERVAL 5 MINUTE),REPEAT('1',32)
         )"
    );
    $pdo->exec(
        "UPDATE user_login_mfa_challenges
            SET revoked_at=NOW(6),otp_hash=NULL
          WHERE challenge_id=REPEAT('e',64)"
    );
    $row = $pdo->query(
        "SELECT revoked_at,otp_hash,destination_hmac
           FROM user_login_mfa_challenges WHERE challenge_id=REPEAT('e',64)"
    )->fetch(PDO::FETCH_ASSOC);
    $report(
        is_array($row)
        && $row['revoked_at'] !== null
        && $row['otp_hash'] === null
        && $row['destination_hmac'] !== null,
        'terminal e-mail challenge permits OTP hash purge and retains destination HMAC'
    );

    $invalidActiveBlocked = false;
    try {
        $pdo->exec(
            "INSERT INTO user_login_mfa_challenges(
                challenge_id,transaction_id,u_id,factor_type,otp_hash,destination_hmac,
                max_attempts,expires_at,correlation_id
             ) VALUES(
                REPEAT('2',64),REPEAT('a',64),'PURGETEST','EMAIL_OTP',
                NULL,REPEAT('3',64),5,DATE_ADD(NOW(),INTERVAL 5 MINUTE),REPEAT('4',32)
             )"
        );
    } catch (PDOException) {
        $invalidActiveBlocked = true;
    }
    $report($invalidActiveBlocked, 'active e-mail challenge still requires OTP hash');
} finally {
    if ($created) {
        $pdo->exec('USE information_schema');
        $pdo->exec("DROP DATABASE {$quoted}");
    }
}

$leftovers = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.schemata
      WHERE schema_name LIKE 'oneid_user_mfa_purge_%'"
)->fetchColumn();
$report($leftovers === 0, 'isolated purge rehearsal database is removed');
printf(
    "RESULT checks=%d failures=%d shared_database_mutations=0 rehearsal_database_removed=%s\n",
    $checks,
    $failed,
    $leftovers === 0 ? 'yes' : 'no'
);
exit($failed === 0 ? 0 : 1);
