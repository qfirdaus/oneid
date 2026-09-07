<?php

declare(strict_types=1);

namespace OneId\App\Admin;

use OneId\App\Audit\AuditIdentityResolver;
use OneId\App\Auth\UserMfa\UserMfaOperationalModeResolver;
use PDO;
use Throwable;

final class UserMfaPolicyWorkflowService
{
    private const TARGETS = ['OFF','ENROLLMENT','PILOT_ENFORCED','ENFORCED','EMERGENCY_BYPASS'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $environment,
        private readonly string $runtimeCeiling,
        private readonly bool $totpRuntimeEnabled
    ) {
    }

    public function read(): array
    {
        $policy = $this->policy(false);
        $open = $this->pdo->prepare(
            "SELECT request_id,previous_mode,requested_mode,restore_mode,transition_strategy,
                    grace_until,starts_at,expires_at,request_status,change_reason,change_reference,
                    expected_policy_version,requested_at
               FROM user_mfa_policy_change_requests
              WHERE environment=:environment
                AND request_status IN ('PENDING_APPROVAL','APPROVED','ACTIVE')
              ORDER BY request_id DESC LIMIT 1"
        );
        $open->execute([':environment' => $this->environment]);
        return [
            'status' => 1,
            'code' => 'USER_MFA_WORKFLOW_LOADED',
            'data' => [
                'mode' => (string) $policy['policy_mode'],
                'configuration_version' => (int) $policy['configuration_version'],
                'runtime_ceiling' => $this->runtimeCeiling,
                'environment' => $this->environment,
                'requires_separate_approval' => $this->environment === 'production',
                'impact' => $this->impact(),
                'open_request' => $open->fetch(PDO::FETCH_ASSOC) ?: null,
            ],
        ];
    }

    public function request(array $input, string $sessionAdmin, string $ipAddress): array
    {
        $target = strtoupper(trim((string) ($input['target_mode'] ?? '')));
        $strategy = strtoupper(trim((string) ($input['transition_strategy'] ?? '')));
        $reason = trim((string) ($input['change_reason'] ?? ''));
        $reference = trim((string) ($input['change_reference'] ?? ''));
        $typed = trim((string) ($input['typed_confirmation'] ?? ''));
        $version = filter_var($input['configuration_version'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $duration = filter_var($input['duration_minutes'] ?? 60, FILTER_VALIDATE_INT);
        if (!in_array($target, self::TARGETS, true)
            || !in_array($strategy, ['GRACE','IMMEDIATE'], true)
            || $version === false || strlen($reason) < 10 || strlen($reason) > 500
            || preg_match('/\A[A-Za-z0-9._-]{8,100}\z/', $reference) !== 1
            || !hash_equals('CHANGE USER MFA TO ' . $target, $typed)
            || filter_var($ipAddress, FILTER_VALIDATE_IP) === false
            || ($target === 'EMERGENCY_BYPASS' && !in_array($duration, [30,60,120,240,480], true))
        ) {
            throw new SsoConfigurationException('USER_MFA_WORKFLOW_INPUT_INVALID', bin2hex(random_bytes(8)));
        }
        (new UserMfaOperationalModeResolver())->assertWithinRuntimeCeiling($target, $this->runtimeCeiling);
        $admin = $this->adminId($sessionAdmin);
        $correlation = bin2hex(random_bytes(16));
        $started = false;
        try {
            $this->pdo->beginTransaction();
            $started = true;
            $before = $this->policy(true);
            if ((int) $before['configuration_version'] !== (int) $version) {
                throw new SsoConfigurationException('USER_MFA_WORKFLOW_POLICY_STALE', $correlation);
            }
            if ((string) $before['policy_mode'] === $target) {
                throw new SsoConfigurationException('USER_MFA_WORKFLOW_NO_CHANGE', $correlation);
            }
            $open=$this->pdo->prepare("SELECT request_id,requested_mode,request_status FROM user_mfa_policy_change_requests WHERE environment=:environment AND request_status IN ('PENDING_APPROVAL','APPROVED','ACTIVE') FOR UPDATE");
            $open->execute([':environment'=>$this->environment]);$existing=$open->fetch(PDO::FETCH_ASSOC);
            if(is_array($existing)){
                if($existing['request_status']==='ACTIVE'&&$existing['requested_mode']!=='EMERGENCY_BYPASS'){
                    $close=$this->pdo->prepare("UPDATE user_mfa_policy_change_requests SET request_status='RESTORED',restored_at=NOW(6) WHERE request_id=:id");$close->execute([':id'=>$existing['request_id']]);
                }else{throw new SsoConfigurationException('USER_MFA_WORKFLOW_OPEN_REQUEST_EXISTS',$correlation);}
            }
            $clock = $this->pdo->query("SELECT NOW(6) AS db_now_value,DATE_ADD(NOW(6),INTERVAL 5 MINUTE) AS grace_time_value,DATE_ADD(NOW(6),INTERVAL ".(int)$duration." MINUTE) AS expiry_time_value")->fetch(PDO::FETCH_ASSOC);
            if(!is_array($clock))throw new SsoConfigurationException('USER_MFA_WORKFLOW_CLOCK_UNAVAILABLE',$correlation);
            $now = (string)$clock['db_now_value'];
            $grace = $strategy === 'GRACE' ? (string)$clock['grace_time_value'] : null;
            $expires = $target === 'EMERGENCY_BYPASS' ? (string)$clock['expiry_time_value'] : null;
            $restore = $target === 'EMERGENCY_BYPASS' ? (string) $before['policy_mode'] : null;
            $requiresApproval = $this->requiresSeparateApproval($target, (int) $duration);
            $status = $requiresApproval ? 'PENDING_APPROVAL' : 'APPROVED';
            $insert = $this->pdo->prepare(
                'INSERT INTO user_mfa_policy_change_requests(environment,previous_mode,requested_mode,restore_mode,
                    transition_strategy,grace_until,starts_at,expires_at,request_status,requested_by,
                    change_reason,change_reference,expected_policy_version,correlation_id)
                 VALUES(:environment,:previous,:target,:restore,:strategy,:grace,:starts,:expires,:status,:admin,
                    :reason,:reference,:version,:correlation)'
            );
            $insert->execute([':environment'=>$this->environment,':previous'=>$before['policy_mode'],':target'=>$target,
                ':restore'=>$restore,':strategy'=>$strategy,':grace'=>$grace,':starts'=>$now,':expires'=>$expires,
                ':status'=>$status,':admin'=>$admin,':reason'=>$reason,':reference'=>$reference,
                ':version'=>(int)$version,':correlation'=>$correlation]);
            $requestId = (int) $this->pdo->lastInsertId();
            $result = ['status'=>1,'code'=>'USER_MFA_CHANGE_PENDING_APPROVAL','request_id'=>$requestId,
                'request_status'=>$status,'correlation_id'=>$correlation];
            if (!$requiresApproval) {
                if ($this->environment !== 'production') {
                    $digest = $this->digest($requestId);
                    $this->recordApproval($requestId, 'APPROVED', $admin, 'Staging controlled approval', (int)$version, $digest, $ipAddress);
                }
                $result = $this->activate($requestId, $admin, $ipAddress, $correlation);
            }
            $this->audit($admin, 'request', $target, $reference, $correlation, $ipAddress);
            $this->pdo->commit();
            return $result;
        } catch (SsoConfigurationException $e) {
            if ($started && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        } catch (Throwable $e) {
            if ($started && $this->pdo->inTransaction()) $this->pdo->rollBack();
            $driverCode=$e instanceof \PDOException&&isset($e->errorInfo[1])?(string)$e->errorInfo[1]:(string)$e->getCode();
            error_log('User MFA workflow request failed correlation='.$correlation.' exception='.get_class($e).' driver_code='.preg_replace('/[^A-Za-z0-9._-]/','',$driverCode).' message='.preg_replace('/[\r\n\x00-\x1F\x7F]/',' ',substr($e->getMessage(),0,300)));
            throw new SsoConfigurationException('USER_MFA_WORKFLOW_FAILED', $correlation);
        }
    }

    public function decide(int $requestId, string $decision, string $reason, string $sessionAdmin, string $ipAddress): array
    {
        $decision = strtoupper(trim($decision));
        $reason = trim($reason);
        if ($requestId < 1 || !in_array($decision, ['APPROVED','REJECTED'], true)
            || strlen($reason) < 10 || strlen($reason) > 500 || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            throw new SsoConfigurationException('USER_MFA_APPROVAL_INPUT_INVALID', bin2hex(random_bytes(8)));
        }
        $admin = $this->adminId($sessionAdmin);
        $correlation = bin2hex(random_bytes(16));
        $this->pdo->beginTransaction();
        try {
            $request = $this->requestRow($requestId, true);
            if ($request['request_status'] !== 'PENDING_APPROVAL' || $request['environment'] !== $this->environment) {
                throw new SsoConfigurationException('USER_MFA_APPROVAL_STATE_INVALID', $correlation);
            }
            if ($this->environment === 'production' && hash_equals((string)$request['requested_by'], $admin)) {
                throw new SsoConfigurationException('USER_MFA_APPROVAL_SELF_REJECTED', $correlation);
            }
            $policy = $this->policy(true);
            if ((int)$policy['configuration_version'] !== (int)$request['expected_policy_version']) {
                throw new SsoConfigurationException('USER_MFA_APPROVAL_POLICY_STALE', $correlation);
            }
            $this->recordApproval($requestId, $decision, $admin, $reason, (int)$request['expected_policy_version'], $this->digest($requestId), $ipAddress);
            if ($decision === 'REJECTED') {
                $update=$this->pdo->prepare("UPDATE user_mfa_policy_change_requests SET request_status='REJECTED',approved_by=:admin,approved_at=NOW(6),rejected_at=NOW(6) WHERE request_id=:id");
                $update->execute([':admin'=>$admin,':id'=>$requestId]);
                $result=['status'=>1,'code'=>'USER_MFA_CHANGE_REJECTED','request_id'=>$requestId,'correlation_id'=>$correlation];
            } else {
                $update=$this->pdo->prepare("UPDATE user_mfa_policy_change_requests SET request_status='APPROVED',approved_by=:admin,approved_at=NOW(6) WHERE request_id=:id");
                $update->execute([':admin'=>$admin,':id'=>$requestId]);
                $result=$this->activate($requestId,$admin,$ipAddress,$correlation);
            }
            $this->audit($admin, strtolower($decision), (string)$request['requested_mode'], (string)$request['change_reference'], $correlation, $ipAddress);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($e instanceof SsoConfigurationException) throw $e;
            throw new SsoConfigurationException('USER_MFA_APPROVAL_FAILED', $correlation);
        }
    }

    private function activate(int $requestId, string $actor, string $ip, string $correlation): array
    {
        $request=$this->requestRow($requestId,true); $before=$this->policy(true);
        if (!in_array($request['request_status'], ['APPROVED'], true)
            || (int)$request['expected_policy_version'] !== (int)$before['configuration_version']) {
            throw new SsoConfigurationException('USER_MFA_ACTIVATION_STATE_INVALID',$correlation);
        }
        $target=(string)$request['requested_mode']; $next=(int)$before['configuration_version']+1;
        $totp=$target==='OFF'?0:($this->totpRuntimeEnabled?1:0);
        $update=$this->pdo->prepare('UPDATE user_login_mfa_policy SET policy_mode=:mode,email_enabled=1,totp_enabled=:totp,configuration_version=:next,readiness_reference=:reference,updated_by=:actor WHERE singleton_key=1 AND configuration_version=:version');
        $update->execute([':mode'=>$target,':totp'=>$totp,':next'=>$next,':reference'=>$request['change_reference'],':actor'=>$actor,':version'=>$before['configuration_version']]);
        if($update->rowCount()!==1)throw new SsoConfigurationException('USER_MFA_ACTIVATION_POLICY_STALE',$correlation);
        $impact=$this->impact(); $revokedT=0; $revokedC=0;
        if($request['transition_strategy']==='IMMEDIATE'){
            $revokedC=(int)$this->pdo->exec("UPDATE user_login_mfa_challenges c JOIN user_login_mfa_transactions t ON t.transaction_id=c.transaction_id SET c.revoked_at=COALESCE(c.revoked_at,NOW(6)),c.otp_hash=CASE WHEN c.factor_type='EMAIL_OTP' THEN NULL ELSE c.otp_hash END WHERE t.transaction_status IN ('PENDING','VERIFIED') AND c.consumed_at IS NULL AND c.revoked_at IS NULL");
            $revokedT=(int)$this->pdo->exec("UPDATE user_login_mfa_transactions SET transaction_status='REVOKED',revoked_at=NOW(6) WHERE transaction_status IN ('PENDING','VERIFIED')");
        }else{
            $capChallenges=$this->pdo->prepare("UPDATE user_login_mfa_challenges c JOIN user_login_mfa_transactions t ON t.transaction_id=c.transaction_id SET c.expires_at=LEAST(c.expires_at,:grace) WHERE t.transaction_status IN ('PENDING','VERIFIED') AND c.consumed_at IS NULL AND c.revoked_at IS NULL");
            $capChallenges->execute([':grace'=>$request['grace_until']]);
            $capTransactions=$this->pdo->prepare("UPDATE user_login_mfa_transactions SET expires_at=LEAST(expires_at,:grace) WHERE transaction_status IN ('PENDING','VERIFIED')");
            $capTransactions->execute([':grace'=>$request['grace_until']]);
        }
        $resulting=$before;$resulting['policy_mode']=$target;$resulting['totp_enabled']=$totp;$resulting['configuration_version']=$next;
        $history=$this->pdo->prepare('INSERT INTO user_login_mfa_policy_history(configuration_version,previous_policy,resulting_policy,changed_by,change_reason,change_reference,correlation_id) VALUES(:version,:before,:after,:actor,:reason,:reference,:correlation)');
        $history->execute([':version'=>$next,':before'=>json_encode($before,JSON_THROW_ON_ERROR),':after'=>json_encode($resulting,JSON_THROW_ON_ERROR),':actor'=>$actor,':reason'=>$request['change_reason'],':reference'=>$request['change_reference'],':correlation'=>$correlation]);
        $finish=$this->pdo->prepare("UPDATE user_mfa_policy_change_requests SET request_status='ACTIVE',applied_policy_version=:version,activated_at=NOW(6) WHERE request_id=:id");
        $finish->execute([':version'=>$next,':id'=>$requestId]);
        $run=$this->pdo->prepare("INSERT INTO user_mfa_policy_transition_runs(request_id,run_type,run_status,attempt_number,planned_transactions,planned_challenges,executed_transactions,executed_challenges,correlation_id,completed_at) VALUES(:id,'ACTIVATE','SUCCEEDED',1,:pt,:pc,:et,:ec,:correlation,NOW(6))");
        $run->execute([':id'=>$requestId,':pt'=>$impact['pending_transactions'],':pc'=>$impact['pending_challenges'],':et'=>$revokedT,':ec'=>$revokedC,':correlation'=>$correlation]);
        return ['status'=>1,'code'=>'USER_MFA_CHANGE_ACTIVATED','request_id'=>$requestId,'request_status'=>'ACTIVE','data'=>['effective_mode'=>$target,'configuration_version'=>$next,'revoked_transactions'=>$revokedT,'revoked_challenges'=>$revokedC,'grace_until'=>$request['grace_until'],'expires_at'=>$request['expires_at']],'correlation_id'=>$correlation];
    }

    private function policy(bool $lock): array { $r=$this->pdo->query('SELECT * FROM user_login_mfa_policy WHERE singleton_key=1'.($lock?' FOR UPDATE':''))->fetch(PDO::FETCH_ASSOC);if(!is_array($r))throw new SsoConfigurationException('USER_MFA_POLICY_UNAVAILABLE',bin2hex(random_bytes(8)));return $r; }
    private function requiresSeparateApproval(string $target,int $duration):bool{return $this->environment==='production'&&$target==='EMERGENCY_BYPASS'&&$duration>120;}
    private function requestRow(int $id,bool $lock): array{$s=$this->pdo->prepare('SELECT * FROM user_mfa_policy_change_requests WHERE request_id=:id'.($lock?' FOR UPDATE':''));$s->execute([':id'=>$id]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!is_array($r))throw new SsoConfigurationException('USER_MFA_REQUEST_NOT_FOUND',bin2hex(random_bytes(8)));return $r;}
    private function impact(): array{return ['pending_transactions'=>(int)$this->pdo->query("SELECT COUNT(*) FROM user_login_mfa_transactions WHERE transaction_status IN ('PENDING','VERIFIED')")->fetchColumn(),'pending_challenges'=>(int)$this->pdo->query("SELECT COUNT(*) FROM user_login_mfa_challenges c JOIN user_login_mfa_transactions t ON t.transaction_id=c.transaction_id WHERE t.transaction_status IN ('PENDING','VERIFIED') AND c.consumed_at IS NULL AND c.revoked_at IS NULL")->fetchColumn()];}
    private function adminId(string $id): string{$s=$this->pdo->prepare('SELECT u_id FROM user_tbl WHERE u_type=1 AND avail_status=1 AND (u_id=:a OR data2=:b OR data3=:c OR data4=:d)');$s->execute([':a'=>$id,':b'=>$id,':c'=>$id,':d'=>$id]);$r=$s->fetchAll(PDO::FETCH_COLUMN);if(count($r)!==1)throw new SsoConfigurationException('USER_MFA_WORKFLOW_ADMIN_INVALID',bin2hex(random_bytes(8)));return(string)$r[0];}
    private function digest(int $id): string{$r=$this->requestRow($id,false);return hash('sha256',json_encode([$r['environment'],$r['previous_mode'],$r['requested_mode'],$r['restore_mode'],$r['transition_strategy'],$r['grace_until'],$r['starts_at'],$r['expires_at'],$r['change_reason'],$r['change_reference'],$r['expected_policy_version']],JSON_THROW_ON_ERROR));}
    private function recordApproval(int$id,string$decision,string$actor,string$reason,int$version,string$digest,string$ip):void{$s=$this->pdo->prepare('INSERT INTO user_mfa_policy_change_approvals(request_id,decision,decided_by,decision_reason,expected_policy_version,request_payload_digest,correlation_id,source_ip) VALUES(:id,:decision,:actor,:reason,:version,:digest,:correlation,:ip)');$s->execute([':id'=>$id,':decision'=>$decision,':actor'=>$actor,':reason'=>$reason,':version'=>$version,':digest'=>$digest,':correlation'=>bin2hex(random_bytes(16)),':ip'=>$ip]);}
    private function audit(string$actor,string$action,string$target,string$reference,string$correlation,string$ip):void{$public=(new AuditIdentityResolver($this->pdo))->resolve($actor);$s=$this->pdo->prepare('INSERT INTO syslog(log_type,log_detail,ip_addr,datetime) VALUES(64,:detail,:ip,NOW())');$s->execute([':detail'=>"admin={$public} action=user_mfa_workflow_{$action} target={$target} reference={$reference} correlation={$correlation}",':ip'=>$ip]);}
}
