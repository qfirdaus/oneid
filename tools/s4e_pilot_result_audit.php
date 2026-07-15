<?php

/** Read-only, PII-redacted audit for one completed controlled pilot header. */
if (PHP_SAPI !== 'cli') { exit(2); }
$headerId = 0; $expectedAdmin = '';
foreach ($argv as $argument) {
    if (preg_match('/\A--header=([1-9][0-9]*)\z/', $argument, $m) === 1) $headerId=(int)$m[1];
    if (preg_match('/\A--expected-admin=([A-Za-z0-9-]{1,20})\z/', $argument, $m) === 1) $expectedAdmin=$m[1];
}
if ($headerId < 1 || $expectedAdmin === '') { fwrite(STDERR,"Usage: --header=N --expected-admin=ID\n"); exit(2); }
$root=dirname(__DIR__);require_once $root.'/lib/config.php';
$reflection=new ReflectionProperty(Database::class,'pdo');$reflection->setAccessible(true);
/** @var PDO $pdo */ $pdo=$reflection->getValue($operation);

$headerQuery=$pdo->prepare('SELECT ext_head_status,ext_head_initial_sourcedata,ext_head_uploaded_data,total_new,total_updated,total_deactivated,total_reactivated,triggered_by FROM ext_data_temp_header WHERE ext_head_id=?');
$headerQuery->execute([$headerId]);$header=$headerQuery->fetch(PDO::FETCH_ASSOC);
if(!is_array($header)){fwrite(STDERR,"FAIL header_not_found\n");exit(1);}
$audit=['New'=>0,'Update'=>0,'Deactivate'=>0,'Reactivate'=>0];
$auditQuery=$pdo->prepare('SELECT action,COUNT(*) total FROM sync_change_log WHERE ext_head_id=? GROUP BY action');$auditQuery->execute([$headerId]);
foreach($auditQuery->fetchAll(PDO::FETCH_ASSOC)as$row){$key=match(strtoupper((string)$row['action'])){'NEW'=>'New','UPDATE'=>'Update','DEACTIVATE'=>'Deactivate','REACTIVATE'=>'Reactivate',default=>null};if($key!==null)$audit[$key]=(int)$row['total'];}
$bodyQuery=$pdo->prepare('SELECT ext_body_status,COUNT(*) total FROM ext_data_temp_body WHERE ext_head_id=? GROUP BY ext_body_status');$bodyQuery->execute([$headerId]);
$body=[];foreach($bodyQuery->fetchAll(PDO::FETCH_ASSOC)as$row)$body[(int)$row['ext_body_status']]=(int)$row['total'];
$syslogQuery=$pdo->prepare("SELECT COUNT(*) FROM syslog WHERE log_type=22 AND log_detail=?");
$syslogQuery->execute([sprintf('ADMIN_SYNC_SAFE header=%d new=2 updated=1 deactivated=0 reactivated=0',$headerId)]);$syslogCount=(int)$syslogQuery->fetchColumn();
$headerCounts=['New'=>(int)$header['total_new'],'Update'=>(int)$header['total_updated'],'Deactivate'=>(int)$header['total_deactivated'],'Reactivate'=>(int)$header['total_reactivated']];
$expected=['New'=>2,'Update'=>1,'Deactivate'=>0,'Reactivate'=>0];
$adminMatch=hash_equals($expectedAdmin,(string)$header['triggered_by']);
$pass=in_array((int)$header['ext_head_status'],[2,4],true)&&(int)$header['ext_head_initial_sourcedata']===6485
    &&(int)$header['ext_head_uploaded_data']===2&&$headerCounts===$expected&&$audit===$expected
    &&($body[2]??0)===2&&$adminMatch&&$syslogCount===1;
printf("HEADER id=%d status=%d source=%d uploaded=%d new=%d update=%d deactivate=%d reactivate=%d triggered_admin_match=%s\n",$headerId,(int)$header['ext_head_status'],(int)$header['ext_head_initial_sourcedata'],(int)$header['ext_head_uploaded_data'],$headerCounts['New'],$headerCounts['Update'],$headerCounts['Deactivate'],$headerCounts['Reactivate'],$adminMatch?'yes':'no');
printf("AUDIT new=%d update=%d deactivate=%d reactivate=%d staged_completed=%d admin_sync_safe=%d\n",$audit['New'],$audit['Update'],$audit['Deactivate'],$audit['Reactivate'],$body[2]??0,$syslogCount);
printf("RESULT reconciliation=%s mutation_statements=0\n",$pass?'pass':'fail');exit($pass?0:1);
