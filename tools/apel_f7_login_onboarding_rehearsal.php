<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);require_once $root.'/lib/config.php';require_once $root.'/lib/Database.php';
$db=null;$pdo=null;$started=false;$selected='';$beforeHash='';$beforeRequired=-1;$beforeTokens=-1;
try{
 $db=new Database();$property=(new ReflectionClass($db))->getProperty('pdo');$property->setAccessible(true);$pdo=$property->getValue($db);
 if(!$pdo instanceof PDO)throw new RuntimeException('APEL_F7_AUTH_CONNECTION_UNAVAILABLE');
 $pdo->beginTransaction();$started=true;
 $q=$pdo->query("SELECT u.u_id,u.u_password,u.password_change_required,u.avail_status,u.data5
  FROM user_external_identity i JOIN user_tbl u ON u.u_id=i.u_id
  WHERE i.source_code='STUDENT_APEL' AND i.source_active=1 AND u.avail_status=1
  ORDER BY i.u_id LIMIT 1 FOR UPDATE");$account=$q->fetch(PDO::FETCH_ASSOC);
 if(!is_array($account))throw new RuntimeException('APEL_F7_AUTH_ACCOUNT_NOT_FOUND');
 $selected=(string)$account['u_id'];$beforeHash=(string)$account['u_password'];$beforeRequired=(int)$account['password_change_required'];
 $t=$pdo->prepare('SELECT COUNT(*) FROM token_tbl WHERE user_id=:u AND status=1');$t->execute([':u'=>$selected]);$beforeTokens=(int)$t->fetchColumn();
 $temporary=bin2hex(random_bytes(24)).'!Aa9';$temporaryHash=oneid_password_hash($temporary);
 $u=$pdo->prepare('UPDATE user_tbl SET u_password=:p,password_change_required=1 WHERE u_id=:u');$u->execute([':p'=>$temporaryHash,':u'=>$selected]);if($u->rowCount()!==1)throw new RuntimeException('APEL_F7_AUTH_REHEARSAL_SETUP_FAILED');
 $authenticated=$db->func_authenticate($selected,$temporary);
 if(!is_array($authenticated)||(string)($authenticated['u_id']??'')!==$selected)throw new RuntimeException('APEL_F7_AUTH_REHEARSAL_REJECTED');
 if((int)($authenticated['password_change_required']??0)!==1)throw new RuntimeException('APEL_F7_ONBOARDING_NOT_REQUIRED');
 $emailValid=filter_var((string)($account['data5']??''),FILTER_VALIDATE_EMAIL)!==false;
 $pdo->rollBack();$started=false;unset($temporary,$temporaryHash,$authenticated);
 $verify=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 $v=$verify->prepare('SELECT u_password,password_change_required FROM user_tbl WHERE u_id=:u');$v->execute([':u'=>$selected]);$after=$v->fetch(PDO::FETCH_ASSOC);
 $t=$verify->prepare('SELECT COUNT(*) FROM token_tbl WHERE user_id=:u AND status=1');$t->execute([':u'=>$selected]);$afterTokens=(int)$t->fetchColumn();
 $restored=is_array($after)&&hash_equals($beforeHash,(string)$after['u_password'])&&$beforeRequired===(int)$after['password_change_required']&&$beforeTokens===$afterTokens;
 if(!$restored)throw new RuntimeException('APEL_F7_AUTH_STATE_NOT_RESTORED');
 printf("RESULT rehearsal=pass authentication=accepted password_change_required=1 email_ready=%s transaction=rolled_back credential_retained=no active_sessions_unchanged=yes raw_identity_exposed=0 committed_mutations=0\n",$emailValid?'yes':'no');
}catch(Throwable$e){if($started&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();unset($temporary,$temporaryHash);printf("RESULT rehearsal=failed code=%s credential_retained=no raw_identity_exposed=0\n",preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'APEL_F7_AUTH_REHEARSAL_FAILED');exit(1);}
