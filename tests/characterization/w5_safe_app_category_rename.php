<?php

if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__,2);
require_once $root.'/app/Admin/WebAppManagementException.php';
require_once $root.'/app/Admin/WebAppCategoryService.php';
use OneId\App\Admin\WebAppCategoryService;
use OneId\App\Admin\WebAppManagementException;

final class W5CategoryFake
{
    public array|false $category=['sp_group_id'=>7,'sp_group_name'=>'Old Category'];
    public array|false $duplicate=false;
    public int $renameResult=1;
    public int $auditResult=1;
    public bool $duplicateRace=false;
    public array $calls=[];
    public function beginTransaction():void{$this->calls[]=['begin'];}
    public function commit():void{$this->calls[]=['commit'];}
    public function rollback():void{$this->calls[]=['rollback'];}
    public function admin_get_app_category_for_update(int $id):array|false{$this->calls[]=['lock',$id];return $this->category;}
    public function admin_find_other_app_category_by_name_for_update(string $name,int $id):array|false{$this->calls[]=['find-other',$name,$id];return $this->duplicate;}
    public function admin_rename_app_category(int $id,string $name):int{$this->calls[]=['rename',$id,$name];if($this->duplicateRace)throw new PDOException('duplicate',23000);return $this->renameResult;}
    public function syslog_record(int $event,string $detail,string $ip):int{$this->calls[]=['audit',$event,$detail,$ip];return $this->auditResult;}
}

$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$reason=static function(callable $cb):string{try{$cb();}catch(WebAppManagementException $e){return $e->reason;}return '';};

$op=new W5CategoryFake();$result=(new WebAppCategoryService($op))->rename('7','  New   Category  ','ADMIN1','127.0.0.1');
$report($result['code']==='W5_CATEGORY_RENAMED','valid rename returns structured success');
$report(array_column($op->calls,0)===['begin','lock','find-other','rename','audit','commit'],'lock rename and mandatory audit share one transaction');
$report(($op->calls[2][1]??'')==='New Category'&&($op->calls[3][2]??'')==='New Category','rename normalizes whitespace consistently');

$system=new W5CategoryFake();
$report($reason(fn()=>(new WebAppCategoryService($system))->rename('0','Name','ADMIN1','127.0.0.1'))==='W5_SYSTEM_CATEGORY_PROTECTED'&&$system->calls===[],'system category is rejected before database I/O');
$invalid=new W5CategoryFake();
$report($reason(fn()=>(new WebAppCategoryService($invalid))->rename('7','   ','ADMIN1','127.0.0.1'))==='W5_CATEGORY_NAME_INVALID'&&$invalid->calls===[],'invalid name is rejected before database I/O');
$unchanged=new W5CategoryFake();
$report($reason(fn()=>(new WebAppCategoryService($unchanged))->rename('7','old category','ADMIN1','127.0.0.1'))==='W5_CATEGORY_UNCHANGED'&&!in_array('rename',array_column($unchanged->calls,0),true)&&in_array('rollback',array_column($unchanged->calls,0),true),'unchanged name performs zero mutation');
$duplicate=new W5CategoryFake();$duplicate->duplicate=['sp_group_id'=>8,'sp_group_name'=>'Taken'];
$report($reason(fn()=>(new WebAppCategoryService($duplicate))->rename('7','Taken','ADMIN1','127.0.0.1'))==='W5_CATEGORY_DUPLICATE'&&!in_array('rename',array_column($duplicate->calls,0),true),'duplicate name performs zero mutation');
$race=new W5CategoryFake();$race->duplicateRace=true;
$report($reason(fn()=>(new WebAppCategoryService($race))->rename('7','Racing Name','ADMIN1','127.0.0.1'))==='W5_CATEGORY_DUPLICATE'&&in_array('rollback',array_column($race->calls,0),true),'unique-key race returns stable duplicate code and rolls back');
$auditFail=new W5CategoryFake();$auditFail->auditResult=0;
$report($reason(fn()=>(new WebAppCategoryService($auditFail))->rename('7','New Name','ADMIN1','127.0.0.1'))==='W5_AUDIT_NOT_WRITTEN'&&in_array('rollback',array_column($auditFail->calls,0),true)&&!in_array('commit',array_column($auditFail->calls,0),true),'audit failure rolls back rename');

printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
