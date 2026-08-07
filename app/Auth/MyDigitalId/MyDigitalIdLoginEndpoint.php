<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdLoginEndpoint
{
    public static function run(): never
    {
        self::bootstrap();
        $config = MyDigitalIdConfig::fromRuntime();
        if (!$config->enabled) {
            self::notFound();
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            self::finish('Method Not Allowed');
        }

        \oneid_start_secure_session();
        require_once dirname(__DIR__, 3) . '/lib/config.php';
        if (($_SESSION['login_status'] ?? '') === 'true') {
            session_write_close();
            http_response_code(409);
            self::finish('An authenticated session already exists');
        }
        $store = new MyDigitalIdAuthorizationTransactionStore();
        $transaction = $store->create($_SESSION, time(), (string) ($_GET['return'] ?? ''));
        $url = (new MyDigitalIdAuthorizationRequest($config))->url($transaction);
        session_write_close();
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
        header('Location: ' . $url, true, 303);
        exit;
    }

    private static function bootstrap(): void
    {
        $root = dirname(__DIR__, 3);
        require_once $root . '/bootstrap/app.php';
        require_once $root . '/lib/session_security.php';
        require_once $root . '/lib/secrets.php';
        require_once $root . '/vendor/autoload.php';
    }

    private static function notFound(): never
    {
        http_response_code(404);
        self::finish('Not Found');
    }

    private static function finish(string $body): never
    {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo $body;
        exit;
    }
}
