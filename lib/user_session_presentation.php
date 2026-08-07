<?php

declare(strict_types=1);

/** @return array<string, mixed> */
function oneid_user_session_presentation_config(bool $pageEligible = true): array
{
    $enabled = $pageEligible
        && filter_var(oneid_config('ONEID_USER_SESSION_WARNING_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN)
        && ($_SESSION['login_status'] ?? '') === 'true';

    return [
        'enabled' => $enabled,
        'apiUrl' => APP_URL . '/lib/q_func.php',
        'landingUrl' => APP_URL . '/',
        'csrfToken' => $enabled ? oneid_csrf_token() : '',
        'warningSeconds' => 120,
        'text' => [
            'eyebrow' => oneid_translate('user_session.eyebrow'),
            'warningTitle' => oneid_translate('user_session.warning_title'),
            'warningBody' => oneid_translate('user_session.warning_body'),
            'otherAppsNote' => oneid_translate('user_session.other_apps_note'),
            'stayConnected' => oneid_translate('user_session.stay_connected'),
            'endSession' => oneid_translate('user_session.end_session'),
            'renewedTitle' => oneid_translate('user_session.renewed_title'),
            'renewedBody' => oneid_translate('user_session.renewed_body'),
            'expiredTitle' => oneid_translate('user_session.expired_title'),
            'expiredBody' => oneid_translate('user_session.expired_body'),
            'revokedBody' => oneid_translate('user_session.revoked_body'),
            'inactiveBody' => oneid_translate('user_session.inactive_body'),
            'requestFailed' => oneid_translate('user_session.request_failed'),
            'ok' => oneid_translate('user_session.ok'),
        ],
    ];
}
