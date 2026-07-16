<?php

namespace OneId\App\Admin;

use Throwable;

final class SsoConfigurationService
{
    private const ALLOWED_TIMEOUTS = ['0.5', '1', '2', '12', '24', '48', '72', '168'];

    public function __construct(private readonly object $operation)
    {
    }

    public function read(): array
    {
        $correlationId = bin2hex(random_bytes(8));
        try {
            $stored = $this->operation->get_system_config();
            if (!is_array($stored)) {
                throw new SsoConfigurationException('SC2_CONFIG_NOT_FOUND', $correlationId);
            }

            return [
                'status' => 1,
                'code' => 'SC2_CONFIG_LOADED',
                'message' => 'Authentication and SSO token policy loaded.',
                'data' => $this->normalizeStored($stored, $correlationId),
                'correlation_id' => $correlationId,
            ];
        } catch (SsoConfigurationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            error_log('SC2 config read failed correlation_id=' . $correlationId . ' exception=' . get_class($exception));
            throw new SsoConfigurationException('SC2_CONFIG_READ_FAILED', $correlationId);
        }
    }

    /** @param array<string, mixed> $post */
    public function preview(array $post): array
    {
        $correlationId = bin2hex(random_bytes(8));
        $allowed = ['preview_configuration_update','token_timeout','sso_settings_multi_session'];
        if (array_diff(array_keys($post), $allowed) !== []) {
            throw new SsoConfigurationException('SC2_UNEXPECTED_FIELD', $correlationId);
        }
        $after = [
            'token_timeout'=>$this->timeout($post['token_timeout']??null,$correlationId),
            'multi_session'=>$this->booleanFlag($post['sso_settings_multi_session']??null,'SC2_MULTI_SESSION_INVALID',$correlationId),
        ];
        $stored = $this->operation->get_system_config();
        if (!is_array($stored)) throw new SsoConfigurationException('SC2_CONFIG_NOT_FOUND',$correlationId);
        $before = $this->normalizeStored($stored,$correlationId);
        $impact = $this->operation->preview_policy_revocation(
            $after['token_timeout'],
            (float)$after['token_timeout'] < (float)$before['token_timeout'],
            $before['multi_session']===1 && $after['multi_session']===0
        );
        return ['status'=>1,'code'=>'SC5_PREVIEW_CREATED','before'=>$before,'after'=>$after,'impact'=>$impact,'grace_minutes'=>15,'correlation_id'=>$correlationId];
    }

    /** @param array<string, mixed> $post */
    public function update(array $post, string $adminId, string $ipAddress, array $approvedImpact = []): array
    {
        $correlationId = bin2hex(random_bytes(8));
        $allowedFields = [
            'update_configuration',
            'token_timeout',
            'sso_settings_multi_session',
            'policy_preview_id',
        ];
        if (array_diff(array_keys($post), $allowedFields) !== []) {
            throw new SsoConfigurationException('SC2_UNEXPECTED_FIELD', $correlationId);
        }

        $timeout = $this->timeout($post['token_timeout'] ?? null, $correlationId);
        $multiSession = $this->booleanFlag(
            $post['sso_settings_multi_session'] ?? null,
            'SC2_MULTI_SESSION_INVALID',
            $correlationId
        );
        $adminId = trim($adminId);
        if ($adminId === '' || strlen($adminId) > 20 || preg_match('/^[A-Za-z0-9._@-]+$/', $adminId) !== 1) {
            throw new SsoConfigurationException('SC3_ADMIN_ID_INVALID', $correlationId);
        }
        $ipAddress = trim($ipAddress);
        if ($ipAddress === '' || strlen($ipAddress) > 50 || preg_match('/[\x00-\x1F\x7F]/', $ipAddress) === 1) {
            throw new SsoConfigurationException('SC3_IP_ADDRESS_INVALID', $correlationId);
        }

        $started = false;
        try {
            $this->operation->beginTransaction();
            $started = true;
            $stored = $this->operation->get_system_config_for_update();
            if (!is_array($stored) || !isset($stored['id'])) {
                throw new SsoConfigurationException('SC3_CONFIG_NOT_FOUND', $correlationId);
            }
            $before = $this->normalizeStored($stored, $correlationId);
            $after = [
                'token_timeout' => $timeout,
                'multi_session' => $multiSession,
            ];
            $timeoutReduced=(float)$after['token_timeout']<(float)$before['token_timeout'];
            $disableMultiple=$before['multi_session']===1&&$after['multi_session']===0;
            $liveImpact=$this->operation->preview_policy_revocation($after['token_timeout'],$timeoutReduced,$disableMultiple);
            if((int)($approvedImpact['affected_tokens']??-1)!==$liveImpact['affected_tokens']||(int)($approvedImpact['affected_users']??-1)!==$liveImpact['affected_users']){
                throw new SsoConfigurationException('SC5_PREVIEW_STALE',$correlationId);
            }
            if ($before === $after) {
                $this->operation->commit();
                $started = false;
                return [
                    'status' => 1,
                    'code' => 'SC2_CONFIG_UNCHANGED',
                    'message' => 'The saved policy already matches the submitted values.',
                    'changed' => false,
                    'data' => $after,
                    'correlation_id' => $correlationId,
                ];
            }

            $affected = $this->operation->update_configuration_by_id(
                (int) $stored['id'],
                $timeout,
                $multiSession
            );
            if ($affected !== 1) {
                throw new SsoConfigurationException('SC3_CONFIG_UPDATE_NOT_APPLIED', $correlationId);
            }

            $auditDetail = sprintf(
                'admin=%s action=update_sso_config before_token_timeout=%s after_token_timeout=%s before_multi_session=%d after_multi_session=%d correlation=%s',
                $adminId,
                $before['token_timeout'],
                $after['token_timeout'],
                $before['multi_session'],
                $after['multi_session'],
                $correlationId
            );
            if ($this->operation->syslog_record(19, $auditDetail, $ipAddress) !== 1) {
                throw new SsoConfigurationException('SC3_AUDIT_NOT_WRITTEN', $correlationId);
            }
            $scheduled=0;$revokeAt=null;
            if($liveImpact['affected_tokens']>0){
                $revokeAt=date('Y-m-d H:i:s',time()+900);
                $scheduled=$this->operation->schedule_policy_revocation($after['token_timeout'],$timeoutReduced,$disableMultiple,$revokeAt,$correlationId);
                if($scheduled!==$liveImpact['affected_tokens'])throw new SsoConfigurationException('SC5_SCHEDULE_COUNT_MISMATCH',$correlationId);
                $scheduleDetail=sprintf('admin=%s action=schedule_sso_policy_revocation tokens=%d users=%d revoke_at=%s correlation=%s',$adminId,$scheduled,$liveImpact['affected_users'],$revokeAt,$correlationId);
                if($this->operation->syslog_record(30,$scheduleDetail,$ipAddress)!==1)throw new SsoConfigurationException('SC5_SCHEDULE_AUDIT_FAILED',$correlationId);
            }
            $this->operation->commit();
            $started = false;

            return [
                'status' => 1,
                'code' => 'SC2_CONFIG_UPDATED',
                'message' => 'Authentication and SSO token policy updated.',
                'changed' => true,
                'data' => $after,
                'enforcement'=>['scheduled_tokens'=>$scheduled,'affected_users'=>$liveImpact['affected_users'],'revoke_at'=>$revokeAt,'grace_minutes'=>15],
                'correlation_id' => $correlationId,
            ];
        } catch (SsoConfigurationException $exception) {
            if ($started) {
                try {
                    $this->operation->rollback();
                } catch (Throwable $ignored) {
                    error_log('SC3 config rollback failed correlation_id=' . $correlationId);
                }
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($started) {
                try {
                    $this->operation->rollback();
                } catch (Throwable $ignored) {
                    error_log('SC3 config rollback failed correlation_id=' . $correlationId);
                }
            }
            error_log('SC2 config update failed correlation_id=' . $correlationId . ' exception=' . get_class($exception));
            throw new SsoConfigurationException('SC2_CONFIG_UPDATE_FAILED', $correlationId);
        }
    }

    /** @param array<string, mixed> $stored */
    private function normalizeStored(array $stored, string $correlationId): array
    {
        return [
            'token_timeout' => $this->timeout($stored['token_timeout'] ?? null, $correlationId),
            'multi_session' => $this->booleanFlag(
                $stored['multi_session'] ?? null,
                'SC2_STORED_MULTI_SESSION_INVALID',
                $correlationId
            ),
        ];
    }

    private function timeout(mixed $value, string $correlationId): string
    {
        if (!is_scalar($value)) {
            throw new SsoConfigurationException('SC2_TOKEN_TIMEOUT_INVALID', $correlationId);
        }
        $normalized = trim((string) $value);
        if (!in_array($normalized, self::ALLOWED_TIMEOUTS, true)) {
            throw new SsoConfigurationException('SC2_TOKEN_TIMEOUT_INVALID', $correlationId);
        }
        return $normalized;
    }

    private function booleanFlag(mixed $value, string $reason, string $correlationId): int
    {
        if (!is_scalar($value)) {
            throw new SsoConfigurationException($reason, $correlationId);
        }
        $normalized = trim((string) $value);
        if ($normalized !== '0' && $normalized !== '1') {
            throw new SsoConfigurationException($reason, $correlationId);
        }
        return (int) $normalized;
    }
}
