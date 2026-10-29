<?php

declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);require_once $root.'/lib/config.php';require_once $root.'/bootstrap/sync_runtime.php';
use OneId\App\Sync\Apel\ApelDataQualityAudit;use OneId\App\Sync\Apel\ApelSourceConfig;use OneId\App\Sync\Apel\ApelStudentSource;
try{$rows=(new ApelStudentSource(ApelSourceConfig::fromPrivateRuntime()))->fetchAll();
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$users=$pdo->query('SELECT u_id,data2,avail_status,account_source,sync_protected FROM user_tbl')->fetchAll();
$members=$pdo->query('SELECT u_id,source_code,external_user_id,source_active FROM user_external_identity')->fetchAll();
echo json_encode((new ApelDataQualityAudit())->audit($rows,$users,$members),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable$e){$known=['APEL_PDO_MYSQL_TLS_UNAVAILABLE','APEL_SOURCE_CONNECTION_FAILED','APEL_TLS_NOT_ACTIVE','APEL_SOURCE_QUERY_FAILED','APEL_SOURCE_EMPTY','APEL_SOURCE_ROW_INVALID'];
printf("RESULT ready=no error=%s mutation_statements=0\n",in_array($e->getMessage(),$known,true)?$e->getMessage():'APEL_F3_AUDIT_FAILED');exit(1);}
