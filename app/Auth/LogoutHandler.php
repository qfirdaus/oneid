<?php

namespace OneId\App\Auth;

final class LogoutHandler
{
    public static function handle(
        object $operation,
        string $redirectUrl,
        ?string $federatedLogoutUrl = null
    ): never
    {
        if (isset($_COOKIE['sso_cre'])) {
            $cookie = \LOCAL_COOKIES_HANDLER();
            $operation->update_specific_token_status(
                (string) ($_SESSION['login_user'] ?? ''),
                $cookie->sso_cre,
                0,
                'USER_LOGOUT'
            );
            \oneid_clear_sso_cookie();
        }

        $_SESSION = [];
        session_destroy();
        header('Cache-Control: no-store');
        header('Location: ' . ($federatedLogoutUrl ?? $redirectUrl), true, 303);
        exit;
    }
}
