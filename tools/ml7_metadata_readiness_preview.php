<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/app/Metadata/BilingualMetadataRepository.php';

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$repository = new \OneId\App\Metadata\BilingualMetadataRepository($pdo);
echo json_encode(
    $repository->preview(),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
), PHP_EOL;
