<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/config.php';
require_once dirname(__DIR__, 2) . '/app/Locale/ApiResponseLocalizer.php';
require_once dirname(__DIR__, 2) . '/app/Mail/OneIdEmailTemplate.php';

use OneId\App\Locale\ApiResponseLocalizer;
use OneId\App\Mail\OneIdEmailTemplate;

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};

$ms = require dirname(__DIR__, 2) . '/config/locales/ms.php';
$en = require dirname(__DIR__, 2) . '/config/locales/en.php';
$report(array_keys($ms) === array_keys($en), 'BM and English catalogue ordered parity');
$report(count(array_filter(
    array_keys($ms),
    static fn(string $key): bool => str_starts_with($key, 'api.')
)) >= 20, 'ML6 API catalogue coverage');

$legacy = [
    'status' => 0,
    'code' => 'W4_CATEGORY_DUPLICATE',
    'msg' => 'Application category was not created.',
    'correlation_id' => 'abc123',
];
$localized = ApiResponseLocalizer::enrich($legacy, 'ms');
$report(
    $localized['msg'] === $legacy['msg']
    && $localized['translation_key'] === 'api.application.failed'
    && $localized['localized_msg'] === $ms['api.application.failed'],
    'legacy msg preserved while localized presentation is added'
);
$localizedEnglish = ApiResponseLocalizer::enrich($legacy, 'en');
$report(
    $localizedEnglish['localized_msg'] === $en['api.application.failed'],
    'English API presentation resolves from the same stable code'
);

$families = [
    'SC2_CONFIG_LOADED',
    'SC3_CONFIG_UPDATED',
    'SC6_RECOVERY_UNCHANGED',
    'W5_CATEGORY_RENAMED',
    'WA3_APP_CREATE_FAILED',
    'M2_USER_REACTIVATED',
    'M3_PROFILE_SAVED',
    'AS0_SESSIONS_LOADED',
    'UC4_PASSWORD_CHANGED_SESSION_ROTATED',
    'FAVOURITES_STORAGE_UNAVAILABLE',
    'APP_ACCESS_DENIED',
    'VALIDATION_FAILED',
];
$report(
    count(array_filter(
        $families,
        static fn(string $code): bool => ApiResponseLocalizer::translationKeyFor($code) !== null
    )) === count($families),
    'active in-scope response families have translation mapping'
);

foreach (['SYNC_APPLY_COMPLETED', 'ODL_OPERATIONAL_APPLY_DISABLED',
    'RESYNC_APPLIED', 'STEP_UP_VERIFIED', 'ADMIN_2FA_INTERNAL_ERROR'] as $code) {
    $excluded = ApiResponseLocalizer::enrich(['status' => 0, 'code' => $code], 'en');
    $report(
        !isset($excluded['translation_key'], $excluded['localized_msg']),
        "excluded boundary remains canonical: {$code}"
    );
}

$existing = ApiResponseLocalizer::enrich([
    'status' => 0,
    'code' => 'UC2_CONFIRMATION_MISMATCH',
    'translation_key' => 'dashboard.password.mismatch',
    'msg' => 'legacy password mismatch',
], 'en');
$report(
    $existing['translation_key'] === 'dashboard.password.mismatch'
    && $existing['localized_msg'] === $en['dashboard.password.mismatch']
    && $existing['msg'] === 'legacy password mismatch',
    'existing stable translation key takes precedence'
);

$msMail = OneIdEmailTemplate::deliveryTest('Firdaus', 'ms');
$enMail = OneIdEmailTemplate::deliveryTest('Firdaus', 'en');
$report(
    str_contains($msMail, $ms['email.test.headline'])
    && str_contains($msMail, $ms['email.test.intro'])
    && !str_contains($msMail, $en['email.test.headline']),
    'BM delivery-test e-mail uses one locale'
);
$report(
    str_contains($enMail, $en['email.test.headline'])
    && str_contains($enMail, $en['email.test.intro'])
    && !str_contains($enMail, $ms['email.test.headline']),
    'English delivery-test e-mail uses one locale'
);
$report(
    OneIdEmailTemplate::deliveryTestPlainText('ms') === $ms['email.test.plain']
    && OneIdEmailTemplate::deliveryTestPlainText('en') === $en['email.test.plain'],
    'plain-text e-mail parity follows selected locale'
);

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
