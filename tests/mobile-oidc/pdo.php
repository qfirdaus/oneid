<?php
declare(strict_types=1);
require_once __DIR__ . '/Fixtures.php';
use OneId\Tests\MobileOidc\Fixture;
use OneId\App\Auth\MobileOidc\{PdoIdentitySource, PdoStateStore, MobileIdentityAdapter, UatAdapterFactory};
use OneId\App\Auth\Totp;

$input = json_decode(stream_get_contents(STDIN, 8192), true, 16, JSON_THROW_ON_ERROR);
$socket = $input['socket'] ?? '';
if (PHP_SAPI !== 'cli' || !preg_match('~\A/var/www/oneid-uat/\.private/mobile-oidc-poc/sql-[a-f0-9]{12}/mysql\.sock\z~', $socket)
    || realpath($socket) !== $socket) exit(2);
$pdo = new PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', 'root', '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$pdo->exec('CREATE DATABASE oneid_mobile_fixture CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
$pdo->exec('USE oneid_mobile_fixture');
$ddl = [
    'CREATE TABLE user_tbl(u_id VARCHAR(20) PRIMARY KEY,u_category INT,u_type INT,avail_status INT,u_password VARCHAR(255),password_change_required INT,data1 VARCHAR(100),data2 VARCHAR(100),data3 VARCHAR(100),data4 VARCHAR(100),data5 VARCHAR(100),data6 VARCHAR(100),data7 VARCHAR(100)) ENGINE=InnoDB',
    'CREATE TABLE external_source(source_code VARCHAR(30) PRIMARY KEY,source_family VARCHAR(20)) ENGINE=InnoDB',
    'CREATE TABLE user_external_identity(u_id VARCHAR(20),source_code VARCHAR(30),source_active INT,PRIMARY KEY(u_id,source_code)) ENGINE=InnoDB',
    'CREATE TABLE user_mfa_factors(factor_id BIGINT PRIMARY KEY,u_id VARCHAR(20),factor_type VARCHAR(20),factor_status VARCHAR(20),encrypted_secret BLOB,secret_nonce BLOB,key_version VARCHAR(32),last_used_time_step BIGINT NULL,last_used_at DATETIME(6) NULL) ENGINE=InnoDB',
    'CREATE TABLE user_login_mfa_policy(singleton_key INT PRIMARY KEY,policy_mode VARCHAR(30),login_scope VARCHAR(30),email_enabled INT,totp_enabled INT,pending_ttl_seconds INT,otp_ttl_seconds INT,max_attempts INT,resend_cooldown_seconds INT,hourly_send_limit INT,configuration_version INT) ENGINE=InnoDB',
    'CREATE TABLE user_login_mfa_category_policy(category_code VARCHAR(20) PRIMARY KEY,enforcement_enabled INT) ENGINE=InnoDB',
    'CREATE TABLE user_login_mfa_pilot_users(u_id VARCHAR(20) PRIMARY KEY,pilot_status VARCHAR(20)) ENGINE=InnoDB',
    'CREATE TABLE user_login_mfa_exemptions(u_id VARCHAR(20),exemption_status VARCHAR(20),starts_at DATETIME(6),expires_at DATETIME(6)) ENGINE=InnoDB',
    'CREATE TABLE token_tbl(fixture_marker VARCHAR(20)) ENGINE=InnoDB',
];
foreach ($ddl as $sql) $pdo->exec($sql);
$schema = file_get_contents(dirname(__DIR__, 2) . '/docs/migrations/mobile-oidc-state.sql');
$schema = preg_replace('/^--.*$/m', '', $schema);
foreach (explode(';', $schema) as $sql) if (trim($sql) !== '') $pdo->exec($sql);
$pdo->exec("INSERT INTO user_login_mfa_policy VALUES(1,'ENFORCED','PASSWORD_ONLY',1,1,300,120,5,60,10,1)");
$pdo->exec("INSERT INTO user_login_mfa_category_policy VALUES('STAFF',0),('STUDENT',1)");
$pdo->exec("INSERT INTO token_tbl VALUES('WEB_UNCHANGED')");
$f = new Fixture(); $f->time = time();
$q = $pdo->prepare('INSERT INTO user_tbl VALUES(:u_id,:u_category,:u_type,:avail_status,:u_password,:password_change_required,:data1,:data2,:data3,:data4,:data5,:data6,:data7)');
foreach ($f->source->rows as $row) { unset($row['families'], $row['totp']); $q->execute($row); }
$pdo->exec("INSERT INTO external_source VALUES('STAFF','staff'),('STUDENT','student')");
$pdo->exec("INSERT INTO user_external_identity VALUES('STAFF_FIXTURE','STAFF',1),('STUDENT_FIXTURE','STUDENT',1)");
$store = new PdoStateStore($pdo);
$source = new PdoIdentitySource($pdo, 'ENFORCED', true, fn() => true);
$key = random_bytes(32);
$make = fn() => new MobileIdentityAdapter($source, $store, $f->provider, $f->delivery, $f->primitive,
    ['fixture-client' => ['http://127.0.0.1/callback']], $key, true, 'staging', fn() => $f->time);
$adapter = $make();
$checks = 0;
function check(bool $ok, string $name): void { global $checks; $checks++; if (!$ok) throw new RuntimeException('FAIL ' . $name); echo 'PASS ' . $name . PHP_EOL; }
$begin = fn() => $adapter->begin(bin2hex(random_bytes(16)), $f->binding, $f->agent, '127.0.0.1')['transaction_id'];
$password = fn($id, $number) => $adapter->password($id, $f->binding, $f->agent, $number, 'Fixture-only!4927');
$finish = fn($id) => $adapter->complete($id, $f->binding, $f->agent);
check($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql', 'real MySQL native PDO fixture connected over private UNIX socket');
try { $source->account('STAFF_FIXTURE'); $outside = false; } catch (RuntimeException) { $outside = true; }
check($outside, 'identity reads require transaction lock');
$id = $begin();
check(($password($id, '0530-09')['code'] ?? '') === 'AUTHENTICATION_READY', 'PDO staff resolver and real category policy align');
check(($finish($id)['code'] ?? '') === 'LOGIN_ACCEPTED', 'PDO transaction persists mobile provider acceptance');
$first = $f->provider->accepted[0];
$restarted = $make();
check(isset($restarted->session($first['sid'], 'fixture-client', $first['sub'])['sub']), 'adapter reconstruction preserves SQL session and subject');
$id = $begin();
check(($password($id, 'M123456')['code'] ?? '') === 'MFA_REQUIRED', 'PDO matric resolver requires real student policy');
check(($adapter->sendEmail($id, $f->binding, $f->agent)['code'] ?? '') === 'OTP_SENT', 'PDO email challenge persists and delivery activated');
check(($adapter->verify($id, $f->binding, $f->agent, 'email', $f->delivery->otp)['code'] ?? '') === 'AUTHENTICATION_READY', 'PDO email challenge verifies through shared OTP primitive');
check(($finish($id)['code'] ?? '') === 'LOGIN_ACCEPTED', 'PDO student MFA completes without legacy token creation');
$student = $f->provider->accepted[1];
check($student['sub'] !== $first['sub'], 'SQL subject map separates staff and student accounts');
$pdo->exec("UPDATE user_tbl SET u_type=1 WHERE u_id='STAFF_FIXTURE'");
check($store->transaction(fn() => $source->policy($source->account('STAFF_FIXTURE') + ['account_type' => 'staff']))['required'] === false, 'PDO admin-staff follows disabled staff category for mobile');
$pdo->exec("UPDATE user_login_mfa_policy SET policy_mode='OFF'");
check($store->transaction(fn() => $source->policy($source->account('STAFF_FIXTURE') + ['account_type' => 'staff']))['required'] === false, 'mobile admin-staff follows user MFA OFF');
$pdo->exec("UPDATE user_login_mfa_category_policy SET enforcement_enabled=1 WHERE category_code='STAFF'");
$pdo->exec("UPDATE user_login_mfa_policy SET policy_mode='ENFORCED'");
check($store->transaction(fn() => $source->policy($source->account('STAFF_FIXTURE') + ['account_type' => 'staff']))['required'] === true, 'mobile admin-staff requires MFA when user policy enforces staff');
$runtimeOff = new \OneId\App\Auth\MobileOidc\PdoIdentitySource($pdo, 'OFF', false, fn() => true);
check($store->transaction(fn() => $runtimeOff->policy($runtimeOff->account('STAFF_FIXTURE') + ['account_type' => 'staff']))['required'] === false, 'runtime OFF disables mobile MFA even with stored ENFORCED');
$pdo->exec("UPDATE user_login_mfa_policy SET policy_mode='PILOT_ENFORCED'");
check($store->transaction(fn() => $source->policy($source->account('STAFF_FIXTURE') + ['account_type' => 'staff']))['required'] === false, 'admin-staff outside pilot does not require mobile MFA');
$pdo->exec("INSERT INTO user_login_mfa_pilot_users VALUES('STAFF_FIXTURE','ACTIVE')");
check($store->transaction(fn() => $source->policy($source->account('STAFF_FIXTURE') + ['account_type' => 'staff']))['required'] === true, 'admin-staff inside pilot requires mobile MFA');
$pdo->exec("UPDATE user_login_mfa_policy SET policy_mode='ENROLLMENT'");
check($store->transaction(fn() => $source->policy($source->account('STAFF_FIXTURE') + ['account_type' => 'staff']))['required'] === false, 'enrollment mode does not force mobile MFA');
$pdo->exec("UPDATE user_login_mfa_category_policy SET enforcement_enabled=0 WHERE category_code='STAFF'");
$pdo->exec("UPDATE user_login_mfa_policy SET policy_mode='ENFORCED'");
$pdo->exec("UPDATE user_tbl SET u_type=0 WHERE u_id='STAFF_FIXTURE'");
$pdo->exec("DELETE FROM user_external_identity WHERE u_id='STAFF_FIXTURE'");
$id = $begin(); check(($password($id, '0530-09')['code'] ?? '') === 'AUTHENTICATION_READY', 'PDO manual staff category fallback works without membership');
$pdo->exec("UPDATE user_tbl SET password_change_required=1 WHERE u_id='STAFF_FIXTURE'");
check(($finish($id)['error'] ?? '') === 'MOBILE_TRANSACTION_INVALID', 'PDO locked recheck catches password policy change before acceptance');
$pdo->exec("UPDATE user_tbl SET password_change_required=0 WHERE u_id='STAFF_FIXTURE'");
$pdo->exec("INSERT INTO user_login_mfa_exemptions VALUES('STUDENT_FIXTURE','ACTIVE',NOW()-INTERVAL 1 MINUTE,NOW()+INTERVAL 1 MINUTE)");
check($store->transaction(fn() => $source->policy($source->account('STUDENT_FIXTURE') + ['account_type' => 'student']))['required'] === false, 'existing active temporary exemption policy reused');
$pdo->exec("UPDATE user_login_mfa_exemptions SET expires_at=NOW()-INTERVAL 1 SECOND");
check($store->transaction(fn() => $source->policy($source->account('STUDENT_FIXTURE') + ['account_type' => 'student']))['required'] === true, 'expired exemption restores mandatory MFA');
$material = $f->primitive->enroll('Fixture', 'Student');
$q = $pdo->prepare("INSERT INTO user_mfa_factors VALUES(1,'STUDENT_FIXTURE','TOTP','ACTIVE',:secret,:nonce,:version,NULL,NULL)");
$q->execute(['secret' => $material['encrypted_secret'], 'nonce' => $material['secret_nonce'], 'version' => $material['key_version']]);
$id = $begin(); $password($id, 'M123456'); $otp = Totp::codeAt($material['secret'], $f->time);
check(($adapter->verify($id, $f->binding, $f->agent, 'totp', $otp)['code'] ?? '') === 'AUTHENTICATION_READY', 'PDO encrypted TOTP verifies and atomically advances shared counter');
check((int) $pdo->query('SELECT last_used_time_step FROM user_mfa_factors WHERE factor_id=1')->fetchColumn() === intdiv($f->time, 30), 'shared OneID TOTP step persisted');
$stale = ['factor_id' => 1, 'last_used_time_step' => null];
check(!$store->transaction(fn() => $source->consumeTotp($stale, intdiv($f->time, 30))), 'stale concurrent TOTP compare-and-swap is refused');
$id2 = $begin(); $password($id2, 'M123456');
check(($adapter->verify($id2, $f->binding, $f->agent, 'totp', $otp)['error'] ?? '') === 'MOBILE_FACTOR_INVALID', 'PDO TOTP replay rejected across transactions');
$adapter->invalidateAccount('STUDENT_FIXTURE');
check(($finish($id)['error'] ?? '') === 'MOBILE_TRANSACTION_INVALID', 'persistent security version invalidates pending completed MFA');
try { $store->transaction(function () use ($store): void { $store->put('fixture-rollback', ['value' => 1]); throw new RuntimeException('fixture rollback'); }); } catch (RuntimeException) {}
check($store->transaction(fn() => $store->get('fixture-rollback')) === null, 'SQL rollback preserves atomic state');
check($pdo->query('SELECT fixture_marker FROM token_tbl')->fetchAll(PDO::FETCH_COLUMN) === ['WEB_UNCHANGED'], 'legacy token table unchanged');
check((int) $pdo->query("SELECT COUNT(*) FROM mobile_oidc_records WHERE record_key LIKE 'audit:%'")->fetchColumn() > 0, 'authentication audit persisted without credentials');
check(session_status() === PHP_SESSION_NONE && headers_list() === [], 'SQL adapter emits no legacy PHP session or cookies');
$rejected = false;
try { UatAdapterFactory::create($pdo, [], $f->delivery, $f->primitive, fn() => true); }
catch (RuntimeException) { $rejected = true; }
check($rejected, 'composition root defaults to disabled');
$configured = UatAdapterFactory::create($pdo, ['enabled' => true, 'environment' => 'staging',
    'mfa_runtime_mode' => 'ENFORCED', 'mfa_activation_authorized' => true,
    'admin_url' => 'http://127.0.0.1:4445', 'issuer' => 'http://127.0.0.1:4444',
    'clients' => ['fixture-client' => ['http://127.0.0.1/callback']], 'binding_key' => $key],
    $f->delivery, $f->primitive, fn() => true);
check($configured instanceof MobileIdentityAdapter, 'explicit UAT composition connects same PDO and shared dependencies without activating routes');
require __DIR__.'/mydigitalid-pdo.php';
require __DIR__.'/recovery-pdo.php';
echo "Result: {$checks}/{$checks} passed\n";
