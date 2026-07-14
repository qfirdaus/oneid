<?php

/**
 * Build a user-friendly label from information that is actually exposed by
 * the browser User-Agent. A web application cannot reliably obtain a desktop
 * hostname or asset name from the browser.
 */
function oneid_format_device_info(
    $deviceType,
    $brand = '',
    $model = '',
    $clientName = '',
    $osName = ''
): string {
    $deviceType = trim((string) $deviceType);
    $brand = trim((string) $brand);
    $model = trim((string) $model);
    $clientName = trim((string) $clientName);
    $osName = trim((string) $osName);

    $primary = $deviceType === '' ? 'Unknown device' : ucfirst($deviceType);
    $hardware = array_values(array_unique(array_filter([$brand, $model], 'strlen')));

    if ($hardware !== []) {
        $primary .= ' (' . implode(' ', $hardware) . ')';
    }

    $parts = [$primary];
    foreach ([$clientName, $osName] as $context) {
        if ($context !== '' && !in_array($context, $parts, true)) {
            $parts[] = $context;
        }
    }

    return implode(' · ', $parts);
}

/**
 * Clean legacy values such as "desktop ()" without mutating historical rows.
 */
function oneid_normalize_device_info($deviceInfo): string
{
    $normalized = preg_replace('/\s*\(\s*\)/', '', trim((string) $deviceInfo));
    $normalized = trim((string) preg_replace('/\s{2,}/', ' ', (string) $normalized));

    return $normalized === '' ? 'Unknown device' : ucfirst($normalized);
}
