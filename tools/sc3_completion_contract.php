<?php

declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(2);}require_once dirname(__DIR__).'/app/Admin/SsoConfigurationException.php';require_once dirname(__DIR__).'/app/Admin/SsoConfigurationService.php';
use OneId\App\Admin\SsoConfigurationException;use OneId\App\Admin\SsoConfigurationService;
final class Sc3CompletionFake{public array $stored=['id'=>1,'configuration_version'=>1,'token_timeout'=>24,'multi_session'=>1,'password_reset_email_enabled'=>1];public array $history=[];public int $commits=0,$rollbacks=0;function get_system_config(){return $this->stored;}function get_system_config_for_update(){return $this->stored;}function configuration_history_latest_success(){return null;}function beginTransaction(){return true;}function commit(){$this->commits++;return true;}function rollback(){$this->rollbacks++;return true;}function preview_policy_revocation(){return ['affected_tokens'=>0,'affected_users'=>0,'timeout_tokens'=>0,'multiple_tokens'=>0];}function update_configuration_by_id($id,$timeout,$multi,$version){if($version!==$this->stored['configuration_version'])return 0;$this->stored['token_timeout']=$timeout;$this->stored['multi_session']=$multi;$this->stored['configuration_version']++;return 1;}function syslog_record(){return 1;}function configuration_history_record($entry){$this->history[]=$entry;return 1;}function schedule_policy_revocation(){return 0;}function configuration_history_list(){return ['rows'=>[],'total'=>0];}}
$checks=0;$failed=0;$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$fake=new Sc3CompletionFake();$service=new SsoConfigurationService($fake);$previewA=$service->preview(['preview_configuration_update'=>'','token_timeout'=>'12','sso_settings_multi_session'=>'1','change_reason'=>'Approved admin A policy change']);$previewB=$service->preview(['preview_configuration_update'=>'','token_timeout'=>'2','sso_settings_multi_session'=>'1','change_reason'=>'Approved admin B policy change']);
$report($previewA['configuration_version']===1&&$previewB['configuration_version']===1,'concurrent previews bind the same original revision');
$result=$service->update(['update_configuration'=>'','token_timeout'=>'12','sso_settings_multi_session'=>'1','configuration_version'=>'1','change_reason'=>'Approved admin A policy change','policy_preview_id'=>'x'],'admin.a','127.0.0.1',['affected_tokens'=>0,'affected_users'=>0]);
$report(($result['data']['configuration_version']??0)===2&&$fake->commits===1,'first approved update increments and commits revision 2');
try{$service->update(['update_configuration'=>'','token_timeout'=>'2','sso_settings_multi_session'=>'1','configuration_version'=>'1','change_reason'=>'Approved admin B policy change','policy_preview_id'=>'y'],'admin.b','127.0.0.2',['affected_tokens'=>0,'affected_users'=>0]);$report(false,'stale concurrent update is rejected');}catch(SsoConfigurationException $e){$service->recordRejection($e->reason,'admin.b','127.0.0.2',$e->correlationId,'Approved admin B policy change');$report($e->reason==='SC3_CONFIGURATION_STALE'&&$fake->stored['token_timeout']==='12','stale concurrent update is rejected without overwriting revision 2');}
$report(count($fake->history)===2&&$fake->history[0]['outcome']==='SUCCESS'&&$fake->history[1]['outcome']==='REJECTED','success and rejected outcomes are recorded in structured history');
$root=dirname(__DIR__);$ui=(string)file_get_contents($root.'/admin/dashboard.php');$db=(string)file_get_contents($root.'/lib/Database.php');$migration=(string)file_get_contents($root.'/docs/migrations/20260719_sc3_completion_up.sql');
$report(str_contains($migration,'configuration_version')&&str_contains($migration,'configuration_change_history'),'forward migration contains revision and structured history schema');
$report(str_contains($db,'configuration_history_record')&&str_contains($db,'configuration_history_list'),'persistence provides atomic history writer and bounded history reader');
$report(str_contains($ui,'sso_config_change_reason')&&str_contains($ui,'Configuration History')&&str_contains($ui,'sso_config_last_changed'),'UI requires change reason and exposes Last Changed plus read-only history');
$report(
    str_contains($ui, 'id="configuration_authentication"')
        && str_contains($ui, 'id="configuration_recovery"')
        && str_contains($ui, 'id="configuration_audit"')
        && str_contains($ui, "loadSsoConfigHistory(1);"),
    'UI separates Authentication, Account Recovery and Audit History into accessible tabs'
);
$report(
    str_contains($ui, 'configuration-history-table')
        && str_contains($ui, 'configuration-history-col-event')
        && str_contains($ui, 'configuration-history-col-reason')
        && str_contains($ui, 'colspan="4"')
        && str_contains($ui, 'data-label="Reason &amp; Reference"'),
    'Audit History uses a compact four-column top-left responsive table'
);
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
