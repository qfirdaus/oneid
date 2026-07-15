<?php
if(PHP_SAPI!=='cli'){exit(2);} $root=dirname(__DIR__);$files=['tools/s4e_isolated_pilot_rehearsal.php','tools/s4e_isolated_pilot_child.php'];$s=[];$c=0;$f=0;
$r=static function(bool $ok,string $l)use(&$c,&$f):void{$c++;if(!$ok)$f++;printf("%s %s\n",$ok?'PASS':'FAIL',$l);};
foreach($files as$file){$s[$file]=(string)file_get_contents($root.'/'.$file);exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$file),$o,$code);$r($code===0,'PHP lint '.$file);$o=[];$code=1;}
$p=$s['tools/s4e_isolated_pilot_rehearsal.php'];$ch=$s['tools/s4e_isolated_pilot_child.php'];
$r(str_contains($p,'S4D-20260715-212233')&&str_contains($p,"hash_file('sha256'"),'runner requires fixed backup and checksum');
$r(str_contains($p,"ONEID_SYNC_APPLY_ENABLED')==='false'")&&str_contains($p,"ONEID_SYNC_PILOT_ENABLED','false')==='false'"),'source execution requires all Apply flags disabled');
$r(str_contains($p,"'ONEID_DB_DSN'='mysql:host=")||str_contains($p,"['ONEID_DB_DSN']='mysql:host="),'child receives generated database DSN only');
$r(str_contains($p,"\$target==='oneiddb'")&&str_contains($p,"preg_match('/\\Aoneiddb_s4e_"),'cleanup rejects source and non-generated names');
$r(str_contains($p,"hash_equals('ISOLATED-PILOT oneiddb'")&&str_contains($p,'stream_isatty(STDIN)'),'execution requires interactive source-bound confirmation');
$r(str_contains($ch,"\$actual === 'oneiddb'")&&str_contains($ch,'ONEID_REHEARSAL_TARGET_DATABASE'),'child independently rejects source database');
$r(str_contains($ch,"fromValues('true', '2', '1', '0', '0')")&&str_contains($ch,"'Deactivate' => 0, 'Reactivate' => 0"),'child runs exact non-destructive pilot subset');
$r(str_contains($ch,'SyncDatabaseStageException')&&str_contains($ch,'stage=%s sqlstate=%s driver=%d'),'child reports sanitized stage telemetry');
$r(str_contains($p,'DROP DATABASE')&&str_contains($p,'restore_target_dropped=')&&str_contains($p,'source_modified=no'),'runner always reports cleanup and source state');
$r(!str_contains($p,"--password=")&&str_contains($p,"['MYSQL_PWD']=DB_PASSWORD"),'password is absent from process arguments');
printf("RESULT checks=%d failed=%d\n",$c,$f);exit($f===0?0:1);
