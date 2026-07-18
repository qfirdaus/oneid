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
                'last_changed' => $this->operation->configuration_history_latest_success(),
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
        $allowed = ['preview_configuration_update','token_timeout','sso_settings_multi_session','change_reason'];
        if (array_diff(array_keys($post), $allowed) !== []) {
            throw new SsoConfigurationException('SC2_UNEXPECTED_FIELD', $correlationId);
        }
        $after = [
            'token_timeout'=>$this->timeout($post['token_timeout']??null,$correlationId),
            'multi_session'=>$this->booleanFlag($post['sso_settings_multi_session']??null,'SC2_MULTI_SESSION_INVALID',$correlationId),
        ];
        $changeReason=$this->changeReason($post['change_reason']??null,$correlationId);
        $stored = $this->operation->get_system_config();
        if (!is_array($stored)) throw new SsoConfigurationException('SC2_CONFIG_NOT_FOUND',$correlationId);
        $before = $this->normalizeStored($stored,$correlationId);
        $impact = $this->operation->preview_policy_revocation(
            $after['token_timeout'],
            (float)$after['token_timeout'] < (float)$before['token_timeout'],
            $before['multi_session']===1 && $after['multi_session']===0
        );
        return ['status'=>1,'code'=>'SC5_PREVIEW_CREATED','before'=>$before,'after'=>$after,'configuration_version'=>(int)$before['configuration_version'],'change_reason'=>$changeReason,'impact'=>$impact,'grace_minutes'=>15,'correlation_id'=>$correlationId];
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
            'configuration_version',
            'change_reason',
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
        $expectedVersion=filter_var($post['configuration_version']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($expectedVersion===false)throw new SsoConfigurationException('SC3_CONFIGURATION_VERSION_INVALID',$correlationId);
        $changeReason=$this->changeReason($post['change_reason']??null,$correlationId);
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
            if((int)$before['configuration_version']!==(int)$expectedVersion)throw new SsoConfigurationException('SC3_CONFIGURATION_STALE',$correlationId);
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
            $beforePolicy=['token_timeout'=>$before['token_timeout'],'multi_session'=>$before['multi_session']];
            if ($beforePolicy === $after) {
                $this->operation->commit();
                $started = false;
                return [
                    'status' => 1,
                    'code' => 'SC2_CONFIG_UNCHANGED',
                    'message' => 'The saved policy already matches the submitted values.',
                    'changed' => false,
                    'data' => $before,
                    'correlation_id' => $correlationId,
                ];
            }

            $affected = $this->operation->update_configuration_by_id(
                (int) $stored['id'],
                $timeout,
                $multiSession
                ,(int)$expectedVersion
            );
            if ($affected !== 1) {
                throw new SsoConfigurationException('SC3_CONFIG_UPDATE_NOT_APPLIED', $correlationId);
            }

            $auditDetail = sprintf(
                'admin=%s action=update_sso_config before_token_timeout=%s after_token_timeout=%s before_multi_session=%d after_multi_session=%d change_reason=%s correlation=%s',
                $adminId,
                $before['token_timeout'],
                $after['token_timeout'],
                $before['multi_session'],
                $after['multi_session'],
                $changeReason,
                $correlationId
            );
            if ($this->operation->syslog_record(19, $auditDetail, $ipAddress) !== 1) {
                throw new SsoConfigurationException('SC3_AUDIT_NOT_WRITTEN', $correlationId);
            }
            $newVersion=(int)$expectedVersion+1;
            if($this->operation->configuration_history_record(['version_before'=>(int)$expectedVersion,'version_after'=>$newVersion,'actor_id'=>$adminId,'ip_address'=>$ipAddress,'action_name'=>'UPDATE_SSO_CONFIGURATION','outcome'=>'SUCCESS','reason_code'=>'SC3_CONFIG_UPDATED','change_reason'=>$changeReason,'before'=>$beforePolicy,'after'=>$after,'correlation_id'=>$correlationId])!==1)throw new SsoConfigurationException('SC3_HISTORY_NOT_WRITTEN',$correlationId);
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
                'data' => $after+['configuration_version'=>$newVersion],
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
            'configuration_version' => max(1,(int)($stored['configuration_version']??0)),
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

    public function recordRejection(string $reasonCode,string $adminId,string $ipAddress,string $correlationId,?string $changeReason=null): void
    {
        $allowed=['SC2_UNEXPECTED_FIELD','SC2_TOKEN_TIMEOUT_INVALID','SC2_MULTI_SESSION_INVALID','SC3_CHANGE_REASON_INVALID','SC3_CONFIGURATION_VERSION_INVALID','SC3_CONFIGURATION_STALE','SC3_CONFIG_NOT_FOUND','SC3_CONFIG_UPDATE_NOT_APPLIED','SC3_AUDIT_NOT_WRITTEN','SC3_HISTORY_NOT_WRITTEN','SC5_PREVIEW_STALE','SC5_PREVIEW_INVALID','SC5_SCHEDULE_COUNT_MISMATCH','SC5_SCHEDULE_AUDIT_FAILED'];
        $safeReason=in_array($reasonCode,$allowed,true)?$reasonCode:'SC3_UPDATE_REJECTED';
        $safeChangeReason=is_string($changeReason)&&strlen($changeReason)>=10&&strlen($changeReason)<=500&&preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$changeReason)!==1?$changeReason:null;
        try{$this->operation->configuration_history_record(['version_before'=>null,'version_after'=>null,'actor_id'=>substr($adminId!==''?$adminId:'unknown',0,20),'ip_address'=>substr($ipAddress!==''?$ipAddress:'unknown',0,50),'action_name'=>'UPDATE_SSO_CONFIGURATION','outcome'=>'REJECTED','reason_code'=>$safeReason,'change_reason'=>$safeChangeReason,'correlation_id'=>$correlationId]);}catch(Throwable $exception){error_log('SC3 rejected history failed correlation_id='.$correlationId);}
    }

    public function history(int $page,int $pageSize): array
    {
        $page=max(1,$page);$pageSize=in_array($pageSize,[10,25,50],true)?$pageSize:10;
        $result=$this->operation->configuration_history_list($page,$pageSize);$rows=[];
        foreach($result['rows']??[] as$row){$rows[]=['id'=>(int)$row['history_id'],'version_before'=>$row['configuration_version_before']===null?null:(int)$row['configuration_version_before'],'version_after'=>$row['configuration_version_after']===null?null:(int)$row['configuration_version_after'],'actor'=>(string)$row['actor_id'],'action'=>(string)$row['action_name'],'outcome'=>(string)$row['outcome'],'reason_code'=>(string)$row['reason_code'],'change_reason'=>$row['change_reason'],'before'=>$row['before_json']?json_decode($row['before_json'],true):null,'after'=>$row['after_json']?json_decode($row['after_json'],true):null,'correlation_id'=>(string)$row['correlation_id'],'created_at'=>(string)$row['created_at']];}
        return ['status'=>1,'code'=>'SC3_HISTORY_LOADED','data'=>$rows,'meta'=>['page'=>$page,'page_size'=>$pageSize,'total'=>(int)($result['total']??0),'total_pages'=>max(1,(int)ceil(((int)($result['total']??0))/$pageSize))]];
    }

    private function changeReason(mixed $value,string $correlationId): string
    {
        if(!is_scalar($value))throw new SsoConfigurationException('SC3_CHANGE_REASON_INVALID',$correlationId);$reason=trim((string)$value);
        if(strlen($reason)<10||strlen($reason)>500||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$reason)===1)throw new SsoConfigurationException('SC3_CHANGE_REASON_INVALID',$correlationId);
        return $reason;
    }
}
