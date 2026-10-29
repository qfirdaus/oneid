<?php

declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);require_once dirname(__DIR__).'/lib/config.php';
$mode=$argv[1]??'--check';if(!in_array($mode,['--check','--apply','--rollback'],true)){fwrite(STDERR,"Usage: php tools/apel_f4_source_register.php [--check|--apply|--rollback]\n");exit(2);}
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$tables=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN('external_source','user_external_identity')")->fetchColumn();
if($tables!==2){fwrite(STDERR,"FAIL APEL_PROVENANCE_SCHEMA_UNAVAILABLE\n");exit(1);}
$read=$pdo->prepare("SELECT source_family,lifecycle_state,is_required,avail_status FROM external_source WHERE source_code='STUDENT_APEL'");$read->execute();$row=$read->fetch();
$members=(int)$pdo->query("SELECT COUNT(*) FROM user_external_identity WHERE source_code='STUDENT_APEL'")->fetchColumn();
$valid=is_array($row)&&$row['source_family']==='student'&&$row['lifecycle_state']==='dormant'&&(int)$row['is_required']===0&&(int)$row['avail_status']===1;
printf("APEL_F4_SOURCE state=%s memberships=%d mode=%s\n",$row===false?'absent':($valid?'dormant':'invalid'),$members,$mode);
if($mode==='--check')exit($valid?0:1);
if((getenv('ONEID_APEL_F4_CHANGE_ID')?:'')!=='ONEID-APEL-F4-20261029-01'){fwrite(STDERR,"FAIL APEL_F4_CHANGE_ID_REQUIRED\n");exit(1);}
$pdo->beginTransaction();try{
if($mode==='--apply'){
    if($valid){$pdo->rollBack();echo"PASS STUDENT_APEL already registered dormant\n";exit(0);}
    if($row!==false)throw new RuntimeException('APEL_SOURCE_PARTIAL_STATE');
    $q=$pdo->prepare("INSERT INTO external_source(source_code,source_name,source_family,lifecycle_state,is_required,avail_status) VALUES('STUDENT_APEL','APEL Student','student','dormant',0,1)");$q->execute();
    if($q->rowCount()!==1)throw new RuntimeException('APEL_SOURCE_INSERT_MISMATCH');$pdo->commit();echo"PASS STUDENT_APEL registered lifecycle=dormant memberships=0\n";exit(0);
}
if(!$valid||$members!==0)throw new RuntimeException('APEL_SOURCE_ROLLBACK_GUARD');
$q=$pdo->prepare("DELETE FROM external_source WHERE source_code='STUDENT_APEL' AND lifecycle_state='dormant' AND is_required=0");$q->execute();
if($q->rowCount()!==1)throw new RuntimeException('APEL_SOURCE_ROLLBACK_MISMATCH');$pdo->commit();echo"PASS STUDENT_APEL dormant registration removed\n";
}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,"FAIL ".$e->getMessage()."\n");exit(1);}
