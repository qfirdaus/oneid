<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__).'/lib/config.php';
$mode=$argv[1]??'--check';
if(!in_array($mode,['--check','--apply','--rollback'],true))exit(2);
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$clause=(string)$pdo->query("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_external_identity_event_type'")->fetchColumn();
$ready=str_contains($clause,'FULL_NEW')&&str_contains($clause,'FULL_ROLLED_BACK');
printf("ODL_F8_SCHEMA full_events=%s mode=%s\n",$ready?'yes':'no',$mode);
if($mode==='--check')exit($ready?0:1);
if((getenv('ONEID_ODL_F8_CHANGE_ID')?:'')!=='ONEID-ODL-F8-20260724-01'){
 fwrite(STDERR,"FAIL ODL_F8_CHANGE_ID_REQUIRED\n");exit(1);
}
if($mode==='--apply'){
 if($ready){echo"PASS ODL F8 event schema already installed\n";exit(0);}
 $pdo->exec((string)file_get_contents(dirname(__DIR__).'/docs/migrations/20260724_odl_f8_full_event_up.sql'));
 echo"PASS ODL F8 event schema installed user_mutations=0\n";exit(0);
}
$events=(int)$pdo->query("SELECT COUNT(*) FROM user_external_identity_event
 WHERE event_type IN('FULL_NEW','FULL_ROLLED_BACK')")->fetchColumn();
if(!$ready||$events!==0){fwrite(STDERR,"FAIL ODL_F8_SCHEMA_ROLLBACK_BLOCKED\n");exit(1);}
$pdo->exec((string)file_get_contents(dirname(__DIR__).'/docs/migrations/20260724_odl_f8_full_event_down.sql'));
echo"PASS ODL F8 event schema rolled back\n";
