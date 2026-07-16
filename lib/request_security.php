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
            'admin_get_password_recovery_settings',
            'update_password_recovery',
            'test_password_recovery_email',
            'preview_configuration_update',
            'action_add_new_webapp_category',
            'action_remove_app_category',
            'action_add_new_app',
            'action_edit_app_info',
            'admin_search_user_account',
            'admin_preview_sync_user',
            'admin_add_sync_user',
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
    ];
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

    if ($matchedLevel === 'admin') {
        if (!oneid_is_authenticated()) {
            oneid_json_deny(401, 'Authentication required');
        }
        if (!oneid_is_admin()) {
            oneid_json_deny(403, 'Administrator access required');
        }
    }

    if ($matchedLevel !== 'public' && oneid_is_authenticated() && $operation !== null) {
        $state=$operation->get_password_change_requirement((string)$_SESSION['login_user']);
        if(!is_array($state)||(int)($state['avail_status']??0)!==1){oneid_json_deny(401,'Account is not active');}
        $_SESSION['password_change_required']=(int)($state['password_change_required']??0);
        if($_SESSION['password_change_required']===1&&!in_array($matchedActions[0],['check_default_password','action_change_password'],true)){
            if(!headers_sent()){http_response_code(403);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');}
            echo json_encode(['status'=>403,'code'=>'UC3_PASSWORD_CHANGE_REQUIRED','error'=>'Password change required before this action']);exit;
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
