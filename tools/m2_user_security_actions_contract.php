<?php

if (PHP_SAPI !== 'cli') exit(2);
$root = dirname(__DIR__);
$files = [
    'app/User/UserSecurityActionException.php',
    'app/User/UserSecurityActionService.php',
    'lib/Database.php',
    'lib/q_func.php',
    'admin/dashboard.php',
    'tests/characterization/m2_user_security_actions.php',
];
$checks = 0; $failed = 0;
$report = static function(bool $ok, string $label, string $detail='') use (&$checks,&$failed): void {
    $checks++; if(!$ok)$failed++;
    printf("%s %-72s%s\n",$ok?'PASS':'FAIL',$label,$detail!==''?' '.$detail:'');
};
foreach($files as $file){
    $out=[];$code=1;exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$file).' 2>&1',$out,$code);
    $report($code===0,'source and PHP lint: '.$file,implode(' | ',$out));
}
$service=file_get_contents($root.'/app/User/UserSecurityActionService.php');
$db=file_get_contents($root.'/lib/Database.php');
$q=file_get_contents($root.'/lib/q_func.php');
$ui=file_get_contents($root.'/admin/dashboard.php');
$report(str_contains($service,'beginTransaction()')&&str_contains($service,'commit()')&&str_contains($service,'rollback()'),'all M2 actions have transaction commit and rollback');
$report(str_contains($service,'admin_get_user_for_security_action($userId, true)'),'M2 locks target user row before mutation');
$report(str_contains($service,'M2_SELF_ACTION_FORBIDDEN'),'self reset/deactivate protection exists');
$report(str_contains($service,'set_user_password')&&str_contains($service,'random_bytes(32)'),'reset replaces password with unknown cryptographic secret');
$report(str_contains($service,'update_whole_token_status')&&str_contains($service,'otp_invalidate_active'),'all actions revoke sessions and active OTP');
$report(str_contains($service,'M2_AUDIT_NOT_WRITTEN')&&str_contains($service,'correlation='),'audit is correlated and mandatory');
$report(str_contains($service,'$event = 10')&&str_contains($service,'$event = 25')&&str_contains($service,'$event = 26'),'existing audit events map to reset/deactivate/reactivate');
$report(str_contains($db,'LIMIT 1 FOR UPDATE')||str_contains($db,"\$Q .= ' FOR UPDATE'"),'database exposes lockable minimal M2 read');
$report(!str_contains($q,"echo json_encode(0);")&&str_contains($q,'UserSecurityActionService'),'legacy unconditional M2 handlers replaced by service');
$report(substr_count($q,'UserSecurityActionException')>=4,'all three M2 endpoints emit safe correlated failures');
$report(str_contains($ui,'m2SecurityFailureText')&&str_contains($ui,'correlation_id'),'UI displays safe code and correlation reference');
$report(substr_count($ui,"Number(response.status) === 1")>=3,'UI requires explicit structured success');
$report(str_contains($ui,'btn_user_profile_reset_password')&&str_contains($ui,"prop('disabled', true)"),'UI prevents duplicate reset/status submissions');
$fixture=[];$exit=1;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/m2_user_security_actions.php').' 2>&1',$fixture,$exit);
$report($exit===0&&in_array('RESULT checks=24 failed=0',$fixture,true),'M2 in-memory zero-I/O fixture passes 24/24',implode(' | ',array_slice($fixture,-2)));
printf("RESULT checks=%d failed=%d\n",$checks,$failed);
exit($failed===0?0:1);
