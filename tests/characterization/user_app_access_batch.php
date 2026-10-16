<?php
use OneId\App\User\UserAppAccessBatchException;
use OneId\App\User\UserAppAccessBatchService;
require_once dirname(__DIR__,2).'/app/User/UserAppAccessBatchException.php';
require_once dirname(__DIR__,2).'/app/User/UserAppAccessBatchService.php';
final class UserAclBatchFake{
 public array $apps=[
  ['sp_id'=>'CAT','sp_name'=>'Category App','sp_description'=>'','sp_domain'=>'','sp_image'=>'','sp_sso_support'=>0,'avail_status'=>1,'category_allow'=>1,'direct_allow'=>0,'denied'=>0],
  ['sp_id'=>'DIRECT','sp_name'=>'Direct App','sp_description'=>'','sp_domain'=>'','sp_image'=>'','sp_sso_support'=>0,'avail_status'=>1,'category_allow'=>0,'direct_allow'=>1,'denied'=>0],
  ['sp_id'=>'DENY','sp_name'=>'Denied App','sp_description'=>'','sp_domain'=>'','sp_image'=>'','sp_sso_support'=>1,'avail_status'=>1,'category_allow'=>1,'direct_allow'=>0,'denied'=>1],
  ['sp_id'=>'FREE','sp_name'=>'Free App','sp_description'=>'','sp_domain'=>'','sp_image'=>'','sp_sso_support'=>1,'avail_status'=>1,'category_allow'=>0,'direct_allow'=>0,'denied'=>0],
 ];
 public array $calls=[];public bool $audit=true;public bool $rolled=false;
 function admin_get_user_for_profile_action($id,$lock=false){return ['u_id'=>$id,'u_category'=>4,'avail_status'=>1];}
 function admin_get_user_app_access_catalog($u,$c){return $this->apps;}
 function beginTransaction(){$this->calls[]='begin';}function commit(){$this->calls[]='commit';}function rollback(){$this->rolled=true;$this->calls[]='rollback';}
 function admin_get_user_direct_acl_ids($u,$l=false){return ['DIRECT'];}function admin_get_user_blacklist_rows($u,$l=false){return [['aclblk_id'=>1,'sp_id'=>'DENY']];}function admin_get_category_acl_sp_ids($c,$l=false){return ['CAT','DENY'];}
 function admin_add_user_direct_acl_batch($u,$ids){$this->calls[]=['add_direct',$ids];return count($ids);}function admin_remove_user_direct_acl_batch($u,$ids){$this->calls[]=['remove_direct',$ids];return count($ids);}function admin_add_user_blacklist_batch($u,$ids){$this->calls[]=['add_deny',$ids];return count($ids);}function admin_remove_user_blacklist_batch($u,$ids){$this->calls[]=['remove_deny',$ids];return count($ids);}
 function update_whole_token_status($u,$s,$r){$this->calls[]='revoke_all';return 2;}function update_other_token_status($u,$t,$s,$r){$this->calls[]=['revoke_other',$t];return 1;}function syslog_record($e,$d,$ip){$this->calls[]='audit';return $this->audit?1:0;}
}
$checks=0;$failed=0;$ok=function($v,$m)use(&$checks,&$failed){$checks++;if(!$v)$failed++;echo ($v?'PASS ':'FAIL ').$m."\n";};
$f=new UserAclBatchFake;$s=new UserAppAccessBatchService($f);$state=$s->state('user-1');$ok(strlen($state['revision'])===64,'state has conflict revision');$ok(count($state['apps'])===4,'state exposes full catalog');
$r=$s->apply('user-1',['DENY','FREE'],$state['revision'],'admin-1','127.0.0.1','current-token');$ok($r['changed']===4,'batch classifies four mutations');$ok(count(array_filter($f->calls,fn($v)=>$v==='revoke_all'))===1,'other user sessions are revoked together');$ok($r['current_session_preserved']===false,'other user update does not preserve an unrelated admin token');$ok(in_array('commit',$f->calls,true),'successful batch commits');
$self=new UserAclBatchFake;$selfService=new UserAppAccessBatchService($self);$selfState=$selfService->state('admin-1');$selfResult=$selfService->apply('admin-1',['DENY','FREE'],$selfState['revision'],'admin-1','127.0.0.1','current-token');$preserveCalls=array_values(array_filter($self->calls,fn($v)=>is_array($v)&&($v[0]??'')==='revoke_other'));$ok($selfResult['current_session_preserved']===true&&$selfResult['sessions_revoked']===1,'self update preserves current administrator session and revokes other sessions');$ok(($preserveCalls[0][1]??'')==='current-token','self update excludes the exact current token');
try{$s->apply('user-1',[],str_repeat('0',64),'admin-1','127.0.0.1');$ok(false,'stale state rejected');}catch(UserAppAccessBatchException $e){$ok($e->reason==='USER_ACL_STATE_CHANGED','stale state rejected');$ok($f->rolled,'stale transaction rolls back');}
$f2=new UserAclBatchFake;$f2->audit=false;$s2=new UserAppAccessBatchService($f2);$st=$s2->state('user-1');try{$s2->apply('user-1',['CAT','DIRECT'],$st['revision'],'admin-1','127.0.0.1');$ok(false,'audit failure rejected');}catch(UserAppAccessBatchException $e){$ok($e->reason==='USER_ACL_AUDIT_NOT_WRITTEN','audit is mandatory');$ok($f2->rolled,'audit failure rolls back');}
echo "RESULT checks=$checks failed=$failed\n";exit($failed?1:0);
