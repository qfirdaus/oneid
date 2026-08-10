<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/session_security.php'; oneid_start_secure_session();
require_once __DIR__.'/../lib/config.php';
require_once __DIR__.'/../lib/request_security.php';
require_once __DIR__.'/../lib/environment_banner.php';
$transaction=(string)($_SESSION['user_mfa_pending_transaction']??'');
$user=(string)($_SESSION['user_mfa_pending_user']??'');
if($transaction===''||$user===''||oneid_is_authenticated()){header('Location: '.APP_URL.'/',true,303);exit;}
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$statement=$pdo->prepare(
  "SELECT t.transaction_status,t.expires_at,p.email_enabled,p.totp_enabled,
          EXISTS(SELECT 1 FROM user_mfa_factors f WHERE f.u_id=t.u_id AND f.factor_type='TOTP' AND f.factor_status='ACTIVE') active_totp,
          COALESCE((SELECT preferred_factor FROM user_mfa_preferences x WHERE x.u_id=t.u_id),'EMAIL_OTP') preferred_factor,
          u.data5 email
     FROM user_login_mfa_transactions t
     JOIN user_login_mfa_policy p ON p.singleton_key=1
     JOIN user_tbl u ON u.u_id=t.u_id
    WHERE t.transaction_id=:transaction AND t.u_id=:user"
);
$statement->execute([':transaction'=>$transaction,':user'=>$user]);$state=$statement->fetch();
if(!is_array($state)||$state['transaction_status']!=='PENDING'||strtotime((string)$state['expires_at'])<time()){unset($_SESSION['user_mfa_pending_transaction'],$_SESSION['user_mfa_pending_user']);header('Location: '.APP_URL.'/',true,303);exit;}
$email=(string)$state['email'];$at=strpos($email,'@');$masked=$at===false?'':substr($email,0,1).'***'.substr($email,max(1,$at-1));
$activeTotp=(int)$state['active_totp']===1;
$totp=(int)$state['totp_enabled']===1&&$activeTotp;
$preferredTotp=$totp&&(string)$state['preferred_factor']==='TOTP';
$totpUnavailableKey=$activeTotp?'stepup.unavailable':'stepup.not_registered';
$h=static fn(string$key):string=>htmlspecialchars(oneid_translate($key),ENT_QUOTES,'UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
?><!doctype html><html lang="<?=htmlspecialchars(oneid_current_locale(),ENT_QUOTES,'UTF-8')?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=$h('user_mfa.title.challenge')?> | OneID@UPNM</title><link rel="stylesheet" href="../dist/css/user-mfa-flow.css?v=20260730-5"><link rel="stylesheet" href="../dist/css/oneid-environment-banner.css?v=20260810-1"></head>
<body class="user-mfa-flow<?=oneid_environment_body_class()?>"><?php oneid_render_environment_banner(); ?><main class="mfa-shell"><aside class="mfa-brand"><img class="mfa-logo" src="../img/logo_oneid-1.png" alt="OneID@UPNM"><div class="mfa-brand-copy"><span class="mfa-eyebrow"><?=$h('stepup.protected_access')?></span><h1><?=$h('user_mfa.title.challenge')?></h1><p><?=$h('user_mfa.challenge.security_intro')?></p></div></aside>
<section class="mfa-content"><header class="mfa-top"><div><h2><?=$h('stepup.verify_identity')?></h2><p><?=$h('stepup.choose_available')?></p></div><span class="mfa-badge"><?=$h('user_mfa.security.badge')?></span></header><div id="message"></div>
<section class="mfa-card"><h3><?=$h('stepup.method_title')?></h3><p class="mfa-intro"><?=$h('stepup.method_intro')?></p>
<div class="mfa-field"><label for="factor"><?=$h('stepup.choose_method')?></label><select id="factor" class="mfa-control"><option value="EMAIL_OTP" <?=$preferredTotp?'':'selected'?>><?=$h('stepup.email_title')?></option><option value="TOTP" <?=$preferredTotp?'selected':''?> <?=$totp?'':'disabled'?>>Microsoft Authenticator<?=$totp?'':' — '.$h($totpUnavailableKey)?></option></select></div>
<button id="continue" class="mfa-button"><?=$h('stepup.continue')?></button>
<div id="emailBox" class="mfa-factor mfa-hidden"><strong><?=$h('stepup.email_title')?></strong><small><?=$h('stepup.email_will_send')?> <?=htmlspecialchars($masked,ENT_QUOTES,'UTF-8')?></small><button id="sendEmail" class="mfa-button mfa-secondary"><?=$h('stepup.email_send')?></button><div class="mfa-field"><label for="emailCode"><?=$h('stepup.six_digit_code')?></label><input id="emailCode" class="mfa-control mfa-otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000"></div><button id="verifyEmail" class="mfa-button"><?=$h('stepup.email_verify')?></button></div>
<div id="totpBox" class="mfa-factor mfa-hidden"><strong>Microsoft Authenticator</strong><small><?=$h('stepup.authenticator_hint')?></small><div class="mfa-field"><label for="totpCode"><?=$h('stepup.six_digit_code')?></label><input id="totpCode" class="mfa-control mfa-otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000"></div><button id="verifyTotp" class="mfa-button"><?=$h('stepup.authenticator_verify')?></button></div>
</section><a class="mfa-back" href="../">&#8592; <?=$h('stepup.back')?></a><footer class="mfa-foot"><span>OneID@UPNM &bull; <?=$h('user_mfa.security.footer')?></span><span><?=$h('user_mfa.security.department')?></span></footer></section></main>
<script>
const api=<?=json_encode(APP_URL.'/lib/q_func.php')?>,csrf=<?=json_encode(oneid_csrf_token())?>,transaction=<?=json_encode($transaction)?>;let challenge='';
const messageElement=document.getElementById('message'),factorElement=document.getElementById('factor'),emailBoxElement=document.getElementById('emailBox'),totpBoxElement=document.getElementById('totpBox');
const sendEmailButton=document.getElementById('sendEmail'),verifyEmailButton=document.getElementById('verifyEmail'),verifyTotpButton=document.getElementById('verifyTotp');
const loginText=<?=json_encode([
  'send' => oneid_translate('stepup.email_send'),
  'sending' => oneid_translate('user_mfa.login.sending'),
  'verifyEmail' => oneid_translate('stepup.email_verify'),
  'verifyTotp' => oneid_translate('stepup.authenticator_verify'),
  'verifying' => oneid_translate('user_mfa.login.verifying'),
  'sent' => oneid_translate('user_mfa.login.sent'),
  'resend' => oneid_translate('user_mfa.login.resend'),
  'cooldown' => oneid_translate('user_mfa.login.cooldown'),
  'rateLimited' => oneid_translate('user_mfa.login.rate_limited'),
  'deliveryFailed' => oneid_translate('user_mfa.login.delivery_failed'),
  'requestFirst' => oneid_translate('user_mfa.login.request_first'),
  'codeRequired' => oneid_translate('user_mfa.login.code_required'),
  'codeInvalid' => oneid_translate('user_mfa.login.code_invalid'),
  'codeExpired' => oneid_translate('user_mfa.login.code_expired'),
  'failed' => oneid_translate('user_mfa.security.failed'),
],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?>;
function msg(text,ok=false){messageElement.innerHTML='<div class="mfa-message '+(ok?'mfa-ok':'mfa-bad')+'"></div>';messageElement.firstChild.textContent=text}
async function post(action,data={}){const response=await fetch(api,{method:'POST',headers:{'X-CSRF-Token':csrf,'Accept':'application/json'},body:new URLSearchParams({_csrf_token:csrf,[action]:'1',transaction_id:transaction,...data})});const result=await response.json();if(!response.ok||result.status===0)throw result;return result}
function errorText(error){const code=String(error&&error.code||'');return({USER_MFA_RESEND_COOLDOWN:loginText.cooldown,USER_MFA_RATE_LIMITED:loginText.rateLimited,USER_MFA_DELIVERY_FAILED:loginText.deliveryFailed,USER_MFA_VERIFICATION_FAILED:loginText.codeInvalid,USER_MFA_CHALLENGE_EXPIRED:loginText.codeExpired,USER_MFA_PENDING_EXPIRED:loginText.codeExpired}[code]||loginText.failed)+(error&&error.correlation_id?' ['+error.correlation_id+']':'')}
function bindCodeEnter(input,button){input.setAttribute('aria-keyshortcuts','Enter');input.addEventListener('keydown',event=>{if(event.key!=='Enter'||event.isComposing||event.repeat)return;event.preventDefault();if(!button.disabled)button.click()})}
let resendTimer=null;
function startResend(seconds){clearInterval(resendTimer);let remaining=Math.max(1,Number(seconds)||60);sendEmailButton.disabled=true;const tick=()=>{sendEmailButton.textContent=loginText.resend+' ('+remaining+'s)';remaining--;if(remaining<0){clearInterval(resendTimer);sendEmailButton.disabled=false;sendEmailButton.textContent=loginText.resend}};tick();resendTimer=setInterval(tick,1000)}
document.getElementById('continue').addEventListener('click',()=>{emailBoxElement.classList.add('mfa-hidden');totpBoxElement.classList.add('mfa-hidden');(factorElement.value==='TOTP'?totpBoxElement:emailBoxElement).classList.remove('mfa-hidden')});
sendEmailButton.addEventListener('click',async()=>{try{sendEmailButton.disabled=true;sendEmailButton.textContent=loginText.sending;const r=await post('user_mfa_email_request');challenge=r.challenge_id;msg(loginText.sent+' '+r.masked_email,true);startResend(r.resend_after_seconds)}catch(e){sendEmailButton.disabled=false;sendEmailButton.textContent=loginText.send;msg(errorText(e))}});
function completeLogin(redirect){location.replace(new URL(String(redirect||'page/dashboard').replace(/^page\//,'/page/'),location.origin).href)}
verifyEmailButton.addEventListener('click',async()=>{const code=document.getElementById('emailCode').value.trim();if(challenge===''){msg(loginText.requestFirst);return}if(!/^[0-9]{6}$/.test(code)){msg(loginText.codeRequired);return}try{verifyEmailButton.disabled=true;verifyEmailButton.textContent=loginText.verifying;const r=await post('user_mfa_email_verify',{challenge_id:challenge,code});completeLogin(r.redirect_uri)}catch(e){verifyEmailButton.disabled=false;verifyEmailButton.textContent=loginText.verifyEmail;msg(errorText(e))}});
verifyTotpButton.addEventListener('click',async()=>{const code=document.getElementById('totpCode').value.trim();if(!/^[0-9]{6}$/.test(code)){msg(loginText.codeRequired);return}try{verifyTotpButton.disabled=true;verifyTotpButton.textContent=loginText.verifying;const r=await post('user_mfa_totp_verify_login',{code});completeLogin(r.redirect_uri)}catch(e){verifyTotpButton.disabled=false;verifyTotpButton.textContent=loginText.verifyTotp;msg(errorText(e))}});
bindCodeEnter(document.getElementById('emailCode'),verifyEmailButton);
bindCodeEnter(document.getElementById('totpCode'),verifyTotpButton);
</script></body></html>
