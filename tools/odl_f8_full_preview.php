<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/readonly_odbc.php';
require_once dirname(__DIR__).'/bootstrap/sync_runtime.php';
try{
 $config=\OneId\App\Sync\Odl\OdlFullConfig::fromPrivateRuntime();
 if(!$config->previewEnabled)throw new RuntimeException('ODL_FULL_PREVIEW_DISABLED');
 $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 ]);
 $source=new \OneId\App\Sync\Odl\OdlStudentSource(
  \OneId\App\Sync\Odl\OdlSourceConfig::fromPrivateRuntime()
 );
 $reader=new \OneId\App\Sync\Odl\OdlShadowPreviewReader($pdo);
 $plan=(new \OneId\App\Sync\Odl\OdlFullPlanner())->plan(
  $source->fetchAll(),$reader->users(),$reader->memberships(),$config
 );
 $response=['status'=>1,'mode'=>'odl_controlled_full_preview',
  'can_apply'=>false,'execution_authorized'=>false,'source'=>'STUDENT_ODL_PG',
  'source_rows'=>$plan->sourceRows,'counts'=>$plan->counts(),
  'plan_hash'=>$plan->planHash(),'actions'=>$plan->safeActions(),
  'blocking_codes'=>[],'risk_level'=>'normal','mutation_statements'=>0,
  'automatic_scheduler'=>false];
 $response['preview_digest']=hash('sha256',
  json_encode($response,JSON_UNESCAPED_SLASHES)?:'');
 echo json_encode($response,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES
  |JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable$e){
 printf("RESULT ready=no code=%s can_apply=false mutation_statements=0\n",
  preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'ODL_FULL_PREVIEW_FAILED');
 exit(1);
}
