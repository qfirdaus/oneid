<?php

declare(strict_types=1);

/**
 * @return null|array{mode:string,label:string,message:string}
 */
function oneid_environment_banner_state(?string $environment = null): ?array
{
    $environment ??= (string) oneid_config('ONEID_ENVIRONMENT', '');
    $environment = strtolower(trim($environment));

    return match ($environment) {
        'production' => null,
        'local', 'development' => [
            'mode' => 'development',
            'label' => 'DEVELOPMENT ENVIRONMENT',
            'message' => 'Sistem ini untuk pembangunan dan ujian sahaja',
        ],
        'staging' => [
            'mode' => 'staging',
            'label' => 'STAGING ENVIRONMENT',
            'message' => 'Sistem ini bukan persekitaran production',
        ],
        default => [
            'mode' => 'warning',
            'label' => 'ENVIRONMENT NOT CONFIGURED',
            'message' => 'Sahkan konfigurasi runtime sebelum meneruskan',
        ],
    };
}

function oneid_environment_body_class(?string $environment = null): string
{
    return oneid_environment_banner_state($environment) === null
        ? ''
        : ' oneid-environment-visible';
}

function oneid_render_environment_banner(?string $environment = null): void
{
    $state = oneid_environment_banner_state($environment);
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
