<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/config.php';
$mode=$argv[1]??'--check';
if(!in_array($mode,['--check','--apply'],true)){fwrite(STDERR,"Usage: php tools/product_tour_progress_migrate.php [--check|--apply]\n");exit(2);}
if($operation->supportsUserProductTourProgress()){echo "PASS user_product_tour_progress table is present\n";exit(0);}
if($mode==='--check'){fwrite(STDERR,"FAIL user_product_tour_progress table is missing\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/docs/migrations/20260916_user_product_tour_progress_up.sql');
if($sql===false||trim($sql)===''){fwrite(STDERR,"FAIL migration file could not be read\n");exit(1);}
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec($sql);
$verify=$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_product_tour_progress'");
if((int)$verify->fetchColumn()!==1){fwrite(STDERR,"FAIL migration did not create user_product_tour_progress\n");exit(1);}
echo "PASS user_product_tour_progress migration applied\n";
