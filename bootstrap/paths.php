<?php

/**
 * Stable filesystem paths for both the legacy document root and the parallel
 * public/ document root introduced during restructuring R0-R3.
 */

$oneidProjectRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', $oneidProjectRoot);
}

$configuredPublicPath = trim((string) getenv('ONEID_PUBLIC_PATH'));
if ($configuredPublicPath === '') {
    $configuredPublicPath = PROJECT_ROOT . DIRECTORY_SEPARATOR . 'public';
}

if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', rtrim($configuredPublicPath, '/\\'));
}

if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', PROJECT_ROOT . DIRECTORY_SEPARATOR . 'storage');
}

if (!defined('LEGACY_PUBLIC_PATH')) {
    define('LEGACY_PUBLIC_PATH', PROJECT_ROOT);
}

function oneid_project_path(string $path = ''): string
{
    return PROJECT_ROOT . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, '/\\'));
}

function oneid_public_path(string $path = ''): string
{
    return PUBLIC_PATH . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, '/\\'));
}

function oneid_storage_path(string $path = ''): string
{
    return STORAGE_PATH . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, '/\\'));
}

