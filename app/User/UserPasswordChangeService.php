<?php
namespace OneId\App\User;
use Throwable;
final class UserPasswordChangeService
{
    public function __construct(private readonly object $operation){}
    public function change(string $userId,string $current,string $new,string $confirmation,string $device,string $ip,bool $keepCurrentSession=true):array
    {
        $correlation=bin2hex(random_bytes(8));$started=false;
        if($new===''||!hash_equals($new,$confirmation))throw new UserPasswordChangeException('UC2_CONFIRMATION_MISMATCH',$correlation);
        [$valid,$message]=oneid_validate_new_password($new,$userId);if(!$valid)throw new UserPasswordChangeException('UC5_PASSWORD_QUALITY_REJECTED',$correlation);
        try{$this->operation->beginTransaction();$started=true;$user=$this->operation->get_user_password_change_for_update($userId);
            if(!is_array($user)||(int)($user['avail_status']??0)!==1)throw new UserPasswordChangeException('UC2_USER_NOT_ACTIVE',$correlation);
            $stored=(string)($user['u_password']??'');
            if(!oneid_password_verify($current,$stored))throw new UserPasswordChangeException('UC2_CURRENT_PASSWORD_INVALID',$correlation);
            if(oneid_password_verify($new,$stored))throw new UserPasswordChangeException('UC2_PASSWORD_REUSE_CURRENT',$correlation);
            foreach($this->operation->get_password_history_hashes($userId,5) as $historyHash){if(oneid_password_verify($new,(string)$historyHash))throw new UserPasswordChangeException('UC5_PASSWORD_HISTORY_REUSED',$correlation);}
            if($this->operation->record_password_history($userId,$stored)!==1)throw new UserPasswordChangeException('UC5_PASSWORD_HISTORY_WRITE_FAILED',$correlation);
            if($this->operation->set_user_password($userId,$new,0)!==1)throw new UserPasswordChangeException('UC2_PASSWORD_NOT_CHANGED',$correlation);
            $this->operation->prune_password_history($userId,5);
            $revoked=(int)$this->operation->update_whole_token_status($userId,0);
            $invalidated=(int)$this->operation->otp_invalidate_active($userId);
            $token=null;if($keepCurrentSession){$token=oneid_generate_sso_token();if($this->operation->add_new_token($token,$userId,$device)!==1)throw new UserPasswordChangeException('UC2_REPLACEMENT_TOKEN_FAILED',$correlation);}
            $detail=sprintf('user=%s action=change_password tokens_revoked=%d otp_invalidated=%d correlation=%s',$userId,$revoked,$invalidated,$correlation);
            if($this->operation->syslog_record(21,$detail,$ip)!==1)throw new UserPasswordChangeException('UC2_AUDIT_FAILED',$correlation);
            $this->operation->commit();$started=false;
            return ['status'=>1,'code'=>$keepCurrentSession?'UC4_PASSWORD_CHANGED_SESSION_ROTATED':'UC4_PASSWORD_CHANGED_REAUTH_REQUIRED','msg'=>'Password successfully changed','correlation_id'=>$correlation,'replacement_token'=>$token,'password_change_required'=>0,'reauthentication_required'=>!$keepCurrentSession];
        }catch(Throwable $e){if($started){try{$this->operation->rollback();}catch(Throwable $ignored){error_log('UC2 rollback failed correlation='.$correlation);}}
            if($e instanceof UserPasswordChangeException)throw $e;error_log('UC2 failed correlation='.$correlation.' exception='.get_class($e));throw new UserPasswordChangeException('UC2_OPERATION_FAILED',$correlation);}
    }
}
