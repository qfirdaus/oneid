<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Locale/LocaleResolver.php';
require_once dirname(__DIR__) . '/app/Locale/Translator.php';
require_once dirname(__DIR__) . '/app/Locale/PdoLocalePreferenceRepository.php';

use OneId\App\Locale\LocaleResolver;
use OneId\App\Locale\PdoLocalePreferenceRepository;
use OneId\App\Locale\Translator;

function oneid_current_locale(): string
{
    if (
        !function_exists('oneid_config')
        || filter_var(oneid_config('ONEID_LOCALE_INFRASTRUCTURE_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN) !== true
    ) {
        return LocaleResolver::DEFAULT_LOCALE;
    }
    $authenticated = ($_SESSION['login_status'] ?? '') === 'true'
        ? ($_SESSION['oneid_authenticated_locale'] ?? null)
        : null;
    return (new LocaleResolver())->resolve(
        $authenticated,
        $_SESSION['oneid_locale'] ?? null,
        $_COOKIE['oneid_locale'] ?? null,
        oneid_system_default_locale()
    );
}

function oneid_system_default_locale(): string
{
    static $resolved;
    if (is_string($resolved)) {
        return $resolved;
    }
    $runtime = LocaleResolver::valid(
        function_exists('oneid_config') ? oneid_config('ONEID_DEFAULT_LOCALE', 'ms') : 'ms'
    ) ?? LocaleResolver::DEFAULT_LOCALE;
    if (!defined('DB_DSN') || !defined('DB_USERNAME') || !defined('DB_PASSWORD')) {
        return $resolved = $runtime;
    }
    try {
        $pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2,
        ]);
        $stored = $pdo->query('SELECT default_locale FROM sys_config WHERE singleton_key=1')->fetchColumn();
        return $resolved = (LocaleResolver::valid($stored) ?? $runtime);
    } catch (Throwable $exception) {
        return $resolved = $runtime;
    }
}

function oneid_set_session_locale(string $locale): bool
{
    if (
        !function_exists('oneid_config')
        || filter_var(oneid_config('ONEID_LOCALE_INFRASTRUCTURE_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN) !== true
    ) {
        return false;
    }
    $valid = LocaleResolver::valid($locale);
    if ($valid === null || session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    $_SESSION['oneid_locale'] = $valid;
    return true;
}

function oneid_set_guest_locale_cookie(string $locale, ?int $now = null): bool
{
    if (
        !function_exists('oneid_config')
        || filter_var(oneid_config('ONEID_LOCALE_INFRASTRUCTURE_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN) !== true
    ) {
        return false;
    }
    $valid = LocaleResolver::valid($locale);
    if ($valid === null || headers_sent()) {
        return false;
    }
    $expires = ($now ?? time()) + (180 * 86400);
    $set = setcookie('oneid_locale', $valid, [
        'expires' => $expires,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if ($set) {
        $_COOKIE['oneid_locale'] = $valid;
    }
    return $set;
}

function oneid_promote_authenticated_locale(string $userId): void
{
    if (
        trim($userId) === ''
        || !defined('DB_DSN')
        || !defined('DB_USERNAME')
        || !defined('DB_PASSWORD')
        || filter_var(oneid_config('ONEID_LOCALE_INFRASTRUCTURE_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN) !== true
    ) {
        return;
    }
    try {
        $repository = new PdoLocalePreferenceRepository(new PDO(
            DB_DSN,
            DB_USERNAME,
            DB_PASSWORD,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        ));
        $selected = LocaleResolver::valid(
            $_SESSION['oneid_locale'] ?? $_COOKIE['oneid_locale'] ?? null
        );
        if ($selected !== null) {
            $repository->save($userId, $selected);
            $_SESSION['oneid_authenticated_locale'] = $selected;
            return;
        }
        $stored = $repository->find($userId);
        if ($stored !== null) {
            $_SESSION['oneid_authenticated_locale'] = $stored;
        }
    } catch (Throwable $exception) {
        error_log('Locale preference persistence unavailable: ' . get_class($exception));
    }
}

function oneid_translator(): Translator
{
    static $translator;
    if (!$translator instanceof Translator) {
        $translator = new Translator([
            'ms' => require dirname(__DIR__) . '/config/locales/ms.php',
            'en' => require dirname(__DIR__) . '/config/locales/en.php',
        ]);
    }
    return $translator;
}

/** @param array<string,string|int|float> $parameters */
function oneid_translate(string $key, array $parameters = [], ?string $locale = null): string
{
    return oneid_translator()->translate($key, $locale ?? oneid_current_locale(), $parameters);
}

/** @return array{code:string,translation_key:string,msg:string} */
function oneid_localized_response(string $code, string $translationKey, string $legacyMessage): array
{
    return [
        'code' => $code,
        'translation_key' => $translationKey,
        'msg' => $legacyMessage,
    ];
}
