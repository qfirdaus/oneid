<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/config.php';

$mode=$argv[1]??'--check';
if(!in_array($mode,['--check','--apply'],true)){
    fwrite(STDERR,"Usage: php tools/w3_reconcile_inactive_app_state.php [--check|--apply]\n");exit(2);
}
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$apps=$pdo->query("SELECT sp_id,sp_group_id FROM sp_list WHERE avail_status=0 AND sp_group_id<>0 ORDER BY sp_id",PDO::FETCH_ASSOC)->fetchAll();
$refs=[];
foreach(['acl_group','acl_single','acl_blacklist','user_app_favourite'] as $table){
    $exists=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=".$pdo->quote($table))->fetchColumn()===1;
    $refs[$table]=$exists?$pdo->query("SELECT r.* FROM `{$table}` r INNER JOIN sp_list s ON s.sp_id=r.sp_id WHERE s.avail_status=0 ORDER BY r.sp_id",PDO::FETCH_ASSOC)->fetchAll():[];
}
$totalRefs=array_sum(array_map('count',$refs));
printf("W3 inactive_nonarchive_category=%d inactive_access_refs=%d mode=%s\n",count($apps),$totalRefs,$mode);
if($mode==='--check')exit(($apps===[]&&$totalRefs===0)?0:1);
if($apps===[]&&$totalRefs===0){echo "PASS no reconciliation required\n";exit(0);}

$backupDir=dirname(__DIR__).'/storage/backups/W3-'.date('Ymd-His');
if(!mkdir($backupDir,0700,true)&&!is_dir($backupDir))throw new RuntimeException('Unable to create W3 backup directory.');
$snapshot=$backupDir.'/inactive-app-state.json';
$payload=json_encode(['created_at'=>date(DATE_ATOM),'apps'=>$apps,'references'=>$refs],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
if($payload===false||file_put_contents($snapshot,$payload.PHP_EOL,LOCK_EX)===false)throw new RuntimeException('Unable to write W3 snapshot.');
chmod($snapshot,0600);$hash=hash_file('sha256',$snapshot);
file_put_contents($backupDir.'/SHA256SUMS',$hash.'  inactive-app-state.json'.PHP_EOL,LOCK_EX);chmod($backupDir.'/SHA256SUMS',0600);

$pdo->beginTransaction();
try{
    $pdo->query("SELECT sp_id FROM sp_list WHERE avail_status=0 FOR UPDATE")->fetchAll();
    foreach(array_keys($refs) as $table){
        if($refs[$table]!==[]){$pdo->exec("DELETE r FROM `{$table}` r INNER JOIN sp_list s ON s.sp_id=r.sp_id WHERE s.avail_status=0");}
    }
    $pdo->exec("UPDATE sp_list SET sp_group_id=0 WHERE avail_status=0 AND sp_group_id<>0");
    $remaining=(int)$pdo->query("SELECT COUNT(*) FROM sp_list WHERE avail_status=0 AND sp_group_id<>0")->fetchColumn();
    if($remaining!==0)throw new RuntimeException('Inactive app category reconciliation incomplete.');
    $pdo->commit();
    printf("PASS apps=%d references=%d snapshot=%s sha256=%s\n",count($apps),$totalRefs,$snapshot,$hash);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,"FAIL W3 reconciliation rolled back: {$e->getMessage()}\n");exit(1);}
