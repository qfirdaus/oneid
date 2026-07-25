<?php
declare(strict_types=1);

namespace OneId\App\Locale;

final class LocaleResolver
{
    public const DEFAULT_LOCALE = 'ms';
    public const ALLOWED_LOCALES = ['ms', 'en'];

    public static function valid(mixed $locale): ?string
    {
        $normalized = strtolower(trim((string) $locale));
        return in_array($normalized, self::ALLOWED_LOCALES, true)
            ? $normalized
            : null;
    }

    public function resolve(
        mixed $authenticatedPreference,
        mixed $sessionLocale,
        mixed $guestCookie,
        mixed $systemDefault = self::DEFAULT_LOCALE
    ): string {
        foreach ([$authenticatedPreference, $sessionLocale, $guestCookie, $systemDefault] as $candidate) {
            $valid = self::valid($candidate);
            if ($valid !== null) {
                return $valid;
            }
        }
        return self::DEFAULT_LOCALE;
    }
}
