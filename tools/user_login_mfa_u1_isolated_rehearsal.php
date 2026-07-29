<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$root = dirname(__DIR__);
$database = 'oneid_user_mfa_u1_' . bin2hex(random_bytes(6));
if (preg_match('/\Aoneid_user_mfa_u1_[a-f0-9]{12}\z/', $database) !== 1) {
    throw new RuntimeException('USER_MFA_U1_REHEARSAL_NAME_INVALID');
}
$quotedDatabase = '`' . $database . '`';
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$splitSql = static function (string $sql): array {
    return array_values(array_filter(
        array_map('trim', preg_split('/;\s*(?:\R|$)/', $sql) ?: []),
        static fn(string $statement): bool => $statement !== ''
    ));
};

$created = false;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$failed): void {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) {
        $failed++;
    }
};

try {
    $pdo->exec("CREATE DATABASE {$quotedDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $created = true;
    $pdo->exec("USE {$quotedDatabase}");
    $pdo->exec(
        'CREATE TABLE user_tbl (
            u_id VARCHAR(20) NOT NULL,
            PRIMARY KEY (u_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
    );
    $pdo->exec("INSERT INTO user_tbl(u_id) VALUES ('U1TEST'),('ADMIN1')");

    foreach ($splitSql((string) file_get_contents(
        $root . '/docs/migrations/20260729_user_login_mfa_u1_up.sql'
    )) as $statement) {
        $pdo->exec($statement);
    }

    $tableCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema = DATABASE()
            AND table_name IN (
              'user_login_mfa_policy','user_login_mfa_policy_history',
              'user_mfa_factors','user_mfa_preferences',
              'user_login_mfa_transactions','user_login_mfa_challenges'
            )"
    )->fetchColumn();
    $report($tableCount === 6, 'six additive U1 tables are created');

    $policy = $pdo->query(
        'SELECT policy_mode,login_scope,email_enabled,totp_enabled,
                pending_ttl_seconds,otp_ttl_seconds,max_attempts,
                resend_cooldown_seconds,hourly_send_limit
           FROM user_login_mfa_policy WHERE singleton_key=1'
    )->fetch();
    $report(
        is_array($policy)
        && $policy['policy_mode'] === 'OFF'
        && $policy['login_scope'] === 'PASSWORD_ONLY'
        && (int) $policy['email_enabled'] === 1
        && (int) $policy['totp_enabled'] === 0
        && (int) $policy['pending_ttl_seconds'] === 300
        && (int) $policy['otp_ttl_seconds'] === 300
        && (int) $policy['max_attempts'] === 5
        && (int) $policy['resend_cooldown_seconds'] === 60
        && (int) $policy['hourly_send_limit'] === 10,
        'singleton policy is fail-closed with approved limits'
    );

    $invalidPolicyBlocked = false;
    try {
        $pdo->exec(
            "UPDATE user_login_mfa_policy
                SET policy_mode='ENFORCED',email_enabled=0
              WHERE singleton_key=1"
        );
    } catch (PDOException) {
        $invalidPolicyBlocked = true;
    }
    $report($invalidPolicyBlocked, 'database blocks enforced MFA without email');

    $factor = $pdo->prepare(
        'INSERT INTO user_mfa_factors(
            u_id,factor_type,encrypted_secret,secret_nonce,key_version,
            factor_status,enrollment_session_hash,enrollment_browser_digest,
            correlation_id
         ) VALUES(?,?,?,?,?,?,?,?,?)'
    );
    $factor->execute([
        'U1TEST', 'TOTP', random_bytes(32), random_bytes(24), 'uat-v1',
        'ACTIVE', str_repeat('a', 64), str_repeat('b', 64), str_repeat('c', 32),
    ]);
    $factorId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO user_mfa_preferences(
            u_id,preferred_factor,configuration_version,correlation_id
         ) VALUES(?,?,?,?)'
    )->execute(['U1TEST', 'TOTP', 1, str_repeat('d', 32)]);

    $transactionId = str_repeat('e', 64);
    $pdo->prepare(
        'INSERT INTO user_login_mfa_transactions(
            transaction_id,u_id,primary_method,transaction_status,
            session_binding_hash,browser_digest,requesting_ip,policy_version,
            expires_at,correlation_id
         ) VALUES(?,?,?,?,?,?,?,?,DATE_ADD(NOW(6),INTERVAL 5 MINUTE),?)'
    )->execute([
        $transactionId, 'U1TEST', 'PASSWORD', 'PENDING',
        str_repeat('f', 64), str_repeat('1', 64), '127.0.0.1', 1,
        str_repeat('2', 32),
    ]);

    $pdo->prepare(
        'INSERT INTO user_login_mfa_challenges(
            challenge_id,transaction_id,u_id,factor_type,factor_id,
            attempts,max_attempts,expires_at,correlation_id
         ) VALUES(?,?,?,?,?,?,?,DATE_ADD(NOW(6),INTERVAL 5 MINUTE),?)'
    )->execute([
        str_repeat('3', 64), $transactionId, 'U1TEST', 'TOTP', $factorId,
        0, 5, str_repeat('4', 32),
    ]);
    $report(
        (int) $pdo->query('SELECT COUNT(*) FROM user_login_mfa_challenges')->fetchColumn() === 1,
        'dummy factor transaction and challenge satisfy constraints'
    );

    $forbidden = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema=DATABASE()
            AND table_name LIKE '%mfa%'
            AND LOWER(column_name) IN (
              'raw_otp','raw_totp','raw_secret','password','nric','session_id'
            )"
    )->fetchColumn();
    $report($forbidden === 0, 'schema contains no forbidden raw-material columns');

    foreach ($splitSql((string) file_get_contents(
        $root . '/docs/migrations/20260729_user_login_mfa_u1_down.sql'
    )) as $statement) {
        $pdo->exec($statement);
    }
    $remaining = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema=DATABASE() AND table_name<>'user_tbl'"
    )->fetchColumn();
    $report($remaining === 0, 'down migration removes U1 tables in dependency order');
} finally {
    if ($created) {
        $pdo->exec('USE information_schema');
        $pdo->exec("DROP DATABASE {$quotedDatabase}");
    }
}

$leftovers = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.schemata
      WHERE schema_name LIKE 'oneid_user_mfa_u1_%'"
)->fetchColumn();
$report($leftovers === 0, 'isolated rehearsal database is removed');

printf(
    "RESULT checks=%d failures=%d live_schema_mutations=0 rehearsal_database_removed=%s\n",
    6,
    $failed,
    $leftovers === 0 ? 'yes' : 'no'
);
exit($failed === 0 ? 0 : 1);
