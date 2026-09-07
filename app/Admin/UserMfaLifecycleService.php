<?php
declare(strict_types=1);
namespace OneId\App\Admin;
use PDO;use Throwable;

final class UserMfaLifecycleService
{
    private readonly ?\Closure $notification;
    private readonly string $environment;
    public function __construct(private readonly PDO $pdo,?callable $notification=null,?string $environment=null){$this->notification=$notification===null?null:\Closure::fromCallable($notification);$environment=strtolower(trim((string)($environment??(function_exists('oneid_config')?\oneid_config('ONEID_ENVIRONMENT',''):''))));$this->environment=in_array($environment,['local','staging','production'],true)?$environment:'';}

    /** @return array<string,mixed>|null */
    public function processNext(string $environment):?array
    {
        $this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare("SELECT * FROM user_mfa_policy_change_requests WHERE environment=:environment AND request_status='ACTIVE' AND ((requested_mode='EMERGENCY_BYPASS' AND expires_at<=NOW(6)) OR (transition_strategy='GRACE' AND grace_until<=NOW(6) AND NOT EXISTS(SELECT 1 FROM user_mfa_policy_transition_runs r WHERE r.request_id=user_mfa_policy_change_requests.request_id AND r.run_type='GRACE_REVOKE' AND r.run_status IN('SUCCEEDED','NOOP')))) ORDER BY COALESCE(expires_at,grace_until),request_id LIMIT 1 FOR UPDATE SKIP LOCKED");
            $s->execute([':environment'=>$environment]);$request=$s->fetch(PDO::FETCH_ASSOC);
            if(!is_array($request)){$this->pdo->commit();return null;}
            $result=$request['requested_mode']==='EMERGENCY_BYPASS'?$this->restoreLocked($request,null,'AUTO_RESTORE','Bypass expired automatically','127.0.0.1'):$this->graceLocked($request);
            $this->pdo->commit();return $result;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function queueDueWarnings(string $environment):int
    {
        if($this->notification===null)return 0;$s=$this->pdo->prepare("SELECT request_id,requested_by,expires_at,change_reference,TIMESTAMPDIFF(MINUTE,NOW(6),expires_at) minutes_left FROM user_mfa_policy_change_requests WHERE environment=:environment AND requested_mode='EMERGENCY_BYPASS' AND request_status='ACTIVE' AND expires_at>NOW(6) AND expires_at<=DATE_ADD(NOW(6),INTERVAL 30 MINUTE)");$s->execute([':environment'=>$environment]);$queued=0;foreach($s->fetchAll(PDO::FETCH_ASSOC)as$r){$threshold=(int)$r['minutes_left']<=10?10:30;$id=($this->notification)('SECURITY_POLICY_CHANGED',(string)$r['requested_by'],bin2hex(random_bytes(16)),'BYPASS_WARNING_'.$threshold.'|'.$r['request_id'],['Policy'=>'Global User MFA emergency bypass','Expires at'=>(string)$r['expires_at'],'Reference'=>(string)$r['change_reference']]);if($id!==null)$queued++;}return$queued;
    }

    /** @return array<string,mixed> */
    public function manualRestore(int $requestId,string $actor,string $reason,string $reference,string $typed,string $ip):array
    {
        if($requestId<1||strlen(trim($reason))<10||strlen(trim($reason))>500||preg_match('/\A[A-Za-z0-9._-]{8,100}\z/',trim($reference))!==1||!hash_equals('RESTORE USER MFA NOW',trim($typed))||filter_var($ip,FILTER_VALIDATE_IP)===false)throw new SsoConfigurationException('USER_MFA_RESTORE_INPUT_INVALID',bin2hex(random_bytes(8)));
        $actor=$this->adminId($actor);$this->pdo->beginTransaction();
        try{if($this->environment==='')throw new SsoConfigurationException('USER_MFA_RESTORE_ENVIRONMENT_INVALID',bin2hex(random_bytes(8)));$s=$this->pdo->prepare("SELECT * FROM user_mfa_policy_change_requests WHERE request_id=:id AND environment=:environment FOR UPDATE");$s->execute([':id'=>$requestId,':environment'=>$this->environment]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!is_array($r)||$r['requested_mode']!=='EMERGENCY_BYPASS'||$r['request_status']!=='ACTIVE')throw new SsoConfigurationException('USER_MFA_RESTORE_STATE_INVALID',bin2hex(random_bytes(8)));$result=$this->restoreLocked($r,$actor,'MANUAL_RESTORE',$reason,$ip,$reference);$this->pdo->commit();return$result;}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();if($e instanceof SsoConfigurationException)throw$e;throw new SsoConfigurationException('USER_MFA_RESTORE_FAILED',bin2hex(random_bytes(8)));}
    }

    private function graceLocked(array$r):array
    {
        $correlation=bin2hex(random_bytes(16));$plannedT=$this->countTransactions();$plannedC=$this->countChallenges();
        $c=(int)$this->pdo->exec("UPDATE user_login_mfa_challenges c JOIN user_login_mfa_transactions t ON t.transaction_id=c.transaction_id SET c.revoked_at=COALESCE(c.revoked_at,NOW(6)),c.otp_hash=CASE WHEN c.factor_type='EMAIL_OTP' THEN NULL ELSE c.otp_hash END WHERE t.transaction_status IN('PENDING','VERIFIED') AND c.consumed_at IS NULL AND c.revoked_at IS NULL AND c.expires_at<=NOW(6)");
        $t=(int)$this->pdo->exec("UPDATE user_login_mfa_transactions SET transaction_status='REVOKED',revoked_at=NOW(6) WHERE transaction_status IN('PENDING','VERIFIED') AND expires_at<=NOW(6)");
        $this->run((int)$r['request_id'],'GRACE_REVOKE',$plannedT||$plannedC?'SUCCEEDED':'NOOP',$plannedT,$plannedC,$t,$c,$correlation);
        return['code'=>'USER_MFA_GRACE_CLEANED','request_id'=>(int)$r['request_id'],'transactions'=>$t,'challenges'=>$c,'correlation_id'=>$correlation];
    }

    private function restoreLocked(array$r,?string$actor,string$type,string$reason,string$ip,?string$reference=null):array
    {
        $correlation=bin2hex(random_bytes(16));$p=$this->pdo->query('SELECT * FROM user_login_mfa_policy WHERE singleton_key=1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
        if(!is_array($p)||$p['policy_mode']!=='EMERGENCY_BYPASS'||(int)$p['configuration_version']!==(int)$r['applied_policy_version'])throw new SsoConfigurationException('USER_MFA_RESTORE_POLICY_STALE',$correlation);
        $restore=(string)$r['restore_mode'];$next=(int)$p['configuration_version']+1;$actor=$actor??(string)$r['requested_by'];$reference=$reference??((string)$r['change_reference'].'-AUTO-RESTORE');
        $u=$this->pdo->prepare('UPDATE user_login_mfa_policy SET policy_mode=:mode,configuration_version=:next,readiness_reference=:reference,updated_by=:actor WHERE singleton_key=1 AND configuration_version=:version');$u->execute([':mode'=>$restore,':next'=>$next,':reference'=>$reference,':actor'=>$actor,':version'=>$p['configuration_version']]);if($u->rowCount()!==1)throw new SsoConfigurationException('USER_MFA_RESTORE_POLICY_STALE',$correlation);
        $after=$p;$after['policy_mode']=$restore;$after['configuration_version']=$next;$h=$this->pdo->prepare('INSERT INTO user_login_mfa_policy_history(configuration_version,previous_policy,resulting_policy,changed_by,change_reason,change_reference,correlation_id) VALUES(:version,:before,:after,:actor,:reason,:reference,:correlation)');$h->execute([':version'=>$next,':before'=>json_encode($p,JSON_THROW_ON_ERROR),':after'=>json_encode($after,JSON_THROW_ON_ERROR),':actor'=>$actor,':reason'=>$reason,':reference'=>$reference,':correlation'=>$correlation]);
        $q=$this->pdo->prepare("UPDATE user_mfa_policy_change_requests SET request_status='RESTORED',restored_at=NOW(6) WHERE request_id=:id AND request_status='ACTIVE'");$q->execute([':id'=>$r['request_id']]);$this->run((int)$r['request_id'],$type,'SUCCEEDED',0,0,0,0,$correlation);
        $a=$this->pdo->prepare('INSERT INTO syslog(log_type,log_detail,ip_addr,datetime) VALUES(64,:detail,:ip,NOW())');$a->execute([':detail'=>"action=user_mfa_".strtolower($type)." request=".$r['request_id']." restore={$restore} reference={$reference} correlation={$correlation}",':ip'=>$ip]);
        if($this->notification!==null)($this->notification)('SECURITY_POLICY_CHANGED',$actor,$correlation,$type.'|'.$r['request_id'],['Policy'=>'Global User MFA','After'=>$restore,'Reference'=>$reference]);
        return['status'=>1,'code'=>'USER_MFA_BYPASS_RESTORED','request_id'=>(int)$r['request_id'],'data'=>['effective_mode'=>$restore,'configuration_version'=>$next],'correlation_id'=>$correlation];
    }
    private function run(int$id,string$type,string$status,int$pt,int$pc,int$et,int$ec,string$c):void{$n=$this->pdo->prepare('SELECT COALESCE(MAX(attempt_number),0)+1 FROM user_mfa_policy_transition_runs WHERE request_id=:id AND run_type=:type');$n->execute([':id'=>$id,':type'=>$type]);$attempt=(int)$n->fetchColumn();if($attempt<1||$attempt>100)throw new SsoConfigurationException('USER_MFA_WORKER_ATTEMPT_LIMIT',$c);$s=$this->pdo->prepare('INSERT INTO user_mfa_policy_transition_runs(request_id,run_type,run_status,attempt_number,planned_transactions,planned_challenges,executed_transactions,executed_challenges,correlation_id,completed_at) VALUES(:id,:type,:status,:attempt,:pt,:pc,:et,:ec,:correlation,NOW(6))');$s->execute([':id'=>$id,':type'=>$type,':status'=>$status,':attempt'=>$attempt,':pt'=>$pt,':pc'=>$pc,':et'=>$et,':ec'=>$ec,':correlation'=>$c]);}
    private function countTransactions():int{return(int)$this->pdo->query("SELECT COUNT(*) FROM user_login_mfa_transactions WHERE transaction_status IN('PENDING','VERIFIED') AND expires_at<=NOW(6)")->fetchColumn();}private function countChallenges():int{return(int)$this->pdo->query("SELECT COUNT(*) FROM user_login_mfa_challenges c JOIN user_login_mfa_transactions t ON t.transaction_id=c.transaction_id WHERE t.transaction_status IN('PENDING','VERIFIED') AND c.consumed_at IS NULL AND c.revoked_at IS NULL AND c.expires_at<=NOW(6)")->fetchColumn();}
    private function adminId(string$id):string{$s=$this->pdo->prepare('SELECT u_id FROM user_tbl WHERE u_type=1 AND avail_status=1 AND (u_id=:a OR data2=:b OR data3=:c OR data4=:d)');$s->execute([':a'=>$id,':b'=>$id,':c'=>$id,':d'=>$id]);$r=$s->fetchAll(PDO::FETCH_COLUMN);if(count($r)!==1)throw new SsoConfigurationException('USER_MFA_RESTORE_ADMIN_INVALID',bin2hex(random_bytes(8)));return(string)$r[0];}
}
