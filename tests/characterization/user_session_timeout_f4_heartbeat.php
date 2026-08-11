<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(2); }

$root=dirname(__DIR__,2);
$dashboard=file_get_contents($root.'/page/dashboard.php')?:'';
$controller=file_get_contents($root.'/public/dist/js/oneid-user-session.js')?:'';
$security=file_get_contents($root.'/page/user_mfa_security.php')?:'';
$session=file_get_contents($root.'/lib/session_security.php')?:'';
$api=file_get_contents($root.'/api.php')?:'';
$checks=0;$failed=0;$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;$failed+=$ok?0:1;echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;};

$report(str_contains($dashboard,'update_specific_token_datetime:"1"')&&str_contains($dashboard,'}, 300000);'),'legacy five-minute token activity heartbeat remains compatible');
$report(!str_contains($dashboard,'location.reload(true);'),'heartbeat failure no longer forces a page reload');
$report(str_contains($dashboard,"['USER_SESSION_EXPIRED','SSO_TOKEN_REVOKED','ACCOUNT_INACTIVE']"),'heartbeat recognizes only explicit terminal backend codes');
$report(str_contains($dashboard,'OneIdUserSession.handleExternalError(xhr.status, code)'),'heartbeat delegates coded failures to the session controller');
$report(str_contains($dashboard,"terminalCodes.indexOf(code) !== -1")&&str_contains($dashboard,"window.location.replace("),'feature-flag fallback redirects only explicit terminal states');
$report(str_contains($dashboard,'sessionStatusUnavailable')&&str_contains($dashboard,'oneidHeartbeatLastWarningAt')&&str_contains($dashboard,'icon:\'warning\''),'offline and server failures show a rate-limited localized warning');
$report(str_contains($controller,'function handleExternalError(status, code)')&&str_contains($controller,"normalizedStatus === 401"),'controller revalidates an uncoded 401 instead of assuming expiry');
$report(str_contains($controller,"error.code === 'USER_SESSION_EXPIRED'")&&!str_contains($controller,'|| error.status === 401'),'controller terminal routing depends on stable codes rather than HTTP status alone');
$report(str_contains($controller,"window.setTimeout(synchronize, 15000)"),'network and server errors retry status without logout');
$report(str_contains($dashboard,'$(document).ajaxSuccess')&&str_contains($dashboard,"oneid:user-activity-committed"),'successful dashboard requests notify the deadline controller');
$report(str_contains($dashboard,"['update_specific_token_datetime','user_session_status','admin_step_up_status']"),'technical heartbeat and status actions are excluded from meaningful activity notifications');
$report(str_contains($security,"new CustomEvent('oneid:user-activity-committed')"),'Account Security successful actions resynchronize the authoritative deadline');
$report(str_contains($controller,"document.addEventListener('oneid:user-activity-committed'")&&str_contains($controller,'activityCommitted: activityCommitted'),'controller exposes and consumes the activity synchronization boundary');
$report(str_contains($controller,'resyncRequested = true')&&str_contains($controller,'if (resyncRequested)'),'activity synchronization is queued when a status request is already in flight');
$report(!str_contains($controller,'setInterval(synchronize')&&str_contains($session,"['update_specific_token_datetime']"),'deadline synchronization adds no session-keeping polling and token heartbeat remains technical');
$report(str_contains($dashboard,'oneid-user-session.js?v=20260811-1')&&str_contains($security,'oneid-user-session.js?v=20260811-1'),'both authenticated user pages load the corrected cache-busted controller');
$report(!str_contains($api,'OneIdUserSession')&&!str_contains($api,'user-activity-committed'),'service-provider API remains outside presentation error handling');

echo"RESULT checks={$checks} failed={$failed}".PHP_EOL;exit($failed===0?0:1);
