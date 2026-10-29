<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);require_once $root.'/lib/config.php';require_once $root.'/bootstrap/sync_runtime.php';
use OneId\App\Sync\Adapters\{PdoApelPilotPersistenceAdapter,SecureInitialPasswordFactory};use OneId\App\Sync\Apel\ApelPilotWriter;
try{$correlation=$argv[1]??'';if((getenv('ONEID_APEL_F6_ROLLBACK')?:'')!=='AUTHORIZE_APEL_F6_PILOT_ROLLBACK')throw new RuntimeException('APEL_PILOT_ROLLBACK_NOT_AUTHORIZED');
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$removed=(new ApelPilotWriter(new PdoApelPilotPersistenceAdapter($pdo),new SecureInitialPasswordFactory()))->rollbackCommitted($correlation,true);
printf("RESULT rolled_back=yes correlation_id=%s removed=%d\n",$correlation,$removed);
}catch(Throwable$e){printf("RESULT rolled_back=no code=%s\n",preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'APEL_F6_ROLLBACK_FAILED');exit(1);}
