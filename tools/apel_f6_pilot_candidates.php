<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);require_once $root.'/lib/config.php';require_once $root.'/bootstrap/sync_runtime.php';
use OneId\App\Sync\Apel\ApelSourceConfig;use OneId\App\Sync\Apel\ApelStudentSource;
try{$rows=(new ApelStudentSource(ApelSourceConfig::fromPrivateRuntime()))->fetchAll();$digests=[];
foreach($rows as$row){$m=preg_replace('/[\s\p{Pd}]+/u','',trim((string)($row['data4']??'')))??'';$i=preg_replace('/[\s\p{Pd}]+/u','',trim((string)($row['data2']??'')))??'';if($m!==''&&$i!=='')$digests[]=hash('sha256',$m.'|'.$i);}
$digests=array_values(array_unique($digests));sort($digests,SORT_STRING);
if(count($digests)!==3)throw new RuntimeException('APEL_PILOT_CANDIDATE_COUNT_NOT_THREE');
echo "ONEID_APEL_PILOT_IDENTITY_DIGESTS=",implode(',',$digests),"\nRESULT candidates=3 raw_identities_exposed=0 mutation_statements=0\n";
}catch(Throwable$e){printf("RESULT ready=no code=%s mutation_statements=0\n",preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'APEL_F6_CANDIDATES_FAILED');exit(1);}
