<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__).'/lib/config.php';
$mode=$argv[1]??'--check';
if(!in_array($mode,['--check','--apply','--rollback'],true)){fwrite(STDERR,"Usage: php tools/odl_f7_schema_migrate.php [--check|--apply|--rollback]\n");exit(2);}
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$exists=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_external_identity_event'")->fetchColumn();
$events=$exists?(int)$pdo->query('SELECT COUNT(*) FROM user_external_identity_event')->fetchColumn():0;
printf("ODL_F7_SCHEMA table=%d/1 events=%d mode=%s\n",$exists,$events,$mode);
if($mode==='--check')exit($exists===1?0:1);
if((getenv('ONEID_ODL_F7_CHANGE_ID')?:'')!=='ONEID-ODL-F7-20260723-01'){fwrite(STDERR,"FAIL ODL_F7_CHANGE_ID_REQUIRED\n");exit(1);}
if($mode==='--apply'){
    if($exists===1){echo "PASS ODL F7 audit schema already installed\n";exit(0);}
    $pdo->exec((string)file_get_contents(dirname(__DIR__).'/docs/migrations/20260723_odl_f7_pilot_audit_up.sql'));
    echo "PASS ODL F7 audit schema installed pilot_users=0 mutation_users=0\n";exit(0);
}
if($exists!==1||$events!==0){fwrite(STDERR,"FAIL ODL_F7_ROLLBACK_REQUIRES_ZERO_EVENTS\n");exit(1);}
$pdo->exec((string)file_get_contents(dirname(__DIR__).'/docs/migrations/20260723_odl_f7_pilot_audit_down.sql'));
echo "PASS ODL F7 audit schema rolled back events=0\n";
