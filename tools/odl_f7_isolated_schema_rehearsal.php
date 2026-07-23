<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__).'/lib/config.php';
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$source=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$db='oneid_odl_f7_rehearsal_'.bin2hex(random_bytes(4));
$quoted='`'.str_replace('`','``',$db).'`';
try{
 $pdo->exec("CREATE DATABASE $quoted CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
 $pdo->exec("CREATE TABLE $quoted.external_source LIKE `$source`.external_source");
 $pdo->exec("INSERT INTO $quoted.external_source SELECT * FROM `$source`.external_source");
 $pdo->exec("USE $quoted");
 $pdo->exec((string)file_get_contents(dirname(__DIR__).'/docs/migrations/20260723_odl_f7_pilot_audit_up.sql'));
 $table=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_external_identity_event'")->fetchColumn();
 $events=(int)$pdo->query('SELECT COUNT(*) FROM user_external_identity_event')->fetchColumn();
 printf("PASS forward table=%d events=%d user_mutations=0\n",$table,$events);
 if($table!==1||$events!==0)throw new RuntimeException('ODL_F7_FORWARD_FAILED');
 $pdo->exec((string)file_get_contents(dirname(__DIR__).'/docs/migrations/20260723_odl_f7_pilot_audit_down.sql'));
 $table=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_external_identity_event'")->fetchColumn();
 printf("PASS rollback table=%d events=0 user_mutations=0\n",$table);
 if($table!==0)throw new RuntimeException('ODL_F7_ROLLBACK_FAILED');
}finally{
 try{$pdo->exec("USE `$source`");$pdo->exec("DROP DATABASE IF EXISTS $quoted");}catch(Throwable){}
}
echo "RESULT checks=2 failed=0\n";
