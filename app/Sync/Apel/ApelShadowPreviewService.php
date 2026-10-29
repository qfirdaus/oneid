<?php
declare(strict_types=1);

namespace OneId\App\Sync\Apel;

use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\DTO\SourceSnapshot;
use OneId\App\Sync\Odl\OdlShadowPreviewReader;
use OneId\App\Sync\SourceAware\SourceAwareStudentPlanner;

final class ApelShadowPreviewService
{
    public function __construct(
        private readonly ApelShadowPreviewConfig $config,
        private readonly ExternalUserSourceInterface $source,
        private readonly OdlShadowPreviewReader $reader,
        private readonly SourceAwareStudentPlanner $planner
    ) {}

    /** @return array<string,mixed> */
    public function preview(): array
    {
        if (!$this->config->enabled) {
            throw new \RuntimeException('APEL_SHADOW_PREVIEW_DISABLED');
        }
        try {
            $rows = $this->source->fetchAll();
            $snapshot = new SourceSnapshot(
                ApelSourceDefinition::SOURCE_CODE,
                ApelSourceDefinition::SOURCE_FAMILY,
                'success',
                $rows,
                $this->config->baselineRows
            );
        } catch (\Throwable $exception) {
            $status = match ($exception->getMessage()) {
                'APEL_SOURCE_CONNECTION_FAILED', 'APEL_PDO_MYSQL_TLS_UNAVAILABLE',
                'APEL_TLS_NOT_ACTIVE' => 'connection_failed',
                'APEL_SOURCE_QUERY_FAILED' => 'query_failed',
                'APEL_SOURCE_EMPTY' => 'success',
                default => throw $exception,
            };
            $snapshot = new SourceSnapshot(
                ApelSourceDefinition::SOURCE_CODE,
                ApelSourceDefinition::SOURCE_FAMILY,
                $status,
                [],
                $this->config->baselineRows
            );
        }

        $plan = $this->planner->plan(
            [$snapshot],
            $this->reader->users(),
            $this->reader->memberships()
        );
        $safe = $plan->safeProjection();
        $safe['mode'] = 'apel_shadow_preview';
        $safe['source'] = ApelSourceDefinition::SOURCE_CODE;
        $safe['source_rows'] = count($snapshot->rows);
        $safe['action_counts'] = [
            'membership' => $this->counts($plan->membershipActions),
            'account' => $this->counts($plan->accountActions),
        ];
        unset($safe['membership_actions'], $safe['account_actions']);
        $safe['preview_digest'] = hash(
            'sha256',
            json_encode($safe, JSON_UNESCAPED_SLASHES) ?: ''
        );
        return $safe;
    }

    /** @param list<array<string,mixed>> $actions @return array<string,int> */
    private function counts(array $actions): array
    {
        $counts = [];
        foreach ($actions as $action) {
            $name = (string) ($action['action'] ?? 'UNKNOWN');
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }
        ksort($counts, SORT_STRING);
        return $counts;
    }
}
