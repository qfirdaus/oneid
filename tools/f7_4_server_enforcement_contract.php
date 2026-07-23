<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(2);}require_once dirname(__DIR__).'/lib/request_security.php';
final class F74State{public array $state;public array $audit=[];public function __construct(array $s){$this->state=$s;}public function admin_step_up_authorization_state($a,$s,$b,$p){return$this->state;}public function syslog_record($e,$d,$ip){$this->audit[]=[$e,$d,$ip];return 1;}}
if(session_status()!==PHP_SESSION_ACTIVE){session_id('f74contractsession');session_start();}$_SESSION=['login_status'=>'true','login_user'=>'admin-contract','login_user_type'=>'1'];$_SERVER['HTTP_USER_AGENT']='F7.4 contract browser';$_SERVER['REMOTE_ADDR']='127.0.0.1';
$base=['admin_2fa_enabled'=>1,'u_type'=>1,'avail_status'=>1,'exact_valid'=>0,'exact_expired'=>0,'other_valid'=>0];$tests=[];
$tests['disabled_compatible']=oneid_admin_step_up_decision(new F74State(array_replace($base,['admin_2fa_enabled'=>0])),'ADMIN_ACCESS')['allowed']===true;
$tests['exact_grant_allows']=oneid_admin_step_up_decision(new F74State(array_replace($base,['exact_valid'=>1])),'ADMIN_ACCESS')['reason']==='STEP_UP_GRANTED';
$tests['missing_denied']=oneid_admin_step_up_decision(new F74State($base),'ADMIN_ACCESS')['reason']==='STEP_UP_REQUIRED';
$tests['expired_denied']=oneid_admin_step_up_decision(new F74State(array_replace($base,['exact_expired'=>1])),'ADMIN_ACCESS')['reason']==='STEP_UP_EXPIRED';
$tests['purpose_isolated']=oneid_admin_step_up_decision(new F74State(array_replace($base,['other_valid'=>1])),'SECURITY_CONFIGURATION_CHANGE')['reason']==='STEP_UP_PURPOSE_MISMATCH';
$tests['unknown_purpose_denied']=oneid_admin_step_up_decision(new F74State($base),'UNKNOWN')['allowed']===false;
$map=oneid_q_func_action_map();$tests['inventory_49']=count($map['admin'])===49&&count(array_unique($map['admin']))===49;
$security=['update_password_recovery','test_password_recovery_email','preview_configuration_update','update_configuration'];$tests['sensitive_exact']=count(array_filter($map['admin'],fn($a)=>oneid_admin_action_purpose($a)==='SECURITY_CONFIGURATION_CHANGE'))===count($security)&&count(array_diff($security,$map['admin']))===0;
$tests['ordinary_admin_access']=count(array_filter($map['admin'],fn($a)=>oneid_admin_action_purpose($a)==='ADMIN_ACCESS'))===45;
$tests['challenge_tier_bounded']=$map['step_up']===['admin_step_up_status','admin_step_up_request_email','admin_step_up_verify_email','admin_step_up_verify_totp'];
$op=new F74State($base);$cid=oneid_audit_step_up_rejection($op,'ADMIN_ACCESS','STEP_UP_REQUIRED');$dump=serialize($op->audit);$tests['structured_safe_audit']=preg_match('/\A[a-f0-9]{16}\z/',$cid)===1&&str_contains($dump,'STEP_UP_REQUIRED')&&!str_contains($dump,session_id());
$guard=(string)file_get_contents(dirname(__DIR__).'/lib/request_security.php');$dashboard=(string)file_get_contents(dirname(__DIR__).'/admin/dashboard.php');$users=(string)file_get_contents(dirname(__DIR__).'/admin/user_list.php');$tests['ajax_guard_wired']=str_contains($guard,"oneid_require_admin_step_up(\$operation,oneid_admin_action_purpose(\$matchedActions[0]),true)");$tests['pages_guarded']=str_contains($dashboard,"oneid_require_admin_step_up(\$operation, 'ADMIN_ACCESS', false)")&&str_contains($users,"oneid_require_admin_step_up(\$operation, 'ADMIN_ACCESS', false)");
$failed=array_keys(array_filter($tests,fn($v)=>!$v));printf("F7_4_ENFORCEMENT checks=%d passed=%d\n",count($tests),count($tests)-count($failed));if($failed){fwrite(STDERR,'FAIL '.implode(',',$failed)."\n");exit(1);}echo "PASS FEATURE_OFF_COMPATIBILITY_PURPOSE_ISOLATION_DIRECT_GUARDS\n";
