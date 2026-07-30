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
$authorized = filter_var(oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED', false), FILTER_VALIDATE_BOOLEAN);
if ($mode === 'OFF' || !$authorized) {
    http_response_code(404);
    exit('Not found');
}

$user = (string) ($_SESSION['login_user'] ?? '');
$pdo = new PDO(
    DB_DSN,
    DB_USERNAME,
    DB_PASSWORD,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$pilot = $pdo->prepare(
    "SELECT COUNT(*) FROM user_login_mfa_pilot_users
      WHERE u_id=:user AND pilot_status='ACTIVE'"
);
$pilot->execute([':user' => $user]);
if ((int) $pilot->fetchColumn() !== 1) {
    http_response_code(403);
    exit('Pilot access required');
}
$state = $pdo->prepare(
    "SELECT p.email_enabled,p.totp_enabled,
            (SELECT data5 FROM user_tbl u WHERE u.u_id=:user3 LIMIT 1) email,
            EXISTS(SELECT 1 FROM user_mfa_factors f
                    WHERE f.u_id=:user AND f.factor_type='TOTP' AND f.factor_status='ACTIVE') active_totp,
            COALESCE((SELECT preferred_factor FROM user_mfa_preferences x WHERE x.u_id=:user2),'EMAIL_OTP') preferred_factor
       FROM user_login_mfa_policy p
      WHERE p.singleton_key=1"
);
$state->execute([':user' => $user, ':user2' => $user, ':user3' => $user]);
$security = $state->fetch() ?: [];
$activeTotp = (int) ($security['active_totp'] ?? 0) === 1;
$totpEnabled = (int) ($security['totp_enabled'] ?? 0) === 1;
$preferred = (string) ($security['preferred_factor'] ?? 'EMAIL_OTP');
$email = trim((string) ($security['email'] ?? ''));
$maskedEmail = oneid_translate('stepup.none');
if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
    [$emailLocal, $emailDomain] = explode('@', $email, 2);
    $maskedEmail = substr($emailLocal, 0, 1)
        . str_repeat('*', max(2, min(8, strlen($emailLocal) - 1)))
        . '@' . $emailDomain;
}
$h = static fn (string $key): string => htmlspecialchars(oneid_translate($key), ENT_QUOTES, 'UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
?>
<!doctype html>
<html lang="<?=htmlspecialchars(oneid_current_locale(), ENT_QUOTES, 'UTF-8')?>">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?=$h('user_mfa.security.title')?> | OneID@UPNM</title>
  <link rel="stylesheet" href="../dist/css/user-mfa-flow.css?v=20260730-5">
</head>
<body class="user-mfa-flow">
<main class="mfa-shell">
  <aside class="mfa-brand">
    <img class="mfa-logo" src="../img/logo_oneid-1.png" alt="OneID@UPNM">
    <div class="mfa-brand-copy">
      <span class="mfa-eyebrow"><?=$h('stepup.protected_access')?></span>
      <h1><?=$h('user_mfa.security.heading')?></h1>
      <p><?=$h('user_mfa.security.intro')?></p>
    </div>
    <div class="mfa-trust"><span class="mfa-trust-icon">&#128737;</span><span><?=$h('user_mfa.security.trust')?></span></div>
  </aside>
  <section class="mfa-content">
    <header class="mfa-top">
      <div><h2><?=$activeTotp ? $h('user_mfa.security.title') : $h('stepup.enroll_authenticator')?></h2><p><?=$activeTotp ? $h('stepup.method_intro') : $h('stepup.step2_enroll')?></p></div>
      <span class="mfa-badge"><?=$h('user_mfa.security.badge')?></span>
    </header>
    <div id="mfaMessage" role="status" aria-live="polite"></div>

    <?php if (!$activeTotp): ?>
    <div class="mfa-message mfa-ok"><?=$h('user_mfa.security.enroll_ready')?></div>
    <div class="mfa-status-strip"><?=htmlspecialchars(oneid_translate('user_mfa.security.status', [
        'feature' => oneid_translate('stepup.active'),
        'email' => $maskedEmail,
        'authenticator' => oneid_translate('stepup.not_registered'),
    ]), ENT_QUOTES, 'UTF-8')?></div>
    <?php else: ?>
    <section class="mfa-card">
      <h3><?=$h('stepup.method_title')?></h3>
      <div class="mfa-status-grid">
        <div class="mfa-status"><strong><?=$h('stepup.email_title')?></strong><span class="mfa-state is-active"><?=$h('stepup.active')?></span></div>
        <div class="mfa-status"><strong>Microsoft Authenticator</strong><span id="totpState" class="mfa-state <?=$activeTotp ? 'is-active' : ''?>"><?=$activeTotp ? $h('stepup.active') : $h('stepup.not_registered')?></span></div>
      </div>
      <p class="mfa-intro"><?=$h('user_mfa.security.preference_help')?></p>
      <div class="mfa-field">
        <label for="preferredFactor"><?=$h('stepup.choose_method')?></label>
        <select id="preferredFactor" class="mfa-control">
          <option value="EMAIL_OTP" <?=$preferred === 'EMAIL_OTP' ? 'selected' : ''?>><?=$h('stepup.email_title')?></option>
          <?php if ($activeTotp && $totpEnabled): ?><option value="TOTP" <?=$preferred === 'TOTP' ? 'selected' : ''?>>Microsoft Authenticator</option><?php endif; ?>
        </select>
      </div>
      <button id="savePreference" class="mfa-button mfa-secondary" type="button"><?=$h('admin.configuration.save_preference')?></button>
    </section>
    <?php endif; ?>

    <?php if ($totpEnabled && !$activeTotp): ?>
    <section class="mfa-card mfa-setup-card" id="enrollCard">
      <div class="mfa-settings-title"><h3><?=$h('stepup.enroll_authenticator')?></h3><span class="mfa-settings-tag"><?=$h('user_mfa.security.setup_tag')?></span></div>
      <p class="mfa-intro"><?=$h('user_mfa.security.setup_intro')?></p>
      <div class="mfa-field"><label for="deviceLabel"><?=$h('stepup.device_name')?></label><input id="deviceLabel" class="mfa-control" maxlength="100" value="<?=$h('stepup.device_default')?>"><small class="mfa-help"><?=$h('stepup.device_help')?></small></div>
      <button id="beginEnrollment" class="mfa-button" type="button"><?=$h('stepup.generate_qr')?></button>
      <div id="confirmPanel" class="mfa-enrollment-provision mfa-hidden">
        <div class="mfa-provision-grid">
          <div class="mfa-qr-panel">
            <h4><?=$h('stepup.scan_qr')?></h4><small class="mfa-help"><?=$h('stepup.qr_warning')?></small>
            <img id="totpQr" class="mfa-qr" width="260" height="260" alt="QR enrollment Microsoft Authenticator">
            <span class="mfa-key-label"><?=$h('stepup.manual_key')?></span><div id="manualKey" class="mfa-mono"></div>
            <details class="mfa-alternative"><summary><?=$h('stepup.alternative')?></summary><p><?=$h('stepup.alternative_help')?></p><a id="authenticatorUri" href="#"><?=$h('stepup.open_authenticator')?></a></details>
          </div>
          <div class="mfa-guide-panel">
            <h4><?=$h('stepup.need_authenticator')?></h4><p class="mfa-intro"><?=$h('stepup.install_intro')?></p>
            <div class="mfa-store-links">
              <a class="mfa-store-link" href="https://go.microsoft.com/fwlink/?linkid=2273143" target="_blank" rel="noopener noreferrer"><span class="mfa-store-icon">&#128241;</span><span><small><?=$h('stepup.download_for')?></small><strong>iPhone / App Store</strong></span></a>
              <a class="mfa-store-link" href="https://go.microsoft.com/fwlink/?linkid=2273142" target="_blank" rel="noopener noreferrer"><span class="mfa-store-icon">&#9654;</span><span><small><?=$h('stepup.download_for')?></small><strong>Android / Google Play</strong></span></a>
            </div>
            <a class="mfa-official-help" href="https://support.microsoft.com/en-us/authenticator/download-microsoft-authenticator" target="_blank" rel="noopener noreferrer"><?=$h('stepup.official_guide')?></a>
            <ol class="mfa-guide-list"><li><?=$h('stepup.guide_1')?></li><li><?=$h('stepup.guide_2')?></li><li><?=$h('stepup.guide_3')?></li><li><?=$h('stepup.guide_4')?></li></ol>
            <div class="mfa-field"><label for="confirmCode"><?=$h('stepup.first_code')?></label><input id="confirmCode" class="mfa-control mfa-otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000"></div>
            <button id="confirmEnrollment" class="mfa-button" type="button"><?=$h('stepup.confirm_enrollment')?></button>
          </div>
        </div>
      </div>
    </section>
    <?php elseif ($activeTotp): ?>
    <section class="mfa-card">
      <h3><?=$h('stepup.revoke_current')?></h3>
      <p class="mfa-intro"><?=$h('user_mfa.security.revoke_warning')?></p>
      <div class="mfa-field"><label for="revokeCode"><?=$h('user_mfa.security.revoke_code')?></label><input id="revokeCode" class="mfa-control mfa-otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000"></div>
      <button id="revokeAuthenticator" class="mfa-button mfa-danger" type="button"><?=$h('user_mfa.security.revoke')?></button>
    </section>
    <?php endif; ?>

    <a class="mfa-back" href="dashboard">&#8592; <?=$h('user_mfa.security.back')?></a>
    <footer class="mfa-foot"><span>OneID@UPNM &bull; <?=$h('user_mfa.security.footer')?></span><span><?=$h('user_mfa.security.department')?></span></footer>
  </section>
</main>
<script>
const api='../lib/q_func',csrf=<?=json_encode(oneid_csrf_token())?>;
const messageElement=document.getElementById('mfaMessage');
let factorId='';
function showMessage(text,ok=false){messageElement.innerHTML='<div class="mfa-message '+(ok?'mfa-ok':'mfa-bad')+'"></div>';messageElement.firstChild.textContent=text}
async function post(action,data={}){const response=await fetch(api,{method:'POST',headers:{'X-CSRF-Token':csrf,'Accept':'application/json'},body:new URLSearchParams({_csrf_token:csrf,[action]:'1',...data})});const result=await response.json();if(!response.ok||result.status===0)throw result;return result}
<?php if ($activeTotp): ?>
document.getElementById('savePreference').addEventListener('click',async()=>{try{await post('user_mfa_totp_preference',{factor:document.getElementById('preferredFactor').value});showMessage(<?=json_encode(oneid_translate('user_mfa.security.preference_saved'))?>,true)}catch(e){showMessage(e.code||<?=json_encode(oneid_translate('user_mfa.security.failed'))?>)}});
<?php endif; ?>
<?php if ($totpEnabled && !$activeTotp): ?>
document.getElementById('beginEnrollment').addEventListener('click',async()=>{try{const result=await post('user_mfa_totp_enroll',{device_label:document.getElementById('deviceLabel').value});factorId=result.factor_id;const uri=String(result.provisioning_uri||'');document.getElementById('totpQr').src='user-mfa-totp-qr?factor_id='+encodeURIComponent(factorId);document.getElementById('authenticatorUri').href=uri||'#';document.getElementById('manualKey').textContent=uri?(new URL(uri)).searchParams.get('secret')||'':'';document.getElementById('confirmPanel').classList.remove('mfa-hidden');showMessage(<?=json_encode(oneid_translate('user_mfa.security.enrollment_started'))?>,true)}catch(e){showMessage(e.code||<?=json_encode(oneid_translate('user_mfa.security.failed'))?>)}});
document.getElementById('confirmEnrollment').addEventListener('click',async()=>{try{await post('user_mfa_totp_confirm',{factor_id:factorId,code:document.getElementById('confirmCode').value});showMessage(<?=json_encode(oneid_translate('user_mfa.security.confirmed'))?>,true);setTimeout(()=>location.reload(),900)}catch(e){showMessage(e.code||<?=json_encode(oneid_translate('user_mfa.security.failed'))?>)}});
<?php elseif ($activeTotp): ?>
document.getElementById('revokeAuthenticator').addEventListener('click',async()=>{const button=document.getElementById('revokeAuthenticator'),code=document.getElementById('revokeCode').value.trim();if(!/^[0-9]{6}$/.test(code)){showMessage(<?=json_encode(oneid_translate('user_mfa.login.code_required'))?>);return}if(!window.confirm(<?=json_encode(oneid_translate('user_mfa.security.revoke_confirm'))?>)){return}try{button.disabled=true;button.textContent=<?=json_encode(oneid_translate('user_mfa.security.revoking'))?>;const result=await post('user_mfa_totp_revoke',{code,reason:'SELF_SERVICE'});showMessage(<?=json_encode(oneid_translate('user_mfa.security.revoked'))?>,true);setTimeout(()=>location.href=result.redirect_uri||'../',900)}catch(e){button.disabled=false;button.textContent=<?=json_encode(oneid_translate('user_mfa.security.revoke'))?>;showMessage(e.code||<?=json_encode(oneid_translate('user_mfa.security.failed'))?>)}});
<?php endif; ?>
</script>
</body></html>
