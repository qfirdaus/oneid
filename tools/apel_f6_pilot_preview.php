<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);require_once $root.'/lib/config.php';require_once $root.'/bootstrap/sync_runtime.php';
use OneId\App\Sync\Apel\{ApelPilotConfig,ApelPilotPlanner,ApelSourceConfig,ApelStudentSource};use OneId\App\Sync\Odl\OdlShadowPreviewReader;
try{$config=ApelPilotConfig::fromPrivateRuntime();if(!$config->previewEnabled)throw new RuntimeException('APEL_PILOT_PREVIEW_DISABLED');
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$reader=new OdlShadowPreviewReader($pdo);$plan=(new ApelPilotPlanner($config->allowedIdentityDigests))->plan((new ApelStudentSource(ApelSourceConfig::fromPrivateRuntime()))->fetchAll(),$reader->users(),$reader->memberships());
$out=['status'=>1,'mode'=>'apel_controlled_pilot_preview','can_apply'=>false,'execution_authorized'=>false,'counts'=>$plan->legacyCounts(),'source_rows'=>$plan->sourceRows,'plan_hash'=>$plan->planHash(),'actions'=>$plan->safeProjection(),'mutation_statements'=>0];
$out['preview_digest']=hash('sha256',json_encode($out,JSON_UNESCAPED_SLASHES)?:'');echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
}catch(Throwable$e){printf("RESULT ready=no code=%s can_apply=false mutation_statements=0\n",preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'APEL_F6_PREVIEW_FAILED');exit(1);}
