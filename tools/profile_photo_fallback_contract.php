<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$dashboard = (string) file_get_contents($projectRoot . '/page/dashboard.php');
$resolver = (string) file_get_contents($projectRoot . '/page/profile_photo.php');
$sharedResponder = (string) file_get_contents($projectRoot . '/app/ProfilePhoto/ProfilePhotoResponder.php');
$adminDashboard = (string) file_get_contents($projectRoot . '/admin/dashboard.php');
$wrapper = (string) file_get_contents($projectRoot . '/public/page/profile-photo.php');
$fallback = (string) file_get_contents($projectRoot . '/public/img/default-profile.svg');

$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$passed) {
        $failed++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};

$report(
    str_contains($dashboard, 'src="profile-photo.php"')
        && !str_contains($dashboard, "checkImageExists('https://esmartcard")
        && !str_contains($dashboard, "checkImageExists('https://kemasukan"),
    'dashboard uses the same-origin resolver without browser-side cross-origin probes'
);
$report(
    str_contains($resolver, 'oneid_require_authenticated_page()')
        && str_contains($resolver, 'oneid_require_active_sso_page($operation)'),
    'profile photo resolver requires an authenticated active SSO session'
);
$report(
    str_contains($sharedResponder, "stripos(\$identifier, 'TEST') === false")
        && str_contains($sharedResponder, "preg_match('/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*\$/")
        && str_contains($sharedResponder, "'https://esmartcard.upnm.edu.my/img/staf/'")
        && str_contains($sharedResponder, "'https://kemasukan.upnm.edu.my/tawaran/pelajar/student_image/'"),
    'empty, synthetic and unsafe identifiers use the fallback without upstream requests'
);
$report(
    str_contains($sharedResponder, 'CURLOPT_CONNECTTIMEOUT => 3')
        && str_contains($sharedResponder, 'CURLOPT_TIMEOUT => 6')
        && str_contains($sharedResponder, 'CURLOPT_PROTOCOLS => CURLPROTO_HTTPS')
        && str_contains($sharedResponder, 'CURLOPT_SSL_VERIFYPEER => true'),
    'upstream retrieval is HTTPS-only with TLS verification and bounded timeouts'
);
$report(
    str_contains($sharedResponder, '2 * 1024 * 1024')
        && str_contains($sharedResponder, 'getimagesizefromstring')
        && str_contains($sharedResponder, "['image/jpeg', 'image/png']"),
    'remote payload size and decoded image MIME are validated'
);
$report(
    str_contains($sharedResponder, "'X-OneID-Profile-Photo: ' . \$source")
        && str_contains($sharedResponder, "Cache-Control: private, no-store")
        && str_contains($fallback, 'Neutral user silhouette'),
    'local fallback is valid, private and identifiable without leaking user data'
);
$report(
    str_contains($wrapper, "'/page/profile_photo.php'")
        && is_file($projectRoot . '/public/img/default-profile.svg'),
    'public wrapper and local fallback asset are deployed'
);
$report(
    str_contains($resolver, 'oneid_require_active_sso_page($operation)')
        && str_contains($resolver, 'oneid_require_admin_page()')
        && str_contains($resolver, "preg_match('/\\A[A-Za-z0-9._@-]{1,20}\\z/'")
        && str_contains($resolver, "\$_GET['user_id']"),
    'shared resolver restricts requested user photos to active-SSO administrators and validates user IDs'
);
$report(
    str_contains($adminDashboard, 'src="../page/profile-photo.php"')
        && str_contains($adminDashboard, "'../page/profile-photo.php?user_id=' + encodeURIComponent")
        && str_contains($adminDashboard, 'class="active-session-avatar"')
        && str_contains($adminDashboard, 'loading="lazy"'),
    'admin identity and active-session rows use same-origin profile photo resolvers'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
