<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Documentation/ApprovedReleaseCatalogue.php';

$catalogue = new \OneId\App\Documentation\ApprovedReleaseCatalogue(dirname(__DIR__));
echo json_encode(
    $catalogue->preview(),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
), PHP_EOL;
