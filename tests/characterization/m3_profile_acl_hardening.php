<?php

if (PHP_SAPI !== 'cli') exit(2);

$root = dirname(__DIR__, 2);
require_once $root . '/app/User/UserManagementException.php';
require_once $root . '/app/User/UserProfilePolicyService.php';
require_once $root . '/app/User/UserAclManagementService.php';

use OneId\App\User\UserAclManagementService;
use OneId\App\User\UserManagementException;
use OneId\App\User\UserProfilePolicyService;

final class M3FakeOperation
{
    public array|false $user = [
        'u_id'=>'USER1','u_category'=>2,'u_type'=>1,'avail_status'=>1,
        'data1'=>'Original Name','account_source'=>'manual','sync_protected'=>1,
    ];
    public array|false $category = ['uc_id'=>3,'uc_name'=>'New Category'];
    public array|false $app = ['sp_id'=>'APP1','sp_name'=>'Application'];
    public array $acl = ['direct_allow'=>0,'category_allow'=>0,'denied'=>0];
    public array|false $blacklist = ['aclblk_id'=>7,'u_id'=>'USER1','sp_id'=>'APP1'];
    public array $calls = [];
    public int $auditResult = 1;
    public int $mutationResult = 1;

    public function beginTransaction(): void { $this->calls[]=['begin']; }
    public function commit(): void { $this->calls[]=['commit']; }
    public function rollback(): void { $this->calls[]=['rollback']; }
    public function admin_get_user_for_profile_action(string $id, bool $lock): array|false
    { $this->calls[]=['user',$id,$lock]; return $this->user; }
    public function admin_get_active_user_category(int $id): array|false
    { $this->calls[]=['category',$id]; return $this->category; }
    public function admin_update_user_profile_category(string $id,string $name,int $category): int
    { $this->calls[]=['profile',$id,$name,$category]; return $this->mutationResult; }
    public function admin_get_active_service_provider_for_acl(string $id): array|false
    { $this->calls[]=['app',$id]; return $this->app; }
    public function admin_get_user_acl_state(string $user,string $app): array
    { $this->calls[]=['acl-state',$user,$app]; return $this->acl; }
    public function add_new_specific_apps_to_user(string $user,string $app): int
    { $this->calls[]=['allow',$user,$app]; return $this->mutationResult; }
    public function admin_set_deny_access_record(string $app,string $user): int
    { $this->calls[]=['deny',$user,$app]; return $this->mutationResult; }
    public function admin_get_blacklist_record_for_action(int $id,bool $lock): array|false
    { $this->calls[]=['blacklist',$id,$lock]; return $this->blacklist; }
    public function admin_uplift_blacklist_record(int $id): int
    { $this->calls[]=['uplift',$id]; return $this->mutationResult; }
    public function update_whole_token_status(string $id,int $status): int
    { $this->calls[]=['tokens',$id,$status]; return 1; }
    public function syslog_record(int $event,string $detail,string $ip): int
    { $this->calls[]=['audit',$event,$detail,$ip]; return $this->auditResult; }
}

$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{
    $checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);
};
$reason=static function(callable $callback):string{
    try{$callback();}catch(UserManagementException $e){return $e->reason;}return '';
};

$profileOp=new M3FakeOperation();
$profile=(new UserProfilePolicyService($profileOp))->save('USER1','Updated Name','3','ADMIN1','127.0.0.1');
$report($profile['code']==='M3_PROFILE_SAVED','manual profile save returns explicit success');
$report(in_array(['profile','USER1','Updated Name',3],$profileOp->calls,true),'validated profile and category are persisted together');
$audit=array_values(array_filter($profileOp->calls,fn($c)=>$c[0]==='audit'))[0]??[];
$report(($audit[1]??null)===18&&str_contains((string)($audit[2]??''),'role_preserved=1'),'profile audit records that role is preserved');
$report(in_array(['tokens','USER1',0],$profileOp->calls,true),'category change revokes active sessions');
$report(array_column($profileOp->calls,0)===['begin','user','category','profile','tokens','audit','commit'],'profile save is one ordered transaction');

$external=new M3FakeOperation();$external->user['account_source']='external';
$report($reason(fn()=>(new UserProfilePolicyService($external))->save('USER1','Changed','3','ADMIN1','127.0.0.1'))==='M3_EXTERNAL_NAME_READ_ONLY','external-managed name is read-only');
$report(in_array('rollback',array_column($external->calls,0),true)&&!in_array('profile',array_column($external->calls,0),true),'external name rejection has zero mutation');

$categoryOnly=new M3FakeOperation();$categoryOnly->user['account_source']='external';
$result=(new UserProfilePolicyService($categoryOnly))->save('USER1','Original Name','3','ADMIN1','127.0.0.1');
$report($result['code']==='M3_PROFILE_SAVED','external account category may be saved without changing name');
$report(($categoryOnly->user['u_type']??null)===1&&!array_filter($categoryOnly->calls,fn($c)=>in_array('u_type',$c,true)),'category workflow never mutates administrator role');
$externalSpacing=new M3FakeOperation();$externalSpacing->user['account_source']='external';$externalSpacing->user['data1']='Name  With Space';
$report((new UserProfilePolicyService($externalSpacing))->save('USER1','Name  With Space','3','ADMIN1','127.0.0.1')['code']==='M3_PROFILE_SAVED','external category save preserves authoritative name whitespace');

$unchanged=new M3FakeOperation();$unchanged->category=['uc_id'=>2,'uc_name'=>'Current'];
$unchangedResult=(new UserProfilePolicyService($unchanged))->save('USER1','Original Name','2','ADMIN1','127.0.0.1');
$report($unchangedResult['code']==='M3_PROFILE_UNCHANGED','unchanged profile has explicit result');
$report(!in_array('profile',array_column($unchanged->calls,0),true)&&in_array('rollback',array_column($unchanged->calls,0),true),'unchanged profile performs zero mutation');

$badCategory=new M3FakeOperation();$badCategory->category=false;
$report($reason(fn()=>(new UserProfilePolicyService($badCategory))->save('USER1','Original Name','99','ADMIN1','127.0.0.1'))==='M3_CATEGORY_NOT_ACTIVE','inactive or missing category fails closed');
$auditFail=new M3FakeOperation();$auditFail->auditResult=0;
$report($reason(fn()=>(new UserProfilePolicyService($auditFail))->save('USER1','Updated','3','ADMIN1','127.0.0.1'))==='M3_AUDIT_NOT_WRITTEN','profile audit is mandatory');
$report(in_array('rollback',array_column($auditFail->calls,0),true)&&!in_array('commit',array_column($auditFail->calls,0),true),'profile audit failure rolls back');

$allowOp=new M3FakeOperation();
$allow=(new UserAclManagementService($allowOp))->allow('USER1','APP1','ADMIN1','127.0.0.1');
$report($allow['code']==='M3_ACL_ALLOWED','direct allow returns explicit success');
$report(array_column($allowOp->calls,0)===['begin','user','app','acl-state','allow','tokens','audit','commit'],'direct allow validates, mutates, revokes and audits atomically');
$allowAudit=array_values(array_filter($allowOp->calls,fn($c)=>$c[0]==='audit'))[0]??[];
$report(($allowAudit[1]??null)===28&&str_contains((string)($allowAudit[2]??''),'correlation='),'allow writes correlated event 28');

$duplicate=new M3FakeOperation();$duplicate->acl['direct_allow']=1;
$report($reason(fn()=>(new UserAclManagementService($duplicate))->allow('USER1','APP1','ADMIN1','127.0.0.1'))==='M3_ACL_ALREADY_ALLOWED','duplicate direct allow is rejected');
$deniedAllow=new M3FakeOperation();$deniedAllow->acl['denied']=1;
$report($reason(fn()=>(new UserAclManagementService($deniedAllow))->allow('USER1','APP1','ADMIN1','127.0.0.1'))==='M3_ACL_DENIED_UPLIFT_FIRST','allow cannot silently override a deny');

$denyOp=new M3FakeOperation();$denyOp->acl['category_allow']=1;
$deny=(new UserAclManagementService($denyOp))->deny('USER1','APP1','ADMIN1','127.0.0.1');
$report($deny['code']==='M3_ACL_DENIED','effective access can be denied');
$denyAudit=array_values(array_filter($denyOp->calls,fn($c)=>$c[0]==='audit'))[0]??[];
$report(($denyAudit[1]??null)===29,'deny writes event 29');
$notAllowed=new M3FakeOperation();
$report($reason(fn()=>(new UserAclManagementService($notAllowed))->deny('USER1','APP1','ADMIN1','127.0.0.1'))==='M3_ACL_NOT_CURRENTLY_ALLOWED','deny rejects an app with no effective access');

$upliftOp=new M3FakeOperation();
$uplift=(new UserAclManagementService($upliftOp))->uplift('USER1','7','ADMIN1','127.0.0.1');
$report($uplift['code']==='M3_ACL_DENY_UPLIFTED','uplift returns explicit success');
$report(in_array(['blacklist',7,true],$upliftOp->calls,true)&&in_array(['uplift',7],$upliftOp->calls,true),'uplift locks and deletes the exact deny record');
$wrongOwner=new M3FakeOperation();$wrongOwner->blacklist['u_id']='OTHER';
$report($reason(fn()=>(new UserAclManagementService($wrongOwner))->uplift('USER1','7','ADMIN1','127.0.0.1'))==='M3_DENY_RECORD_OWNER_MISMATCH','uplift rejects deny record belonging to another user');
$aclAuditFail=new M3FakeOperation();$aclAuditFail->auditResult=0;
$report($reason(fn()=>(new UserAclManagementService($aclAuditFail))->allow('USER1','APP1','ADMIN1','127.0.0.1'))==='M3_AUDIT_NOT_WRITTEN','ACL audit is mandatory');
$report(in_array('rollback',array_column($aclAuditFail->calls,0),true),'ACL audit failure rolls back mutation');

printf("RESULT checks=%d failed=%d\n",$checks,$failed);
exit($failed===0?0:1);
