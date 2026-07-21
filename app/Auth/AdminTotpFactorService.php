<?php

declare(strict_types=1);

namespace OneId\App\Auth;

use Throwable;

final class AdminTotpFactorService
{
    /** @var callable(): int */
    private $clock;

    public function __construct(private readonly object $operation,private readonly TotpSecretCipher $cipher,?callable $clock=null)
    {$this->clock=$clock??static fn():int=>time();}

    public function enroll(string $adminId,string $currentPassword,string $sessionId,string $userAgent,string $ipAddress,string $label='Microsoft Authenticator'):array
    {
        $cid=bin2hex(random_bytes(8));$admin=$this->id($adminId,$cid);$session=$this->binding($sessionId,$cid);$browser=hash('sha256',substr($userAgent,0,1000));$ip=$this->ip($ipAddress,$cid);$label=trim($label);
        if($label===''||strlen($label)>100)throw new AdminStepUpException('TOTP_LABEL_INVALID',$cid);
        $secret=Totp::generateSecret();$encrypted=$this->cipher->encrypt($secret);$started=false;
        try{$this->operation->beginTransaction();$started=true;$context=$this->operation->admin_mfa_enrollment_context_for_update($admin);
            if(!is_array($context)||(int)($context['u_type']??0)!==1||(int)($context['avail_status']??0)!==1)throw new AdminStepUpException('TOTP_ADMIN_NOT_ELIGIBLE',$cid);
            if(!$this->password($currentPassword,(string)($context['u_password']??'')))throw new AdminStepUpException('TOTP_CURRENT_PASSWORD_INVALID',$cid);
            $this->operation->admin_mfa_revoke_pending_factors($admin);
            $factorId=$this->operation->admin_mfa_create_pending_factor(['admin_user_id'=>$admin,'encrypted_secret'=>$encrypted['ciphertext'],'secret_nonce'=>$encrypted['nonce'],'key_version'=>$encrypted['key_version'],'device_label'=>$label,'created_by'=>$admin,'correlation_id'=>$cid,'enrollment_session_hash'=>$session,'enrollment_browser_digest'=>$browser]);
            if(!is_int($factorId)||$factorId<1)throw new AdminStepUpException('TOTP_ENROLLMENT_CREATE_FAILED',$cid);
            $this->audit(44,$admin,'enrolled_pending',$cid,$ip);$this->operation->commit();$started=false;
            return['status'=>1,'code'=>'TOTP_ENROLLMENT_PENDING','factor_id'=>$factorId,'secret'=>$secret,'provisioning_uri'=>Totp::provisioningUri('OneID@UPNM',$admin,$secret),'correlation_id'=>$cid];
        }catch(AdminStepUpException $e){if($started)$this->operation->rollback();throw$e;}catch(Throwable $e){if($started)$this->operation->rollback();throw new AdminStepUpException('TOTP_ENROLLMENT_FAILED',$cid);}
    }

    public function confirm(string $adminId,int $factorId,string $code,string $sessionId,string $userAgent,string $ipAddress):array
    {
        $cid=bin2hex(random_bytes(8));$admin=$this->id($adminId,$cid);$session=$this->binding($sessionId,$cid);$browser=hash('sha256',substr($userAgent,0,1000));$ip=$this->ip($ipAddress,$cid);$started=false;
        try{$this->operation->beginTransaction();$started=true;$factor=$this->operation->admin_mfa_factor_for_update($factorId);
            if(!$this->factorMatches($factor,$admin,'PENDING')||!hash_equals($session,(string)$factor['enrollment_session_hash'])||!hash_equals($browser,(string)$factor['enrollment_browser_digest']))throw new AdminStepUpException('TOTP_CONFIRMATION_INVALID',$cid);
            $secret=$this->cipher->decrypt($factor['encrypted_secret'],$factor['secret_nonce'],$factor['key_version']);$step=Totp::matchTimeStep($secret,$code,($this->clock)(),1,null);
            if($step===null){$this->audit(47,$admin,'confirmation_failed',$cid,$ip);$this->operation->commit();$started=false;throw new AdminStepUpException('TOTP_CONFIRMATION_INVALID',$cid);}
            if($this->operation->admin_mfa_confirm_factor($factorId,$step)!==1)throw new AdminStepUpException('TOTP_CONFIRMATION_NOT_APPLIED',$cid);
            $this->audit(45,$admin,'confirmed',$cid,$ip);$this->operation->commit();$started=false;return['status'=>1,'code'=>'TOTP_CONFIRMED','factor_id'=>$factorId,'correlation_id'=>$cid];
        }catch(AdminStepUpException $e){if($started)$this->operation->rollback();throw$e;}catch(Throwable $e){if($started)$this->operation->rollback();throw new AdminStepUpException('TOTP_CONFIRMATION_FAILED',$cid);}
    }

    public function verifyActive(string $adminId,int $factorId,string $code,string $ipAddress):array
    {
        $cid=bin2hex(random_bytes(8));$admin=$this->id($adminId,$cid);$ip=$this->ip($ipAddress,$cid);$started=false;
        try{$this->operation->beginTransaction();$started=true;$factor=$this->operation->admin_mfa_factor_for_update($factorId);
            if(!$this->factorMatches($factor,$admin,'ACTIVE'))throw new AdminStepUpException('TOTP_VERIFICATION_FAILED',$cid);
            $secret=$this->cipher->decrypt($factor['encrypted_secret'],$factor['secret_nonce'],$factor['key_version']);$step=Totp::matchTimeStep($secret,$code,($this->clock)(),1,$factor['last_used_time_step']===null?null:(int)$factor['last_used_time_step']);
            if($step===null){$this->audit(47,$admin,'verification_failed',$cid,$ip);$this->operation->commit();$started=false;throw new AdminStepUpException('TOTP_VERIFICATION_FAILED',$cid);}
            if($this->operation->admin_mfa_record_factor_use($factorId,$step)!==1)throw new AdminStepUpException('TOTP_REPLAYED',$cid);
            $this->audit(46,$admin,'verified',$cid,$ip);$this->operation->commit();$started=false;return['status'=>1,'code'=>'TOTP_VERIFIED','factor_id'=>$factorId,'time_step'=>$step,'correlation_id'=>$cid];
        }catch(AdminStepUpException $e){if($started)$this->operation->rollback();throw$e;}catch(Throwable $e){if($started)$this->operation->rollback();throw new AdminStepUpException('TOTP_VERIFICATION_FAILED',$cid);}
    }

    public function revoke(string $adminId,int $factorId,?string $currentPassword,string $sessionId,string $userAgent,string $ipAddress,string $reason):array
    {
        $cid=bin2hex(random_bytes(8));$admin=$this->id($adminId,$cid);$session=$this->binding($sessionId,$cid);$browser=hash('sha256',substr($userAgent,0,1000));$ip=$this->ip($ipAddress,$cid);$reason=trim($reason);
        if(strlen($reason)<10||strlen($reason)>500)throw new AdminStepUpException('TOTP_REVOCATION_REASON_INVALID',$cid);$started=false;
        try{$this->operation->beginTransaction();$started=true;$context=$this->operation->admin_mfa_enrollment_context_for_update($admin);$passwordOk=is_array($context)&&$this->password((string)$currentPassword,(string)($context['u_password']??''));
            $recovery=$this->operation->admin_step_up_has_valid_email_recovery_grant($admin,$session,$browser);
            if(!$passwordOk&&!$recovery)throw new AdminStepUpException('TOTP_REVOCATION_AUTH_REQUIRED',$cid);
            $factor=$this->operation->admin_mfa_factor_for_update($factorId);if(!$this->factorMatches($factor,$admin,null))throw new AdminStepUpException('TOTP_FACTOR_NOT_FOUND',$cid);
            if($this->operation->admin_mfa_revoke_factor($factorId)!==1)throw new AdminStepUpException('TOTP_REVOCATION_NOT_APPLIED',$cid);$this->operation->admin_mfa_clear_totp_preference($admin);
            $this->audit($recovery?49:48,$admin,$recovery?'recovery_revoked':'revoked',$cid,$ip);$this->operation->commit();$started=false;return['status'=>1,'code'=>'TOTP_REVOKED','recovery_used'=>(bool)$recovery,'correlation_id'=>$cid];
        }catch(AdminStepUpException $e){if($started)$this->operation->rollback();throw$e;}catch(Throwable $e){if($started)$this->operation->rollback();throw new AdminStepUpException('TOTP_REVOCATION_FAILED',$cid);}
    }

    private function factorMatches(mixed $factor,string $admin,?string $status):bool{return is_array($factor)&&hash_equals($admin,(string)($factor['admin_user_id']??''))&&($status===null||($factor['factor_status']??'')===$status);}
    private function password(string $plain,string $hash):bool{return function_exists('oneid_password_verify')?\oneid_password_verify($plain,$hash):password_verify($plain,$hash);}
    private function audit(int $event,string $admin,string $outcome,string $cid,string $ip):void{if($this->operation->syslog_record($event,sprintf('admin=%s action=admin_totp outcome=%s correlation=%s',$admin,$outcome,$cid),$ip)!==1)throw new AdminStepUpException('TOTP_AUDIT_FAILED',$cid);}
    private function id(string $id,string $cid):string{$id=trim($id);if($id===''||strlen($id)>20||preg_match('/\A[A-Za-z0-9._@-]+\z/',$id)!==1)throw new AdminStepUpException('TOTP_ADMIN_INVALID',$cid);return$id;}
    private function binding(string $id,string $cid):string{if($id===''||strlen($id)>256)throw new AdminStepUpException('TOTP_SESSION_INVALID',$cid);return hash('sha256',$id);}
    private function ip(string $ip,string $cid):string{if(filter_var($ip,FILTER_VALIDATE_IP)===false)throw new AdminStepUpException('TOTP_IP_INVALID',$cid);return$ip;}
}
