<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
$root = dirname(__DIR__);
$files = ['app/Admin/ActiveSessionRevocationException.php','app/Admin/ActiveSessionRevocationConfig.php','app/Admin/Adapters/SessionRevocationPreviewStore.php','app/Admin/ActiveSessionRevocationService.php','app/Admin/ActiveSessionService.php','lib/Database.php','lib/request_security.php','lib/q_func.php','admin/dashboard.php','page/admin_step_up.php'];
foreach ($files as $file) {
    exec('php -l '.escapeshellarg($root.'/'.$file), $output, $code);
    if ($code !== 0) { fwrite(STDERR, "FAIL lint $file\n"); exit(1); }
    echo "PASS lint $file\n";
}
$guard=(string)file_get_contents($root.'/lib/request_security.php');
$ui=(string)file_get_contents($root.'/admin/dashboard.php');
$db=(string)file_get_contents($root.'/lib/Database.php');
$runtime=(string)file_get_contents($root.'/config/runtime.php');
$checks=[
    'exact_purpose'=>str_contains($guard,"return 'ACTIVE_SESSION_REVOCATION'")&&str_contains($guard,'admin_apply_active_session_revocation'),
    'feature_off'=>str_contains($runtime,"'ONEID_ACTIVE_SESSION_REVOCATION_ENABLED' => 'false'"),
    'token_secrecy'=>str_contains($ui,'revocation_target_id')&&!str_contains($ui,'internal_token_id'),
    'targeted_transaction'=>str_contains($db,'admin_session_revocation_target_for_update')&&str_contains($db,'admin_revoke_exact_session'),
    'ui_pilot'=>str_contains($ui,'active-session-revoke')&&str_contains($ui,'admin_preview_active_session_revocation'),
    'guided_confirmation_ui'=>str_contains($ui,'as3-reason-chip')&&str_contains($ui,'data-as3-confirmation')&&str_contains($ui,"confirmation!==phrase")&&str_contains($ui,'show_active_session_revocation_modal'),
    'audit_event'=>is_file($root.'/docs/migrations/20260805_as3_session_revocation_audit_event_up.sql'),
];
$failed=array_keys(array_filter($checks,fn($value)=>!$value));
foreach($checks as$key=>$value) echo ($value?'PASS ':'FAIL ').$key."\n";
if($failed) exit(1);
passthru('php '.escapeshellarg($root.'/tests/characterization/as3_controlled_session_revocation.php'),$code);
exit($code);
