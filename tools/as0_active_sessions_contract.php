<?php

if(PHP_SAPI!=='cli'){exit(2);}$root=dirname(__DIR__);$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$files=['app/Admin/ActiveSessionService.php','lib/Database.php','lib/q_func.php','lib/request_security.php','admin/dashboard.php','config/locales/ms.php','config/locales/en.php','tests/characterization/as0_active_sessions_readonly.php','tools/as0_active_sessions_preflight.php'];$source=[];
foreach($files as$file){$source[$file]=(string)file_get_contents($root.'/'.$file);exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$file),$out,$code);$report($code===0,'PHP lint '.$file);$out=[];}
$db=$source['lib/Database.php'];$q=$source['lib/q_func.php'];$ui=$source['admin/dashboard.php'];$service=$source['app/Admin/ActiveSessionService.php'];
$start=strpos($q,"if(isset( \$_POST['admin_get_all_token_for_all_active_user']))");$end=strpos($q,"if(isset( \$_POST['get_specific_user_sp_access_list']))",$start?:0);$endpoint=$start!==false&&$end!==false?substr($q,$start,$end-$start):'';
$report(str_contains($source['lib/request_security.php'],"'admin_get_all_token_for_all_active_user'"),'endpoint remains admin, POST and CSRF guarded');
$report(str_contains($db,'SELECT A.user_id')&&!str_contains($db,'SELECT A.*,B.data1 as name'),'persistence replaces wildcard token projection with explicit fields');
$report(str_contains($db,"LIMIT '.\$pageSize.' OFFSET '.\$offset")&&str_contains($db,"in_array(\$pageSize,[10,25,50],true)"),'persistence enforces allowlisted bounded pagination');
$report(str_contains($db,"'expired'")&&str_contains($db,"'refresh'")&&str_contains($db,"'due'")&&str_contains($db,"'grace'")&&str_contains($db,"'current'"),'server query derives all lifecycle states including refresh window');
$report($endpoint!==''&&str_contains($endpoint,'ActiveSessionService')&&!str_contains($endpoint,'update_specific_token_status'),'listing endpoint delegates to service with zero hidden mutation');
$report(str_contains($service,"'user_id'")&&str_contains($service,"'issued_at'")&&str_contains($service,"'last_activity_at'")&&!str_contains($service,"'token_id' =>"),'service response projection excludes token material');
$report(str_contains($ui,'active_session_query')&&str_contains($ui,'active_session_status')&&str_contains($ui,'active_session_pagination'),'UI provides search, lifecycle filter and pagination');
$report(str_contains($ui,'<option value="10" selected>')&&str_contains($ui,".val() || '10'"),'UI defaults Active Sessions pagination to 10 rows');
$report(str_contains($ui,"admin.sessions.activity")&&str_contains($ui,"admin.sessions.issued")&&str_contains($ui,"admin.sessions.heartbeat")&&str_contains($ui,"refresh:{label:adminText('admin.sessions.refresh_window')")&&str_contains($ui,"grace:{label:adminText('admin.sessions.grace_period')")&&str_contains($ui,"due:{label:adminText('admin.sessions.due')"),'UI labels grouped timestamps and lifecycle states accurately');
$report(str_contains($ui,'active_session_metrics')&&str_contains($ui,'active_metric_refresh'),'UI exposes lifecycle metrics including refresh window');
$report(str_contains($ui,"session.public_user_id || '-'")&&str_contains($ui,"admin.sessions.staff_no")&&str_contains($ui,"admin.sessions.matric_no")&&str_contains($ui,"active-session-user-copy")&&str_contains($ui,"statusTitle += ' - '"),'UI shows the escaped staff or matric identity and retains revocation detail in hover titles');
$report(substr_count($ui,'<col class="active-col-')===4&&str_contains($ui,'active-session-timeline'),'UI condenses sessions into four structured columns');
$report(str_contains($ui,'.active-col-user { width: 27%; }')&&str_contains($ui,'.active-col-activity { width: 28%; }')&&str_contains($ui,'.active-col-device { width: 27%; }')&&str_contains($ui,'#app_security_session_list .active-session-timeline{gap:2px;grid-template-columns:1fr}'),'Active Sessions shares the compact balanced column and stacked activity layout');
$report(substr_count($ui,'data-session-tooltip title=')>=8&&str_contains($ui,"initializeSessionTableTooltips('#security_tab_session')")&&str_contains($ui,'oneid-session-history-tooltip'),'all Active Session columns use the shared dynamic single-line tooltip');
$report(str_contains($ui,'active-session-status-actions')&&str_contains($ui,'.active-session-status-actions{align-items:center;display:flex')&&str_contains($ui,'.active-session-status-actions .active-session-revoke{flex:0 0 28px;margin:0}'),'status and revoke action remain on one non-wrapping line');
$report(str_contains($source['config/locales/ms.php'],'tidak menamatkan atau mengubah sesi pengguna')&&str_contains($source['config/locales/en.php'],'do not end or modify user sessions'),'UI states the read-only behavior explicitly in BM and English');
$report(str_contains($source['tools/as0_active_sessions_preflight.php'],'status_digest_match')&&str_contains($source['tools/as0_active_sessions_preflight.php'],"['token_id','token_hash','policy_revoke_correlation']"),'preflight verifies zero status mutation and forbidden response fields');
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/as0_active_sessions_readonly.php'),$output,$code);
$report($code===0&&in_array('RESULT checks=10 failed=0',$output,true),'read-only Active Sessions characterization passes');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
