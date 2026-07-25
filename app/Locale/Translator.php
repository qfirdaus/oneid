<?php
declare(strict_types=1);

namespace OneId\App\Locale;

final class Translator
{
    /** @param array<string,array<string,string>> $catalogues */
    public function __construct(
        private readonly array $catalogues,
        private readonly string $hardFallback = LocaleResolver::DEFAULT_LOCALE
    ) {
    }

    /** @param array<string,string|int|float> $parameters */
    public function translate(string $key, string $locale, array $parameters = []): string
    {
        $resolvedLocale = LocaleResolver::valid($locale) ?? $this->hardFallback;
        $message = $this->catalogues[$resolvedLocale][$key]
            ?? $this->catalogues[$this->hardFallback][$key]
            ?? $key;

        foreach ($parameters as $name => $value) {
            $message = str_replace('{' . $name . '}', (string) $value, $message);
        }
        return $message;
    }

    /** @return string[] */
    public function missingKeys(string $locale): array
    {
        $localeKeys = array_keys($this->catalogues[$locale] ?? []);
        $fallbackKeys = array_keys($this->catalogues[$this->hardFallback] ?? []);
        return array_values(array_diff($fallbackKeys, $localeKeys));
    }
}
