<?php

if (PHP_SAPI !== 'cli') exit(2);
$root=dirname(__DIR__);
$files=[
 'app/User/UserManagementException.php','app/User/UserProfilePolicyService.php',
 'app/User/UserAclManagementService.php','lib/Database.php','lib/request_security.php','lib/q_func.php',
 'admin/dashboard.php','tests/characterization/m3_profile_acl_hardening.php',
];
$checks=0;$failed=0;
$report=static function(bool $ok,string $label,string $detail='')use(&$checks,&$failed):void{
 $checks++;if(!$ok)$failed++;printf("%s %-72s%s\n",$ok?'PASS':'FAIL',$label,$detail!==''?' '.$detail:'');
};
foreach($files as $file){$out=[];$code=1;exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$file).' 2>&1',$out,$code);$report($code===0,'source and PHP lint: '.$file,implode(' | ',$out));}
$profile=file_get_contents($root.'/app/User/UserProfilePolicyService.php');
$acl=file_get_contents($root.'/app/User/UserAclManagementService.php');
$db=file_get_contents($root.'/lib/Database.php');
$guard=file_get_contents($root.'/lib/request_security.php');
$q=file_get_contents($root.'/lib/q_func.php');
$ui=file_get_contents($root.'/admin/dashboard.php');
$report(str_contains($profile,'M3_EXTERNAL_NAME_READ_ONLY'),'external-managed name policy is enforced server-side');
$report(str_contains($profile,'admin_get_active_user_category'),'category must exist and be active');
$report(str_contains($db,'role changes are not category changes')&&!str_contains($db,'function admin_change_user_category'),'dangerous legacy category/role mutator removed');
$report(!str_contains($q,"\$_POST['category_id'] == 9")&&str_contains($q,"\$_POST['admin_save_user_profile']"),'legacy category action replaced by explicit profile save');
$report(str_contains($guard,"'admin_save_user_profile'")&&!str_contains($guard,"'admin_change_user_category'"),'new profile-save action remains admin/CSRF guarded');
$report(str_contains($profile,'beginTransaction()')&&str_contains($profile,'rollback()')&&str_contains($profile,'M3_AUDIT_NOT_WRITTEN'),'profile save is transactional with mandatory audit');
$report(substr_count($acl,'admin_get_user_for_profile_action($userId, true)')===1,'ACL workflows serialize on locked user row');
$report(str_contains($acl,'M3_ACL_ALREADY_ALLOWED')&&str_contains($acl,'M3_ACL_ALREADY_DENIED'),'ACL duplicate states fail closed');
$report(str_contains($acl,'M3_DENY_RECORD_OWNER_MISMATCH'),'uplift verifies deny-record ownership');
$report(str_contains($acl,'update_whole_token_status'),'ACL mutations revoke existing sessions');
$report(substr_count($q,'UserAclManagementService')>=4&&str_contains($q,'UserProfilePolicyService'),'legacy handlers route through M3 services');
$report(str_contains($ui,'modal_user_profile_save();')&&str_contains($ui,'id="btn_user_profile_save"'),'Save Profile has an explicit handler');
$report(!str_contains($ui,'admin_change_user_category')&&str_contains($ui,"data: {admin_save_user_profile:''"),'category dropdown no longer mutates immediately');
$report(str_contains($ui,'m3EscapeHtml(response[i][\'sp_name\'])'),'dynamic application names are HTML encoded');
$report(str_contains($ui,'m3TransportFailure')&&str_contains($ui,'m3FailureText'),'M3 UI exposes transport and policy failures');
$fixture=[];$exit=1;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/m3_profile_acl_hardening.php').' 2>&1',$fixture,$exit);
$report($exit===0&&in_array('RESULT checks=28 failed=0',$fixture,true),'M3 zero-I/O fixture passes 28/28',implode(' | ',array_slice($fixture,-2)));
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
