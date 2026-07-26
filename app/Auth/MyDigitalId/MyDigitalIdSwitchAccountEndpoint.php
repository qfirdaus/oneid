<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdSwitchAccountEndpoint
{
    public static function run(): never
    {
        $root = dirname(__DIR__, 3);
        require_once $root . '/bootstrap/app.php';
        require_once $root . '/lib/session_security.php';
        require_once $root . '/lib/request_security.php';
        require_once $root . '/lib/secrets.php';
        require_once $root . '/vendor/autoload.php';

        $config = MyDigitalIdConfig::fromRuntime();
        if (!$config->enabled) {
            self::finish(404, 'Not Found');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            self::finish(405, 'Method Not Allowed');
        }

        \oneid_start_secure_session();
        $expectedCsrf = \oneid_csrf_token();
        $receivedCsrf = \oneid_request_csrf_token();
        if (
            preg_match('/^[a-f0-9]{64}$/D', $receivedCsrf) !== 1
            || !hash_equals($expectedCsrf, $receivedCsrf)
        ) {
            self::finish(403, 'Forbidden');
        }

        $idToken = MyDigitalIdRejectedLogoutState::consume($_SESSION, time());
        unset(
            $_SESSION[MyDigitalIdAuthorizationTransactionStore::SESSION_KEY],
            $_SESSION['openid_connect_state'],
            $_SESSION['openid_connect_nonce'],
            $_SESSION['openid_connect_code_verifier']
        );

        if (!is_string($idToken)) {
            $_SESSION['oneid_login_flash'] = 'mydigitalid_temporary';
            session_write_close();
            self::redirect(\APP_URL . '/');
        }

        try {
            $providerLogoutUrl = (new MyDigitalIdLogoutUrl($config))->build($idToken);
        } catch (\Throwable) {
            $_SESSION['oneid_login_flash'] = 'mydigitalid_temporary';
            session_write_close();
            self::redirect(\APP_URL . '/');
        }

        session_regenerate_id(true);
        session_write_close();
        self::redirect($providerLogoutUrl);
    }

    private static function redirect(string $url): never
    {
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
        header('Referrer-Policy: no-referrer');
        header('Location: ' . $url, true, 303);
        exit;
    }

    private static function finish(int $status, string $body): never
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        echo $body;
        exit;
    }
}
