<?php

/**
 * Shared authentication, authorization and CSRF controls for OneID web flows.
 */
function oneid_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('A PHP session is required for CSRF protection.');
    }

    if (empty($_SESSION['oneid_csrf_token']) || !is_string($_SESSION['oneid_csrf_token'])) {
        $_SESSION['oneid_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['oneid_csrf_token'];
}

function oneid_is_authenticated(): bool
{
    return ($_SESSION['login_status'] ?? '') === 'true'
        && isset($_SESSION['login_user'])
        && trim((string) $_SESSION['login_user']) !== '';
}

function oneid_is_admin(): bool
{
    return oneid_is_authenticated()
        && (string) ($_SESSION['login_user_type'] ?? '') === '1';
}

function oneid_json_deny(int $status, string $message): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }

    echo json_encode([
        'error' => $message,
        'status' => $status,
    ]);
    exit;
}

function oneid_request_csrf_token(): string
{
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_string($headerToken) && $headerToken !== '') {
        return $headerToken;
    }

    $postToken = $_POST['_csrf_token'] ?? '';
    return is_string($postToken) ? $postToken : '';
}

function oneid_authenticated_sso_token_is_active(object $operation): bool
{
    if (!oneid_is_authenticated() || !method_exists($operation, 'is_specific_token_active')) {
        return false;
    }

    $token = oneid_sso_cookie_token();
    return $token !== '' && $operation->is_specific_token_active(
        (string) $_SESSION['login_user'],
        $token
    ) === true;
}

function oneid_deny_revoked_sso_token(): never
{
    oneid_clear_local_authenticated_session();
    oneid_json_deny(401, 'SSO session token is no longer active');
}

function oneid_require_csrf(): void
{
    $expectedToken = oneid_csrf_token();
    $providedToken = oneid_request_csrf_token();

    if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
        oneid_json_deny(403, 'Invalid CSRF token');
    }
}

function oneid_q_func_action_map(): array
{
    return [
        'public' => [
            'auth',
            'action_forgot_password',
            'action_submit_OTP',
            'action_reset_password',
        ],
        'user' => [
            'check_default_password',
            'action_change_password',
            'admin_get_all_token_for_specific_user',
            'get_specific_user_app_list',
            'user_set_app_favourite',
            'go_to_service_provider',
            'user_signoff_security_sessions',
            'update_specific_token_datetime',
        ],
        'admin' => [
            'admin_search_keyword_user',
            'admin_get_app_all_group',
            'admin_get_all_service_provider',
            'admin_get_sso_settings',
            'admin_get_configuration_history',
            'admin_get_password_recovery_settings',
            'update_password_recovery',
            'test_password_recovery_email',
            'preview_configuration_update',
            'action_add_new_webapp_category',
            'action_rename_webapp_category',
            'action_remove_app_category',
            'action_add_new_app',
            'action_edit_app_info',
            'admin_search_user_account',
            'admin_preview_sync_user',
            'admin_add_sync_user',
            'admin_apply_full_sync',
            'admin_apply_operational_sync',
            'admin_get_get_specific_user_profile_info',
            'action_add_new_user_manual_check_user_id',
            'admin_preview_specific_user_resync',
            'admin_apply_specific_user_resync',
            'admin_reactivate_user_record',
            'admin_deactivate_user_record',
            'action_add_new_category',
            'admin_get_all_user_category',
            'admin_get_specific_category_user_listing',
            'admin_get_category_site_listing',
            'admin_get_category_site_listing_add_new_site',
            'add_acl_category',
            'remove_acl_category',
            'admin_remove_category',
            'admin_save_user_profile',
            'add_new_specific_apps_to_user',
            'admin_get_specific_service_provider',
            'action_remove_app',
            'admin_get_all_blacklist_record',
            'admin_set_deny_access_record',
            'update_configuration',
            'admin_uplift_blacklist_record',
            'admin_get_all_token_for_all_active_user',
            'get_specific_user_sp_access_list',
            'get_add_new_specific_apps_to_accissible_list',
            'admin_get_audit_range',
            'admin_get_sync_sessions',
            'admin_get_sync_log_detail',
            'admin_reset_password_user',
        ],
        'step_up' => [
            'admin_step_up_status',
            'admin_step_up_request_email',
            'admin_step_up_verify_email',
            'admin_step_up_verify_totp',
        ],
        'mfa_setup' => [
            'admin_totp_enroll',
            'admin_totp_confirm',
            'admin_totp_revoke',
            'admin_mfa_set_preference',
            'admin_2fa_update_lifetime',
            'admin_2fa_bootstrap_enable',
        ],
    ];
}

function oneid_admin_action_purpose(string $action): string
{
    $securityConfiguration = [
        'update_password_recovery',
        'test_password_recovery_email',
        'preview_configuration_update',
        'update_configuration',
    ];
    return in_array($action, $securityConfiguration, true)
        ? 'SECURITY_CONFIGURATION_CHANGE'
        : 'ADMIN_ACCESS';
}

function oneid_admin_step_up_decision(object $operation, string $purpose): array
{
    $allowedPurposes = ['ADMIN_ACCESS','SECURITY_CONFIGURATION_CHANGE','ACTIVE_SESSION_REVOCATION'];
    if (!in_array($purpose, $allowedPurposes, true) || !oneid_is_admin()) {
        return ['allowed'=>false,'reason'=>'STEP_UP_REQUIRED','feature_enabled'=>null];
    }
    if (!method_exists($operation, 'admin_step_up_authorization_state')) {
        return ['allowed'=>false,'reason'=>'STEP_UP_STATE_UNAVAILABLE','feature_enabled'=>null];
    }
    $sessionId = session_id();
    if ($sessionId === '') {
        return ['allowed'=>false,'reason'=>'STEP_UP_REQUIRED','feature_enabled'=>null];
    }
    $browser = hash('sha256', substr((string)($_SERVER['HTTP_USER_AGENT']??''), 0, 1000));
    $state = $operation->admin_step_up_authorization_state(
        (string)$_SESSION['login_user'], hash('sha256',$sessionId), $browser, $purpose
    );
    if (!is_array($state)||(int)($state['u_type']??0)!==1||(int)($state['avail_status']??0)!==1) {
        return ['allowed'=>false,'reason'=>'STEP_UP_STATE_UNAVAILABLE','feature_enabled'=>null];
    }
    $enabled=(int)($state['admin_2fa_enabled']??1)===1;
    if (!$enabled) {return ['allowed'=>true,'reason'=>'STEP_UP_DISABLED','feature_enabled'=>false];}
    if ((int)($state['exact_valid']??0)>0) {return ['allowed'=>true,'reason'=>'STEP_UP_GRANTED','feature_enabled'=>true,'remaining_seconds'=>max(0,(int)($state['exact_remaining_seconds']??0))];}
    if ((int)($state['exact_expired']??0)>0) {return ['allowed'=>false,'reason'=>'STEP_UP_EXPIRED','feature_enabled'=>true];}
    if ((int)($state['other_valid']??0)>0) {return ['allowed'=>false,'reason'=>'STEP_UP_PURPOSE_MISMATCH','feature_enabled'=>true];}
    return ['allowed'=>false,'reason'=>'STEP_UP_REQUIRED','feature_enabled'=>true];
}

function oneid_audit_step_up_rejection(object $operation,string $purpose,string $reason): string
{
    $correlation=bin2hex(random_bytes(8));$ip=(string)($_SERVER['REMOTE_ADDR']??'');
    if(filter_var($ip,FILTER_VALIDATE_IP)===false){$ip='0.0.0.0';}
    $detail=sprintf('admin=%s action=admin_step_up_guard purpose=%s outcome=rejected reason=%s correlation=%s',(string)($_SESSION['login_user']??''),$purpose,$reason,$correlation);
    if(!method_exists($operation,'syslog_record')||$operation->syslog_record(40,$detail,$ip)!==1){return '';}
    return $correlation;
}

function oneid_require_admin_step_up(object $operation,string $purpose,bool $json=true): void
{
    $decision=oneid_admin_step_up_decision($operation,$purpose);if($decision['allowed']){return;}
    $correlation=oneid_audit_step_up_rejection($operation,$purpose,(string)$decision['reason']);
    if($correlation===''){oneid_json_deny(503,'Step-up authorization unavailable');}
    if($json){if(!headers_sent()){http_response_code(403);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');}echo json_encode(['status'=>403,'code'=>$decision['reason'],'error'=>'Administrator step-up authentication required','purpose'=>$purpose,'correlation_id'=>$correlation]);exit;}
    if(!headers_sent()){http_response_code(403);header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: no-store');}echo 'Administrator step-up authentication required. Correlation: '.$correlation;exit;
}

function oneid_complete_step_up_rotation(object $operation,string $purpose,string $correlation): array
{
    $admin=(string)($_SESSION['login_user']??'');$old=session_id();$browser=hash('sha256',substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,1000));
    if($admin===''||$old===''||preg_match('/\A[a-f0-9]{16}\z/',$correlation)!==1||!session_regenerate_id(true))throw new RuntimeException('STEP_UP_SESSION_ROTATION_FAILED');
    $new=session_id();unset($_SESSION['oneid_csrf_token']);$csrf=oneid_csrf_token();
    if(!method_exists($operation,'admin_step_up_rebind_grant')||$operation->admin_step_up_rebind_grant($admin,$purpose,$correlation,hash('sha256',$old),hash('sha256',$new),$browser)!==1)throw new RuntimeException('STEP_UP_GRANT_REBIND_FAILED');
    return['csrf_token'=>$csrf];
}

function oneid_guard_q_func_request(array $post, ?object $operation = null): string
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        oneid_json_deny(405, 'Method not allowed');
    }

    $actionMap = oneid_q_func_action_map();
    $matchedActions = [];
    $matchedLevel = null;

    foreach ($actionMap as $level => $actions) {
        foreach ($actions as $action) {
            if (array_key_exists($action, $post)) {
                $matchedActions[] = $action;
                $matchedLevel = $level;
            }
        }
    }

    if (count($matchedActions) !== 1 || $matchedLevel === null) {
        oneid_json_deny(400, 'Exactly one recognized action is required');
    }

    oneid_require_csrf();

    if ($matchedLevel === 'user' && !oneid_is_authenticated()) {
        oneid_json_deny(401, 'Authentication required');
    }

    if (in_array($matchedLevel, ['admin','step_up','mfa_setup'], true)) {
        if (!oneid_is_authenticated()) {
            oneid_json_deny(401, 'Authentication required');
        }
        if (!oneid_is_admin()) {
            oneid_json_deny(403, 'Administrator access required');
        }
    }

    if ($matchedLevel !== 'public' && oneid_is_authenticated() && $operation !== null) {
        if (!oneid_authenticated_sso_token_is_active($operation)) {
            oneid_deny_revoked_sso_token();
        }
        $state=$operation->get_password_change_requirement((string)$_SESSION['login_user']);
        if(!is_array($state)||(int)($state['avail_status']??0)!==1){oneid_json_deny(401,'Account is not active');}
        $_SESSION['password_change_required']=(int)($state['password_change_required']??0);
        if($_SESSION['password_change_required']===1&&!in_array($matchedActions[0],['check_default_password','action_change_password'],true)){
            if(!headers_sent()){http_response_code(403);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');}
            echo json_encode(['status'=>403,'code'=>'UC3_PASSWORD_CHANGE_REQUIRED','error'=>'Password change required before this action']);exit;
        }
        if($matchedLevel==='admin'){
            oneid_require_admin_step_up($operation,oneid_admin_action_purpose($matchedActions[0]),true);
        }
        if($matchedLevel==='mfa_setup'){
            oneid_require_admin_step_up($operation,'SECURITY_CONFIGURATION_CHANGE',true);
        }
    }

    return $matchedActions[0];
}

function oneid_require_authenticated_page(): void
{
    if (oneid_is_authenticated()) {
        return;
    }

    if (!headers_sent()) {
        header('Location: ' . APP_URL . '/', true, 302);
    }
    exit;
}

function oneid_require_admin_page(): void
{
    if (!oneid_is_authenticated()) {
        if (!headers_sent()) {
            header('Location: ' . APP_URL . '/', true, 302);
        }
        exit;
    }

    if (!oneid_is_admin()) {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo 'Forbidden';
        exit;
    }
}

function oneid_require_active_sso_page(object $operation): void
{
    if (oneid_authenticated_sso_token_is_active($operation)) {
        return;
    }

    oneid_clear_local_authenticated_session();
    if (!headers_sent()) {
        header('Location: ' . APP_URL . '/', true, 302);
    }
    exit;
}
