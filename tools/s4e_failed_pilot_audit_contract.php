<?php
if (PHP_SAPI !== 'cli') { exit(2); }
$path = __DIR__ . '/s4e_failed_pilot_audit.php';
$source = (string) file_get_contents($path);
$checks=0; $failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path),$out,$code);
$report($code===0,'failed-pilot audit passes PHP lint');
$report(str_contains($source,'information_schema.TABLES')&&str_contains($source,'information_schema.COLUMNS'),'audit checks engines and schema read-only');
$report(str_contains($source,"SELECT :probe + :probe")&&str_contains($source,'ATTR_EMULATE_PREPARES'),'audit diagnoses duplicate named placeholders with SELECT');
$report(str_contains($source,'IS_USED_LOCK')&&!str_contains($source,'GET_LOCK('),'audit observes but never acquires lock');
$forbidden=['INSERT INTO','UPDATE ext_','DELETE FROM','->exec(','beginTransaction(','->commit(','->rollback('];
$report(array_filter($forbidden,static fn(string $item):bool=>str_contains($source,$item))===[],'audit contains no mutation or transaction caller');
$report(str_contains($source,'mutation_statements=0'),'audit emits zero-mutation marker');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
