<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/session_security.php';
oneid_start_secure_session();
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/SSO_IDP_INC.php';
require_once __DIR__ . '/../lib/request_security.php';

oneid_require_authenticated_page();
oneid_require_active_sso_page($operation);
$mode = (string) oneid_config('ONEID_USER_MFA_MODE', 'OFF');
$authorized = filter_var(
    oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED', false),
    FILTER_VALIDATE_BOOLEAN
);
if ($mode === 'OFF' || !$authorized) {
    http_response_code(404);
    exit('Not found');
}
header('Location: ' . APP_URL . '/page/dashboard?security=user_mfa', true, 303);
exit;
header('Cache-Control: no-store, no-cache, must-revalidate');
$csrf = oneid_csrf_token();
?>
<!doctype html>
<html lang="<?=htmlspecialchars(oneid_current_locale(), ENT_QUOTES, 'UTF-8')?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars(oneid_translate('user_mfa.security.title'), ENT_QUOTES, 'UTF-8')?></title>
<link rel="stylesheet" href="../dist/css/style.css"></head>
<body><main class="container" style="max-width:720px;padding-top:40px">
<h1><?=htmlspecialchars(oneid_translate('user_mfa.security.title'), ENT_QUOTES, 'UTF-8')?></h1>
<p><?=htmlspecialchars(oneid_translate('user_mfa.security.intro'), ENT_QUOTES, 'UTF-8')?></p>
<div id="mfa-message" role="status" aria-live="polite"></div>
<label for="device-label"><?=htmlspecialchars(oneid_translate('user_mfa.security.device'), ENT_QUOTES, 'UTF-8')?></label>
<input id="device-label" class="form-control" maxlength="100" value="Microsoft Authenticator">
<button id="mfa-enroll" class="btn btn-primary" type="button"><?=htmlspecialchars(oneid_translate('user_mfa.security.enroll'), ENT_QUOTES, 'UTF-8')?></button>
<section id="mfa-confirm-panel" hidden>
  <img id="mfa-qr" alt="Microsoft Authenticator QR code">
  <label for="mfa-code"><?=htmlspecialchars(oneid_translate('user_mfa.email.label'), ENT_QUOTES, 'UTF-8')?></label>
  <input id="mfa-code" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code">
  <button id="mfa-confirm" class="btn btn-success" type="button"><?=htmlspecialchars(oneid_translate('user_mfa.security.confirm'), ENT_QUOTES, 'UTF-8')?></button>
</section>
<hr><label for="mfa-revoke-code"><?=htmlspecialchars(oneid_translate('user_mfa.security.revoke_code'), ENT_QUOTES, 'UTF-8')?></label>
<input id="mfa-revoke-code" class="form-control" inputmode="numeric" maxlength="6" autocomplete="one-time-code">
<button id="mfa-revoke" class="btn btn-danger" type="button"><?=htmlspecialchars(oneid_translate('user_mfa.security.revoke'), ENT_QUOTES, 'UTF-8')?></button>
<p><a href="dashboard"><?=htmlspecialchars(oneid_translate('user_mfa.security.back'), ENT_QUOTES, 'UTF-8')?></a></p>
</main><script src="../vendors/bower_components/jquery/dist/jquery.min.js"></script><script>
const csrf=<?=json_encode($csrf)?>;let factor='';
function post(action,data){data=data||{};data[action]='';data._csrf_token=csrf;return $.ajax({type:'POST',url:'../lib/q_func',dataType:'json',data:data});}
function message(text,ok){$('#mfa-message').attr('class',ok?'alert alert-success':'alert alert-danger').text(text);}
$('#mfa-enroll').on('click',function(){post('user_mfa_totp_enroll',{device_label:$('#device-label').val()}).done(function(r){factor=r.factor_id;$('#mfa-qr').attr('src','user-mfa-totp-qr?factor_id='+encodeURIComponent(factor));$('#mfa-confirm-panel').prop('hidden',false);message('Enrollment started.',true);}).fail(function(){message('Enrollment failed.',false);});});
$('#mfa-confirm').on('click',function(){post('user_mfa_totp_confirm',{factor_id:factor,code:$('#mfa-code').val()}).done(function(){message('Microsoft Authenticator is active.',true);$('#mfa-confirm-panel').prop('hidden',true);}).fail(function(){message('Confirmation failed.',false);});});
$('#mfa-revoke').on('click',function(){post('user_mfa_totp_revoke',{code:$('#mfa-revoke-code').val(),reason:'SELF_SERVICE'}).done(function(r){message('Authenticator revoked. Sign in again.',true);setTimeout(function(){location.href=r.redirect_uri||'../';},1500);}).fail(function(){message('Revocation failed.',false);});});
</script></body></html>
