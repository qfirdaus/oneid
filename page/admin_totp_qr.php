<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/session_security.php';
oneid_start_secure_session();
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/SSO_IDP_INC.php';
require_once __DIR__ . '/../lib/request_security.php';
require_once __DIR__ . '/../app/Auth/TotpKeyring.php';
require_once __DIR__ . '/../app/Auth/TotpSecretCipher.php';
require_once __DIR__ . '/../app/Auth/Totp.php';
require_once __DIR__ . '/../app/Auth/QrLogoOverlay.php';
require_once __DIR__ . '/../lib/vendor/phpqrcode/qrlib.php';

oneid_require_admin_page();
oneid_require_active_sso_page($operation);
oneid_require_admin_step_up($operation, 'SECURITY_CONFIGURATION_CHANGE', false);

$factor = filter_input(INPUT_GET, 'factor_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($factor === false || $factor === null) {
    http_response_code(400);
    exit('Invalid factor');
}

$session = hash('sha256', session_id());
$browser = hash('sha256', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000));
$row = $operation->admin_mfa_pending_factor_for_qr(
    (string) $_SESSION['login_user'],
    (int) $factor,
    $session,
    $browser
);
if (!is_array($row)) {
    http_response_code(404);
    exit('Not found');
}

$cipher = new \OneId\App\Auth\TotpSecretCipher(
    \OneId\App\Auth\TotpKeyring::fromFile((string) oneid_config('ONEID_TOTP_KEYRING_PATH', ''))
);
$secret = $cipher->decrypt($row['encrypted_secret'], $row['secret_nonce'], $row['key_version']);
$uri = \OneId\App\Auth\Totp::provisioningUri('OneID@UPNM', (string) $_SESSION['login_user'], $secret);
unset($secret);

ob_start();
QRcode::png($uri, false, QR_ECLEVEL_H, 7, 3);
$qrPng = (string) ob_get_clean();
unset($uri);
$brandedQrPng = \OneId\App\Auth\QrLogoOverlay::apply(
    $qrPng,
    dirname(__DIR__) . '/public/img/logo_upnm_30.png'
);
unset($qrPng);

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
echo $brandedQrPng;
unset($brandedQrPng);
