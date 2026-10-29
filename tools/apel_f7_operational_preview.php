<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);require_once$root.'/lib/config.php';require_once$root.'/bootstrap/sync_runtime.php';
use OneId\App\Sync\Apel\{ApelOperationalConfig,ApelOperationalPreviewService,ApelSourceConfig,ApelStudentSource};
try{$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$out=(new ApelOperationalPreviewService(ApelOperationalConfig::fromPrivateRuntime(),new ApelStudentSource(ApelSourceConfig::fromPrivateRuntime()),$pdo))->preview();echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
}catch(Throwable$e){printf("RESULT ready=no code=%s can_apply=false cron_enabled=false mutation_statements=0\n",preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'APEL_F7_OPERATIONAL_PREVIEW_FAILED');exit(1);}
