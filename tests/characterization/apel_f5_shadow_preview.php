<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/sync_runtime.php';

use OneId\App\Sync\Apel\ApelShadowPreviewConfig;
use OneId\App\Sync\Apel\ApelShadowPreviewService;
use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\Odl\OdlShadowPreviewReader;
use OneId\App\Sync\SourceAware\SourceAwareSafetyPolicy;
use OneId\App\Sync\SourceAware\SourceAwareStudentPlanner;

$checks = [];
$report = static function (bool $ok, string $name) use (&$checks): void {
    $checks[$name] = $ok;
    echo ($ok ? 'PASS ' : 'FAIL '), $name, "\n";
};
$disabled = false;
try { ApelShadowPreviewConfig::fromValues('false', '')->enabled || throw new RuntimeException('APEL_SHADOW_PREVIEW_DISABLED'); }
catch (Throwable) { $disabled = true; }
$report($disabled, 'preview defaults dormant');
$invalid = false;
try { ApelShadowPreviewConfig::fromValues('true', '0'); } catch (Throwable) { $invalid = true; }
$report($invalid, 'enabled preview requires positive baseline');

$source = new class implements ExternalUserSourceInterface {
    public function fetchAll(): array { return [[
        'source_code' => 'STUDENT_APEL', 'data1' => 'Student',
        'data2' => 'IC1', 'data4' => 'APEL1', 'data5' => '',
        'data6' => 'Faculty', 'data7' => 'Programme',
    ]]; }
};
$reader = new OdlShadowPreviewReader(
    null,
    static fn(): array => [],
    static fn(): array => []
);
$result = (new ApelShadowPreviewService(
    ApelShadowPreviewConfig::fromValues('true', '1'),
    $source,
    $reader,
    new SourceAwareStudentPlanner(new SourceAwareSafetyPolicy())
))->preview();
$report(($result['allowed'] ?? false) === true, 'healthy source is allowed');
$report(($result['source_rows'] ?? 0) === 1, 'source row count is reported');
$report(($result['action_counts']['account']['CANDIDATE_NEW'] ?? 0) === 1, 'new candidate is counted');
$report(($result['can_apply'] ?? true) === false, 'shadow preview cannot apply');
$report(($result['mutation_statements'] ?? -1) === 0, 'shadow preview performs zero mutations');
$report(!isset($result['account_actions']) && !isset($result['membership_actions']), 'identities are not exposed');

$failures = count(array_filter($checks, static fn(bool $ok): bool => !$ok));
printf("RESULT checks=%d failures=%d\n", count($checks), $failures);
exit($failures === 0 ? 0 : 1);
