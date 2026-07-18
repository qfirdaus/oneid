<?php

namespace OneId\App\Sync;

use OneId\App\Sync\Contracts\SyncPolicyInterface;
use OneId\App\Sync\DTO\SyncPlan;

/**
 * Pure legacy-compatible sync decision planner.
 *
 * This class performs no I/O and has no persistence, source or session access.
 */
final class SyncPlanner
{
    public function __construct(private SyncPolicyInterface $policy)
    {
    }

    /**
     * @param list<array<string, mixed>> $externalRows
     * @param list<array<string, mixed>> $activeUsers
     * @param list<string> $inactiveUserIds
     */
    public function plan(array $externalRows, array $activeUsers, array $inactiveUserIds): SyncPlan
    {
        $sourceRows = count($externalRows);
        $discardedInvalid = 0;
        $discardedExcluded = 0;
        $discardedProtectedCollisions = 0;
        $list = $externalRows;

        foreach ($list as $index => $row) {
            $list[$index]['hash'] = $this->rowHash($row);
            if (!isset($list[$index]['data4'])
                || $list[$index]['data4'] === ''
                || $list[$index]['data4'] === ' '
            ) {
                unset($list[$index]);
                $discardedInvalid++;
            }
        }

        $normalize = static fn($value): string => trim((string) $value);
        $normalizeStudentIdentity = static function ($value) use ($normalize): string {
            $compacted = preg_replace('/[\s\p{Pd}]+/u', '', $normalize($value));
            return $compacted ?? $normalize($value);
        };
        $excluded = array_flip(array_map($normalize, $this->policy->excludedUserIds()));
        $list = array_values(array_filter($list, static function (array $row) use (
            $excluded,
            $normalize,
            &$discardedExcluded
        ): bool {
            if (isset($excluded[$normalize($row['data4'] ?? '')])) {
                $discardedExcluded++;
                return false;
            }
            return true;
        }));

        $ssoList = array_values(array_filter(
            $activeUsers,
            static fn(array $row): bool => !isset($excluded[$normalize($row['u_id'] ?? '')])
        ));

        // Manual accounts are locally authoritative. Mirror the S1 writer
        // policy in the pure planner so preview counts cannot propose an
        // overwrite or deactivation that production would refuse.
        $protectedIdentityMap = [];
        $protectedManualUsers = 0;
        foreach ($ssoList as $ssoRow) {
            if (($ssoRow['account_source'] ?? '') !== 'manual'
                || (int) ($ssoRow['sync_protected'] ?? 0) !== 1
            ) {
                continue;
            }
            $protectedManualUsers++;
            foreach (['u_id', 'data2', 'data4'] as $identityField) {
                $identity = $normalize($ssoRow[$identityField] ?? '');
                if ($identity !== '') {
                    $protectedIdentityMap[$identity] = true;
                    $protectedIdentityMap[$normalizeStudentIdentity($identity)] = true;
                }
            }
        }
        if ($protectedIdentityMap !== []) {
            $ssoList = array_values(array_filter(
                $ssoList,
                static fn(array $row): bool => ($row['account_source'] ?? '') !== 'manual'
                    || (int) ($row['sync_protected'] ?? 0) !== 1
            ));
            $list = array_values(array_filter($list, static function (array $row) use (
                $protectedIdentityMap,
                $normalize,
                &$discardedProtectedCollisions
            ): bool {
                $primary = $normalize($row['data4'] ?? '');
                $secondary = $normalize($row['data2'] ?? '');
                $collides = isset($protectedIdentityMap[$primary])
                    || ($secondary !== '' && isset($protectedIdentityMap[$secondary]));
                if ($collides) {
                    $discardedProtectedCollisions++;
                }
                return !$collides;
            }));
        }

        $ssoByUid = [];
        foreach ($ssoList as $ssoRow) {
            $ssoByUid[$ssoRow['u_id']] = $ssoRow;
        }
        $inactiveUidMap = array_flip($inactiveUserIds);

        $externalByIc = [];
        $externalByStudent = [];
        foreach ($list as $row) {
            $isStudent = isset($row['ext_data_source_category'])
                && in_array($normalize($row['ext_data_source_category']), [
                    'Pelajar',
                    'PelajarPelajar',
                    'PentadbiranPelajar',
                    'AkademikPelajar',
                ], true);
            if ($isStudent) {
                $matric = $normalizeStudentIdentity($row['data4'] ?? '');
                $ic = $normalizeStudentIdentity($row['data2'] ?? '');
                if ($matric !== '' && $ic !== '') {
                    $externalByStudent[$matric . '|' . $ic] = $row;
                }
            } else {
                $ic = $normalize($row['data4'] ?? '');
                if ($ic !== '') {
                    $externalByIc[$ic] = $row;
                }
            }
        }

        $toRemove = [];
        $matchedSso = [];
        foreach ($ssoList as $sso) {
            $row = null;
            $matric = $normalizeStudentIdentity($sso['u_id'] ?? '');
            $ic = $normalizeStudentIdentity($sso['data2'] ?? '');
            $studentKey = $matric . '|' . $ic;

            if ($matric !== '' && $ic !== '' && isset($externalByStudent[$studentKey])) {
                $row = $externalByStudent[$studentKey];
            } elseif (isset($externalByIc[$normalize($sso['data4'] ?? '')])) {
                $row = $externalByIc[$normalize($sso['data4'])];
            }

            if ($row !== null) {
                $row['u_id'] = $sso['u_id'];
                $matchedSso[] = $row;
            } else {
                $toRemove[] = $sso;
            }
        }

        $actions = [];
        foreach ($toRemove as $remove) {
            $actions[] = [
                'action' => 'DEACTIVATE',
                'u_id' => $remove['u_id'],
                'row' => $remove,
                'old_data' => SyncDataTransformer::buildLogSnapshot($remove),
                'new_data' => null,
                'changed_fields' => null,
                'category_id' => null,
                'change_hash' => null,
            ];
        }

        foreach ($matchedSso as $update) {
            $old = $ssoByUid[$update['u_id']] ?? null;
            if ($old) {
                $changedFields = SyncDataTransformer::getChangedFields($old, $update);
                if ($changedFields !== '') {
                    $fields = explode(',', $changedFields);
                    $actions[] = [
                        'action' => 'UPDATE',
                        'u_id' => $update['u_id'],
                        'row' => $update,
                        'old_data' => SyncDataTransformer::pickLogFields($old, $fields),
                        'new_data' => SyncDataTransformer::pickLogFields($update, $fields),
                        'changed_fields' => $changedFields,
                        'category_id' => null,
                        'change_hash' => $update['hash'],
                    ];
                }
            }
        }

        foreach ($list as $index => $row) {
            $list[$index]['u_changes_hash'] = $this->rowHash($row);
            $list[$index]['source'] = '2';
        }

        $ssoHashMap = [];
        foreach ($ssoList as $ssoRow) {
            if (!empty($ssoRow['u_changes_hash'])) {
                $ssoHashMap[$ssoRow['u_changes_hash']] = $ssoRow;
            }
        }
        foreach ($matchedSso as $update) {
            $newHash = $this->rowHash($update);
            $oldHash = $ssoByUid[$update['u_id']]['u_changes_hash'] ?? null;
            if ($oldHash !== null && $oldHash !== '') {
                unset($ssoHashMap[$oldHash]);
            }
            $ssoHashMap[$newHash] = array_merge(
                $update,
                ['u_changes_hash' => $newHash, 'source' => '1']
            );
        }

        $filtered = SyncDataTransformer::removeDuplicateKeys(
            array_merge($list, array_values($ssoHashMap))
        );
        $matchedData4 = [];
        foreach ($matchedSso as $matched) {
            $key = $normalize($matched['data4'] ?? '');
            if ($key !== '') {
                $matchedData4[$key] = true;
            }
        }

        foreach ($filtered as $row) {
            if ($row['source'] == '1') {
                continue;
            }
            $data4 = $normalize($row['data4'] ?? '');
            if ($data4 !== '' && isset($matchedData4[$data4])) {
                continue;
            }
            $action = isset($inactiveUidMap[$row['data4']]) ? 'REACTIVATE' : 'NEW';
            $actions[] = [
                'action' => $action,
                'u_id' => $row['data4'],
                'row' => $row,
                'old_data' => null,
                'new_data' => SyncDataTransformer::buildLogSnapshot($row),
                'changed_fields' => null,
                'category_id' => $this->policy->categoryIdFor($row['ext_data_source_category']),
                'change_hash' => $this->rowHash($row),
            ];
        }

        $warnings = [];
        if ($discardedProtectedCollisions > 0) {
            $warnings[] = 'External identities colliding with protected manual accounts were excluded.';
        }

        return new SyncPlan(
            $actions,
            $sourceRows,
            $discardedInvalid,
            $discardedExcluded,
            $warnings,
            $protectedManualUsers,
            $discardedProtectedCollisions
        );
    }

    /** @param array<string, mixed> $row */
    private function rowHash(array $row): string
    {
        return SyncDataTransformer::computeHash(
            $row['data1'],
            $row['data2'],
            $row['data3'],
            $row['data4'],
            $row['data5'],
            $row['data6'],
            $row['data7'],
            $row['data8'],
            $row['data9'],
            $row['data10'],
            $row['data11'],
            $row['data12'],
            $row['ext_data_source_category']
        );
    }
}
