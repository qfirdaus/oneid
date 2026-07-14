<?php

if (PHP_SAPI !== 'cli') exit(2);

$root = dirname(__DIR__, 2);
require_once $root . '/app/Admin/WebAppManagementException.php';
require_once $root . '/app/Admin/WebAppService.php';

use OneId\App\Admin\WebAppManagementException;
use OneId\App\Admin\WebAppService;

final class W3FakeOperation
{
    public array|false $app=['sp_id'=>'APP1','sp_name'=>'App','avail_status'=>1,'sp_group_id'=>7];
    public int $archiveResult=1;
    public int $auditResult=1;
    public array $calls=[];
    public function beginTransaction():void{$this->calls[]=['begin'];}
    public function commit():void{$this->calls[]=['commit'];}
    public function rollback():void{$this->calls[]=['rollback'];}
    public function admin_get_service_provider_for_update(string $id):array|false{$this->calls[]=['lock',$id];return $this->app;}
    public function admin_archive_service_provider(string $id):int{$this->calls[]=['archive',$id];return $this->archiveResult;}
    public function admin_delete_app_access_references(string $table,string $id):int{$this->calls[]=['cleanup',$table,$id];return 1;}
    public function syslog_record(int $event,string $detail,string $ip):int{$this->calls[]=['audit',$event,$detail,$ip];return $this->auditResult;}
}

$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$reason=static function(callable $callback):string{try{$callback();}catch(WebAppManagementException $e){return $e->reason;}return '';};

$op=new W3FakeOperation();
$result=(new WebAppService($op))->archive('APP1','ADMIN1','127.0.0.1');
$report($result['code']==='W3_APP_ARCHIVED','active app is archived explicitly');
$report(array_column($op->calls,0)===['begin','lock','archive','cleanup','cleanup','cleanup','cleanup','audit','commit'],'archive, cleanup and audit share one ordered transaction');
$cleanup=array_values(array_map(fn($c)=>$c[1],array_filter($op->calls,fn($c)=>$c[0]==='cleanup')));
$report($cleanup===['acl_group','acl_single','acl_blacklist','user_app_favourite'],'all access and preference references are cleaned');
$audit=array_values(array_filter($op->calls,fn($c)=>$c[0]==='audit'))[0]??[];
$report(($audit[1]??0)===15&&str_contains((string)($audit[2]??''),'correlation='),'archive uses correlated ADMIN_REMOVE_APP event');

$inactive=new W3FakeOperation();$inactive->app['avail_status']=0;
$report($reason(fn()=>(new WebAppService($inactive))->archive('APP1','ADMIN1','127.0.0.1'))==='W3_APP_ALREADY_INACTIVE','inactive app fails closed');
$report(!in_array('archive',array_column($inactive->calls,0),true)&&in_array('rollback',array_column($inactive->calls,0),true),'inactive rejection performs zero mutation');

$auditFail=new W3FakeOperation();$auditFail->auditResult=0;
$report($reason(fn()=>(new WebAppService($auditFail))->archive('APP1','ADMIN1','127.0.0.1'))==='W3_AUDIT_NOT_WRITTEN','audit is mandatory');
$report(in_array('rollback',array_column($auditFail->calls,0),true)&&!in_array('commit',array_column($auditFail->calls,0),true),'audit failure rolls back all mutations');

printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
