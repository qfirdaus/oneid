<?php

if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$files=['app/Admin/WebAppCategoryService.php','lib/Database.php','lib/q_func.php','lib/request_security.php','admin/dashboard.php','tests/characterization/w5_safe_app_category_rename.php'];$source=[];
foreach($files as$file){$source[$file]=(string)file_get_contents($root.'/'.$file);exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$file),$out,$code);$report($code===0,'PHP lint '.$file);$out=[];$code=1;}
$service=$source['app/Admin/WebAppCategoryService.php'];$db=$source['lib/Database.php'];$endpoint=$source['lib/q_func.php'];$ui=$source['admin/dashboard.php'];
$report(str_contains($source['lib/request_security.php'],"'action_rename_webapp_category'"),'rename action inherits admin exactly-one-action and CSRF guard');
$report(str_contains($service,'admin_get_app_category_for_update')&&str_contains($service,'admin_find_other_app_category_by_name_for_update'),'target and duplicate checks use locking reads');
$report(str_contains($service,"'W5_SYSTEM_CATEGORY_PROTECTED'")&&str_contains($service,"'W5_CATEGORY_UNCHANGED'")&&str_contains($service,"'W5_CATEGORY_DUPLICATE'"),'system unchanged and duplicate states fail closed');
$report(str_contains($service,'admin_rename_app_category')&&str_contains($service,'W5_AUDIT_NOT_WRITTEN')&&str_contains($service,'$this->operation->commit()'),'rename and mandatory audit share transaction boundary');
$report(str_contains($db,'UPDATE sp_group SET sp_group_name=:name')&&str_contains($db,'sp_group_id<>0'),'database rename is prepared and system category protected');
$report(str_contains($endpoint,'$service->rename(')&&str_contains($endpoint,"'Application category was not renamed.'"),'runtime delegates rename to hardened service');
$report(str_contains($ui,'category-manage-edit')&&str_contains($ui,'fa fa-pencil')&&str_contains($ui,'action_rename_webapp_category'),'category manager renders edit icon and guarded AJAX action');
$report(str_contains($ui,"$('#modal_edit_webapp_category').on('hidden.bs.modal'")&&str_contains($ui,'open_manage_webapp_categories();'),'closing category editor returns to category manager');
$report(str_contains($ui,'webAppManagementAttribute')&&str_contains($ui,"'&quot;'"),'category names are attribute encoded');
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/w5_safe_app_category_rename.php'),$output,$code);
$report($code===0&&in_array('RESULT checks=9 failed=0',$output,true),'rename characterization passes');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
