<?php

declare(strict_types=1);

/**
 * @return null|array{mode:string,label:string,message:string}
 */
function oneid_environment_banner_state(?string $environment = null, ?string $locale = null): ?array
{
    $environment ??= (string) oneid_config('ONEID_ENVIRONMENT', '');
    $environment = strtolower(trim($environment));

    return match ($environment) {
        'production' => null,
        'local', 'development' => [
            'mode' => 'development',
            'label' => oneid_environment_banner_copy(
                'environment_banner.development.label',
                'DEVELOPMENT ENVIRONMENT',
                $locale
            ),
            'message' => oneid_environment_banner_copy(
                'environment_banner.development.message',
                'This system is for development and testing only',
                $locale
            ),
        ],
        'staging' => [
            'mode' => 'staging',
            'label' => oneid_environment_banner_copy(
                'environment_banner.staging.label',
                'STAGING ENVIRONMENT',
                $locale
            ),
            'message' => oneid_environment_banner_copy(
                'environment_banner.staging.message',
                'This is not the production environment',
                $locale
            ),
        ],
        default => [
            'mode' => 'warning',
            'label' => oneid_environment_banner_copy(
                'environment_banner.warning.label',
                'ENVIRONMENT NOT CONFIGURED',
                $locale
            ),
            'message' => oneid_environment_banner_copy(
                'environment_banner.warning.message',
                'Verify the runtime configuration before continuing',
                $locale
            ),
        ],
    };
}

function oneid_environment_banner_copy(string $key, string $fallback, ?string $locale = null): string
{
    return function_exists('oneid_translate')
        ? oneid_translate($key, [], $locale)
        : $fallback;
}

function oneid_environment_body_class(?string $environment = null): string
{
    return oneid_environment_banner_state($environment) === null
        ? ''
        : ' oneid-environment-visible';
}

function oneid_render_environment_banner(?string $environment = null, ?string $locale = null): void
{
    $state = oneid_environment_banner_state($environment, $locale);
    if ($state === null) {
        return;
    }

    $mode = htmlspecialchars($state['mode'], ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars($state['label'], ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($state['message'], ENT_QUOTES, 'UTF-8');
    echo '<div class="oneid-environment-banner oneid-environment-banner--' . $mode
        . '" role="status" aria-label="' . $label . '"><strong>' . $label
        . '</strong><span aria-hidden="true">&mdash;</span><span>' . $message . '</span></div>';
}
