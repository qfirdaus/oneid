<?php
declare(strict_types=1);
namespace OneId\App\Auth;
use Throwable;

final class AdminStepUpPolicyService
{
    private const ALLOWED_MINUTES=[5,10,15,30];
    public function __construct(private readonly object $operation){}

    public function update(string $admin,mixed $minutes,mixed $version,string $reason,string $ip):array
    {
        $cid=bin2hex(random_bytes(8));
        $minutes=filter_var($minutes,FILTER_VALIDATE_INT);
        $version=filter_var($version,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $reason=trim($reason);
        if(!in_array($minutes,self::ALLOWED_MINUTES,true))throw new AdminStepUpException('STEP_UP_LIFETIME_INVALID',$cid);
        if($version===false)throw new AdminStepUpException('STEP_UP_CONFIGURATION_VERSION_INVALID',$cid);
        if(strlen($reason)<10||strlen($reason)>500)throw new AdminStepUpException('STEP_UP_CHANGE_REASON_INVALID',$cid);
        if(filter_var($ip,FILTER_VALIDATE_IP)===false)throw new AdminStepUpException('STEP_UP_IP_INVALID',$cid);
        $started=false;
        try{
            $this->operation->beginTransaction();$started=true;
            $stored=$this->operation->get_system_config_for_update();
            if(!is_array($stored)||!isset($stored['id']))throw new AdminStepUpException('STEP_UP_CONFIGURATION_UNAVAILABLE',$cid);
            $before=(int)($stored['admin_step_up_lifetime_minutes']??15);
            if(!in_array($before,self::ALLOWED_MINUTES,true))throw new AdminStepUpException('STEP_UP_CONFIGURATION_INVALID',$cid);
            if((int)$stored['configuration_version']!==$version)throw new AdminStepUpException('STEP_UP_CONFIGURATION_STALE',$cid);
            if($before===$minutes){$this->operation->commit();$started=false;return['status'=>1,'code'=>'STEP_UP_LIFETIME_UNCHANGED','lifetime_minutes'=>$before,'configuration_version'=>(int)$version,'correlation_id'=>$cid];}
            if($this->operation->update_admin_step_up_lifetime_by_version((int)$stored['id'],$minutes,(int)$version)!==1)throw new AdminStepUpException('STEP_UP_CONFIGURATION_STALE',$cid);
            $this->operation->configuration_history_record(['version_before'=>(int)$version,'version_after'=>(int)$version+1,'actor_id'=>$admin,'ip_address'=>$ip,'action_name'=>'ADMIN_STEP_UP_LIFETIME_UPDATE','outcome'=>'SUCCESS','reason_code'=>'ADMIN_STEP_UP_LIFETIME_UPDATED','change_reason'=>$reason,'before'=>['admin_step_up_lifetime_minutes'=>$before],'after'=>['admin_step_up_lifetime_minutes'=>$minutes],'correlation_id'=>$cid]);
            if($this->operation->syslog_record(54,"admin=$admin action=admin_step_up_lifetime before=$before after=$minutes correlation=$cid",$ip)!==1)throw new AdminStepUpException('STEP_UP_AUDIT_FAILED',$cid);
            $this->operation->commit();$started=false;
            return['status'=>1,'code'=>'STEP_UP_LIFETIME_UPDATED','lifetime_minutes'=>$minutes,'configuration_version'=>(int)$version+1,'applies_to_new_grants_only'=>true,'correlation_id'=>$cid];
        }catch(AdminStepUpException $e){if($started)$this->operation->rollback();throw $e;}
        catch(Throwable $e){if($started)$this->operation->rollback();throw new AdminStepUpException('STEP_UP_CONFIGURATION_UPDATE_FAILED',$cid);}
    }
}
