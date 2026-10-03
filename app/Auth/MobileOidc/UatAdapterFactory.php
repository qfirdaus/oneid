<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;

use PDO;
use RuntimeException;
use OneId\App\Auth\UserMfa\UserMfaTotpPrimitive;

final class UatAdapterFactory
{
    /** Explicit dependency injection only. Does not read live config, open DB, enable a route or start a session. */
    public static function create(PDO $pdo, array $config, OtpDelivery $delivery,
        UserMfaTotpPrimitive $totp, callable $availability): MobileIdentityAdapter
    {
        if (($config['enabled'] ?? false) !== true || ($config['environment'] ?? '') !== 'staging'
            || realpath(dirname(__DIR__, 3)) !== '/var/www/oneid-uat'
            || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('MOBILE_UAT_CONFIGURATION_REQUIRED');
        }
        return HostedAdapterFactory::create($pdo,$config,$delivery,$totp,$availability);
    }
}
