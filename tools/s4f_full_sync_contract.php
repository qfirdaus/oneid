<?php

if (PHP_SAPI !== 'cli') { exit(2); }
$root=dirname(__DIR__);$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$files=['app/Sync/SyncFullConfig.php','app/Sync/FullSyncApprovalGate.php','app/Sync/SyncEngineFactory.php','lib/q_func.php','lib/request_security.php','admin/dashboard.php','config/runtime.php'];
$source=[];
foreach($files as$file){$source[$file]=(string)file_get_contents($root.'/'.$file);exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$file),$out,$code);$report($code===0,'PHP lint '.$file);$out=[];$code=1;}
$config=$source['app/Sync/SyncFullConfig.php'];$q=$source['lib/q_func.php'];$ui=$source['admin/dashboard.php'];
$report(str_contains($source['config/runtime.php'],"'ONEID_SYNC_FULL_ENABLED' => 'false'")&&str_contains($source['config/runtime.php'],"'ONEID_SYNC_FULL_EXPECTED_PLAN_HASH' => ''"),'committed full-sync defaults fail closed');
$report(str_contains($config,'SYNC_FULL_COUNT_MISMATCH')&&str_contains($config,'SYNC_FULL_PLAN_MISMATCH'),'full config binds exact counts and plan hash');
$report(str_contains($source['app/Sync/FullSyncApprovalGate.php'],'$this->config->assertPlan($currentPlan')&&str_contains($source['app/Sync/FullSyncApprovalGate.php'],'consumeAndValidate'),'fresh writer plan is checked before approval consumption');
$report(str_contains($source['app/Sync/SyncEngineFactory.php'],'createFullCoordinator')&&str_contains($source['app/Sync/SyncEngineFactory.php'],'buildSafeOrchestrator()'),'full endpoint uses safe reconciled orchestrator without subset');
$report(str_contains($source['lib/request_security.php'],"'admin_apply_full_sync'")&&str_contains($q,"isset( \$_POST['admin_apply_full_sync'])"),'full action inherits admin CSRF and exactly-one-action guard');
$report(str_contains($q,'full_apply_available')&&str_contains($q,'SYNC_MODE_CONFLICT')&&str_contains($q,'SYNC_FULL_CONFIRMATION_INVALID'),'preview and writer require exclusive mode and confirmation');
$report(str_contains($q,'SYNC_FULL_APPLY_COMPLETED_AUDIT_WARNING')&&str_contains($q,"'audit_marker_recorded'"),'post-commit audit marker failure cannot be reported as an Apply failure');
$report(str_contains($ui,'full_apply_available === true')&&str_contains($ui,'sync_full_confirmation')&&str_contains($ui,'admin_apply_full_sync'),'full UI remains hidden until server availability and typed confirmation');
$report(str_contains($ui,"String(response.plan_hash || '-')")&&!str_contains($ui,"substring(0, 12) + '... / '"),'UI exposes the complete non-PII plan hash required by private runtime approval');
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/s4f_full_sync_gate.php'),$output,$code);
$report($code===0&&in_array('RESULT checks=9 failed=0',$output,true),'full-sync pure characterization passes');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
