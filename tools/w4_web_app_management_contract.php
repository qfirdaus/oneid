<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/config.php';

$root=dirname(__DIR__);$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$db=(string)file_get_contents($root.'/lib/Database.php');
$endpoint=(string)file_get_contents($root.'/lib/q_func.php');
$ui=(string)file_get_contents($root.'/admin/dashboard.php');
$categoryService=(string)file_get_contents($root.'/app/Admin/WebAppCategoryService.php');
$appService=(string)file_get_contents($root.'/app/Admin/WebAppService.php');

$report(str_contains($db,'INNER JOIN sp_list s ON s.sp_group_id=g.sp_group_id AND s.avail_status=1'),'admin directory only emits categories with active apps');
$report(str_contains($ui,'open_manage_webapp_categories')&&str_contains($ui,'assignedCount === 0'),'category manager explains and disables non-empty removal');
$report(str_contains($categoryService,"'W1_SYSTEM_CATEGORY_PROTECTED'")&&str_contains($categoryService,"'W1_CATEGORY_NOT_EMPTY'"),'category deletion is default-protected and empty-only');
$report(str_contains($categoryService,"'W4_CATEGORY_DUPLICATE'")&&str_contains($categoryService,'mb_strlen($name) > 100'),'category creation validates duplicate and length');
$report(substr_count($db,'AND B.avail_status=1')>=2,'effective group and direct ACL exclude inactive apps');
$report(str_contains($appService,"['acl_group','acl_single','acl_blacklist','user_app_favourite']")&&str_contains($appService,'syslog_record(15'),'app archive atomically cleans references and audits');
$report(str_contains($endpoint,'WebAppService($operation)')&&str_contains($endpoint,'WebAppCategoryService($operation)'),'runtime mutations use hardened services');

$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$orphan=(int)$pdo->query("SELECT COUNT(*) FROM sp_list s LEFT JOIN sp_group g ON g.sp_group_id=s.sp_group_id WHERE g.sp_group_id IS NULL")->fetchColumn();
$inactiveRefs=0;
foreach(['acl_group','acl_single','acl_blacklist','user_app_favourite'] as $table){$inactiveRefs+=(int)$pdo->query("SELECT COUNT(*) FROM `{$table}` r INNER JOIN sp_list s ON s.sp_id=r.sp_id WHERE s.avail_status=0")->fetchColumn();}
$fk=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sp_list' AND CONSTRAINT_NAME='fk_sp_list_sp_group'")->fetchColumn();
$unique=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sp_group' AND INDEX_NAME='uq_sp_group_name' AND NON_UNIQUE=0")->fetchColumn();
$report($orphan===0,'database contains zero orphan app category reference');
$report($inactiveRefs===0,'inactive apps contain zero effective ACL or favourite reference');
$report($fk===1,'database enforces app-category foreign key with RESTRICT');
$report($unique===1,'database enforces unique application category names');

$assignedCategory=$pdo->query("SELECT sp_group_id FROM sp_list WHERE sp_group_id<>0 LIMIT 1")->fetchColumn();
$restricts=false;
if($assignedCategory!==false){
    $pdo->beginTransaction();
    try{
        $delete=$pdo->prepare("DELETE FROM sp_group WHERE sp_group_id=:id");
        $delete->execute([':id'=>$assignedCategory]);
    }catch(PDOException $exception){
        $restricts=true;
    }finally{
        if($pdo->inTransaction())$pdo->rollBack();
    }
}
$report($restricts,'foreign key rejects direct deletion of an assigned category');

printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
