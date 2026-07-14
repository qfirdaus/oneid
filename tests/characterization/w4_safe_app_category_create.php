<?php

if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__,2);
require_once $root.'/app/Admin/WebAppManagementException.php';
require_once $root.'/app/Admin/WebAppCategoryService.php';
use OneId\App\Admin\WebAppCategoryService;
use OneId\App\Admin\WebAppManagementException;

final class W4CategoryFake
{
    public array|false $existing=false;public int $createResult=1;public int $auditResult=1;public array $calls=[];
    public function beginTransaction():void{$this->calls[]=['begin'];}
    public function commit():void{$this->calls[]=['commit'];}
    public function rollback():void{$this->calls[]=['rollback'];}
    public function admin_find_app_category_by_name_for_update(string $name):array|false{$this->calls[]=['find',$name];return $this->existing;}
    public function admin_create_app_category(string $name):int{$this->calls[]=['create',$name];return $this->createResult;}
    public function syslog_record(int $event,string $detail,string $ip):int{$this->calls[]=['audit',$event,$detail,$ip];return $this->auditResult;}
}
$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$reason=static function(callable $cb):string{try{$cb();}catch(WebAppManagementException $e){return $e->reason;}return '';};
$op=new W4CategoryFake();$result=(new WebAppCategoryService($op))->create('  Test   Category  ','ADMIN1','127.0.0.1');
$report($result['code']==='W4_CATEGORY_CREATED','valid category returns structured success');
$report(array_column($op->calls,0)===['begin','find','create','audit','commit'],'create and mandatory audit share one transaction');
$report(($op->calls[1][1]??'')==='Test Category'&&($op->calls[2][1]??'')==='Test Category','category whitespace is normalized');
$duplicate=new W4CategoryFake();$duplicate->existing=['sp_group_id'=>7,'sp_group_name'=>'Test Category'];
$report($reason(fn()=>(new WebAppCategoryService($duplicate))->create('test category','ADMIN1','127.0.0.1'))==='W4_CATEGORY_DUPLICATE','normalized duplicate fails closed');
$report(!in_array('create',array_column($duplicate->calls,0),true)&&in_array('rollback',array_column($duplicate->calls,0),true),'duplicate performs zero mutation');
$invalid=new W4CategoryFake();
$report($reason(fn()=>(new WebAppCategoryService($invalid))->create('   ','ADMIN1','127.0.0.1'))==='W4_CATEGORY_NAME_INVALID'&&$invalid->calls===[],'empty category is rejected before database I/O');
$auditFail=new W4CategoryFake();$auditFail->auditResult=0;
$report($reason(fn()=>(new WebAppCategoryService($auditFail))->create('Test','ADMIN1','127.0.0.1'))==='W4_AUDIT_NOT_WRITTEN'&&in_array('rollback',array_column($auditFail->calls,0),true),'audit failure rolls back category creation');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
