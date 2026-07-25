<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Documentation/DocumentInventory.php';

$result = (new \OneId\App\Documentation\DocumentInventory(dirname(__DIR__)))->preview();
if (in_array('--summary', $argv, true)) {
    unset($result['manifest']['items']);
}
echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
), PHP_EOL;
