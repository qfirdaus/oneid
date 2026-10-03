<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;

use PDO;
use RuntimeException;
use OneId\App\Auth\UserMfa\UserMfaTotpPrimitive;

final class HostedAdapterFactory
{
    /** Explicit dependency injection only. Does not read live config, open DB, enable a route or start a session. */
    public static function create(PDO $pdo, array $config, OtpDelivery $delivery,
        UserMfaTotpPrimitive $totp, callable $availability): MobileIdentityAdapter
    {
        $environment=RuntimeEnvironment::validate(realpath(dirname(__DIR__,3)), $config);
        if (($config['enabled']??false)!==true || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql') {
            throw new RuntimeException('MOBILE_CONFIGURATION_REQUIRED');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        // Both stores intentionally share this PDO for user/factor locks and local state.
        return new MobileIdentityAdapter(
            new PdoIdentitySource($pdo, (string) $config['mfa_runtime_mode'],
                ($config['mfa_activation_authorized'] ?? false) === true, $availability, true, $environment),
            new PdoStateStore($pdo),
            new HydraAdminClient((string) $config['admin_url'], (string) $config['issuer']),
            $delivery, $totp, (array) $config['clients'], (string) $config['binding_key'], true, $environment, null,
            isset($config['pilot_identifiers']) ? (array)$config['pilot_identifiers'] : null
        );
    }
}
