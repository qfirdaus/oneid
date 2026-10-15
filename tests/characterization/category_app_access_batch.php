<?php

require_once dirname(__DIR__, 2) . '/app/Admin/UserCategoryAppAccessException.php';
require_once dirname(__DIR__, 2) . '/app/Admin/UserCategoryAppAccessService.php';

use OneId\App\Admin\UserCategoryAppAccessException;
use OneId\App\Admin\UserCategoryAppAccessService;

final class CategoryAccessFake
{
    public array $calls = [];
    public bool $auditSucceeds = true;
    public array $existing = ['APP1', 'APP2'];
    public array $active = ['APP1', 'APP2', 'APP3', 'APP4'];
    public function beginTransaction(): void {$this->calls[]='begin';}
    public function commit(): void {$this->calls[]='commit';}
    public function rollback(): void {$this->calls[]='rollback';}
    public function admin_get_user_category_for_acl_action(int $id, bool $lock): array {$this->calls[]='category-lock';return ['uc_id'=>$id,'uc_name'=>'Staff','avail_status'=>1];}
    public function admin_get_category_acl_sp_ids(int $id, bool $lock): array {$this->calls[]='acl-lock';return $this->existing;}
    public function admin_get_active_service_provider_ids(array $ids): array {$this->calls[]='validate-apps';return array_values(array_intersect($ids,$this->active));}
    public function admin_add_category_acl_batch(int $id,array $ids): int {$this->calls[]='add:'.implode(',',$ids);return count($ids);}
    public function admin_remove_category_acl_batch(int $id,array $ids): int {$this->calls[]='remove:'.implode(',',$ids);return count($ids);}
    public function syslog_record(int $event,string $detail,string $ip): int {$this->calls[]='audit:'.$event.':'.$detail;return $this->auditSucceeds?1:0;}
}

$failed=0;
function check(bool $condition,string $name): void {global $failed;echo ($condition?'PASS ':'FAIL ').$name."\n";if(!$condition)$failed++;}

$fake=new CategoryAccessFake();
$result=(new UserCategoryAppAccessService($fake))->apply(7,['APP2','APP3','APP4'],'ADMIN','127.0.0.1');
check($result['status']===1&&$result['added']===2&&$result['removed']===1,'batch reports exact additions and removals');
check($fake->calls[0]==='begin'&&$fake->calls[1]==='category-lock'&&$fake->calls[2]==='acl-lock','category and ACL state are locked inside transaction');
check(in_array('add:APP3,APP4',$fake->calls,true)&&in_array('remove:APP1',$fake->calls,true),'server calculates authoritative diff');
check(str_starts_with($fake->calls[count($fake->calls)-2],'audit:28:')&&end($fake->calls)==='commit','mandatory correlated audit precedes commit');

$auditFail=new CategoryAccessFake();$auditFail->auditSucceeds=false;
try{(new UserCategoryAppAccessService($auditFail))->apply(7,['APP1','APP2','APP3'],'ADMIN','127.0.0.1');check(false,'audit failure rejects update');}
catch(UserCategoryAppAccessException $e){check($e->reason==='CATEGORY_ACL_AUDIT_NOT_WRITTEN'&&end($auditFail->calls)==='rollback','audit failure rolls back all changes');}

$inactive=new CategoryAccessFake();$inactive->active=['APP1','APP2'];
try{(new UserCategoryAppAccessService($inactive))->apply(7,['APP1','APP2','MISSING99'],'ADMIN','127.0.0.1');check(false,'inactive or unknown application is rejected');}
catch(UserCategoryAppAccessException $e){check($e->reason==='CATEGORY_ACL_APP_UNAVAILABLE'&&end($inactive->calls)==='rollback','invalid addition fails closed and rolls back');}

echo 'RESULT failed='.$failed."\n";
exit($failed===0?0:1);
