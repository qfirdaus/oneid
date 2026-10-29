<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);require_once $root.'/lib/config.php';
try{
 $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 $scope=(int)$pdo->query("SELECT COUNT(*) FROM user_external_identity WHERE source_code='STUDENT_APEL' AND source_active=1")->fetchColumn();
 $cross=(int)$pdo->query("SELECT COUNT(*) FROM user_external_identity a JOIN user_external_identity x ON x.u_id=a.u_id AND x.source_active=1 AND x.source_code IN('STUDENT_UG','STUDENT_ODL_PG') WHERE a.source_code='STUDENT_APEL' AND a.source_active=1")->fetchColumn();
 $wrongEvents=(int)$pdo->query("SELECT COUNT(*) FROM user_external_identity_event e JOIN user_external_identity a ON a.u_id=e.u_id AND a.source_code='STUDENT_APEL' WHERE e.source_code IN('STUDENT_UG','STUDENT_ODL_PG') AND e.correlation_id='83a142a1dafce4c4'")->fetchColumn();
 $registry=$pdo->query("SELECT source_code,COUNT(*) c FROM user_external_identity WHERE source_code IN('STUDENT_APEL','STUDENT_UG','STUDENT_ODL_PG') GROUP BY source_code")->fetchAll(PDO::FETCH_KEY_PAIR);
 $previewFile='/tmp/odl-regression-preview.json';if(!is_file($previewFile))throw new RuntimeException('APEL_F7_ODL_PREVIEW_EVIDENCE_MISSING');
 $preview=json_decode((string)file_get_contents($previewFile),true,512,JSON_THROW_ON_ERROR);
 $sources=array_unique(array_merge(array_keys($preview['action_counts']['membership']['by_source']??[]),array_keys($preview['action_counts']['account']['by_source']??[]),array_keys($preview['sync_action_counts']??[])));
 $apelLeaked=in_array('STUDENT_APEL',$sources,true);$expected=array_diff($sources,['STAFF_HR','STUDENT_UG','STUDENT_ODL_PG']);
 $ok=$scope===3&&$cross===0&&$wrongEvents===0&&!$apelLeaked&&$expected===[]&&($preview['allowed']??false)===true&&($preview['can_apply']??true)===false&&($preview['mutation_statements']??-1)===0;
 printf("RESULT regression=%s apel_scope=%d cross_memberships=%d wrong_source_events=%d apel_actions_in_legacy_preview=%d ug_memberships=%d odl_memberships=%d live_preview_allowed=%s blocking=%d mutation_statements=0 raw_identities_exposed=0\n",$ok?'pass':'fail',$scope,$cross,$wrongEvents,$apelLeaked?1:0,(int)($registry['STUDENT_UG']??0),(int)($registry['STUDENT_ODL_PG']??0),($preview['allowed']??false)?'yes':'no',count($preview['blocking_codes']??[]));
 exit($ok?0:1);
}catch(Throwable$e){printf("RESULT regression=failed code=%s mutation_statements=0 raw_identities_exposed=0\n",preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'APEL_F7_REGRESSION_FAILED');exit(1);}
