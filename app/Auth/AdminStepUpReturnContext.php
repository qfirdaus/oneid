<?php

namespace OneId\App\Auth;

final class AdminStepUpReturnContext
{
    /** @return array<string,array<string,string>> */
    public static function registry(): array
    {
        return [
            'active_sessions' => ['mode'=>'tab','primary'=>'#tab_active_sessions'],
            'configuration_admin_2fa' => ['mode'=>'configuration','primary'=>'#tab_settings','secondary'=>'#configuration_admin_2fa'],
            'configuration_account_recovery' => ['mode'=>'configuration','primary'=>'#tab_settings','secondary'=>'#configuration_recovery'],
            'configuration_locale' => ['mode'=>'configuration','primary'=>'#tab_settings','secondary'=>'#configuration_locale'],
            'configuration_login_banner' => ['mode'=>'configuration','primary'=>'#tab_settings','secondary'=>'#configuration_login_banner'],
            'configuration_maintenance' => ['mode'=>'configuration','primary'=>'#tab_settings','secondary'=>'#configuration_maintenance'],
            'configuration_user_mfa_security' => ['mode'=>'user_mfa','primary'=>'#tab_settings','secondary'=>'#configuration_user_mfa','tertiary'=>'#user_2fa_security_panel'],
            'configuration_user_mfa_category' => ['mode'=>'user_mfa','primary'=>'#tab_settings','secondary'=>'#configuration_user_mfa','tertiary'=>'#user_2fa_category_panel'],
            'configuration_user_mfa_exemption' => ['mode'=>'user_mfa','primary'=>'#tab_settings','secondary'=>'#configuration_user_mfa','tertiary'=>'#user_2fa_exemption_panel'],
            'admin_metadata' => ['mode'=>'metadata'],
        ];
    }

    public static function normalize(string $requested): string
    {
        $aliases = [
            'admin_2fa'=>'configuration_admin_2fa',
            'account_recovery'=>'configuration_account_recovery',
            'admin_locale'=>'configuration_locale',
            'login_banner'=>'configuration_login_banner',
            'user_mfa_policy'=>'configuration_user_mfa_security',
        ];
        $normalized = $aliases[$requested] ?? $requested;
        return isset(self::registry()[$normalized]) ? $normalized : '';
    }

    public static function redirectUrl(string $requested): string
    {
        $context = self::normalize($requested);
        return $context === '' ? \APP_URL.'/admin/dashboard' : \APP_URL.'/admin/dashboard?step_up_return='.rawurlencode($context);
    }
}
