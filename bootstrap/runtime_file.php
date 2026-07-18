<?php

require_once __DIR__ . '/paths.php';

/** Resolve one runtime store for both configuration and secrets. */
function oneid_runtime_file_path(): string
{
    $runtimeFile = trim((string) getenv('ONEID_RUNTIME_FILE'));
    $legacySecretsFile = trim((string) getenv('ONEID_SECRETS_FILE'));

    if ($runtimeFile !== '' && $legacySecretsFile !== '' && $runtimeFile !== $legacySecretsFile) {
        throw new RuntimeException('ONEID_RUNTIME_FILE and ONEID_SECRETS_FILE must reference the same file.');
    }

    if ($runtimeFile !== '') {
        return $runtimeFile;
    }
    if ($legacySecretsFile !== '') {
        return $legacySecretsFile;
    }

    return PROJECT_ROOT . '/.private/runtime.php';
}
