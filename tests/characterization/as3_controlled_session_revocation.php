<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap/app.php';
require_once dirname(__DIR__,2).'/lib/session_security.php';
require_once dirname(__DIR__,2).'/lib/auth_security.php';
require_once dirname(__DIR__,2).'/app/Admin/ActiveSessionRevocationException.php';
require_once dirname(__DIR__,2).'/app/Admin/ActiveSessionRevocationConfig.php';
require_once dirname(__DIR__,2).'/app/Admin/Adapters/SessionRevocationPreviewStore.php';
require_once dirname(__DIR__,2).'/app/Admin/ActiveSessionRevocationService.php';
use OneId\App\Admin\ActiveSessionRevocationException;
use OneId\App\Admin\ActiveSessionRevocationService;
use OneId\App\Admin\Adapters\SessionRevocationPreviewStore;

final class As3Operation {
 public array $row; public int $updates=0,$audits=0,$commits=0,$rollbacks=0;
 public function __construct(string $state='expired',int $type=2,int $current=0){$this->row=['user_id'=>'STUDENT001','token_id'=>hash('sha256','target-token'),'name'=>'Student One','device_info'=>'Firefox / Linux','u_type'=>$type,'issued_at'=>'2026-01-01 00:00:00','last_activity_at'=>'2026-01-01 01:00:00','revoke_at'=>null,'lifecycle_status'=>$state,'is_current'=>$current];}
 public function admin_step_up_authorization_state(){return['admin_2fa_enabled'=>1,'u_type'=>1,'avail_status'=>1,'exact_valid'=>1];}
 public function admin_session_revocation_target(){return$this->row;}
 public function admin_session_revocation_target_for_update(){return$this->row;}
 public function beginTransaction(){return true;} public function commit(){$this->commits++;return true;} public function rollback(){$this->rollbacks++;return true;}
 public function admin_revoke_exact_session(){return ++$this->updates===1?1:0;}
 public function syslog_record($event,$detail,$ip){if($event!==66||str_contains($detail,'target-token'))return 0;$this->audits++;return 1;}
}
putenv('ONEID_ACTIVE_SESSION_REVOCATION_ENABLED=true');putenv('ONEID_ACTIVE_SESSION_REVOCATION_PILOT_STATES=due,expired');putenv('ONEID_ACTIVE_SESSION_REVOCATION_ALLOW_ADMIN_TARGET=false');putenv('ONEID_ACTIVE_SESSION_REVOCATION_ALLOW_REVOKE_ALL=false');
if(session_status()!==PHP_SESSION_ACTIVE){session_id('as3controlledcontract');session_start();}$_SESSION=[];
$op=new As3Operation();$store=new SessionRevocationPreviewStore();$service=new ActiveSessionRevocationService($op,$store,8.0);$target=$store->issueTarget('ADMIN01',['user_id'=>'STUDENT001','token_id'=>$op->row['token_id']]);$preview=$service->preview(['admin_preview_active_session_revocation'=>'','target_id'=>$target],'ADMIN01','current-token');$apply=$service->apply(['admin_apply_active_session_revocation'=>'','approval_id'=>$preview['approval_id'],'reason'=>'Confirmed inactive student session','confirmation'=>$preview['confirmation_phrase']],'ADMIN01','current-token','127.0.0.1');
$checks=['opaque_preview'=>preg_match('/\A[a-f0-9]{64}\z/',$preview['approval_id'])===1&&!str_contains(serialize($preview),'target-token'),'exact_reconcile'=>$apply['revoked']===1&&$op->updates===1&&$op->audits===1&&$op->commits===1,'one_use'=>false,'admin_block'=>false,'current_block'=>false,'state_block'=>false];
try{$service->apply(['admin_apply_active_session_revocation'=>'','approval_id'=>$preview['approval_id'],'reason'=>'Confirmed inactive student session','confirmation'=>$preview['confirmation_phrase']],'ADMIN01','current-token','127.0.0.1');}catch(ActiveSessionRevocationException $e){$checks['one_use']=$e->getMessage()==='AS3_APPROVAL_NOT_AVAILABLE';}
foreach(['admin_block'=>new As3Operation('expired',1,0),'current_block'=>new As3Operation('expired',2,1),'state_block'=>new As3Operation('active',2,0)] as$key=>$blocked){$s=new SessionRevocationPreviewStore();$svc=new ActiveSessionRevocationService($blocked,$s,8.0);$id=$s->issueTarget('ADMIN01',['user_id'=>'STUDENT001','token_id'=>$blocked->row['token_id']]);try{$svc->preview(['admin_preview_active_session_revocation'=>'','target_id'=>$id],'ADMIN01','current-token');}catch(ActiveSessionRevocationException $e){$checks[$key]=str_starts_with($e->getMessage(),'AS3_');}}
$failed=array_keys(array_filter($checks,fn($v)=>!$v));printf("AS3_CONTROLLED_REVOCATION checks=%d passed=%d\n",count($checks),count($checks)-count($failed));if($failed){fwrite(STDERR,'FAIL '.implode(',',$failed)."\n");exit(1);}echo "PASS OPAQUE_ONE_USE_EXACT_TRANSACTION_SELF_LOCKOUT\n";
