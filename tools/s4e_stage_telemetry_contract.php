<?php
if(PHP_SAPI!=='cli'){exit(2);} $root=dirname(__DIR__);$files=['app/Sync/SyncDatabaseStageException.php','app/Sync/Adapters/DatabaseSyncPersistenceAdapter.php','app/Sync/Adapters/DatabaseSyncRunLock.php','app/Sync/Adapters/DatabaseSyncReconciliationReader.php','lib/q_func.php'];$s=[];$c=0;$f=0;
$r=static function(bool $ok,string $l)use(&$c,&$f):void{$c++;if(!$ok)$f++;printf("%s %s\n",$ok?'PASS':'FAIL',$l);};
foreach($files as$file){$s[$file]=(string)file_get_contents($root.'/'.$file);exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$file),$o,$code);$r($code===0,'PHP lint '.$file);$o=[];$code=1;}
$exception=$s['app/Sync/SyncDatabaseStageException.php'];$adapter=$s['app/Sync/Adapters/DatabaseSyncPersistenceAdapter.php'];$q=$s['lib/q_func.php'];
$r(str_contains($exception,"parent::__construct('SYNC_DATABASE_WRITE_FAILED'")&&!str_contains($exception,'getMessage()'),'exception exposes fixed code without PDO message');
$r(str_contains($exception,"preg_match('/^[A-Z0-9]{5}$/")&&str_contains($exception,"preg_match('/^[a-z][a-z0-9_]{1,63}$/"),'stage and SQLSTATE are allowlisted');
$r(str_contains($adapter,"guard('create_header'")&&str_contains($adapter,"guard('insert_external_user'")&&str_contains($adapter,"guard('append_change_audit'"),'writer stages are labeled at adapter boundary');
$r(str_contains($q,'stage=%s sqlstate=%s driver=%d')&&str_contains($q,'$exception->sqlState')&&str_contains($q,'$exception->driverCode'),'Apply log emits sanitized stage metadata only');
$r(str_contains($q,"'SYNC_DATABASE_WRITE_FAILED'"),'client receives stable sanitized failure code');
printf("RESULT checks=%d failed=%d\n",$c,$f);exit($f===0?0:1);
