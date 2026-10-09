<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/config.php';
$mode=$argv[1]??'--check';
if(!in_array($mode,['--check','--apply'],true)){fwrite(STDERR,"Usage: php tools/user_app_recent_migrate.php [--check|--apply]\n");exit(2);}
$exists=static function()use($operation):bool{return $operation->supportsUserAppRecent();};
if($exists()){echo "PASS user_app_recent table is present\n";exit(0);}
if($mode==='--check'){fwrite(STDERR,"FAIL user_app_recent table is missing\n");exit(1);}
$sql=(string)file_get_contents(dirname(__DIR__).'/docs/migrations/20261009_user_app_recent_up.sql');
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec($sql);
$count=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_app_recent'")->fetchColumn();
echo $count===1?"PASS user_app_recent migration applied\n":"FAIL user_app_recent migration not verified\n";
exit($count===1?0:1);
