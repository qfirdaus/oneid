<?php

if (PHP_SAPI !== 'cli') { exit(2); }
require_once dirname(__DIR__, 2) . '/app/Admin/ActiveSessionService.php';

use OneId\App\Admin\ActiveSessionService;

function oneid_token_hash(string $token): string { return hash('sha256', $token); }
function oneid_normalize_device_info($value): string { return trim((string)$value) ?: 'Unknown device'; }

final class As0Operation
{
    public int $reads=0;
    public int $mutations=0;
    public array $filters=[];
    public function admin_list_active_sessions(array $filters): array
    {
        $this->reads++;$this->filters=$filters;
        return ['total'=>1,'metrics'=>['current'=>1,'active'=>0,'refresh'=>0,'grace'=>0,'due'=>0,'expired'=>0],'rows'=>[[
            'user_id'=>'USER-1','name'=>'Example User','device_info'=>'Desktop',
            'issued_at'=>'2026-07-18 10:00:00','last_activity_at'=>'2026-07-18 10:10:00',
            'revoke_at'=>null,'lifecycle_status'=>'current','token_id'=>'must-not-project',
        ]]];
    }
}

$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$reason=static function(callable $fn):string{try{$fn();return '';}catch(InvalidArgumentException $e){return $e->getMessage();}};
$operation=new As0Operation();$service=new ActiveSessionService($operation);
$result=$service->list(['admin_get_all_token_for_all_active_user'=>'','page'=>'1','page_size'=>'25','query'=>'Example','status'=>'all'],'ADMIN-1','raw-token',0.5);
$report($operation->reads===1&&$operation->mutations===0,'listing performs one bounded read and zero mutation');
$report(($operation->filters['offset']??-1)===0&&($operation->filters['page_size']??0)===25,'page and page size are server normalized');
$report(($operation->filters['current_token_hash']??'')===hash('sha256','raw-token'),'current token comparison uses a server-side hash');
$report(($result['data'][0]['status']??'')==='current'&&($result['meta']['total']??0)===1,'response projects lifecycle state and pagination metadata');
$report(($result['meta']['metrics']['current']??0)===1&&array_key_exists('refresh',$result['meta']['metrics']),'response includes complete lifecycle metrics');
$report(!array_key_exists('token_id',$result['data'][0]),'token material is absent from the response projection');
$report($reason(fn()=>$service->list(['page'=>'1','page_size'=>'100','status'=>'all'],'','',0.5))==='AS0_PAGE_SIZE_INVALID','page size outside the allowlist is rejected');
$report($reason(fn()=>$service->list(['page'=>'1','page_size'=>'25','status'=>'unknown'],'','',0.5))==='AS0_STATUS_INVALID','unknown lifecycle filter is rejected');
$report($reason(fn()=>$service->list(['page'=>'1','page_size'=>'25','status'=>'all','extra'=>'x'],'','',0.5))==='AS0_UNEXPECTED_FIELD','unexpected request fields are rejected');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
