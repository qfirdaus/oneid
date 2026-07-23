<?php
declare(strict_types=1);
namespace OneId\App\Sync\SourceAware;

use OneId\App\Sync\DTO\SourceAwarePlan;
use OneId\App\Sync\DTO\SourceSnapshot;

final class SourceAwareStudentPlanner
{
    public function __construct(private readonly SourceAwareSafetyPolicy $safety) {}

    /** @param list<SourceSnapshot> $snapshots
     * @param list<array<string,mixed>> $users
     * @param list<array<string,mixed>> $memberships */
    public function plan(array $snapshots, array $users, array $memberships): SourceAwarePlan
    {
        $decision = $this->safety->assess($snapshots);
        if (!$decision->allowed) {
            return new SourceAwarePlan(false, [], [], $decision->metrics, $decision->blockingCodes);
        }
        $norm = static function (mixed $value): string {
            $value = trim((string) $value);
            return preg_replace('/[\s\p{Pd}]+/u', '', $value) ?? $value;
        };
        $sources = [];
        $pairs = [];
        $profiles = [];
        foreach ($snapshots as $snapshot) {
            if ($snapshot->sourceFamily !== 'student') {
                continue;
            }
            $sources[$snapshot->sourceCode] = true;
            foreach ($snapshot->rows as $row) {
                $code = (string) ($row['source_code'] ?? $snapshot->sourceCode);
                if ($code !== $snapshot->sourceCode) {
                    return $this->blocked($decision->metrics, 'SOURCE_ENVELOPE_CODE_MISMATCH');
                }
                $matric = $norm($row['data4'] ?? '');
                $ic = $norm($row['data2'] ?? '');
                if ($matric === '' || $ic === '') {
                    continue;
                }
                $pair = $matric . '|' . $ic;
                $pairs[$code][$pair] = ['matric' => $matric, 'ic' => $ic];
                foreach (['data1', 'data5', 'data6', 'data7'] as $field) {
                    $value = trim((string) ($row[$field] ?? ''));
                    if ($value !== '') {
                        $profiles[$pair][$field][$value] = true;
                    }
                }
            }
        }
        foreach ($profiles as $fields) {
            foreach ($fields as $values) {
                if (count($values) > 1) {
                    return $this->blocked($decision->metrics, 'STUDENT_PROFILE_CONFLICT');
                }
            }
        }

        $usersByPair = [];
        foreach ($users as $user) {
            $matric = $norm($user['u_id'] ?? '');
            $ic = $norm($user['data2'] ?? '');
            if ($matric !== '' && $ic !== '') {
                $usersByPair[$matric . '|' . $ic][] = $user;
            }
        }
        $byExternal = [];
        $activeByUser = [];
        foreach ($memberships as $membership) {
            $code = (string) ($membership['source_code'] ?? '');
            $external = $norm($membership['external_user_id'] ?? '');
            $uId = $norm($membership['u_id'] ?? '');
            if ($code !== '' && $external !== '') {
                $byExternal[$code][$external] = $membership;
            }
            if ($uId !== '' && (int) ($membership['source_active'] ?? 0) === 1) {
                $activeByUser[$uId][$code] = true;
            }
        }

        $membershipActions = [];
        $accountActions = [];
        $seen = [];
        foreach ($pairs as $code => $sourcePairs) {
            foreach ($sourcePairs as $pair => $identity) {
                $seen[$code][$identity['matric']] = true;
                $matches = $usersByPair[$pair] ?? [];
                if (count($matches) > 1) {
                    return $this->blocked($decision->metrics, 'AMBIGUOUS_STUDENT_IDENTITY');
                }
                if ($matches === []) {
                    $accountActions[] = [
                        'action' => 'CANDIDATE_NEW',
                        'source_code' => $code,
                        'external_user_id' => $identity['matric'],
                    ];
                    continue;
                }
                $uId = $norm($matches[0]['u_id'] ?? '');
                $existing = $byExternal[$code][$identity['matric']] ?? null;
                if ($existing !== null && $norm($existing['u_id'] ?? '') !== $uId) {
                    return $this->blocked($decision->metrics, 'SOURCE_MEMBERSHIP_CONFLICT');
                }
                $membershipActions[] = [
                    'action' => $existing === null ? 'ADD_MEMBERSHIP' : 'KEEP_MEMBERSHIP_ACTIVE',
                    'source_code' => $code,
                    'external_user_id' => $identity['matric'],
                    'u_id' => $uId,
                ];
                $activeByUser[$uId][$code] = true;
                $accountActions[] = [
                    'action' => (int) ($matches[0]['avail_status'] ?? 0) === 1
                        ? 'KEEP_ACCOUNT_ACTIVE' : 'CANDIDATE_REACTIVATE',
                    'source_code' => $code,
                    'u_id' => $uId,
                ];
            }
        }

        foreach ($memberships as $membership) {
            $code = (string) ($membership['source_code'] ?? '');
            if (!isset($sources[$code]) || (int) ($membership['source_active'] ?? 0) !== 1) {
                continue;
            }
            $external = $norm($membership['external_user_id'] ?? '');
            if (isset($seen[$code][$external])) {
                continue;
            }
            $uId = $norm($membership['u_id'] ?? '');
            $membershipActions[] = [
                'action' => 'MARK_MEMBERSHIP_INACTIVE',
                'source_code' => $code,
                'external_user_id' => $external,
                'u_id' => $uId,
            ];
            unset($activeByUser[$uId][$code]);
            $accountActions[] = [
                'action' => ($activeByUser[$uId] ?? []) === []
                    ? 'CANDIDATE_DEACTIVATE' : 'KEEP_ACCOUNT_ACTIVE_OTHER_SOURCE',
                'source_code' => $code,
                'u_id' => $uId,
            ];
        }
        $metrics = $decision->metrics;
        $metrics['membership_actions'] = count($membershipActions);
        $metrics['account_actions'] = count($accountActions);
        return new SourceAwarePlan(true, $membershipActions, $accountActions, $metrics, []);
    }

    /** @param array<string,mixed> $metrics */
    private function blocked(array $metrics, string $code): SourceAwarePlan
    {
        return new SourceAwarePlan(false, [], [], $metrics, [$code]);
    }
}
