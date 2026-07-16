<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/config.php';
$mode=$argv[1]??'--check';if(!in_array($mode,['--check','--apply'],true)){fwrite(STDERR,"Usage: php tools/sc4_token_issued_at_schema_migrate.php [--check|--apply]\n");exit(2);}
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$column=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='token_tbl' AND COLUMN_NAME='token_issued_at'")->fetchColumn();
if($mode==='--apply'&&$column===0){$pdo->exec('ALTER TABLE token_tbl ADD COLUMN token_issued_at DATETIME NULL AFTER token_datetime');$column=1;}
if($column===0){echo "SC4 column=no mode={$mode}\n";exit(1);}
if($mode==='--apply'){$pdo->exec('UPDATE token_tbl SET token_issued_at=token_datetime WHERE token_issued_at IS NULL');$nullable=$pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='token_tbl' AND COLUMN_NAME='token_issued_at'")->fetchColumn();if($nullable==='YES')$pdo->exec('ALTER TABLE token_tbl MODIFY token_issued_at DATETIME NOT NULL');}
$nulls=(int)$pdo->query('SELECT COUNT(*) FROM token_tbl WHERE token_issued_at IS NULL')->fetchColumn();$index=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='token_tbl' AND INDEX_NAME='idx_token_issued_at'")->fetchColumn();
if($mode==='--apply'&&$index===0){$pdo->exec('CREATE INDEX idx_token_issued_at ON token_tbl(token_issued_at)');$index=1;}
printf("SC4 column=yes nulls=%d index=%s mode=%s\n",$nulls,$index?'yes':'no',$mode);exit($nulls===0&&$index===1?0:1);
