<?php

namespace OneId\App\Admin;

use Throwable;

final class UserCategoryAppAccessService
{
    private const MAX_SELECTION = 500;

    public function __construct(private readonly object $operation)
    {
    }

    /** @return array<string,mixed> */
    public function apply(int $categoryId, array $requestedIds, string $adminId, string $ipAddress): array
    {
        $correlationId = bin2hex(random_bytes(8));
        if ($categoryId < 1) {
            throw new UserCategoryAppAccessException('CATEGORY_ACL_CATEGORY_INVALID', $correlationId);
        }
        $adminId = trim($adminId);
        if ($adminId === '') {
            throw new UserCategoryAppAccessException('CATEGORY_ACL_ADMIN_INVALID', $correlationId);
        }

        $desired = $this->normaliseIds($requestedIds, $correlationId);
        $started = false;
        try {
            $this->operation->beginTransaction();
            $started = true;

            $category = $this->operation->admin_get_user_category_for_acl_action($categoryId, true);
            if (!is_array($category) || $category === []) {
                throw new UserCategoryAppAccessException('CATEGORY_ACL_CATEGORY_NOT_FOUND', $correlationId);
            }
            if ((int) ($category['avail_status'] ?? 0) !== 1) {
                throw new UserCategoryAppAccessException('CATEGORY_ACL_CATEGORY_INACTIVE', $correlationId);
            }

            $existing = array_map('strval', $this->operation->admin_get_category_acl_sp_ids($categoryId, true));
            $existing = array_values(array_unique($existing));
            sort($existing, SORT_STRING);

            $toAdd = array_values(array_diff($desired, $existing));
            $toRemove = array_values(array_diff($existing, $desired));

            if ($toAdd !== []) {
                $active = array_map('strval', $this->operation->admin_get_active_service_provider_ids($toAdd));
                sort($active, SORT_STRING);
                $expected = $toAdd;
                sort($expected, SORT_STRING);
                if ($active !== $expected) {
                    throw new UserCategoryAppAccessException('CATEGORY_ACL_APP_UNAVAILABLE', $correlationId);
                }
            }

            $added = $this->operation->admin_add_category_acl_batch($categoryId, $toAdd);
            $removed = $this->operation->admin_remove_category_acl_batch($categoryId, $toRemove);
            if ($added !== count($toAdd) || $removed !== count($toRemove)) {
                throw new UserCategoryAppAccessException('CATEGORY_ACL_MUTATION_MISMATCH', $correlationId);
            }

            $detail = sprintf(
                'admin=%s action=category_acl_batch category=%d add=%s remove=%s correlation=%s',
                $adminId,
                $categoryId,
                $this->idSummary($toAdd),
                $this->idSummary($toRemove),
                $correlationId
            );
            if ($this->operation->syslog_record(28, $detail, $ipAddress) !== 1) {
                throw new UserCategoryAppAccessException('CATEGORY_ACL_AUDIT_NOT_WRITTEN', $correlationId);
            }

            $this->operation->commit();
            return [
                'status' => 1,
                'code' => 'CATEGORY_ACL_UPDATED',
                'added' => $added,
                'removed' => $removed,
                'assigned' => count($desired),
                'correlation_id' => $correlationId,
            ];
        } catch (Throwable $exception) {
            if ($started) {
                try {
                    $this->operation->rollback();
                } catch (Throwable) {
                    error_log('Category ACL rollback failed correlation_id=' . $correlationId);
                }
            }
            if ($exception instanceof UserCategoryAppAccessException) {
                throw $exception;
            }
            error_log('Category ACL batch failed correlation_id=' . $correlationId . ' exception=' . get_class($exception));
            throw new UserCategoryAppAccessException('CATEGORY_ACL_OPERATION_FAILED', $correlationId);
        }
    }

    /** @return list<string> */
    private function normaliseIds(array $values, string $correlationId): array
    {
        if (count($values) > self::MAX_SELECTION) {
            throw new UserCategoryAppAccessException('CATEGORY_ACL_SELECTION_LIMIT', $correlationId);
        }
        $ids = [];
        foreach ($values as $value) {
            if (is_string($value)) {
                $id = trim($value);
                if ($id !== '' && strlen($id) <= 20 && preg_match('/\A[A-Za-z0-9._-]+\z/', $id) === 1) {
                    $ids[$id] = $id;
                    continue;
                }
            }
            throw new UserCategoryAppAccessException('CATEGORY_ACL_APP_ID_INVALID', $correlationId);
        }
        $ids = array_values($ids);
        sort($ids, SORT_STRING);
        return $ids;
    }

    private function idSummary(array $ids): string
    {
        return $ids === [] ? 'none' : implode(',', $ids);
    }
}
