<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/config.php';
$mode=$argv[1]??'--check';
if(!in_array($mode,['--check','--apply'],true)){fwrite(STDERR,"Usage: php tools/w4_web_app_schema_migrate.php [--check|--apply]\n");exit(2);}
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$orphans=(int)$pdo->query("SELECT COUNT(*) FROM sp_list s LEFT JOIN sp_group g ON g.sp_group_id=s.sp_group_id WHERE g.sp_group_id IS NULL")->fetchColumn();
$duplicates=(int)$pdo->query("SELECT COUNT(*) FROM (SELECT LOWER(TRIM(sp_group_name)) n FROM sp_group GROUP BY n HAVING COUNT(*)>1) d")->fetchColumn();
$fk=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sp_list' AND CONSTRAINT_NAME='fk_sp_list_sp_group'")->fetchColumn();
$unique=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sp_group' AND INDEX_NAME='uq_sp_group_name' AND NON_UNIQUE=0")->fetchColumn();
printf("W4 orphan_refs=%d duplicate_names=%d fk=%s unique_name=%s mode=%s\n",$orphans,$duplicates,$fk?'yes':'no',$unique?'yes':'no',$mode);
if($orphans!==0||$duplicates!==0){fwrite(STDERR,"FAIL unsafe schema baseline\n");exit(1);}
if($mode==='--check')exit(($fk===1&&$unique===1)?0:1);
if($unique===0){$pdo->exec("ALTER TABLE sp_group MODIFY sp_group_name VARCHAR(100) NOT NULL, ADD UNIQUE KEY uq_sp_group_name (sp_group_name)");}
if($fk===0){$pdo->exec("ALTER TABLE sp_list ADD CONSTRAINT fk_sp_list_sp_group FOREIGN KEY (sp_group_id) REFERENCES sp_group(sp_group_id) ON UPDATE RESTRICT ON DELETE RESTRICT");}
echo "PASS W4 schema constraints applied\n";
