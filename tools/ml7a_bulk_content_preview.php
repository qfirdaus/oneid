<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/app/Metadata/MetadataContentInventory.php';
require_once dirname(__DIR__) . '/app/Metadata/MetadataBulkContentPlanner.php';

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$inventory = new \OneId\App\Metadata\MetadataContentInventory($pdo);
$result = (new \OneId\App\Metadata\MetadataBulkContentPlanner($inventory))->preview();

if (in_array('--summary', $argv, true)) {
    unset($result['plan']['actions']);
}
echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
), PHP_EOL;
