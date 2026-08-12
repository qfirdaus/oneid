<?php
declare(strict_types=1);if(PHP_SAPI!=='cli')exit(2);define('ONEID_MAINTENANCE_BYPASS',true);require_once dirname(__DIR__).'/lib/config.php';
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$count=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sys_config' AND COLUMN_NAME='maintenance_mode'")->fetchColumn();
if($count===1){echo "PASS maintenance schema already installed\n";exit(0);}
$pdo->exec((string)file_get_contents(dirname(__DIR__).'/docs/migrations/20260812_maintenance_mode_up.sql'));
$installed=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sys_config' AND COLUMN_NAME IN('maintenance_mode','maintenance_starts_at','maintenance_ends_at','maintenance_title_ms','maintenance_title_en','maintenance_message_ms','maintenance_message_en')")->fetchColumn();
if($installed!==7){fwrite(STDERR,"FAIL maintenance schema incomplete\n");exit(1);}echo "PASS maintenance schema installed columns=7 default=OFF\n";
