<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__).'/lib/config.php';
$header=0;
foreach(array_slice($argv,1)as$arg){
 if(str_starts_with($arg,'--header='))$header=(int)substr($arg,9);
}
if($header<1){fwrite(STDERR,"Usage: php tools/odl_f9a_rollback_readiness.php --header=N\n");exit(2);}
try{
 $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 ]);
 $q=$pdo->prepare("SELECT action,COUNT(*) action_count,
  SUM(old_data IS NULL) missing_old_data
  FROM sync_change_log WHERE ext_head_id=:h
  GROUP BY action ORDER BY action");
 $q->execute([':h'=>$header]);$rows=$q->fetchAll();
 if($rows===[])throw new RuntimeException('ODL_F9A_HEADER_NOT_FOUND');
 $counts=[];$blocks=[];
 foreach($rows as$row){
  $action=(string)$row['action'];$counts[$action]=(int)$row['action_count'];
  if(in_array($action,['UPDATE','DEACTIVATE'],true)
   &&(int)$row['missing_old_data']>0)$blocks[]='ROLLBACK_OLD_DATA_MISSING';
  if(!in_array($action,['NEW','UPDATE','DEACTIVATE','REACTIVATE'],true))
   $blocks[]='ROLLBACK_ACTION_UNSUPPORTED';
 }
 $q=$pdo->prepare("SELECT COUNT(*) FROM sync_change_log l
  LEFT JOIN user_external_identity i ON BINARY i.u_id=BINARY l.u_id
   AND i.source_code='STUDENT_ODL_PG'
  WHERE l.ext_head_id=:h AND i.id IS NULL");
 $q->execute([':h'=>$header]);
 if((int)$q->fetchColumn()>0)$blocks[]='ROLLBACK_ODL_MEMBERSHIP_MISSING';
 $blocks=array_values(array_unique($blocks));
 echo json_encode([
  'mode'=>'odl_f9a_rollback_readiness','header_id'=>$header,
  'action_counts'=>$counts,'rollback_ready'=>$blocks===[],
  'blocking_codes'=>$blocks,'mutation_statements'=>0,
 ],JSON_PRETTY_PRINT),PHP_EOL;
}catch(Throwable$e){
 printf("RESULT ready=no code=%s mutation=0\n",
  preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'ODL_F9A_ROLLBACK_CHECK_FAILED');
 exit(1);
}
