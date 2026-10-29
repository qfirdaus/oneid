<?php

declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);require_once $root.'/lib/config.php';require_once $root.'/bootstrap/sync_runtime.php';
use OneId\App\Sync\Apel\ApelSourceConfig;use OneId\App\Sync\Apel\ApelStudentSource;
$keys=['ONEID_APEL_MYSQL_HOST','ONEID_APEL_MYSQL_PORT','ONEID_APEL_MYSQL_DATABASE','ONEID_APEL_MYSQL_USERNAME','ONEID_APEL_MYSQL_PASSWORD','ONEID_APEL_MYSQL_CONNECT_TIMEOUT'];
$missing=[];foreach($keys as$key){try{if(trim(oneid_secret($key))==='')$missing[]=$key;}catch(Throwable){$missing[]=$key;}}
if($missing!==[]){printf("RESULT ready=no missing_keys=%s mutation_statements=0\n",implode(',',$missing));exit(1);}
try{$rows=(new ApelStudentSource(ApelSourceConfig::fromPrivateRuntime()))->fetchAll();$blankMatric=0;$blankIc=0;$wrongCategory=0;$wrongSource=0;
foreach($rows as$row){$blankMatric+=trim((string)($row['data4']??''))===''?1:0;$blankIc+=trim((string)($row['data2']??''))===''?1:0;
$wrongCategory+=($row['ext_data_source_category']??'')!=='Pelajar'?1:0;$wrongSource+=($row['source_code']??'')!=='STUDENT_APEL'?1:0;}
printf("RESULT ready=yes rows=%d blank_matric=%d blank_ic=%d wrong_category=%d wrong_source=%d mutation_statements=0\n",count($rows),$blankMatric,$blankIc,$wrongCategory,$wrongSource);
exit(($blankMatric+$blankIc+$wrongCategory+$wrongSource)===0?0:1);
}catch(Throwable$e){$known=['APEL_PDO_MYSQL_TLS_UNAVAILABLE','APEL_SOURCE_CONNECTION_FAILED','APEL_TLS_NOT_ACTIVE','APEL_SOURCE_QUERY_FAILED','APEL_SOURCE_EMPTY','APEL_SOURCE_ROW_INVALID'];
printf("RESULT ready=no error=%s mutation_statements=0\n",in_array($e->getMessage(),$known,true)?$e->getMessage():'APEL_RUNTIME_PREFLIGHT_FAILED');exit(1);}
