<?php

namespace OneId\App\Auth;

final class LogoutEndpoint
{
    public static function run(): never
    {
        $projectRoot = dirname(__DIR__, 2);

        require_once $projectRoot . '/lib/session_security.php';
        \oneid_start_secure_session();
        require_once $projectRoot . '/lib/config.php';
        require_once $projectRoot . '/lib/SSO_IDP_INC.php';
        require_once __DIR__ . '/LogoutHandler.php';

        if (!isset($operation) || !is_object($operation)) {
            throw new \RuntimeException('Logout operation is unavailable.');
        }

        LogoutHandler::handle($operation, (string) \constant('SSO_IDP_DOMAIN'));
    }
}
