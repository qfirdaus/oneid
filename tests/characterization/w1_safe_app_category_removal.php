<?php

if (PHP_SAPI !== 'cli') exit(2);

$root = dirname(__DIR__, 2);
require_once $root . '/app/Admin/WebAppManagementException.php';
require_once $root . '/app/Admin/WebAppCategoryService.php';

use OneId\App\Admin\WebAppCategoryService;
use OneId\App\Admin\WebAppManagementException;

final class W1FakeOperation
{
    public array|false $category = ['sp_group_id'=>7,'sp_group_name'=>'Empty','sp_group_seq'=>1];
    public int $assigned = 0;
    public int $deleteResult = 1;
    public int $auditResult = 1;
    public array $calls = [];
    public function beginTransaction(): void {$this->calls[]=['begin'];}
    public function commit(): void {$this->calls[]=['commit'];}
    public function rollback(): void {$this->calls[]=['rollback'];}
    public function admin_get_app_category_for_update(int $id): array|false {$this->calls[]=['category',$id,true];return $this->category;}
    public function admin_count_apps_assigned_to_category(int $id): int {$this->calls[]=['count',$id];return $this->assigned;}
    public function admin_delete_empty_app_category(int $id): int {$this->calls[]=['delete',$id];return $this->deleteResult;}
    public function syslog_record(int $event,string $detail,string $ip): int {$this->calls[]=['audit',$event,$detail,$ip];return $this->auditResult;}
}

$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$reason=static function(callable $callback):string{try{$callback();}catch(WebAppManagementException $e){return $e->reason;}return '';};

$successOp=new W1FakeOperation();
$result=(new WebAppCategoryService($successOp))->remove('7','ADMIN1','127.0.0.1');
$report($result['code']==='W1_CATEGORY_REMOVED','empty category removal returns explicit success');
$report(array_column($successOp->calls,0)===['begin','category','count','delete','audit','commit'],'lock, count, delete and audit are one ordered transaction');
$audit=array_values(array_filter($successOp->calls,fn($call)=>$call[0]==='audit'))[0]??[];
$report(($audit[1]??null)===12&&str_contains((string)($audit[2]??''),'correlation='),'removal uses correlated legacy event 12');

$defaultOp=new W1FakeOperation();
$report($reason(fn()=>(new WebAppCategoryService($defaultOp))->remove('0','ADMIN1','127.0.0.1'))==='W1_SYSTEM_CATEGORY_PROTECTED','default category is protected server-side');
$report($defaultOp->calls===[],'default rejection performs zero database I/O');

$nonEmpty=new W1FakeOperation();$nonEmpty->assigned=2;
$report($reason(fn()=>(new WebAppCategoryService($nonEmpty))->remove('7','ADMIN1','127.0.0.1'))==='W1_CATEGORY_NOT_EMPTY','assigned category is rejected');
$report(!in_array('delete',array_column($nonEmpty->calls,0),true)&&in_array('rollback',array_column($nonEmpty->calls,0),true),'non-empty rejection has zero mutation and rolls back');

$missing=new W1FakeOperation();$missing->category=false;
$report($reason(fn()=>(new WebAppCategoryService($missing))->remove('7','ADMIN1','127.0.0.1'))==='W1_CATEGORY_NOT_FOUND','missing category fails closed');

$auditFail=new W1FakeOperation();$auditFail->auditResult=0;
$report($reason(fn()=>(new WebAppCategoryService($auditFail))->remove('7','ADMIN1','127.0.0.1'))==='W1_AUDIT_NOT_WRITTEN','audit is mandatory');
$report(in_array('rollback',array_column($auditFail->calls,0),true)&&!in_array('commit',array_column($auditFail->calls,0),true),'audit failure rolls back deletion');

printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
