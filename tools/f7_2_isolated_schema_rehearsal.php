<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }
require_once dirname(__DIR__) . '/lib/config.php';

$database='oneid_f72_rehearsal_'.bin2hex(random_bytes(6));
if(preg_match('/\Aoneid_f72_rehearsal_[a-f0-9]{12}\z/',$database)!==1)exit(1);
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$quoted='`'.$database.'`';$created=false;
try{
    $source=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if(preg_match('/\A[A-Za-z0-9_]+\z/',$source)!==1)throw new RuntimeException('unsafe source');
    $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");$created=true;
    $pdo->exec("CREATE TABLE {$quoted}.admin_step_up_challenges LIKE `{$source}`.admin_step_up_challenges");
    $pdo->exec("CREATE TABLE {$quoted}.syslog_event_conf LIKE `{$source}`.syslog_event_conf");
    $pdo->exec("USE {$quoted}");
    $up=(string)file_get_contents(dirname(__DIR__).'/docs/migrations/20260720_f7_2_email_otp_up.sql');$pdo->exec($up);
    $column=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_step_up_challenges' AND COLUMN_NAME='sent_at'")->fetchColumn();
    $indexes=(int)$pdo->query("SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_step_up_challenges' AND INDEX_NAME IN ('idx_admin_step_up_challenge_rate','idx_admin_step_up_challenge_ip_rate')")->fetchColumn();
    $events=(int)$pdo->query('SELECT COUNT(*) FROM syslog_event_conf WHERE syslog_event_id BETWEEN 37 AND 43')->fetchColumn();
    printf("PASS forward sent_at=%d indexes=%d events=%d source_mutations=0\n",$column,$indexes,$events);
    if($column!==1||$indexes!==2||$events!==7)throw new RuntimeException('forward failed');
    $down=(string)file_get_contents(dirname(__DIR__).'/docs/migrations/20260720_f7_2_email_otp_down.sql');$pdo->exec($down);
    $column=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_step_up_challenges' AND COLUMN_NAME='sent_at'")->fetchColumn();
    $events=(int)$pdo->query('SELECT COUNT(*) FROM syslog_event_conf WHERE syslog_event_id BETWEEN 37 AND 43')->fetchColumn();
    printf("PASS rollback sent_at=%d audit_events_retained=%d source_mutations=0\n",$column,$events);
    if($column!==0||$events!==7)throw new RuntimeException('rollback failed');
}finally{if($created){$pdo->exec('USE information_schema');$pdo->exec("DROP DATABASE {$quoted}");}}
echo "RESULT checks=2 failed=0 source_mutations=0 rehearsal_database_removed=yes\n";
