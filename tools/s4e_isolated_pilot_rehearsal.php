<?php

/** Backup-based isolated S4E pilot rehearsal. Source oneiddb is never selected for writes. */
if (PHP_SAPI !== 'cli') { exit(2); }
$root = dirname(__DIR__);
require_once $root . '/lib/config.php';

function s4e_iso_fail(string $message): never { fwrite(STDERR, "FAIL {$message}\n"); exit(1); }
function s4e_iso_run(array $command, array $descriptors, array $environment): array
{
    $pipes=[]; $process=proc_open($command,$descriptors,$pipes,null,$environment);
    if(!is_resource($process)) return [1,'unable to start process'];
    $stdout=isset($pipes[1])?(string)stream_get_contents($pipes[1]):'';
    $stderr=isset($pipes[2])?(string)stream_get_contents($pipes[2]):'';
    foreach($pipes as $pipe) if(is_resource($pipe)) fclose($pipe);
    return [proc_close($process),trim($stdout),trim($stderr)];
}
function s4e_iso_quote(string $identifier): string { return '`'.str_replace('`','``',$identifier).'`'; }

$backupDirectory = $root . '/storage/backups/S4D-20260715-212233';
$dump = $backupDirectory . '/oneiddb.full.sql';
$sums = $backupDirectory . '/SHA256SUMS';
$expectedHash = '';
if (is_file($sums)) {
    $line = trim((string) file_get_contents($sums));
    if (preg_match('/\A([a-f0-9]{64})\s+\*?oneiddb\.full\.sql\z/', $line, $match) === 1) $expectedHash=$match[1];
}
$actualHash = is_file($dump) ? hash_file('sha256',$dump) : false;

$reflection = new ReflectionProperty(Database::class,'pdo');$reflection->setAccessible(true);
/** @var PDO $pdo */ $pdo=$reflection->getValue($operation);
$sourceDatabase=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$serverHostname=(string)$pdo->query('SELECT @@hostname')->fetchColumn();
$dsn=(string)DB_DSN; preg_match('/host=([^;]+)/',$dsn,$hostMatch); preg_match('/port=([^;]+)/',$dsn,$portMatch);
$host=$hostMatch[1]??'';$port=$portMatch[1]??'3306';
$preflight=in_array('--preflight',$argv,true);
$flagsDisabled=(string)oneid_config('ONEID_SYNC_APPLY_ENABLED')==='false'
    &&(string)oneid_config('ONEID_SYNC_ENGINE')==='disabled'
    &&(string)oneid_config('ONEID_SYNC_PILOT_ENABLED','false')==='false';
$preflightOk=$sourceDatabase==='oneiddb'&&$expectedHash!==''&&is_string($actualHash)
    &&hash_equals($expectedHash,$actualHash)&&$flagsDisabled
    &&is_executable('/usr/bin/mysql')&&is_readable($dump);
printf("PREFLIGHT source=%s server=%s backup_sha256=%s flags_disabled=%s\n",$sourceDatabase,$serverHostname,$preflightOk?substr($actualHash,0,12).'...':'invalid',$flagsDisabled?'yes':'no');
if($preflight){printf("RESULT preflight=%s mutation_statements=0\n",$preflightOk?'pass':'fail');exit($preflightOk?0:1);}
if(!in_array('--execute',$argv,true)) s4e_iso_fail('choose --preflight or --execute');
if(!$preflightOk) s4e_iso_fail('preflight requirements not met');
$allowedHost=trim((string)oneid_config('ONEID_REHEARSAL_ALLOWED_SERVER_HOSTNAME',''));
$allowedSource=trim((string)oneid_config('ONEID_REHEARSAL_ALLOWED_SOURCE_DATABASE',''));
if(!hash_equals(strtolower($allowedHost),strtolower($serverHostname))||!hash_equals($allowedSource,'oneiddb')) s4e_iso_fail('target allowlist mismatch');
if(!function_exists('stream_isatty')||!stream_isatty(STDIN)) s4e_iso_fail('interactive terminal required');
fwrite(STDERR,"Type ISOLATED-PILOT oneiddb to create and later drop a temporary rehearsal DB: ");
if(!hash_equals('ISOLATED-PILOT oneiddb',trim((string)fgets(STDIN)))) s4e_iso_fail('confirmation mismatch');

$target='oneiddb_s4e_'.date('Ymd_His').'_'.bin2hex(random_bytes(2));
$created=false;$childExit=1;$childOutput='';$dropped=false;
$environment=[];foreach(getenv() as$key=>$value)if(is_string($key)&&is_string($value))$environment[$key]=$value;
$environment['MYSQL_PWD']=DB_PASSWORD;
try{
    $pdo->exec('CREATE DATABASE '.s4e_iso_quote($target).' CHARACTER SET latin1');$created=true;
    [$restoreExit,,$restoreError]=s4e_iso_run(['/usr/bin/mysql','--host='.$host,'--port='.$port,'--user='.DB_USERNAME,$target],[0=>['file',$dump,'r'],1=>['file','/dev/null','w'],2=>['pipe','w']],$environment);
    if($restoreExit!==0)throw new RuntimeException('restore failed '.substr($restoreError,0,120));
    $childEnvironment=$environment;
    $childEnvironment['ONEID_DB_DSN']='mysql:host='.$host.';port='.$port.';dbname='.$target;
    $childEnvironment['ONEID_REHEARSAL_TARGET_DATABASE']=$target;
    [$childExit,$childStdout,$childStderr]=s4e_iso_run([PHP_BINARY,$root.'/tools/s4e_isolated_pilot_child.php'],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$childEnvironment);
    $childOutput=trim($childStdout."\n".$childStderr);
    echo $childOutput."\n";
}catch(Throwable $exception){fwrite(STDERR,"FAIL isolated rehearsal infrastructure error\n");}
finally{
    if($created){
        if($target==='oneiddb'||preg_match('/\Aoneiddb_s4e_[0-9]{8}_[0-9]{6}_[a-f0-9]{4}\z/',$target)!==1)s4e_iso_fail('unsafe cleanup target');
        try{$pdo->exec('DROP DATABASE '.s4e_iso_quote($target));$dropped=true;}catch(Throwable){$dropped=false;}
    }
}
printf("RESULT child_exit=%d restore_target_dropped=%s source_database=oneiddb source_modified=no\n",$childExit,$dropped?'yes':'no');
exit($childExit===0&&$dropped?0:1);
