<?php
declare(strict_types=1);
// Only an isolated local fixture socket. Never load OneID config or accept a database name.
require_once dirname(__DIR__, 2) . '/app/Auth/MobileOidc/bootstrap.php';
use OneId\App\Auth\MobileOidc\LifecycleSchema;
$input = json_decode(stream_get_contents(STDIN, 32768), true, 32, JSON_THROW_ON_ERROR);
$socket = $input['socket'] ?? '';
if (PHP_SAPI !== 'cli' || !preg_match('~\A/var/www/oneid-uat/\.private/mobile-oidc-poc/sql-[a-f0-9]{12}/mysql\.sock\z~', $socket) || realpath($socket) !== $socket) exit(2);
$pdo = new PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$action = $input['action'] ?? 'init';
if ($action === 'init') $pdo->exec('CREATE DATABASE oneid_mobile_hosted_fixture CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
$pdo->exec('USE oneid_mobile_hosted_fixture');
if ($action === 'init') {
    foreach ([
        'CREATE TABLE user_tbl(u_id VARCHAR(20) PRIMARY KEY,u_category INT,u_type INT,avail_status INT,u_password VARCHAR(255),password_change_required INT,data1 VARCHAR(100),data2 VARCHAR(100),data3 VARCHAR(100),data4 VARCHAR(100),data5 VARCHAR(100),data6 VARCHAR(100),data7 VARCHAR(100)) ENGINE=InnoDB',
        'CREATE TABLE external_source(source_code VARCHAR(30) PRIMARY KEY,source_family VARCHAR(20)) ENGINE=InnoDB',
        'CREATE TABLE user_external_identity(u_id VARCHAR(20),source_code VARCHAR(30),source_active INT,PRIMARY KEY(u_id,source_code)) ENGINE=InnoDB',
        'CREATE TABLE user_mfa_factors(factor_id BIGINT PRIMARY KEY,u_id VARCHAR(20),factor_type VARCHAR(20),factor_status VARCHAR(20),encrypted_secret BLOB,secret_nonce BLOB,key_version VARCHAR(32),last_used_time_step BIGINT NULL,last_used_at DATETIME(6) NULL) ENGINE=InnoDB',
        'CREATE TABLE user_login_mfa_policy(singleton_key INT PRIMARY KEY,policy_mode VARCHAR(30),login_scope VARCHAR(30),email_enabled INT,totp_enabled INT,pending_ttl_seconds INT,otp_ttl_seconds INT,max_attempts INT,resend_cooldown_seconds INT,hourly_send_limit INT,configuration_version INT) ENGINE=InnoDB',
        'CREATE TABLE user_login_mfa_category_policy(category_code VARCHAR(20) PRIMARY KEY,enforcement_enabled INT) ENGINE=InnoDB',
        'CREATE TABLE user_login_mfa_pilot_users(u_id VARCHAR(20) PRIMARY KEY,pilot_status VARCHAR(20)) ENGINE=InnoDB',
        'CREATE TABLE user_login_mfa_exemptions(u_id VARCHAR(20),exemption_status VARCHAR(20),starts_at DATETIME(6),expires_at DATETIME(6)) ENGINE=InnoDB',
        'CREATE TABLE user_mfa_policy_change_requests(request_id BIGINT PRIMARY KEY,environment VARCHAR(30),requested_mode VARCHAR(30),restore_mode VARCHAR(30),request_status VARCHAR(30),starts_at DATETIME(6),expires_at DATETIME(6),applied_policy_version INT,activated_at DATETIME(6)) ENGINE=InnoDB',
        'CREATE TABLE token_tbl(user_id VARCHAR(20),status INT,ended_at DATETIME NULL,end_reason VARCHAR(40) NULL) ENGINE=InnoDB',
        'CREATE TABLE otp_codes(u_id VARCHAR(20),otp_consumed_at DATETIME NULL) ENGINE=InnoDB',
        'CREATE TABLE user_password_history(id BIGINT PRIMARY KEY AUTO_INCREMENT,user_id VARCHAR(20),password_hash VARCHAR(255),changed_at DATETIME) ENGINE=InnoDB',
        'CREATE TABLE sys_config(singleton_key INT PRIMARY KEY,maintenance_mode VARCHAR(30),maintenance_starts_at DATETIME NULL,maintenance_ends_at DATETIME NULL) ENGINE=InnoDB',
    ] as $sql) $pdo->exec($sql);
    $schema = preg_replace('/^--.*$/m', '', file_get_contents(dirname(__DIR__, 2) . '/docs/migrations/mobile-oidc-state.sql'));
    foreach (explode(';', $schema) as $sql) if (trim($sql) !== '') $pdo->exec($sql);
    $pdo->exec("INSERT INTO user_login_mfa_policy VALUES(1,'ENFORCED','PASSWORD_ONLY',1,1,300,120,5,60,10,1)");
    $pdo->exec("INSERT INTO user_login_mfa_category_policy VALUES('STAFF',0),('STUDENT',1)");
    $pdo->exec("INSERT INTO sys_config VALUES(1,'OFF',NULL,NULL)");
    $pdo->exec("INSERT INTO external_source VALUES('STAFF','staff'),('STUDENT','student')");
    $pdo->exec("INSERT INTO user_external_identity VALUES('STAFF_FIXTURE','STAFF',1),('STUDENT_FIXTURE','STUDENT',1)");
    $q = $pdo->prepare("INSERT INTO user_tbl VALUES(:uid,:category,0,1,:hash,0,'Fixture User','fake-default',:staff,:matric,'fixture@example.invalid','Synthetic department','Synthetic position')");
    foreach ([['STAFF_FIXTURE',3,'0530-09','fixture-ic'],['STUDENT_FIXTURE',10,'','M123456']] as [$uid,$category,$staff,$matric]) {
        $q->execute(['uid'=>$uid,'category'=>$category,'hash'=>password_hash('Fixture-only!4927',PASSWORD_BCRYPT,['cost'=>4]),'staff'=>$staff,'matric'=>$matric]);
    }
    $pdo->exec("INSERT INTO token_tbl VALUES('STAFF_FIXTURE',1,NULL,NULL),('STUDENT_FIXTURE',1,NULL,NULL)");
    $pdo->exec("INSERT INTO otp_codes VALUES('STUDENT_FIXTURE',NULL)");
    foreach (LifecycleSchema::statements() as $sql) $pdo->exec($sql);
    $pdo->exec('UPDATE mobile_oidc_control SET observer_enabled=1 WHERE singleton_id=1');
} else {
    // Trusted CLI fixture operations only, no HTTP route exposes these mutations.
    $statements = match ($action) {
        'disable_enable' => ["UPDATE user_tbl SET avail_status=0 WHERE u_id='STAFF_FIXTURE'", "UPDATE user_tbl SET avail_status=1 WHERE u_id='STAFF_FIXTURE'"],
        'force_change' => ["UPDATE user_tbl SET password_change_required=1 WHERE u_id='STUDENT_FIXTURE'"],
        'reset_password' => ["UPDATE user_tbl SET u_password='invalid-fixture-reset' WHERE u_id='STAFF_FIXTURE'"],
        'policy_cycle' => ["UPDATE user_login_mfa_category_policy SET enforcement_enabled=1 WHERE category_code='STAFF'", "UPDATE user_login_mfa_category_policy SET enforcement_enabled=0 WHERE category_code='STAFF'"],
        'maintenance_on' => ["UPDATE sys_config SET maintenance_mode='INDEFINITE' WHERE singleton_key=1"],
        'maintenance_off' => ["UPDATE sys_config SET maintenance_mode='OFF' WHERE singleton_key=1"],
        'observer_cycle' => ['UPDATE mobile_oidc_control SET observer_enabled=0 WHERE singleton_id=1', 'UPDATE mobile_oidc_control SET observer_enabled=1 WHERE singleton_id=1'],
        'delete_recreate' => ["CREATE TEMPORARY TABLE old_staff AS SELECT * FROM user_tbl WHERE u_id='STAFF_FIXTURE'", "DELETE FROM user_tbl WHERE u_id='STAFF_FIXTURE'", 'INSERT INTO user_tbl SELECT * FROM old_staff'],
        'rollback_disable' => ['START TRANSACTION', "UPDATE user_tbl SET avail_status=0 WHERE u_id='STAFF_FIXTURE'", 'ROLLBACK'],
        'drop_guard' => ['DROP TRIGGER mobile_oidc_user_au'],
        'factor_revoke' => ["UPDATE user_mfa_factors SET factor_status='REVOKED' WHERE u_id='STUDENT_FIXTURE'"],
        'clear_rate' => ["DELETE FROM mobile_oidc_records WHERE record_key LIKE 'rate:%'"],
        default => [],
    };
    foreach ($statements as $sql) $pdo->exec($sql);
    if (in_array($action, ['legacy_rehash','stale_rehash'], true)) {
        require_once dirname(__DIR__, 2) . '/lib/Database.php';
        $class = new ReflectionClass(\Database::class);
        $legacy = $class->newInstanceWithoutConstructor();
        $class->getProperty('pdo')->setValue($legacy, $pdo);
        if ($action === 'legacy_rehash' && !$legacy->func_authenticate3('0530-09', 'Fixture-only!4927')) throw new RuntimeException('Fixture legacy auth failed');
        if ($action === 'stale_rehash' && $class->getMethod('updatePasswordHash')->invoke($legacy, 'STAFF_FIXTURE', password_hash('stale-overwrite', PASSWORD_DEFAULT), 0, 'a-stale-old-hash') !== 0) throw new RuntimeException('Stale rehash overwrote credential');
        if ($pdo->query('SELECT @oneid_mobile_password_rehash')->fetchColumn() !== null) throw new RuntimeException('Fixture rehash marker not cleared');
    }
    if ($action === 'enroll_totp') {
        $primitive = new \OneId\App\Auth\UserMfa\UserMfaTotpPrimitive(new \OneId\App\Auth\TotpSecretCipher(\OneId\App\Auth\TotpKeyring::fromFile($input['keyring'])));
        $material = $primitive->enroll('Fixture', 'Fixture Student');
        $q = $pdo->prepare("INSERT INTO user_mfa_factors VALUES(1,'STUDENT_FIXTURE','TOTP','ACTIVE',:secret,:nonce,:version,NULL,NULL)");
        $q->execute(['secret'=>$material['encrypted_secret'],'nonce'=>$material['secret_nonce'],'version'=>$material['key_version']]);
        // Private test-only pipe; never printed by Python runner or committed as evidence.
        echo json_encode(['totp_secret'=>$material['secret']]); exit;
    }
}
echo json_encode(['ready'=>LifecycleSchema::ready($pdo), 'triggers'=>count(LifecycleSchema::triggerNames()),
    'web_tokens'=>$pdo->query('SELECT user_id,status FROM token_tbl ORDER BY user_id')->fetchAll(PDO::FETCH_ASSOC),
    'history_count'=>(int)$pdo->query('SELECT COUNT(*) FROM user_password_history')->fetchColumn(),
    'active_reset_otps'=>(int)$pdo->query('SELECT COUNT(*) FROM otp_codes WHERE otp_consumed_at IS NULL')->fetchColumn(),
    'epochs'=>$pdo->query('SELECT u_id,security_version,deleted FROM mobile_oidc_identity_epoch ORDER BY u_id')->fetchAll(PDO::FETCH_ASSOC)]);
