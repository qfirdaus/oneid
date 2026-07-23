<?php

/**
 * Public application metadata.
 *
 * Update the release version here so login, user dashboard, admin dashboard
 * and the latest release badge remain consistent.
 */

if (!defined('ONEID_APP_VERSION')) {
    define('ONEID_APP_VERSION', '2.6.0');
}

if (!defined('ONEID_COPYRIGHT_YEAR')) {
    define('ONEID_COPYRIGHT_YEAR', '2026');
}

if (!defined('ONEID_COPYRIGHT_OWNER')) {
    define('ONEID_COPYRIGHT_OWNER', 'PTMK | Aplikasi Digital');
}

function oneid_application_footer(): string
{
    return sprintf(
        '%s © %s. Version %s',
        ONEID_COPYRIGHT_YEAR,
        ONEID_COPYRIGHT_OWNER,
        ONEID_APP_VERSION
    );
}
