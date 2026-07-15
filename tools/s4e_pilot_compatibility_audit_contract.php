<?php
if(PHP_SAPI!=='cli'){exit(2);} $path=__DIR__.'/s4e_pilot_compatibility_audit.php';$s=(string)file_get_contents($path);$c=0;$f=0;
$r=static function(bool $ok,string $l)use(&$c,&$f):void{$c++;if(!$ok)$f++;printf("%s %s\n",$ok?'PASS':'FAIL',$l);};
exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path),$o,$code);$r($code===0,'compatibility audit passes PHP lint');
$r(str_contains($s,"fromValues('true', '2', '1', '0', '0')"),'audit selects exact deterministic pilot subset');
$r(str_contains($s,'information_schema.COLUMNS')&&str_contains($s,'CHARACTER_MAXIMUM_LENGTH'),'audit checks schema length compatibility');
$r(str_contains($s,'NEW_UID_ALREADY_EXISTS')&&str_contains($s,'UPDATE_UID_MISSING')&&str_contains($s,'CATEGORY_INACTIVE_OR_MISSING'),'audit checks collision target and category preconditions');
$r(str_contains($s,'information_schema.STATISTICS')&&str_contains($s,'UNIQUE_COLLISION_'),'audit checks all unique indexes for selected new identities');
$forbidden=['INSERT INTO','UPDATE user','DELETE FROM','beginTransaction(','->commit(','->rollback('];$r(array_filter($forbidden,static fn(string $x):bool=>str_contains($s,$x))===[],'audit contains SELECT only');
$r(str_contains($s,'mutation_statements=0'),'audit emits zero-mutation marker');printf("RESULT checks=%d failed=%d\n",$c,$f);exit($f===0?0:1);
