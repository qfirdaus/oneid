<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/app/Metadata/MetadataContentInventory.php';

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$inventory = new \OneId\App\Metadata\MetadataContentInventory($pdo);
$result = $inventory->preview();

if (in_array('--summary', $argv, true)) {
    unset($result['manifest']['items'], $result['manifest']['duplicate_content']);
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
