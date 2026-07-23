<?php
declare(strict_types=1);
namespace OneId\App\Sync\Odl;

use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\DTO\SourceSnapshot;
use OneId\App\Sync\SourceAware\SourceAwareStudentPlanner;

final class OdlShadowPreviewService
{
    public function __construct(
        private readonly OdlShadowPreviewConfig $config,
        private readonly ExternalUserSourceInterface $staff,
        private readonly ExternalUserSourceInterface $odl,
        private readonly ExternalUserSourceInterface $ug,
        private readonly OdlShadowPreviewReader $reader,
        private readonly SourceAwareStudentPlanner $planner
    ) {}

    /** @return array<string,mixed> */
    public function preview(): array
    {
        if (!$this->config->enabled) {
            throw new \RuntimeException('ODL_SHADOW_PREVIEW_DISABLED');
        }
        $staffSnapshot = $this->snapshot(
            StaffSource::SOURCE_CODE,
            'staff',
            $this->staff,
            $this->config->staffBaselineRows,
            ['EXTERNAL_STAFF_CONNECTION_FAILED', 'ODBC_EXTENSION_UNAVAILABLE'],
            ['EXTERNAL_STAFF_QUERY_FAILED']
        );
        $odlSnapshot = $this->snapshot(
            OdlStudentSource::SOURCE_CODE,
            'student',
            $this->odl,
            $this->config->odlBaselineRows,
            ['ODL_SOURCE_CONNECTION_FAILED'],
            ['ODL_SOURCE_QUERY_FAILED']
        );
        $ugSnapshot = $this->snapshot(
            UgStudentSource::SOURCE_CODE,
            'student',
            $this->ug,
            $this->config->ugBaselineRows,
            ['EXTERNAL_STUDENT_CONNECTION_FAILED', 'ODBC_EXTENSION_UNAVAILABLE'],
            ['EXTERNAL_STUDENT_QUERY_FAILED']
        );
        $plan = $this->planner->plan(
            [$staffSnapshot, $ugSnapshot, $odlSnapshot],
            $this->reader->users(),
            $this->reader->memberships()
        );
        $safe = $plan->safeProjection();
        $safe['action_counts'] = [
            'membership' => $this->actionCounts($plan->membershipActions),
            'account' => $this->actionCounts($plan->accountActions),
        ];
        unset($safe['membership_actions'], $safe['account_actions']);
        $safe['status'] = 1;
        $safe['mode'] = 'odl_shadow_preview';
        $safe['risk_level'] = $plan->allowed ? 'normal' : 'blocked';
        $safe['source_rows'] = [
            StaffSource::SOURCE_CODE => count($staffSnapshot->rows),
            UgStudentSource::SOURCE_CODE => count($ugSnapshot->rows),
            OdlStudentSource::SOURCE_CODE => count($odlSnapshot->rows),
        ];
        $safe['preview_digest'] = hash(
            'sha256',
            json_encode($safe, JSON_UNESCAPED_SLASHES) ?: ''
        );
        return $safe;
    }

    /** @param list<array<string,mixed>> $actions @return array<string,mixed> */
    private function actionCounts(array $actions): array
    {
        $total = [];
        $bySource = [];
        foreach ($actions as $action) {
            $name = (string) ($action['action'] ?? 'UNKNOWN');
            $source = (string) ($action['source_code'] ?? 'UNKNOWN');
            $total[$name] = ($total[$name] ?? 0) + 1;
            $bySource[$source][$name] = ($bySource[$source][$name] ?? 0) + 1;
        }
        ksort($total, SORT_STRING);
        ksort($bySource, SORT_STRING);
        foreach ($bySource as &$counts) {
            ksort($counts, SORT_STRING);
        }
        unset($counts);
        return ['total' => $total, 'by_source' => $bySource];
    }

    /** @param list<string> $connectionCodes @param list<string> $queryCodes */
    private function snapshot(
        string $sourceCode,
        string $sourceFamily,
        ExternalUserSourceInterface $source,
        int $baseline,
        array $connectionCodes,
        array $queryCodes
    ): SourceSnapshot {
        try {
            $rows = $source->fetchAll();
            return new SourceSnapshot(
                $sourceCode, $sourceFamily, 'success', $rows, $baseline
            );
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            if (in_array($message, $connectionCodes, true)) {
                return new SourceSnapshot(
                    $sourceCode, $sourceFamily, 'connection_failed', [], $baseline
                );
            }
            if (in_array($message, $queryCodes, true)) {
                return new SourceSnapshot(
                    $sourceCode, $sourceFamily, 'query_failed', [], $baseline
                );
            }
            if (in_array($message, [
                'ODL_SOURCE_EMPTY',
                'EXTERNAL_STUDENT_EMPTY',
                'EXTERNAL_STAFF_EMPTY',
            ], true)) {
                return new SourceSnapshot(
                    $sourceCode, $sourceFamily, 'success', [], $baseline
                );
            }
            throw $exception;
        }
    }
}
