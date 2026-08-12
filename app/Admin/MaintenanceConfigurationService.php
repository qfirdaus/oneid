<?php

declare(strict_types=1);

namespace OneId\App\Admin;

use OneId\App\Maintenance\MaintenancePolicy;
use Throwable;

final class MaintenanceConfigurationService
{
    public function __construct(private readonly object $operation) {}

    public function read(): array
    {
        $row=$this->operation->get_maintenance_config();
        if(!is_array($row))throw new SsoConfigurationException('MT2_SCHEMA_UNAVAILABLE',bin2hex(random_bytes(8)));
        return ['status'=>1,'code'=>'MT2_LOADED','data'=>MaintenancePolicy::evaluate($row),'correlation_id'=>bin2hex(random_bytes(8))];
    }

    public function update(array $post,string $admin,string $ip): array
    {
        $cid=bin2hex(random_bytes(8));$mode=strtoupper(trim((string)($post['mode']??'')));
        if(!in_array($mode,['OFF','SCHEDULED','INDEFINITE'],true))throw new SsoConfigurationException('MT2_MODE_INVALID',$cid);
        $version=filter_var($post['configuration_version']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($version===false)throw new SsoConfigurationException('MT2_VERSION_INVALID',$cid);
        $reason=trim((string)($post['change_reason']??''));if(mb_strlen($reason)<10||mb_strlen($reason)>500)throw new SsoConfigurationException('MT2_REASON_INVALID',$cid);
        $localTz=new \DateTimeZone((string)\oneid_config('ONEID_TIMEZONE','Asia/Kuala_Lumpur'));
        $utc=new \DateTimeZone('UTC');$parse=function(string $value)use($localTz,$utc,$cid):?string{$value=trim($value);if($value==='')return null;$d=\DateTimeImmutable::createFromFormat('Y-m-d\TH:i',$value,$localTz);if(!$d)throw new SsoConfigurationException('MT2_TIME_INVALID',$cid);return $d->setTimezone($utc)->format('Y-m-d H:i:s');};
        $starts=$mode==='OFF'?null:$parse((string)($post['starts_at']??''));$ends=$mode==='SCHEDULED'?$parse((string)($post['ends_at']??'')):null;
        if($mode!=='OFF'&&$starts===null)$starts=gmdate('Y-m-d H:i:s');
        if($mode==='SCHEDULED'&&($ends===null||strtotime($ends)<=strtotime((string)$starts)))throw new SsoConfigurationException('MT2_TIME_RANGE_INVALID',$cid);
        $field=function(string $key,int $max,string $fallback)use($post,$cid):string{$v=trim((string)($post[$key]??$fallback));if($v===''||mb_strlen($v)>$max||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$v)===1)throw new SsoConfigurationException('MT2_CONTENT_INVALID',$cid);return $v;};
        $after=['mode'=>$mode,'starts_at'=>$starts,'ends_at'=>$ends,'title_ms'=>$field('title_ms',160,'Sistem OneID sedang diselenggara'),'title_en'=>$field('title_en',160,'OneID is under maintenance'),'message_ms'=>$field('message_ms',1000,'Perkhidmatan tidak tersedia buat sementara waktu.'),'message_en'=>$field('message_en',1000,'The service is temporarily unavailable.')];
        $started=false;try{$this->operation->beginTransaction();$started=true;$before=$this->operation->get_maintenance_config_for_update();if(!is_array($before)||(int)$before['configuration_version']!==(int)$version)throw new SsoConfigurationException('MT3_CONFIGURATION_STALE',$cid);if($this->operation->update_maintenance_config_by_version((int)$before['id'],$after,(int)$version)!==1)throw new SsoConfigurationException('MT3_UPDATE_FAILED',$cid);$next=(int)$version+1;$this->operation->configuration_history_record(['version_before'=>$version,'version_after'=>$next,'actor_id'=>$admin,'ip_address'=>$ip,'action_name'=>'UPDATE_MAINTENANCE_CONFIGURATION','outcome'=>'SUCCESS','reason_code'=>'MT3_UPDATED','change_reason'=>$reason,'before'=>MaintenancePolicy::evaluate($before),'after'=>$after,'correlation_id'=>$cid]);if($this->operation->syslog_record(69,"admin=$admin action=maintenance_update mode=$mode correlation=$cid",$ip)!==1)throw new SsoConfigurationException('MT3_AUDIT_FAILED',$cid);$this->operation->commit();$started=false;return['status'=>1,'code'=>'MT3_UPDATED','data'=>MaintenancePolicy::evaluate($after+['configuration_version'=>$next]),'correlation_id'=>$cid];}catch(Throwable $e){if($started)try{$this->operation->rollback();}catch(Throwable){}if($e instanceof SsoConfigurationException)throw $e;throw new SsoConfigurationException('MT3_UPDATE_FAILED',$cid);}
    }
}
