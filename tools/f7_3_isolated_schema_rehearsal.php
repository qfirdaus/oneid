<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(2);} require_once dirname(__DIR__).'/lib/config.php';
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$source=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if(preg_match('/\A[a-zA-Z0-9_]+\z/',$source)!==1){throw new RuntimeException('F7_3_SOURCE_DATABASE_INVALID');}
$suffix=bin2hex(random_bytes(6));$db='oneid_f73_rehearsal_'.$suffix;
if(preg_match('/\Aoneid_f73_rehearsal_[a-f0-9]{12}\z/',$db)!==1){exit(2);}
$up=(string)file_get_contents(dirname(__DIR__).'/docs/migrations/20260720_f7_3_totp_lifecycle_up.sql');
$down=(string)file_get_contents(dirname(__DIR__).'/docs/migrations/20260720_f7_3_totp_lifecycle_down.sql');
try{
 $pdo->exec("CREATE DATABASE `$db`");
 $pdo->exec("CREATE TABLE `$db`.admin_mfa_factors LIKE `$source`.admin_mfa_factors");
 $pdo->exec("CREATE TABLE `$db`.syslog_event_conf LIKE `$source`.syslog_event_conf");
 $pdo->exec("USE `$db`");$pdo->exec($up);
 $c=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_mfa_factors' AND COLUMN_NAME IN ('enrollment_session_hash','enrollment_browser_digest','last_used_time_step')")->fetchColumn();
 $e=(int)$pdo->query('SELECT COUNT(*) FROM syslog_event_conf WHERE syslog_event_id BETWEEN 44 AND 49')->fetchColumn();
 if($c!==3||$e!==6){throw new RuntimeException('F7_3_FORWARD_REHEARSAL_FAILED');}
 $pdo->exec($down);$remaining=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_mfa_factors' AND COLUMN_NAME IN ('enrollment_session_hash','enrollment_browser_digest','last_used_time_step')")->fetchColumn();
 if($remaining!==0){throw new RuntimeException('F7_3_DOWN_REHEARSAL_FAILED');}
 echo "PASS F7.3 isolated forward/down source_mutations=0\n";
}finally{$pdo->exec("USE `$source`");$pdo->exec("DROP DATABASE IF EXISTS `$db`");echo "PASS rehearsal_database_removed=yes\n";}
