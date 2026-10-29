<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Sync/Apel/ApelSourceDefinition.php';

use OneId\App\Sync\Apel\ApelSourceDefinition;

$root = dirname(__DIR__, 2);
$runtime = (string) file_get_contents($root . '/config/runtime.php');
$document = (string) file_get_contents($root . '/docs/APEL_FASA_0_SOURCE_CONTRACT.md');
$checks = [
    'source identity is isolated' => ApelSourceDefinition::SOURCE_CODE === 'STUDENT_APEL',
    'source family is student' => ApelSourceDefinition::SOURCE_FAMILY === 'student',
    'fixed view is canonical' => ApelSourceDefinition::VIEW_NAME === 'vw_oneid_active_students',
    'category is Pelajar 10' => ApelSourceDefinition::CATEGORY_LABEL === 'Pelajar'
        && ApelSourceDefinition::CATEGORY_ID === 10,
    'connection defaults are private and empty' => str_contains($runtime, "'ONEID_APEL_MYSQL_HOST' => ''")
        && str_contains($runtime, "'ONEID_APEL_MYSQL_PASSWORD' => ''"),
    'all mutation gates default disabled' => str_contains($runtime, "'ONEID_APEL_OPERATIONAL_PREVIEW_ENABLED' => 'false'")
        && str_contains($runtime, "'ONEID_APEL_OPERATIONAL_APPLY_ENABLED' => 'false'")
        && str_contains($runtime, "'ONEID_APEL_OPERATIONAL_ON_DEMAND_ENABLED' => 'false'")
        && str_contains($runtime, "'ONEID_APEL_MANUAL_OPERATIONAL_ENABLED' => 'false'"),
    'phase zero explicitly keeps overlap fail closed' => str_contains($document, 'overlap audit')
        && str_contains($document, 'fail-closed'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $failed += $passed ? 0 : 1;
}
printf("RESULT checks=%d failures=%d mutation_statements=0\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
