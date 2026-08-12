<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(2);}define('ONEID_MAINTENANCE_BYPASS',true);require_once dirname(__DIR__).'/lib/config.php';
$reason='';foreach($argv as $arg){if(str_starts_with($arg,'--reason='))$reason=trim(substr($arg,9));}
if(strlen($reason)<10){fwrite(STDERR,"Usage: php tools/maintenance_disable.php --reason=\"approved operational reason\"\n");exit(1);}
$before=$operation->get_maintenance_config_for_update();if(!is_array($before)){fwrite(STDERR,"FAIL maintenance schema unavailable\n");exit(1);}
$data=['mode'=>'OFF','starts_at'=>null,'ends_at'=>null,'title_ms'=>$before['maintenance_title_ms'],'title_en'=>$before['maintenance_title_en'],'message_ms'=>$before['maintenance_message_ms'],'message_en'=>$before['maintenance_message_en']];
$operation->beginTransaction();try{$version=(int)$before['configuration_version'];if($operation->update_maintenance_config_by_version((int)$before['id'],$data,$version)!==1)throw new RuntimeException('MAINTENANCE_DISABLE_STALE');$cid=bin2hex(random_bytes(8));$actor=get_current_user()?:'cli';$operation->configuration_history_record(['version_before'=>$version,'version_after'=>$version+1,'actor_id'=>substr($actor,0,20),'ip_address'=>'127.0.0.1','action_name'=>'EMERGENCY_DISABLE_MAINTENANCE','outcome'=>'SUCCESS','reason_code'=>'MT_CLI_DISABLED','change_reason'=>$reason,'before'=>$before,'after'=>$data,'correlation_id'=>$cid]);$operation->commit();echo "PASS maintenance disabled reference=$cid\n";}catch(Throwable $e){$operation->rollback();fwrite(STDERR,"FAIL maintenance was not disabled\n");exit(1);}
