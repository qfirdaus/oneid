<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/lib/readonly_odbc.php';
require_once dirname(__DIR__) . '/bootstrap/sync_runtime.php';

$source = new \OneId\App\Sync\Odl\StaffSource();
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$users = $pdo->query(
    'SELECT u_id,u_category,data4,avail_status,account_source,sync_protected
     FROM user_tbl'
)->fetchAll();
$memberships = $pdo->query(
    'SELECT u_id,source_code,external_user_id FROM user_external_identity'
)->fetchAll();
$result = (new \OneId\App\Sync\Provenance\StaffProvenancePreview())->preview(
    $source->fetchAll(),
    $users,
    $memberships
);
echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
), PHP_EOL;
