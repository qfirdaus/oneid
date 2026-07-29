<?php

if (PHP_SAPI !== 'cli') { exit(2); }
$root=dirname(__DIR__);$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$files=['app/Sync/SyncOperationalConfig.php','app/Sync/OperationalSyncApprovalGate.php','app/Sync/SyncEngineFactory.php','lib/q_func.php','lib/request_security.php','admin/dashboard.php','config/runtime.php','bootstrap/sync_runtime.php','tools/s4g_operational_sync_result_audit.php'];
$source=[];
foreach($files as$file){$source[$file]=(string)file_get_contents($root.'/'.$file);exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$file),$out,$code);$report($code===0,'PHP lint '.$file);$out=[];$code=1;}
$config=$source['app/Sync/SyncOperationalConfig.php'];$gate=$source['app/Sync/OperationalSyncApprovalGate.php'];$factory=$source['app/Sync/SyncEngineFactory.php'];$q=$source['lib/q_func.php'];$ui=$source['admin/dashboard.php'];
$localeMs=(string)file_get_contents($root.'/config/locales/ms.php');
$localeEn=(string)file_get_contents($root.'/config/locales/en.php');
$report(str_contains($source['config/runtime.php'],"'ONEID_SYNC_OPERATIONAL_ENABLED' => 'false'")&&str_contains($source['config/runtime.php'],"'ONEID_SYNC_OPERATIONAL_WARN_NEW' => '500'")&&str_contains($source['config/runtime.php'],"'ONEID_SYNC_OPERATIONAL_WARN_UPDATE' => '1000'")&&str_contains($source['config/runtime.php'],"'ONEID_SYNC_OPERATIONAL_MAX_DEACTIVATE' => '50'"),'committed operational defaults fail closed with bounded thresholds');
$report(str_contains($config,'APPLY SYNC DEACTIVATE')&&str_contains($config,'APPLY LARGE SYNC')&&str_contains($config,'assertWithinHardLimits'),'typed confirmation binds counts and hash while enforcing the hard limit');
$report(str_contains($gate,'consumeAndValidate')&&str_contains($gate,'SYNC_OPERATIONAL_CONFIRMATION_INVALID'),'server validates one-time approval before confirmation');
$report(str_contains($factory,'createOperationalCoordinator')&&str_contains($factory,'buildSafeOrchestrator()'),'operational endpoint uses full safe reconciled orchestrator');
$report(str_contains($source['lib/request_security.php'],"'admin_apply_operational_sync'")&&str_contains($q,"isset( \$_POST['admin_apply_operational_sync'])"),'operational action inherits admin CSRF and exactly-one-action guard');
$report(str_contains($q,'operational_apply_available')&&str_contains($q,"array_sum(\$previewCounts) > 0")&&str_contains($q,'SYNC_MODE_CONFLICT'),'preview requires non-empty safe plan and exclusive mode');
$report(str_contains($q,'operational_large_batch')&&str_contains($q,'operational_hard_blocked')&&str_contains($q,'max_deactivate'),'preview exposes server-derived threshold decisions');
$report(str_contains($q,'ADMIN_SYNC_OPERATIONAL_SAFE')&&str_contains($q,'SYNC_OPERATIONAL_APPLY_COMPLETED_AUDIT_WARNING'),'operational Apply emits completion and secondary audit state');
$report(str_contains($source['tools/s4g_operational_sync_result_audit.php'],'ADMIN_SYNC_OPERATIONAL_SAFE')&&str_contains($source['tools/s4g_operational_sync_result_audit.php'],'mutation_statements=0'),'result audit reconciles the operational marker read-only');
$report(str_contains($ui,'operational_apply_available === true')&&str_contains($ui,'admin_apply_operational_sync')&&str_contains($ui,'btn_apply_sync_operational'),'UI requires server availability and typed confirmation');
$report(str_contains($ui,"'largeChange' => oneid_translate('admin.sync.large_change')")&&str_contains($ui,"'blocked' => oneid_translate('admin.sync.blocked')")&&str_contains($ui,"response.risk_level === 'blocked' ? 'badge badge-danger'")&&str_contains($localeMs,"'admin.sync.large_change'")&&str_contains($localeMs,"'admin.sync.blocked'")&&str_contains($localeEn,"'admin.sync.large_change'")&&str_contains($localeEn,"'admin.sync.blocked'"),'UI distinguishes localized soft warning from hard block');
$report(str_contains($ui,'totalChanges === 0')&&str_contains($ui,'externalSyncText.upToDate')&&str_contains($ui,'externalSyncText.noAdminAction')&&str_contains($localeMs,"'admin.sync.up_to_date'")&&str_contains($localeMs,"'admin.sync.no_admin_action'")&&str_contains($localeEn,"'admin.sync.up_to_date'")&&str_contains($localeEn,"'admin.sync.no_admin_action'"),'zero-change preview renders a localized up-to-date state without Apply');
$report(str_contains($ui,'sync-preview-table-wrap')&&str_contains($ui,'width: 230px')&&str_contains($ui,'white-space: nowrap')&&str_contains($ui,'vertical-align: top !important')&&str_contains($ui,'text-align: left !important'),'preview cells use a stable top-left single-line label column');
$report(
    str_contains($ui, "(response.source_rows || 0) + ' (' + sourceLabels[sourceCode] + ')'"),
    'external rows display the selected source only'
);
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/s4g_operational_sync_gate.php'),$output,$code);
$report($code===0&&in_array('RESULT checks=16 failed=0',$output,true),'operational pure characterization passes');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
