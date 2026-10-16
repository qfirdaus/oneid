<?php

namespace OneId\App\User;

use Throwable;

final class UserAppAccessBatchService
{
    private const MAX_SELECTION = 500;

    public function __construct(private readonly object $operation)
    {
    }

    /** @return array<string,mixed> */
    public function state(string $userId): array
    {
        $correlationId = bin2hex(random_bytes(8));
        $userId = $this->validId($userId, 'USER_ACL_USER_ID_INVALID', $correlationId);
        $user = $this->operation->admin_get_user_for_profile_action($userId, false);
        if (!is_array($user) || $user === []) {
            throw new UserAppAccessBatchException('USER_ACL_USER_NOT_FOUND', $correlationId);
        }
        $apps = $this->operation->admin_get_user_app_access_catalog($userId, (int) $user['u_category']);
        return [
            'status' => 1,
            'code' => 'USER_ACL_STATE_READY',
            'revision' => $this->revision((int) $user['u_category'], $apps),
            'apps' => $apps,
            'correlation_id' => $correlationId,
        ];
    }

    /** @return array<string,mixed> */
    public function apply(string $userId, array $desiredAllowedIds, string $expectedRevision, string $adminId, string $ipAddress, string $currentToken = ''): array
    {
        $correlationId = bin2hex(random_bytes(8));
        $userId = $this->validId($userId, 'USER_ACL_USER_ID_INVALID', $correlationId);
        $adminId = $this->validId($adminId, 'USER_ACL_ADMIN_ID_INVALID', $correlationId);
        $desired = $this->normaliseIds($desiredAllowedIds, $correlationId);
        if (preg_match('/\A[a-f0-9]{64}\z/', $expectedRevision) !== 1) {
            throw new UserAppAccessBatchException('USER_ACL_REVISION_INVALID', $correlationId);
        }

        $started = false;
        try {
            $this->operation->beginTransaction();
            $started = true;
            $user = $this->operation->admin_get_user_for_profile_action($userId, true);
            if (!is_array($user) || $user === []) throw new UserAppAccessBatchException('USER_ACL_USER_NOT_FOUND', $correlationId);
            if ((int) ($user['avail_status'] ?? 0) !== 1) throw new UserAppAccessBatchException('USER_ACL_USER_INACTIVE', $correlationId);

            $categoryId = (int) $user['u_category'];
            $this->operation->admin_get_user_direct_acl_ids($userId, true);
            $this->operation->admin_get_user_blacklist_rows($userId, true);
            $this->operation->admin_get_category_acl_sp_ids($categoryId, true);
            $apps = $this->operation->admin_get_user_app_access_catalog($userId, $categoryId);
            if (!hash_equals($this->revision($categoryId, $apps), $expectedRevision)) {
                throw new UserAppAccessBatchException('USER_ACL_STATE_CHANGED', $correlationId);
            }

            $byId = [];
            foreach ($apps as $app) $byId[(string) $app['sp_id']] = $app;
            foreach ($desired as $spId) {
                if (!isset($byId[$spId]) || (int) ($byId[$spId]['avail_status'] ?? 0) !== 1) {
                    throw new UserAppAccessBatchException('USER_ACL_APP_UNAVAILABLE', $correlationId);
                }
            }

            $desiredMap = array_fill_keys($desired, true);
            $addDirect = $removeDirect = $addDeny = $removeDeny = [];
            foreach ($apps as $app) {
                $spId = (string) $app['sp_id'];
                $category = (int) $app['category_allow'] === 1;
                $direct = (int) $app['direct_allow'] === 1;
                $denied = (int) $app['denied'] === 1;
                $wantAllowed = isset($desiredMap[$spId]);
                if ($wantAllowed) {
                    if ($denied) $removeDeny[] = $spId;
                    if (!$category && !$direct) $addDirect[] = $spId;
                } else {
                    if ($category && !$denied) $addDeny[] = $spId;
                    if (!$category && $direct) $removeDirect[] = $spId;
                }
            }

            $counts = [
                'direct_added' => $this->operation->admin_add_user_direct_acl_batch($userId, $addDirect),
                'direct_removed' => $this->operation->admin_remove_user_direct_acl_batch($userId, $removeDirect),
                'denies_added' => $this->operation->admin_add_user_blacklist_batch($userId, $addDeny),
                'denies_removed' => $this->operation->admin_remove_user_blacklist_batch($userId, $removeDeny),
            ];
            $expectedCounts = [count($addDirect), count($removeDirect), count($addDeny), count($removeDeny)];
            if (array_values($counts) !== $expectedCounts) throw new UserAppAccessBatchException('USER_ACL_MUTATION_MISMATCH', $correlationId);

            $changed = array_sum($counts);
            $preserveCurrentSession = $changed > 0
                && hash_equals($adminId, $userId)
                && trim($currentToken) !== '';
            $sessionsRevoked = 0;
            if ($changed > 0) {
                $sessionsRevoked = $preserveCurrentSession
                    ? $this->operation->update_other_token_status($userId, $currentToken, 0, 'SECURITY_ACTION')
                    : $this->operation->update_whole_token_status($userId, 0, 'SECURITY_ACTION');
            }
            $detail = sprintf(
                'admin=%s action=user_acl_batch user=%s direct_add=%s direct_remove=%s deny_add=%s deny_remove=%s sessions_revoked=%d current_session_preserved=%d correlation=%s',
                $adminId, $userId, $this->idSummary($addDirect), $this->idSummary($removeDirect),
                $this->idSummary($addDeny), $this->idSummary($removeDeny), $sessionsRevoked,
                $preserveCurrentSession ? 1 : 0, $correlationId
            );
            if ($this->operation->syslog_record(28, $detail, $ipAddress) !== 1) {
                throw new UserAppAccessBatchException('USER_ACL_AUDIT_NOT_WRITTEN', $correlationId);
            }
            $this->operation->commit();
            return ['status'=>1,'code'=>$changed > 0 ? 'USER_ACL_UPDATED' : 'USER_ACL_UNCHANGED','changed'=>$changed] + $counts + [
                'sessions_revoked'=>$sessionsRevoked,
                'current_session_preserved'=>$preserveCurrentSession,
                'correlation_id'=>$correlationId,
            ];
        } catch (Throwable $exception) {
            if ($started) {
                try { $this->operation->rollback(); } catch (Throwable) { error_log('User ACL batch rollback failed correlation_id='.$correlationId); }
            }
            if ($exception instanceof UserAppAccessBatchException) throw $exception;
            error_log('User ACL batch failed correlation_id='.$correlationId.' exception='.get_class($exception));
            throw new UserAppAccessBatchException('USER_ACL_OPERATION_FAILED', $correlationId);
        }
    }

    private function validId(string $value, string $reason, string $correlationId): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 64 || preg_match('/\A[A-Za-z0-9._@-]+\z/', $value) !== 1) throw new UserAppAccessBatchException($reason, $correlationId);
        return $value;
    }

    /** @return list<string> */
    private function normaliseIds(array $values, string $correlationId): array
    {
        if (count($values) > self::MAX_SELECTION) throw new UserAppAccessBatchException('USER_ACL_SELECTION_LIMIT', $correlationId);
        $ids = [];
        foreach ($values as $value) {
            if (!is_string($value)) throw new UserAppAccessBatchException('USER_ACL_APP_ID_INVALID', $correlationId);
            $id = $this->validId($value, 'USER_ACL_APP_ID_INVALID', $correlationId);
            $ids[$id] = $id;
        }
        $ids = array_values($ids); sort($ids, SORT_STRING); return $ids;
    }

    private function revision(int $categoryId, array $apps): string
    {
        $state = [];
        foreach ($apps as $app) $state[] = implode(':', [(string)$app['sp_id'],(int)$app['avail_status'],(int)$app['category_allow'],(int)$app['direct_allow'],(int)$app['denied']]);
        sort($state, SORT_STRING);
        return hash('sha256', $categoryId.'|'.implode('|', $state));
    }

    private function idSummary(array $ids): string { return $ids === [] ? 'none' : implode(',', $ids); }
}
