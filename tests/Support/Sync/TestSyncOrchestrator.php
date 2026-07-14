<?php

namespace OneId\Tests\Support\Sync;

use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\Contracts\InitialPasswordFactoryInterface;
use OneId\App\Sync\Contracts\SyncPersistenceInterface;
use OneId\App\Sync\Contracts\SyncPolicyInterface;
use OneId\App\Sync\DTO\SyncRunSummary;
use OneId\App\Sync\SyncDataTransformer;

/**
 * Test-only interface-based projection of the legacy sync orchestration.
 *
 * This class intentionally retains the current transaction boundary for parity.
 * It must not be required or instantiated by production runtime files.
 */
final class TestSyncOrchestrator
{
    public function __construct(
        private ExternalUserSourceInterface $source,
        private SyncPersistenceInterface $persistence,
        private SyncPolicyInterface $policy,
        private InitialPasswordFactoryInterface $passwordFactory
    ) {
    }

    public function run(string $triggeredBy): SyncRunSummary
    {
        $countNew = 0;
        $countUpdated = 0;
        $countDeactivated = 0;
        $countReactivated = 0;
        $logBuffer = [];

        $this->persistence->begin();
        $headerId = $this->persistence->createHeader(0);

        // Deliberately outside try/catch to retain the characterized legacy boundary.
        $list = $this->source->fetchAll();

        if (!empty($list)) {
            foreach ($list as $index => $row) {
                $list[$index]['hash'] = $this->rowHash($row);
                if (!isset($list[$index]['data4'])
                    || $list[$index]['data4'] === ''
                    || $list[$index]['data4'] === ' '
                ) {
                    unset($list[$index]);
                }
            }

            $normalize = static fn($value): string => trim((string) $value);
            $excluded = array_flip(array_map($normalize, $this->policy->excludedUserIds()));
            $list = array_values(array_filter(
                $list,
                static fn(array $row): bool => !isset($excluded[$normalize($row['data4'] ?? '')])
            ));

            $ssoList = array_values(array_filter(
                $this->persistence->activeUsers(),
                static fn(array $row): bool => !isset($excluded[$normalize($row['u_id'] ?? '')])
            ));
            $ssoByUid = [];
            foreach ($ssoList as $ssoRow) {
                $ssoByUid[$ssoRow['u_id']] = $ssoRow;
            }
            $inactiveUidMap = array_flip($this->persistence->inactiveUserIds());

            $externalByIc = [];
            $externalByStudent = [];
            foreach ($list as $row) {
                $isStudent = isset($row['ext_data_source_category'])
                    && $normalize($row['ext_data_source_category']) === 'Pelajar';
                if ($isStudent) {
                    $matric = $normalize($row['data4'] ?? '');
                    $ic = $normalize($row['data2'] ?? '');
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
                $matric = $normalize($sso['u_id'] ?? '');
                $ic = $normalize($sso['data2'] ?? '');
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

            try {
                foreach ($toRemove as $remove) {
                    $this->persistence->deactivateUser($remove['u_id']);
                    $logBuffer[] = [
                        'ext_head_id' => $headerId,
                        'u_id' => $remove['u_id'],
                        'action' => 'DEACTIVATE',
                        'old_data' => SyncDataTransformer::buildLogSnapshot($remove),
                        'new_data' => null,
                        'changed_fields' => null,
                    ];
                    $countDeactivated++;
                }

                foreach ($matchedSso as $update) {
                    $old = $ssoByUid[$update['u_id']] ?? null;
                    if ($old) {
                        $changedFields = SyncDataTransformer::getChangedFields($old, $update);
                        if ($changedFields !== '') {
                            $fields = explode(',', $changedFields);
                            $this->persistence->updateUser($update['u_id'], $update, $update['hash']);
                            $logBuffer[] = [
                                'ext_head_id' => $headerId,
                                'u_id' => $update['u_id'],
                                'action' => 'UPDATE',
                                'old_data' => SyncDataTransformer::pickLogFields($old, $fields),
                                'new_data' => SyncDataTransformer::pickLogFields($update, $fields),
                                'changed_fields' => $changedFields,
                            ];
                            $countUpdated++;
                        }
                    }
                }

                $this->persistence->updateHeaderStatus(
                    $headerId,
                    1,
                    'ext_head_initial_sourcedata',
                    count($list)
                );
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

                foreach ($filtered as $index => $row) {
                    if ($filtered[$index]['source'] == '1') {
                        unset($filtered[$index]);
                        continue;
                    }
                    $data4 = $normalize($filtered[$index]['data4'] ?? '');
                    if ($data4 !== '' && isset($matchedData4[$data4])) {
                        unset($filtered[$index]);
                        continue;
                    }
                    $filtered[$index]['ext_body_id'] = $this->persistence->stageExternalUser(
                        $headerId,
                        $filtered[$index]
                    );
                }

                $pending = array_values($filtered);
                if (!empty($pending)) {
                    foreach ($pending as $row) {
                        $categoryId = $this->policy->categoryIdFor($row['ext_data_source_category']);
                        $this->persistence->insertExternalUser(
                            $row,
                            $categoryId,
                            $this->passwordFactory->createHash(),
                            $this->rowHash($row)
                        );
                        $this->persistence->markStagedUser($headerId, $row['ext_body_id'], 2);

                        $uid = $row['data4'];
                        if (isset($inactiveUidMap[$uid])) {
                            $action = 'REACTIVATE';
                            $countReactivated++;
                        } else {
                            $action = 'NEW';
                            $countNew++;
                        }
                        $logBuffer[] = [
                            'ext_head_id' => $headerId,
                            'u_id' => $uid,
                            'action' => $action,
                            'old_data' => null,
                            'new_data' => SyncDataTransformer::buildLogSnapshot($row),
                            'changed_fields' => null,
                        ];
                    }
                    $this->persistence->updateHeaderStatus(
                        $headerId,
                        2,
                        'ext_head_uploaded_data',
                        count($pending)
                    );
                } else {
                    $this->persistence->updateHeaderStatus($headerId, 4, 'ext_head_uploaded_data', 0);
                }

                $this->persistence->appendChanges($logBuffer);
                $this->persistence->updateSummary(
                    $headerId,
                    $countNew,
                    $countUpdated,
                    $countDeactivated,
                    $countReactivated,
                    $triggeredBy
                );
                $this->persistence->commit();
            } catch (\Exception $exception) {
                $this->persistence->rollback();
                throw $exception;
            }
        } else {
            $this->persistence->updateHeaderStatus($headerId, 3, 'ext_head_initial_sourcedata', 0);
            $this->persistence->updateSummary(
                $headerId,
                $countNew,
                $countUpdated,
                $countDeactivated,
                $countReactivated,
                $triggeredBy
            );
            $this->persistence->commit();
        }

        return new SyncRunSummary(
            $headerId,
            $countNew,
            $countUpdated,
            $countDeactivated,
            $countReactivated,
            $this->persistence->header($headerId)
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
