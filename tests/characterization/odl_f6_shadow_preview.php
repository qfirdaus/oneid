<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);

$root = dirname(__DIR__, 2);
require_once $root . '/bootstrap/sync_runtime.php';

use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\Odl\OdlShadowPreviewConfig;
use OneId\App\Sync\Odl\OdlShadowPreviewReader;
use OneId\App\Sync\Odl\OdlShadowPreviewService;
use OneId\App\Sync\SourceAware\SourceAwareSafetyPolicy;
use OneId\App\Sync\SourceAware\SourceAwareStudentPlanner;

final class F6Source implements ExternalUserSourceInterface {
    public function __construct(private array $rows, private ?string $error = null) {}
    public function fetchAll(): array {
        if ($this->error !== null) throw new RuntimeException($this->error);
        return $this->rows;
    }
}
function f6_row(string $source, string $matric, string $ic): array {
    return [
        'source_code' => $source, 'data1' => 'Private Name',
        'data2' => $ic, 'data4' => $matric, 'data5' => 'private@example.test',
        'data6' => 'Faculty', 'data7' => 'Programme',
        'ext_data_source_category' => 'Pelajar',
    ];
}
function f6_service(OdlShadowPreviewConfig $config, ExternalUserSourceInterface $odl, ExternalUserSourceInterface $ug): OdlShadowPreviewService {
    $reader = new OdlShadowPreviewReader(
        null,
        static fn(): array => [],
        static fn(): array => []
    );
    return new OdlShadowPreviewService(
        $config,
        new F6Source([f6_row('STAFF_HR', 'STAFF1', 'STAFFIC')]),
        $odl, $ug, $reader,
        new SourceAwareStudentPlanner(new SourceAwareSafetyPolicy())
    );
}
$checks = 0; $failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++; $failed += $ok ? 0 : 1;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};
$disabled = false;
try {
    f6_service(
        OdlShadowPreviewConfig::fromValues('false', '', '', ''),
        new F6Source([]), new F6Source([])
    )->preview();
} catch (RuntimeException $e) {
    $disabled = $e->getMessage() === 'ODL_SHADOW_PREVIEW_DISABLED';
}
$report($disabled, 'feature gate defaults to fail-closed behaviour');

$healthy = f6_service(
    OdlShadowPreviewConfig::fromValues('true', '1', '1', '1'),
    new F6Source([f6_row('STUDENT_ODL_PG', 'ODL1', 'IC1')]),
    new F6Source([f6_row('STUDENT_UG', 'UG1', 'IC2')])
)->preview();
$report($healthy['status'] === 1 && $healthy['mode'] === 'odl_shadow_preview', 'healthy shadow preview returned');
$report($healthy['can_apply'] === false && $healthy['mutation_statements'] === 0, 'shadow preview cannot apply');
$report(
    $healthy['source_rows'] === [
        'STAFF_HR' => 1,
        'STUDENT_UG' => 1,
        'STUDENT_ODL_PG' => 1,
    ],
    'per-source counts exposed'
);
$encoded = json_encode($healthy, JSON_UNESCAPED_SLASHES);
$report(is_string($encoded) && !str_contains($encoded, 'Private Name') && !str_contains($encoded, 'private@example.test') && !str_contains($encoded, 'IC1'), 'raw PII excluded');
$report(!array_key_exists('approval_id', $healthy) && !array_key_exists('approval_ready', $healthy), 'no approval capability exposed');
$report(strlen((string) $healthy['preview_digest']) === 64, 'preview digest generated');
$report(!isset($healthy['membership_actions']) && !isset($healthy['account_actions']), 'browser response is aggregate only');

$outage = f6_service(
    OdlShadowPreviewConfig::fromValues('true', '1', '53', '1'),
    new F6Source([], 'ODL_SOURCE_CONNECTION_FAILED'),
    new F6Source([f6_row('STUDENT_UG', 'UG1', 'IC2')])
)->preview();
$report($outage['risk_level'] === 'blocked', 'ODL outage returns blocked preview');
$report(in_array('ODL_CONNECTION_FAILED', $outage['blocking_codes'], true), 'ODL outage code exposed');
$report(
    $outage['action_counts'] === [
        'membership' => ['total' => [], 'by_source' => []],
        'account' => ['total' => [], 'by_source' => []],
    ],
    'outage produces zero actions'
);

$invalidFlag = false;
try { OdlShadowPreviewConfig::fromValues('TRUE', '1061', '53', '5452'); }
catch (RuntimeException $e) { $invalidFlag = $e->getMessage() === 'ODL_SHADOW_PREVIEW_FLAG_INVALID'; }
$report($invalidFlag, 'loose feature flag rejected');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
