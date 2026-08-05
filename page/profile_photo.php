<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/session_security.php';
oneid_start_secure_session();
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/request_security.php';
require_once __DIR__ . '/../app/ProfilePhoto/ProfilePhotoResponder.php';

oneid_require_authenticated_page();
oneid_require_active_sso_page($operation);

$userId = (string) ($_SESSION['login_user'] ?? '');
$requestedUserId = trim((string) ($_GET['user_id'] ?? ''));
if ($requestedUserId !== '') {
    oneid_require_admin_page();
    if (preg_match('/\A[A-Za-z0-9._@-]{1,20}\z/', $requestedUserId) !== 1) {
        \OneId\App\ProfilePhoto\ProfilePhotoResponder::sendFallback(PROJECT_ROOT . '/public/img/default-profile.svg');
    }
    $userId = $requestedUserId;
}

$user = $operation->get_specific_user_info($userId);
if (!is_array($user)) {
    \OneId\App\ProfilePhoto\ProfilePhotoResponder::sendFallback(PROJECT_ROOT . '/public/img/default-profile.svg');
}
\OneId\App\ProfilePhoto\ProfilePhotoResponder::send($user, PROJECT_ROOT . '/public/img/default-profile.svg');
