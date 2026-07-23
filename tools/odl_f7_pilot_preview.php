<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/readonly_odbc.php';
require_once dirname(__DIR__).'/bootstrap/sync_runtime.php';
try{
 $config=\OneId\App\Sync\Odl\OdlPilotConfig::fromPrivateRuntime();
 if(!$config->previewEnabled)throw new RuntimeException('ODL_PILOT_PREVIEW_DISABLED');
 $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $source=new \OneId\App\Sync\Odl\OdlStudentSource(\OneId\App\Sync\Odl\OdlSourceConfig::fromPrivateRuntime());
 $reader=new \OneId\App\Sync\Odl\OdlShadowPreviewReader($pdo);
 $plan=(new \OneId\App\Sync\Odl\OdlPilotPlanner($config->allowedIdentityDigests))->plan($source->fetchAll(),$reader->users(),$reader->memberships());
 $response=[
  'status'=>1,'mode'=>'odl_controlled_pilot_preview','can_apply'=>false,
  'execution_authorized'=>false,'counts'=>$plan->legacyCounts(),
  'source_rows'=>$plan->sourceRows,'plan_hash'=>$plan->planHash(),
  'actions'=>$plan->safeProjection(),'mutation_statements'=>0,
 ];
 $response['preview_digest']=hash('sha256',json_encode($response,JSON_UNESCAPED_SLASHES)?:'');
 echo json_encode($response,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable $exception){
 $known=['ODL_PILOT_PREVIEW_DISABLED','ODL_PILOT_FLAG_INVALID','ODL_PILOT_FLAG_COMBINATION_INVALID','ODL_PILOT_ALLOWLIST_INVALID','ODL_PILOT_SCOPE_MUST_EQUAL_THREE','ODL_PILOT_SOURCE_MISMATCH','ODL_PILOT_IDENTITY_INVALID','ODL_PILOT_IDENTITY_DUPLICATE','ODL_PILOT_NEW_ONLY_VIOLATION','ODL_PILOT_ALLOWLIST_NOT_FULLY_RESOLVED','ODL_SOURCE_CONNECTION_FAILED','ODL_TLS_NOT_ACTIVE','ODL_SOURCE_QUERY_FAILED'];
 $code=in_array($exception->getMessage(),$known,true)?$exception->getMessage():'ODL_PILOT_PREVIEW_FAILED';
 printf("RESULT ready=no code=%s can_apply=false mutation_statements=0\n",$code);exit(1);
}
