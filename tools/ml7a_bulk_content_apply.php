<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/app/Metadata/MetadataContentInventory.php';
require_once dirname(__DIR__) . '/app/Metadata/MetadataBulkContentPlanner.php';
require_once dirname(__DIR__) . '/app/Metadata/MetadataBulkContentApplyService.php';

$options = getopt('', ['execute', 'actor:']);
if (!array_key_exists('execute', $options)) {
    fwrite(STDERR, "Refusing mutation: pass --execute inside the approved change window.\n");
    exit(2);
}
$actor = trim((string) ($options['actor'] ?? ''));
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$inventory = new \OneId\App\Metadata\MetadataContentInventory($pdo);
$planner = new \OneId\App\Metadata\MetadataBulkContentPlanner($inventory);
$service = new \OneId\App\Metadata\MetadataBulkContentApplyService($pdo, $planner);

try {
    $result = $service->apply($actor);
    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
