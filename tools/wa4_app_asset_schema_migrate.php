<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';

$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$exists=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sp_app_asset'")->fetchColumn();
if($exists===0){
    $sql=(string)file_get_contents(dirname(__DIR__).'/docs/migrations/20260717_wa4_environment_app_asset_up.sql');
    if(trim($sql)==='')throw new RuntimeException('WA4 migration SQL is empty.');
    $pdo->exec($sql);
}
$columns=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sp_app_asset' AND COLUMN_NAME IN ('sp_id','environment','image_filename','updated_at','updated_by')")->fetchColumn();
$pk=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sp_app_asset' AND INDEX_NAME='PRIMARY'")->fetchColumn();
$fk=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sp_app_asset' AND CONSTRAINT_NAME='fk_sp_app_asset_sp_list'")->fetchColumn();
$rows=(int)$pdo->query("SELECT COUNT(*) FROM sp_app_asset")->fetchColumn();
echo "table=sp_app_asset columns={$columns} primary_columns={$pk} foreign_key={$fk} rows={$rows}\n";
exit($columns===5&&$pk===2&&$fk===1?0:1);
