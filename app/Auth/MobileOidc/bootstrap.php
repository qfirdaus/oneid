<?php
declare(strict_types=1);

// Library-only bootstrap: no config.php, database connection, session or cookies.
require_once dirname(__DIR__, 3) . '/lib/auth_security.php';
foreach (['Totp', 'TotpKeyring', 'TotpSecretCipher'] as $class) {
    require_once dirname(__DIR__) . '/' . $class . '.php';
}
foreach (['UserMfaOtp', 'UserMfaTotpPrimitive', 'UserLoginMfaPolicy', 'PdoUserMfaPolicyReader', 'UserMfaEmailSenderInterface'] as $class) {
    require_once dirname(__DIR__) . '/UserMfa/' . $class . '.php';
}
foreach (['Contracts', 'IdentityResolver', 'LifecycleSchema', 'PdoIdentitySource', 'PdoStateStore', 'HydraAdminClient', 'MobileIdentityAdapter', 'OneIdOtpDelivery', 'RuntimeEnvironment', 'HostedAdapterFactory', 'UatAdapterFactory'] as $class) {
    require_once __DIR__ . '/' . $class . '.php';
}
foreach (['MobileProtocolService', 'HostedLogin', 'ConfiguredOtpDelivery'] as $class) require_once __DIR__ . '/' . $class . '.php';

require_once __DIR__ . '/PdoMobileMyDigitalIdAccounts.php';
require_once __DIR__ . '/MobileMyDigitalId.php';

require_once __DIR__ . '/MobilePasswordRecovery.php';
