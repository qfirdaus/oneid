<?php

declare(strict_types=1);

namespace OneId\App\Admin;

use InvalidArgumentException;

final class SessionHistoryService
{
    private const REASONS=['all','USER_LOGOUT','SESSION_EXPIRED','NEW_LOGIN_REPLACED','PASSWORD_RESET','ADMIN_REVOKED','ACCOUNT_DISABLED','SECURITY_ACTION','UNKNOWN'];
    public function __construct(private readonly object $operation){}
    public function list(array $input):array
    {
        $allowed=['admin_get_session_history','page','page_size','query','reason','date_from','date_to'];if(array_diff(array_keys($input),$allowed)!==[])throw new InvalidArgumentException('SH1_UNEXPECTED_FIELD');
        $page=filter_var($input['page']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$size=filter_var($input['page_size']??null,FILTER_VALIDATE_INT);if($page===false||!in_array($size,[10,25,50],true))throw new InvalidArgumentException('SH1_PAGINATION_INVALID');
        $query=trim((string)($input['query']??''));if(strlen($query)>80||preg_match('/[\x00-\x1F\x7F]/',$query))throw new InvalidArgumentException('SH1_QUERY_INVALID');
        $reason=(string)($input['reason']??'all');if(!in_array($reason,self::REASONS,true))throw new InvalidArgumentException('SH1_REASON_INVALID');
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Kuala_Lumpur')))->format('Y-m-d');
        $date=function(string $value,bool $end)use($today):string{$value=trim($value);if($value==='')$value=$today;$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$d||$d->format('Y-m-d')!==$value)throw new InvalidArgumentException('SH1_DATE_INVALID');return$value.($end?' 23:59:59':' 00:00:00');};
        $from=$date((string)($input['date_from']??''),false);$to=$date((string)($input['date_to']??''),true);if($from>$to)throw new InvalidArgumentException('SH1_DATE_RANGE_INVALID');
        $result=$this->operation->admin_list_session_history(['page_size'=>$size,'offset'=>($page-1)*$size,'query'=>$query,'reason'=>$reason,'from'=>$from,'to'=>$to]);
        $rows=[];foreach($result['rows'] as$row){$staff=in_array((int)($row['u_category']??0),[2,3],true);$rows[]=['user_id'=>(string)$row['user_id'],'public_user_id'=>trim((string)($row['public_user_id']??''))?:'-','public_user_id_type'=>$staff?'staff':'student','name'=>trim((string)($row['name']??''))?:'Unknown user','is_admin'=>(int)($row['u_type']??0)===1,'issued_at'=>(string)$row['issued_at'],'last_activity_at'=>(string)$row['last_activity_at'],'ended_at'=>$row['ended_at']===null?null:(string)$row['ended_at'],'end_reason'=>(string)$row['end_reason'],'device_info'=>function_exists('oneid_normalize_device_info')?\oneid_normalize_device_info($row['device_info']??''):(string)$row['device_info']];}
        $total=(int)$result['total'];return['status'=>1,'code'=>'SH1_HISTORY_LOADED','data'=>$rows,'meta'=>['page'=>$page,'page_size'=>$size,'total'=>$total,'total_pages'=>max(1,(int)ceil($total/$size)),'query'=>$query,'reason'=>$reason,'date_from'=>substr($from,0,10),'date_to'=>substr($to,0,10)]];
    }
}
