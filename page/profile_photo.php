<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/session_security.php';
oneid_start_secure_session();
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/request_security.php';

oneid_require_authenticated_page();
oneid_require_active_sso_page($operation);

$fallbackPath = PROJECT_ROOT . '/public/img/default-profile.svg';
$sendFallback = static function () use ($fallbackPath): never {
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-OneID-Profile-Photo: fallback');
    readfile($fallbackPath);
    exit;
};

$user = $operation->get_specific_user_info((string) ($_SESSION['login_user'] ?? ''));
if (!is_array($user)) {
    $sendFallback();
}

$staffId = trim((string) ($user['data2'] ?? ''));
$studentId = trim((string) ($user['data4'] ?? ''));
$isSafeIdentifier = static function (string $identifier): bool {
    return $identifier !== ''
        && strlen($identifier) <= 40
        && preg_match('/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/', $identifier) === 1
        && stripos($identifier, 'TEST') === false;
};

$candidates = [];
if ($isSafeIdentifier($staffId)) {
    $candidates[] = 'https://esmartcard.upnm.edu.my/img/staf/' . rawurlencode($staffId) . '.jpg';
}
if ($isSafeIdentifier($studentId)) {
    $candidates[] = 'https://kemasukan.upnm.edu.my/tawaran/pelajar/student_image/' . rawurlencode($studentId) . '.jpg';
}
if ($candidates === []) {
    $sendFallback();
}
if (!function_exists('curl_init')) {
    $sendFallback();
}

foreach ($candidates as $url) {
    $body = '';
    $tooLarge = false;
    $handle = curl_init($url);
    if ($handle === false) {
        continue;
    }
    curl_setopt_array($handle, [
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'OneID-Profile-Photo/1.0',
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
            if (strlen($body) + strlen($chunk) > 2 * 1024 * 1024) {
                $tooLarge = true;
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $completed = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $declaredType = strtolower(trim((string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE)));
    curl_close($handle);

    if ($completed === false || $tooLarge || $status !== 200 || $body === '') {
        continue;
    }

    $imageInfo = @getimagesizefromstring($body);
    $detectedType = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
    if (!in_array($detectedType, ['image/jpeg', 'image/png'], true)
        || ($declaredType !== '' && !str_starts_with($declaredType, 'image/'))) {
        continue;
    }

    header('Content-Type: ' . $detectedType);
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-OneID-Profile-Photo: upstream');
    echo $body;
    exit;
}

$sendFallback();
