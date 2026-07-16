<?php

namespace OneId\App\Admin;

use Throwable;

final class PasswordRecoveryConfigurationService
{
    public function __construct(private readonly object $operation) {}

    public function read(): array
    {
        $id=bin2hex(random_bytes(8));$stored=$this->operation->get_system_config();
        if(!is_array($stored))throw new SsoConfigurationException('SC6_CONFIG_NOT_FOUND',$id);
        return ['status'=>1,'code'=>'SC6_RECOVERY_LOADED','data'=>[
            'password_reset_email_enabled'=>$this->flag($stored['password_reset_email_enabled']??null,$id),
            'smtp_health'=>$this->smtpHealth(),
            'manual_recovery_available'=>false,
        ],'correlation_id'=>$id];
    }

    public function update(mixed $value,string $admin,string $ip): array
    {
        $id=bin2hex(random_bytes(8));$enabled=$this->flag($value,$id);$started=false;
        try{$this->operation->beginTransaction();$started=true;$stored=$this->operation->get_system_config_for_update();
            if(!is_array($stored)||!isset($stored['id']))throw new SsoConfigurationException('SC6_CONFIG_NOT_FOUND',$id);
            $before=$this->flag($stored['password_reset_email_enabled']??null,$id);
            if($before===$enabled){$this->operation->commit();return ['status'=>1,'code'=>'SC6_RECOVERY_UNCHANGED','data'=>['password_reset_email_enabled'=>$enabled],'correlation_id'=>$id];}
            if($this->operation->update_password_recovery_by_id((int)$stored['id'],$enabled)!==1)throw new SsoConfigurationException('SC6_UPDATE_NOT_APPLIED',$id);
            $detail=sprintf('admin=%s action=update_password_recovery_email before=%d after=%d manual_recovery_available=0 correlation=%s',$admin,$before,$enabled,$id);
            if($this->operation->syslog_record(33,$detail,$ip)!==1)throw new SsoConfigurationException('SC6_AUDIT_FAILED',$id);
            $this->operation->commit();return ['status'=>1,'code'=>'SC6_RECOVERY_UPDATED','data'=>['password_reset_email_enabled'=>$enabled],'correlation_id'=>$id];
        }catch(SsoConfigurationException $e){if($started)$this->operation->rollback();throw $e;}catch(Throwable $e){if($started)$this->operation->rollback();throw new SsoConfigurationException('SC6_UPDATE_FAILED',$id);}
    }

    public function smtpHealth(): array
    {
        $host=trim((string)oneid_config('ONEID_SMTP_HOST'));$port=(int)oneid_config('ONEID_SMTP_PORT');
        $user=trim((string)oneid_secret('ONEID_SMTP_USERNAME'));$pass=(string)oneid_secret('ONEID_SMTP_PASSWORD');
        $ready=$host!==''&&$port>0&&$user!==''&&$pass!=='';
        return ['status'=>$ready?'configured':'not_configured','host_configured'=>$host!=='','port_configured'=>$port>0,'credential_configured'=>$user!==''&&$pass!=='','live_delivery_verified'=>false];
    }

    private function flag(mixed $value,string $id): int
    {if(!is_scalar($value)||!in_array(trim((string)$value),['0','1'],true))throw new SsoConfigurationException('SC6_EMAIL_POLICY_INVALID',$id);return(int)$value;}
}
