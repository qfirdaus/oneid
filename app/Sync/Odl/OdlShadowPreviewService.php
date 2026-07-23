<?php
declare(strict_types=1);
namespace OneId\App\Sync\Odl;

use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\Adapters\LegacySyncPolicy;
use OneId\App\Sync\DTO\SourceSnapshot;
use OneId\App\Sync\SourceAware\SourceAwareStudentPlanner;
use OneId\App\Sync\SyncPlanner;

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
        $safe['sync_action_counts'] = $plan->allowed
            ? $this->legacyActionCounts(
                $staffSnapshot->rows,
                $ugSnapshot->rows
            )
            : [
                StaffSource::SOURCE_CODE => [],
                UgStudentSource::SOURCE_CODE => [],
                OdlStudentSource::SOURCE_CODE => [],
            ];
        $safe['sync_action_counts'][OdlStudentSource::SOURCE_CODE] =
            $safe['action_counts']['account']['by_source']
                [OdlStudentSource::SOURCE_CODE] ?? [];
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

    /**
     * @param list<array<string,mixed>> $staffRows
     * @param list<array<string,mixed>> $ugRows
     * @return array<string,array<string,int>>
     */
    private function legacyActionCounts(array $staffRows, array $ugRows): array
    {
        $activeUsers = $this->reader->legacyActiveUsers();
        $inactiveUsers = $this->reader->legacyInactiveUserIds();
        $activeUgUsers = array_fill_keys(
            $this->reader->activeUserIdsBySource(UgStudentSource::SOURCE_CODE),
            true
        );
        $planner = new SyncPlanner(new LegacySyncPolicy());
        $staffPlan = $planner->plan(
            $staffRows,
            array_values(array_filter(
                $activeUsers,
                static fn(array $user): bool =>
                    in_array((int) ($user['u_category'] ?? 0), [2, 3], true)
            )),
            $inactiveUsers
        );
        $ugPlan = $planner->plan(
            $ugRows,
            array_values(array_filter(
                $activeUsers,
                static fn(array $user): bool =>
                    in_array((int) ($user['u_category'] ?? 0), [10, 11, 12], true)
                    && isset($activeUgUsers[(string) ($user['u_id'] ?? '')])
            )),
            $inactiveUsers
        );
        $counts = [
            StaffSource::SOURCE_CODE => $this->planActionCounts($staffPlan->actions),
            UgStudentSource::SOURCE_CODE => $this->planActionCounts($ugPlan->actions),
            OdlStudentSource::SOURCE_CODE => [],
        ];
        return $counts;
    }

    /** @param list<array<string,mixed>> $actions @return array<string,int> */
    private function planActionCounts(array $actions): array
    {
        $counts = [];
        foreach ($actions as $action) {
            $name = (string) ($action['action'] ?? 'UNKNOWN');
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }
        ksort($counts, SORT_STRING);
        return $counts;
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
