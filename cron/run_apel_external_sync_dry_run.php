<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);require_once$root.'/lib/config.php';require_once$root.'/bootstrap/sync_runtime.php';
use OneId\App\Sync\Apel\{ApelCronDryRunConfig,ApelOperationalConfig,ApelOperationalPreviewService,ApelSourceConfig,ApelStudentSource};
$correlation=bin2hex(random_bytes(8));
try{$cron=ApelCronDryRunConfig::fromPrivateRuntime();$cron->assertRunnable();$operational=ApelOperationalConfig::fromPrivateRuntime();$operational->assertPreviewEnabled();
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$preview=(new ApelOperationalPreviewService($operational,new ApelStudentSource(ApelSourceConfig::fromPrivateRuntime()),$pdo))->preview();
echo json_encode(['timestamp'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Kuala_Lumpur')))->format(DATE_ATOM),'event'=>'APEL_CRON_DRY_RUN','correlation_id'=>$correlation,'source'=>'STUDENT_APEL','status'=>(int)($preview['status']??0),'risk_level'=>$preview['risk_level']??'unknown','source_rows'=>(int)($preview['source_rows']??0),'counts'=>$preview['counts']??[],'blocking_codes'=>$preview['blocking_codes']??[],'plan_hash'=>$preview['plan_hash']??null,'can_apply'=>false,'mutation_statements'=>0],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
exit(($preview['status']??0)===1&&($preview['blocking_codes']??[])===[]?0:1);
}catch(Throwable$e){echo json_encode(['timestamp'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Kuala_Lumpur')))->format(DATE_ATOM),'event'=>'APEL_CRON_DRY_RUN','correlation_id'=>$correlation,'source'=>'STUDENT_APEL','status'=>0,'code'=>preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'APEL_CRON_DRY_RUN_FAILED','can_apply'=>false,'mutation_statements'=>0],JSON_UNESCAPED_SLASHES),"\n";exit(1);}
