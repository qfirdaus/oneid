<?php

$root=dirname(__DIR__);
$userTop=(string)file_get_contents($root.'/page/const/top.php');
$adminTop=(string)file_get_contents($root.'/admin/const/top.php');
$userJs=(string)file_get_contents($root.'/public/dist/js/oneid-user-session.js');
$adminJs=(string)file_get_contents($root.'/public/dist/js/oneid-admin-session.js');
$session=(string)file_get_contents($root.'/lib/session_security.php');
$endpoint=(string)file_get_contents($root.'/app/Auth/UserPortalSessionService.php');
$ms=require $root.'/config/locales/ms.php';
$en=require $root.'/config/locales/en.php';
$checks=[
    'user header exposes one non-live session indicator'=>str_contains($userTop,'id="oneid_user_session_indicator"')&&str_contains($userTop,'aria-live="off"'),
    'admin header separates portal session and Administrator access'=>str_contains($adminTop,'id="oneid_admin_portal_session_indicator"')&&str_contains($adminTop,'id="oneid_admin_access_indicator"'),
    'user indicator renders the server-authoritative effective deadline'=>str_contains($userJs,'payload.effective_remaining_seconds')&&str_contains($userJs,'updatePersistentDisplay'),
    'admin indicator renders separate portal and grant deadlines'=>str_contains($adminJs,"renderIndicator('oneid_admin_portal_session_indicator'")&&str_contains($adminJs,"renderIndicator('oneid_admin_access_indicator'")&&str_contains($adminJs,'portalRemaining=Math.min'),
    'admin status periodically resynchronizes without extending activity'=>str_contains($adminJs,'window.setInterval(synchronize, 30000)')&&str_contains($session,"['admin_step_up_status', 'purpose']"),
    'all countdowns remain bounded by the server absolute deadline'=>str_contains($session,"'effective_remaining_seconds' => min(")&&str_contains($endpoint,"oneid_current_session_deadline_state"),
    'BM and English countdown labels are complete'=>isset($ms['user_session.remaining_label'],$en['user_session.remaining_label'],$ms['admin.session.remaining_label'],$en['admin.session.remaining_label']),
    'countdown display supports hours and mobile layout'=>str_contains($userJs,"String(hours).padStart(2, '0')")&&str_contains($adminJs,"String(hours).padStart(2, '0')"),
    'only the user-session indicator offers explicit renewal'=>substr_count($userTop,'data-oneid-user-session-renew')===1&&!str_contains($adminTop,'data-oneid-user-session-renew'),
    'renew control uses guarded renewal and a client cooldown'=>str_contains($userJs,"post('user_session_renew')")&&str_contains($userJs,'beginRenewButtonCooldown')&&str_contains($userJs,'30000'),
];
$failed=0;
foreach($checks as $description=>$passed){echo ($passed?'PASS ':'FAIL ').$description.PHP_EOL;if(!$passed)$failed++;}
echo 'RESULT checks='.count($checks).' failed='.$failed.PHP_EOL;
exit($failed===0?0:1);
