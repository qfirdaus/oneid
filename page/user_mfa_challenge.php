<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/session_security.php'; oneid_start_secure_session();
require_once __DIR__.'/../lib/config.php';
require_once __DIR__.'/../lib/request_security.php';
$transaction=(string)($_SESSION['user_mfa_pending_transaction']??'');
$user=(string)($_SESSION['user_mfa_pending_user']??'');
if($transaction===''||$user===''||oneid_is_authenticated()){header('Location: '.APP_URL.'/',true,303);exit;}
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$statement=$pdo->prepare(
  "SELECT t.transaction_status,t.expires_at,p.email_enabled,p.totp_enabled,
          EXISTS(SELECT 1 FROM user_mfa_factors f WHERE f.u_id=t.u_id AND f.factor_type='TOTP' AND f.factor_status='ACTIVE') active_totp,
          u.data5 email
     FROM user_login_mfa_transactions t
     JOIN user_login_mfa_policy p ON p.singleton_key=1
     JOIN user_tbl u ON u.u_id=t.u_id
    WHERE t.transaction_id=:transaction AND t.u_id=:user"
);
$statement->execute([':transaction'=>$transaction,':user'=>$user]);$state=$statement->fetch();
if(!is_array($state)||$state['transaction_status']!=='PENDING'||strtotime((string)$state['expires_at'])<time()){unset($_SESSION['user_mfa_pending_transaction'],$_SESSION['user_mfa_pending_user']);header('Location: '.APP_URL.'/',true,303);exit;}
$email=(string)$state['email'];$at=strpos($email,'@');$masked=$at===false?'':substr($email,0,1).'***'.substr($email,max(1,$at-1));
$totp=(int)$state['totp_enabled']===1&&(int)$state['active_totp']===1;
$h=static fn(string$key):string=>htmlspecialchars(oneid_translate($key),ENT_QUOTES,'UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
?><!doctype html><html lang="<?=htmlspecialchars(oneid_current_locale(),ENT_QUOTES,'UTF-8')?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=$h('user_mfa.title.challenge')?> | OneID@UPNM</title><link rel="stylesheet" href="../dist/css/user-mfa-flow.css"></head>
<body class="user-mfa-flow"><main class="mfa-shell"><aside class="mfa-brand"><img class="mfa-logo" src="../img/logo_oneid-1.png" alt="OneID@UPNM"><div class="mfa-brand-copy"><span class="mfa-eyebrow"><?=$h('stepup.protected_access')?></span><h1><?=$h('user_mfa.title.challenge')?></h1><p><?=$h('stepup.security_intro')?></p></div></aside>
<section class="mfa-content"><header class="mfa-top"><div><h2><?=$h('stepup.verify_identity')?></h2><p><?=$h('stepup.choose_available')?></p></div><span class="mfa-badge"><?=$h('user_mfa.security.badge')?></span></header><div id="message"></div>
<section class="mfa-card"><h3><?=$h('stepup.method_title')?></h3><p class="mfa-intro"><?=$h('stepup.method_intro')?></p>
<div class="mfa-field"><label for="factor"><?=$h('stepup.choose_method')?></label><select id="factor" class="mfa-control"><option value="EMAIL_OTP"><?=$h('stepup.email_title')?></option><?php if($totp):?><option value="TOTP">Microsoft Authenticator</option><?php endif;?></select></div>
<button id="continue" class="mfa-button"><?=$h('stepup.continue')?></button>
<div id="emailBox" class="mfa-factor mfa-hidden"><strong><?=$h('stepup.email_title')?></strong><small><?=$h('stepup.email_will_send')?> <?=htmlspecialchars($masked,ENT_QUOTES,'UTF-8')?></small><button id="sendEmail" class="mfa-button mfa-secondary"><?=$h('stepup.email_send')?></button><div class="mfa-field"><label for="emailCode"><?=$h('stepup.six_digit_code')?></label><input id="emailCode" class="mfa-control mfa-otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code"></div><button id="verifyEmail" class="mfa-button"><?=$h('stepup.email_verify')?></button></div>
<div id="totpBox" class="mfa-factor mfa-hidden"><strong>Microsoft Authenticator</strong><small><?=$h('stepup.authenticator_hint')?></small><div class="mfa-field"><label for="totpCode"><?=$h('stepup.six_digit_code')?></label><input id="totpCode" class="mfa-control mfa-otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code"></div><button id="verifyTotp" class="mfa-button"><?=$h('stepup.authenticator_verify')?></button></div>
</section><a class="mfa-back" href="../">&#8592; <?=$h('stepup.back')?></a><footer class="mfa-foot"><span>OneID@UPNM &bull; <?=$h('user_mfa.security.footer')?></span><span><?=$h('user_mfa.security.department')?></span></footer></section></main>
<script>
const api=<?=json_encode(APP_URL.'/lib/q_func.php')?>,csrf=<?=json_encode(oneid_csrf_token())?>,transaction=<?=json_encode($transaction)?>;let challenge='';
const messageElement=document.getElementById('message'),factorElement=document.getElementById('factor'),emailBoxElement=document.getElementById('emailBox'),totpBoxElement=document.getElementById('totpBox');
function msg(text,ok=false){messageElement.innerHTML='<div class="mfa-message '+(ok?'mfa-ok':'mfa-bad')+'"></div>';messageElement.firstChild.textContent=text}
async function post(action,data={}){const response=await fetch(api,{method:'POST',headers:{'X-CSRF-Token':csrf,'Accept':'application/json'},body:new URLSearchParams({_csrf_token:csrf,[action]:'1',transaction_id:transaction,...data})});const result=await response.json();if(!response.ok||result.status===0)throw result;return result}
document.getElementById('continue').addEventListener('click',()=>{emailBoxElement.classList.add('mfa-hidden');totpBoxElement.classList.add('mfa-hidden');(factorElement.value==='TOTP'?totpBoxElement:emailBoxElement).classList.remove('mfa-hidden')});
document.getElementById('sendEmail').addEventListener('click',async()=>{try{const r=await post('user_mfa_email_request');challenge=r.challenge_id;msg(<?=json_encode(oneid_translate('stepup.email_sent'))?>+' '+r.masked_email,true)}catch(e){msg(e.code||<?=json_encode(oneid_translate('user_mfa.security.failed'))?>)}});
document.getElementById('verifyEmail').addEventListener('click',async()=>{try{const r=await post('user_mfa_email_verify',{challenge_id:challenge,code:document.getElementById('emailCode').value});location.replace(r.redirect_uri)}catch(e){msg(e.code||<?=json_encode(oneid_translate('user_mfa.security.failed'))?>)}});
document.getElementById('verifyTotp').addEventListener('click',async()=>{try{const r=await post('user_mfa_totp_verify_login',{code:document.getElementById('totpCode').value});location.replace(r.redirect_uri)}catch(e){msg(e.code||<?=json_encode(oneid_translate('user_mfa.security.failed'))?>)}});
</script></body></html>
