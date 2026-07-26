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

        $federatedLogoutUrl = null;
        if (
            ($_SESSION['auth_method'] ?? '') === 'mydigitalid'
            && is_string($_SESSION['mydigitalid_id_token'] ?? null)
        ) {
            require_once $projectRoot . '/vendor/autoload.php';
            require_once $projectRoot . '/lib/secrets.php';
            try {
                $config = \OneId\App\Auth\MyDigitalId\MyDigitalIdConfig::fromRuntime();
                $federatedLogoutUrl = (
                    new \OneId\App\Auth\MyDigitalId\MyDigitalIdLogoutUrl($config)
                )->build((string) $_SESSION['mydigitalid_id_token']);
            } catch (\Throwable) {
                // Local logout remains authoritative if provider logout is unavailable.
                $federatedLogoutUrl = null;
            }
        }

        LogoutHandler::handle(
            $operation,
            (string) \constant('SSO_IDP_DOMAIN'),
            $federatedLogoutUrl
        );
    }
}
