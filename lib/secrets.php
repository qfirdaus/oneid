<?php

require_once dirname(__DIR__) . '/bootstrap/paths.php';

/**
 * Read runtime secrets from environment variables or a PHP file outside the
 * web root. Environment variables take precedence over the file.
 */
function oneid_secret(string $key, bool $required = true): string
{
    $environmentValue = getenv($key);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }

    static $secrets = null;

    if ($secrets === null) {
        $secretsFile = getenv('ONEID_SECRETS_FILE');
        if ($secretsFile === false || trim($secretsFile) === '') {
            $secretsFile = dirname(PROJECT_ROOT) . '/.oneid-uat-secrets.php';
        }

        if (!is_file($secretsFile) || !is_readable($secretsFile)) {
            throw new RuntimeException('OneID secret store is missing or unreadable.');
        }

        $loadedSecrets = require $secretsFile;
        if (!is_array($loadedSecrets)) {
            throw new RuntimeException('OneID secret store has an invalid format.');
        }

        $secrets = $loadedSecrets;
    }

    $value = $secrets[$key] ?? '';
    if (!is_string($value) && !is_numeric($value)) {
        $value = '';
    }

    $value = (string) $value;
    if ($required && $value === '') {
        throw new RuntimeException(sprintf('Required OneID secret is not configured: %s', $key));
    }

    return $value;
}
