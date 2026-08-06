<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(2);}
require_once dirname(__DIR__,2).'/app/Auth/AdminStepUpException.php';
require_once dirname(__DIR__,2).'/app/Auth/AdminStepUpSessionService.php';
use OneId\App\Auth\AdminStepUpException;
use OneId\App\Auth\AdminStepUpSessionService;
$checks=0;$failed=0;$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;$failed+=$ok?0:1;echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;};
$operation=new class{
 public bool $committed=false,$rolledBack=false;public int $revoked=1,$created=0,$auditEvent=0;
 public array|false $context=['u_type'=>1,'avail_status'=>1,'admin_2fa_enabled'=>1,'admin_step_up_lifetime_minutes'=>15,'verified_factor'=>'TOTP'];
 public function beginTransaction():void{} public function commit():void{$this->committed=true;} public function rollback():void{$this->rolledBack=true;}
 public function admin_step_up_renewal_context_for_update(...$args):array|false{return $this->context;}
 public function admin_step_up_revoke_active_access_grants(...$args):int{return $this->revoked;}
 public function admin_step_up_create_grant(array $entry):int{$this->created++;return $entry['verified_factor']==='TOTP'?1:0;}
 public function syslog_record(int $event,string $detail,string $ip):int{$this->auditEvent=$event;return str_contains($detail,'lifetime_minutes=15')?1:0;}
};
$result=(new AdminStepUpSessionService($operation))->renew('530','session-id','browser','127.0.0.1');
$report($result['code']==='ADMIN_ACCESS_RENEWED','valid current grant is renewed');
$report($result['grant_remaining_seconds']===900,'renewal uses configured lifetime');
$report($operation->revoked===1&&$operation->created===1&&$operation->committed,'old grant is replaced atomically');
$report($operation->auditEvent===67,'renewal writes dedicated audit event');
$expired=clone $operation;$expired->committed=false;$expired->rolledBack=false;$expired->revoked=0;$thrown=null;
try{(new AdminStepUpSessionService($expired))->renew('530','session-id','browser','127.0.0.1');}catch(AdminStepUpException $exception){$thrown=$exception->reason;}
$report($thrown==='STEP_UP_EXPIRED','expired grant cannot be renewed');
$report($expired->rolledBack&&!$expired->committed,'failed renewal rolls back');
$root=dirname(__DIR__,2);$client=file_get_contents($root.'/dist/js/oneid-admin-session.js')?:'';$security=file_get_contents($root.'/lib/request_security.php')?:'';$session=file_get_contents($root.'/lib/session_security.php')?:'';$dashboard=file_get_contents($root.'/admin/dashboard.php')?:'';
$report(str_contains($client,'var warningSeconds = 120;'),'warning is fixed at two minutes for every configured lifetime');
$report(str_contains($client,"post('admin_step_up_renew')")&&str_contains($security,"'admin_step_up_renew'"),'renewal action is wired through the guarded endpoint');
$report(str_contains($client,'BroadcastChannel')&&str_contains($client,"addEventListener('storage'"),'renewed deadline is synchronized across tabs');
$report(str_contains($client,"document.addEventListener('visibilitychange'")&&str_contains($client,'admin_step_up_status'),'visible tab revalidates against server state');
$report(str_contains($session,"['admin_step_up_status', 'purpose']"),'status polling is a technical heartbeat');
$report(str_contains($dashboard,'oneid-admin-session.js?v=20260806-1'),'Administrator dashboard loads the session controller');
$up=file_get_contents($root.'/docs/migrations/20260806_admin_access_renewal_audit_up.sql')?:'';$down=file_get_contents($root.'/docs/migrations/20260806_admin_access_renewal_audit_down.sql')?:'';
$report(str_contains($up,"67,'ADMIN_ACCESS_RENEW'")&&str_contains($down,'syslog_event_id=67'),'audit dictionary migration is reversible');
$schemaTool=file_get_contents($root.'/tools/admin_access_renewal_schema.php')?:'';
$report(str_contains($schemaTool,"['--check', '--apply']")&&str_contains($schemaTool,'20260806_admin_access_renewal_audit_up.sql'),'staging audit migration has an idempotent check/apply tool');
echo"RESULT checks={$checks} failed={$failed}".PHP_EOL;exit($failed===0?0:1);
