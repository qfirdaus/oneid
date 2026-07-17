<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/config.php';

$root=dirname(__DIR__);
$db=(string)file_get_contents($root.'/lib/Database.php');
$service=(string)file_get_contents($root.'/app/Admin/WebAppService.php');
$runtime=(string)file_get_contents($root.'/config/runtime.php');
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$scalar=static fn(string $sql):int=>(int)$pdo->query($sql)->fetchColumn();
$checks=[];
$checks['environment runtime default fails closed']=str_contains($runtime,"'ONEID_ENVIRONMENT' => ''")&&str_contains($runtime,'Fail closed');
$checks['asset table has five expected columns']=$scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sp_app_asset' AND COLUMN_NAME IN ('sp_id','environment','image_filename','updated_at','updated_by')")===5;
$checks['asset table has composite primary key']=$scalar("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sp_app_asset' AND INDEX_NAME='PRIMARY'")===2;
$checks['asset table has app foreign key']=$scalar("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sp_app_asset' AND CONSTRAINT_NAME='fk_sp_app_asset_sp_list'")===1;
$checks['database validates explicit environment']=str_contains($db,"ONEID_ENVIRONMENT is not configured safely");
$checks['read paths use environment-specific asset fallback']=substr_count($db,"E.environment=:environment")>=8&&substr_count($db,"COALESCE(NULLIF(E.image_filename,''),")>=7;
$checks['new asset write is keyed by runtime environment']=str_contains($db,'INSERT INTO sp_app_asset(sp_id,environment,image_filename,updated_by)')&&str_contains($db,"':environment'=>\$this->environment");
$checks['service create leaves legacy global image blank']=preg_match('/\$data\[\x27url\x27\],\s*\x27\x27,\s*\$data\[\x27category_id\x27\]/',$service)===1;
$checks['service writes environment asset only when icon selected']=substr_count($service,'admin_upsert_app_asset')===2;
$checks['audit records environment']=substr_count($service,'environment=%s')>=2;

$before=$scalar("SELECT COUNT(*) FROM sp_app_asset");
$appId=(string)$pdo->query("SELECT sp_id FROM sp_list WHERE avail_status=1 ORDER BY sp_id LIMIT 1")->fetchColumn();
$isolated=false;
if($appId!==''){
    $pdo->beginTransaction();
    try{
        $insert=$pdo->prepare("INSERT INTO sp_app_asset(sp_id,environment,image_filename,updated_by) VALUES(:id,:environment,:filename,'WA4CONTRACT') ON DUPLICATE KEY UPDATE image_filename=VALUES(image_filename),updated_by=VALUES(updated_by)");
        $insert->execute([':id'=>$appId,':environment'=>'local',':filename'=>'app_icon_wa4_contract_local.png']);
        $insert->execute([':id'=>$appId,':environment'=>'staging',':filename'=>'app_icon_wa4_contract_staging.png']);
        $read=$pdo->prepare("SELECT COALESCE(NULLIF(a.image_filename,''),s.sp_image) FROM sp_list s LEFT JOIN sp_app_asset a ON a.sp_id=s.sp_id AND a.environment=:environment WHERE s.sp_id=:id");
        $values=[];
        foreach(['local','staging','production'] as $environment){$read->execute([':environment'=>$environment,':id'=>$appId]);$values[$environment]=(string)$read->fetchColumn();}
        $isolated=$values['local']==='app_icon_wa4_contract_local.png'&&$values['staging']==='app_icon_wa4_contract_staging.png'&&$values['production']!==$values['local']&&$values['production']!==$values['staging'];
    }finally{$pdo->rollBack();}
}
$checks['transactional probe proves local and staging isolation']=$isolated;
$checks['contract probe leaves zero committed rows']=$scalar("SELECT COUNT(*) FROM sp_app_asset")===$before;

$passed=0;foreach($checks as $label=>$ok){echo($ok?'PASS':'FAIL').' '.$label."\n";$passed+=$ok?1:0;}
printf("RESULT %d/%d\n",$passed,count($checks));
exit($passed===count($checks)?0:1);
