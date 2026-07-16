<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/app/Admin/SsoConfigurationException.php';
require_once dirname(__DIR__).'/app/Admin/PasswordRecoveryConfigurationService.php';
use OneId\App\Admin\PasswordRecoveryConfigurationService;
use OneId\App\Admin\SsoConfigurationException;
final class Sc6Fake{public array $stored=['id'=>1,'password_reset_email_enabled'=>1];public int $updates=0,$commits=0,$rollbacks=0;public array $events=[];function get_system_config(){return $this->stored;}function get_system_config_for_update(){return $this->stored;}function beginTransaction(){return true;}function commit(){$this->commits++;}function rollback(){$this->rollbacks++;}function update_password_recovery_by_id($id,$enabled){$this->updates++;$this->stored['password_reset_email_enabled']=$enabled;return 1;}function syslog_record($event,$detail,$ip){$this->events[]=$event;return 1;}}
$n=0;$f=0;$ok=function($v,$d)use(&$n,&$f){$n++;if(!$v)$f++;printf("%s: %s\n",$v?'PASS':'FAIL',$d);};
$fake=new Sc6Fake();$service=new PasswordRecoveryConfigurationService($fake);$read=$service->read();
$ok($read['code']==='SC6_RECOVERY_LOADED'&&$read['data']['password_reset_email_enabled']===1&&$read['data']['manual_recovery_available']===false,'recovery policy is read separately with no manual fallback');
$updated=$service->update('0','admin.test','127.0.0.1');$ok($updated['code']==='SC6_RECOVERY_UPDATED'&&$fake->events===[33]&&$fake->commits===1,'recovery policy update is atomic and audited');
try{$service->update('yes','admin.test','127.0.0.1');$ok(false,'invalid policy rejected');}catch(SsoConfigurationException $e){$ok($e->reason==='SC6_EMAIL_POLICY_INVALID','invalid policy rejected before mutation');}
$root=dirname(__DIR__);$q=(string)file_get_contents($root.'/lib/q_func.php');$ui=(string)file_get_contents($root.'/admin/dashboard.php');$sso=(string)file_get_contents($root.'/app/Admin/SsoConfigurationService.php');
$ok(str_contains($q,'filter_var((string)$uid_result[\'data5\'],FILTER_VALIDATE_EMAIL)')&&str_contains($q,'otp_invalidate_active($uid_result[\'u_id\'])')&&str_contains($q,"syslog_record(35"),'undeliverable recovery fails closed and records delivery failure');
$ok(str_contains($ui,'Password Recovery')&&str_contains($ui,'test_password_recovery_email')&&!str_contains($sso,'sso_settings_OTP_email'),'UI, endpoint and service separate recovery from SSO policy');
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$cols=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sys_config' AND COLUMN_NAME='password_reset_email_enabled'")->fetchColumn();$events=(int)$pdo->query("SELECT COUNT(*) FROM syslog_event_conf WHERE syslog_event_id IN(33,34,35)")->fetchColumn();$ok($cols===1&&$events===3,'SC6 database column and audit events are installed');
printf("RESULT: checks=%d failures=%d\n",$n,$f);exit($f===0?0:1);
