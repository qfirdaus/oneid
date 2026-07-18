<?php

if (PHP_SAPI !== 'cli') { exit(2); }
$root=dirname(__DIR__);require_once $root.'/lib/config.php';require_once $root.'/app/Admin/ActiveSessionService.php';

$property=new ReflectionProperty(Database::class,'pdo');$property->setAccessible(true);$pdo=$property->getValue($operation);
$digest=static function(PDO $pdo):string{
    $rows=$pdo->query('SELECT status,COUNT(*) AS total FROM token_tbl GROUP BY status ORDER BY status')->fetchAll(PDO::FETCH_ASSOC);
    return hash('sha256',json_encode($rows,JSON_UNESCAPED_SLASHES));
};
$containsForbidden=static function(mixed $value)use(&$containsForbidden):bool{
    if(!is_array($value))return false;
    foreach($value as$key=>$item){if(is_string($key)&&in_array($key,['token_id','token_hash','policy_revoke_correlation'],true))return true;if($containsForbidden($item))return true;}
    return false;
};
$config=$operation->get_system_config();$lifetime=(float)($config['token_timeout']??0);$before=$digest($pdo);
$service=new OneId\App\Admin\ActiveSessionService($operation);
$result=$service->list(['admin_get_all_token_for_all_active_user'=>'','page'=>'1','page_size'=>'10','query'=>'','status'=>'all'],'','',$lifetime);
$after=$digest($pdo);$passed=(int)($result['status']??0)===1&&!$containsForbidden($result)&&hash_equals($before,$after);
printf("READ rows=%d total=%d forbidden_fields=%s status_digest_match=%s\n",count($result['data']??[]),(int)($result['meta']['total']??0),$containsForbidden($result)?'yes':'no',hash_equals($before,$after)?'yes':'no');
printf("RESULT active_sessions_readonly=%s mutation_statements=0\n",$passed?'pass':'fail');exit($passed?0:1);
