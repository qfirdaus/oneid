<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdCallbackEndpoint
{
    public static function run(): never
    {
        $root = dirname(__DIR__, 3);
        require_once $root . '/bootstrap/app.php';
        require_once $root . '/lib/secrets.php';
        require_once $root . '/vendor/autoload.php';

        $config = MyDigitalIdConfig::fromRuntime();
        if (!$config->enabled) {
            self::finish(404, 'Not Found');
        }

        require_once $root . '/lib/session_security.php';
        \oneid_start_secure_session();

        try {
            require_once $root . '/lib/config.php';
            if (!isset($operation) || !is_object($operation)) {
                throw new MyDigitalIdPersistenceException('MYDID_LOCAL_OPERATION_UNAVAILABLE');
            }
            $pdo = new \PDO(
                \DB_DSN,
                \DB_USERNAME,
                \DB_PASSWORD,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            $protector = MyDigitalIdIdentityProtector::fromRuntime();
            $orchestrator = new MyDigitalIdCallbackOrchestrator(
                new MyDigitalIdAuthorizationTransactionStore(),
                new MyDigitalIdProtocolGateway(new MyDigitalIdProtocolClient($config)),
                new MyDigitalIdAccountLinkingService(
                    new PdoMyDigitalIdIdentityRepository($pdo),
                    new PdoMyDigitalIdAccountMatcher($pdo),
                    $protector
                ),
                new MyDigitalIdLocalLoginFinalizer($operation)
            );
            $result = $orchestrator->handle(
                (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
                $_GET,
                $_SESSION,
                time(),
                (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'MyDigital ID'), 0, 255)
            );
            if (!$result['allowed']) {
                self::redirectAccessDenied();
            }
            session_write_close();
            header('Cache-Control: no-store');
            header('Location: ' . \APP_URL . $result['redirect_path'], true, 303);
            exit;
        } catch (MyDigitalIdConfigurationException|MyDigitalIdPersistenceException) {
            self::redirectWithFlash('mydigitalid_invalid');
        } catch (\Throwable) {
            self::redirectWithFlash('mydigitalid_temporary');
        }
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

    private static function redirectWithFlash(string $code): never
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset(
                $_SESSION[MyDigitalIdAuthorizationTransactionStore::SESSION_KEY],
                $_SESSION['openid_connect_state'],
                $_SESSION['openid_connect_nonce'],
                $_SESSION['openid_connect_code_verifier']
            );
            $_SESSION['oneid_login_flash'] = $code;
            session_write_close();
        }
        header('Cache-Control: no-store');
        header('Location: ' . \APP_URL . '/', true, 303);
        exit;
    }

    private static function redirectAccessDenied(): never
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset(
                $_SESSION[MyDigitalIdAuthorizationTransactionStore::SESSION_KEY],
                $_SESSION['openid_connect_state'],
                $_SESSION['openid_connect_nonce'],
                $_SESSION['openid_connect_code_verifier']
            );
            session_write_close();
        }
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        header('Location: ' . \APP_URL . '/auth/mydigitalid/access-denied.php', true, 303);
        exit;
    }
}
