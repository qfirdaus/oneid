<?php

/** Read-only, PII-redacted reconciliation for one completed full-sync header. */
if (PHP_SAPI !== 'cli') { exit(2); }
$values=['header'=>0,'source'=>0,'new'=>-1,'update'=>-1,'deactivate'=>-1,'reactivate'=>-1];$expectedAdmin='';
foreach($argv as$argument){
    if(preg_match('/\A--expected-admin=([A-Za-z0-9-]{1,20})\z/',$argument,$m)===1)$expectedAdmin=$m[1];
    foreach(array_keys($values)as$key)if(preg_match('/\A--'.preg_quote($key,'/').'=(0|[1-9][0-9]*)\z/',$argument,$m)===1)$values[$key]=(int)$m[1];
}
if($values['header']<1||$values['source']<1||min($values['new'],$values['update'],$values['deactivate'],$values['reactivate'])<0||$expectedAdmin===''){fwrite(STDERR,"Usage: --header=N --source=N --new=N --update=N --deactivate=N --reactivate=N --expected-admin=ID\n");exit(2);}
$root=dirname(__DIR__);require_once $root.'/lib/config.php';
$reflection=new ReflectionProperty(Database::class,'pdo');$reflection->setAccessible(true);/** @var PDO $pdo */$pdo=$reflection->getValue($operation);
$query=$pdo->prepare('SELECT ext_head_status,ext_head_initial_sourcedata,ext_head_uploaded_data,total_new,total_updated,total_deactivated,total_reactivated,triggered_by FROM ext_data_temp_header WHERE ext_head_id=?');$query->execute([$values['header']]);$header=$query->fetch(PDO::FETCH_ASSOC);
if(!is_array($header)){fwrite(STDERR,"FAIL header_not_found\n");exit(1);}
$audit=['New'=>0,'Update'=>0,'Deactivate'=>0,'Reactivate'=>0];$query=$pdo->prepare('SELECT action,COUNT(*) total FROM sync_change_log WHERE ext_head_id=? GROUP BY action');$query->execute([$values['header']]);
foreach($query->fetchAll(PDO::FETCH_ASSOC)as$row){$key=match(strtoupper((string)$row['action'])){'NEW'=>'New','UPDATE'=>'Update','DEACTIVATE'=>'Deactivate','REACTIVATE'=>'Reactivate',default=>null};if($key!==null)$audit[$key]=(int)$row['total'];}
$expected=['New'=>$values['new'],'Update'=>$values['update'],'Deactivate'=>$values['deactivate'],'Reactivate'=>$values['reactivate']];
$headerCounts=['New'=>(int)$header['total_new'],'Update'=>(int)$header['total_updated'],'Deactivate'=>(int)$header['total_deactivated'],'Reactivate'=>(int)$header['total_reactivated']];
$detail=sprintf('ADMIN_SYNC_FULL_SAFE header=%d new=%d updated=%d deactivated=%d reactivated=%d',$values['header'],$values['new'],$values['update'],$values['deactivate'],$values['reactivate']);
$query=$pdo->prepare('SELECT COUNT(*) FROM syslog WHERE log_type=22 AND log_detail=?');$query->execute([$detail]);$syslog=(int)$query->fetchColumn();
$pass=in_array((int)$header['ext_head_status'],[2,4],true)&&(int)$header['ext_head_initial_sourcedata']===$values['source']&&(int)$header['ext_head_uploaded_data']===($values['new']+$values['reactivate'])&&$headerCounts===$expected&&$audit===$expected&&hash_equals($expectedAdmin,(string)$header['triggered_by'])&&$syslog===1;
printf("HEADER id=%d status=%d source=%d uploaded=%d admin_match=%s\n",$values['header'],(int)$header['ext_head_status'],(int)$header['ext_head_initial_sourcedata'],(int)$header['ext_head_uploaded_data'],hash_equals($expectedAdmin,(string)$header['triggered_by'])?'yes':'no');
printf("COUNTS new=%d update=%d deactivate=%d reactivate=%d audit_match=%s syslog=%d\n",$headerCounts['New'],$headerCounts['Update'],$headerCounts['Deactivate'],$headerCounts['Reactivate'],$audit===$expected?'yes':'no',$syslog);
printf("RESULT reconciliation=%s mutation_statements=0\n",$pass?'pass':'fail');exit($pass?0:1);
