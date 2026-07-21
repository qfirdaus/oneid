<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(2); }
require_once dirname(__DIR__).'/lib/config.php';
$mode=$argv[1]??'--check';
if(!in_array($mode,['--check','--apply'],true)){fwrite(STDERR,"Usage: php tools/f7_admin_step_up_lifetime_schema_migrate.php [--check|--apply]\n");exit(2);}
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$column=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sys_config' AND COLUMN_NAME='admin_step_up_lifetime_minutes'")->fetchColumn();
$check=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sys_config' AND CONSTRAINT_NAME='chk_sys_config_admin_step_up_lifetime' AND CONSTRAINT_TYPE='CHECK'")->fetchColumn();
$event=(int)$pdo->query("SELECT COUNT(*) FROM syslog_event_conf WHERE syslog_event_id=54 AND syslog_event_name='ADMIN_STEP_UP_LIFETIME_UPDATED'")->fetchColumn();
if($mode==='--apply'&&$column===0&&$check===0){
    $pdo->exec("ALTER TABLE sys_config ADD COLUMN admin_step_up_lifetime_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER admin_2fa_enabled, ADD CONSTRAINT chk_sys_config_admin_step_up_lifetime CHECK (admin_step_up_lifetime_minutes IN (5,10,15,30))");
    $pdo->exec("INSERT INTO syslog_event_conf(syslog_event_id,syslog_event_name) VALUES(54,'ADMIN_STEP_UP_LIFETIME_UPDATED') ON DUPLICATE KEY UPDATE syslog_event_name=VALUES(syslog_event_name)");
    $column=$check=$event=1;
}
$value=$column===1?(int)$pdo->query('SELECT admin_step_up_lifetime_minutes FROM sys_config WHERE singleton_key=1')->fetchColumn():-1;
$valid=$column===1&&$check===1&&$event===1&&in_array($value,[5,10,15,30],true);
printf("F7_ADMIN_STEP_UP_LIFETIME column=%d check=%d event=%d value=%d mode=%s\n",$column,$check,$event,$value,$mode);
exit($valid?0:1);
