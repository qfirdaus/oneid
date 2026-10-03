<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/Auth/MobileOidc/bootstrap.php';
use OneId\App\Auth\MobileOidc\{LifecycleSchema,PdoIdentitySource};
$socket=$argv[1]??'';
$base=realpath(dirname(__DIR__,2).'/.private');
if(PHP_SAPI!=='cli'||!$base||!str_starts_with($socket,$base.'/release-db-')||!str_ends_with($socket,'/mysql.sock')||realpath($socket)!==$socket)exit(2);
chdir(dirname($socket));
$pdo=new PDO('mysql:unix_socket=mysql.sock','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db='release_fixture_'.bin2hex(random_bytes(5));$pdo->exec('CREATE DATABASE '.$db);$pdo->exec('USE '.$db);
$n=0;
function check(bool $ok,string $label):void{global $n;if(!$ok)throw new RuntimeException($label);$n++;echo "PASS $label\n";}
// Reuse only synthetic DDL strings, never load fixture config or a real identity database.
$fixture=file_get_contents(dirname(__DIR__).'/mobile-oidc-hosted/database.php');
preg_match_all("/'(CREATE TABLE [^'\\n]+)'/",$fixture,$matches);
check(count($matches[1])>=13,'synthetic source DDL located');
foreach($matches[1] as $statement)$pdo->exec($statement);
$pdo->exec("INSERT INTO user_tbl(u_id,avail_status) VALUES('SYNTHETIC',1)");
$plan=require dirname(__DIR__,2).'/tools/release/mobile-schema.php';
foreach($plan as $statement)$pdo->exec($statement);
check((int)$pdo->query('SELECT observer_enabled FROM mobile_oidc_control')->fetchColumn()===0,'migration defaults OFF');
check((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()")->fetchColumn()===28,'28 lifecycle triggers');
check((int)$pdo->query('SELECT COUNT(*) FROM mobile_oidc_identity_epoch')->fetchColumn()===1,'existing synthetic identity seeded');
check(!LifecycleSchema::ready($pdo),'OFF observer fails readiness');
$pdo->exec('UPDATE mobile_oidc_control SET observer_enabled=1 WHERE singleton_id=1');
check(LifecycleSchema::ready($pdo),'enabled complete schema ready');
$old=(int)$pdo->query('SELECT security_version FROM mobile_oidc_identity_epoch')->fetchColumn();
$pdo->exec("UPDATE user_tbl SET avail_status=0 WHERE u_id='SYNTHETIC'");
check((int)$pdo->query('SELECT security_version FROM mobile_oidc_identity_epoch')->fetchColumn()===$old+1,'source revocation bumps identity version');
$pdo->beginTransaction();$pdo->exec("UPDATE user_tbl SET avail_status=1 WHERE u_id='SYNTHETIC'");$pdo->rollBack();
check((int)$pdo->query('SELECT security_version FROM mobile_oidc_identity_epoch')->fetchColumn()===$old+1,'source rollback also rolls back version');
$epoch=(int)$pdo->query('SELECT policy_epoch FROM mobile_oidc_control')->fetchColumn();
$pdo->exec('UPDATE mobile_oidc_control SET observer_enabled=0 WHERE singleton_id=1');
check((int)$pdo->query('SELECT policy_epoch FROM mobile_oidc_control')->fetchColumn()===$epoch+1,'disable increments policy epoch');
$pdo->exec("UPDATE user_tbl SET avail_status=1 WHERE u_id='SYNTHETIC'");
check((int)$pdo->query('SELECT security_version FROM mobile_oidc_identity_epoch')->fetchColumn()===$old+1,'disabled observer leaves security versions unchanged');
check((int)$pdo->query('SELECT COUNT(*) FROM mobile_oidc_identity_epoch')->fetchColumn()===1,'rollback retains identity subjects/state');
try{$pdo->exec($plan[0]);$blocked=false;}catch(PDOException){$blocked=true;}check($blocked,'duplicate migration does not silently replace tables');
$reader=new \OneId\App\Auth\UserMfa\PdoUserMfaPolicyReader($pdo,'production');
$ref=new ReflectionProperty($reader,'environment');check($ref->getValue($reader)==='production','MFA policy reader accepts explicit production scope');
// Verify constructor forwards environment to reader, rather than falling back to staging.
$source=new PdoIdentitySource($pdo,'OFF',false,fn()=>true,true,'production');
$found=false;foreach((new ReflectionObject($source))->getProperties() as $property){$value=$property->getValue($source);if($value instanceof \OneId\App\Auth\UserMfa\PdoUserMfaPolicyReader)$found=$ref->getValue($value)==='production';}
check($found,'PDO identity source forwards production MFA environment');
echo "$n private MySQL migration/rollback checks passed.\n";
