<?php
declare(strict_types=1);require_once dirname(__DIR__,2).'/bootstrap/sync_runtime.php';use OneId\App\Sync\Apel\ApelCronDryRunConfig;
$n=0;$f=0;$r=function(bool$o,string$l)use(&$n,&$f){$n++;$f+=$o?0:1;echo($o?'PASS ':'FAIL '),$l,"\n";};
$c=ApelCronDryRunConfig::fromValues('true','true');$r($c->enabled&&$c->dryRun,'APEL cron supports enabled dry-run');
$blocked=false;try{ApelCronDryRunConfig::fromValues('true','false');}catch(RuntimeException$e){$blocked=$e->getMessage()==='APEL_CRON_WRITE_MODE_UNSUPPORTED';}$r($blocked,'write-capable APEL cron is impossible');
$root=dirname(__DIR__,2);$runner=file_get_contents($root.'/cron/run_apel_external_sync_dry_run.php');$r(!str_contains($runner,'createOperationalCoordinator')&&!str_contains($runner,'applyApproved')&&str_contains($runner,"'mutation_statements'=>0"),'runner has no writer coordinator or Apply path');
$scope=file_get_contents($root.'/app/Sync/SyncSourceScope.php');$r(str_contains($scope,'ONEID_APEL_CRON_APPLY_ENABLED'),'shared cron requires a separate private APEL Apply gate');
printf("RESULT checks=%d failures=%d\n",$n,$f);exit($f===0?0:1);
