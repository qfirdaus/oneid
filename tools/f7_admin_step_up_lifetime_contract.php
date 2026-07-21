<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(2);}
$root=dirname(__DIR__);
require_once $root.'/lib/config.php';
require_once $root.'/app/Auth/AdminStepUpException.php';
require_once $root.'/app/Auth/AdminStepUpPolicyService.php';
final class LifetimePolicyFake{
 public array $stored=['id'=>1,'configuration_version'=>7,'admin_step_up_lifetime_minutes'=>15];public int $updates=0,$commits=0,$rollbacks=0;public array $history=[],$events=[];
 public function beginTransaction():void{} public function commit():void{$this->commits++;} public function rollback():void{$this->rollbacks++;}
 public function get_system_config_for_update():array{return $this->stored;}
 public function update_admin_step_up_lifetime_by_version(int $id,int $minutes,int $version):int{if($version!==$this->stored['configuration_version'])return 0;$this->updates++;$this->stored['admin_step_up_lifetime_minutes']=$minutes;$this->stored['configuration_version']++;return 1;}
 public function configuration_history_record(array $entry):int{$this->history[]=$entry;return 1;}
 public function syslog_record(int $event,string $detail,string $ip):int{$this->events[]=[$event,$detail,$ip];return 1;}
}
$checks=[];$fake=new LifetimePolicyFake();$service=new \OneId\App\Auth\AdminStepUpPolicyService($fake);$result=$service->update('0530-09',30,7,'UAT approved lifetime change','127.0.0.1');
$checks['atomic_update_audit']=$result['code']==='STEP_UP_LIFETIME_UPDATED'&&$result['applies_to_new_grants_only']===true&&$fake->updates===1&&$fake->commits===1&&count($fake->history)===1&&($fake->events[0][0]??0)===54;
$beforeUpdates=$fake->updates;$unchanged=$service->update('0530-09',30,8,'Confirm unchanged policy','127.0.0.1');$checks['unchanged_no_mutation']=$unchanged['code']==='STEP_UP_LIFETIME_UNCHANGED'&&$fake->updates===$beforeUpdates;
try{$service->update('0530-09',60,8,'Invalid out of range value','127.0.0.1');$checks['allowlist_rejects']=false;}catch(\OneId\App\Auth\AdminStepUpException $e){$checks['allowlist_rejects']=$e->reason==='STEP_UP_LIFETIME_INVALID';}
$db=(string)file_get_contents($root.'/lib/Database.php');$email=(string)file_get_contents($root.'/app/Auth/AdminStepUpEmailOtpService.php');$totp=(string)file_get_contents($root.'/app/Auth/AdminStepUpTotpService.php');$ui=(string)file_get_contents($root.'/admin/dashboard.php');
$checks['new_grants_only']=str_contains($db,'DATE_ADD(NOW(),INTERVAL {$minutes} MINUTE)')&&!str_contains($db,'UPDATE admin_step_up_grants SET expires_at')&&str_contains($email,"'lifetime_minutes'")&&str_contains($totp,"'lifetime_minutes'");
$guard=(string)file_get_contents($root.'/lib/request_security.php');
$checks['ui_and_guard']=str_contains($ui,'id="admin_step_up_lifetime_minutes"')&&str_contains($ui,'admin_2fa_update_lifetime')&&str_contains($guard,'admin_2fa_update_lifetime');
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$column=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sys_config' AND COLUMN_NAME='admin_step_up_lifetime_minutes'")->fetchColumn();$event=(int)$pdo->query("SELECT COUNT(*) FROM syslog_event_conf WHERE syslog_event_id=54")->fetchColumn();$value=(int)$pdo->query('SELECT admin_step_up_lifetime_minutes FROM sys_config WHERE singleton_key=1')->fetchColumn();$checks['live_schema_default']=$column===1&&$event===1&&$value===15;
$failed=array_keys(array_filter($checks,fn(bool $v):bool=>!$v));foreach($checks as$name=>$pass){printf("%s %s\n",$pass?'PASS':'FAIL',$name);}printf("RESULT checks=%d failed=%d\n",count($checks),count($failed));exit($failed===[]?0:1);
