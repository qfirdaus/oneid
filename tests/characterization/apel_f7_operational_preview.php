<?php
declare(strict_types=1);require_once dirname(__DIR__,2).'/bootstrap/sync_runtime.php';
use OneId\App\Sync\Apel\ApelOperationalConfig;
$n=0;$f=0;$r=function(bool$o,string$l)use(&$n,&$f){$n++;$f+=$o?0:1;echo($o?'PASS ':'FAIL '),$l,"\n";};
$c=ApelOperationalConfig::fromValues('true','false','false','false');$r($c->previewEnabled&&!$c->canApply(),'preview can run with every apply path disabled');
$bad=false;try{ApelOperationalConfig::fromValues('false','true','false','false');}catch(RuntimeException$e){$bad=true;}$r($bad,'apply cannot be enabled without preview');
$bad=false;try{ApelOperationalConfig::fromValues('true','true','true','false');}catch(RuntimeException$e){$bad=true;}$r($bad,'multiple apply modes are rejected');
$source=file_get_contents(dirname(__DIR__,2).'/app/Sync/Apel/ApelOperationalPreviewService.php');$r(str_contains($source,"'can_apply'=>false")&&str_contains($source,"'cron_enabled'=>false")&&str_contains($source,"'mutation_statements'=>0"),'service is preview-only and zero mutation');
printf("RESULT checks=%d failures=%d\n",$n,$f);exit($f===0?0:1);
