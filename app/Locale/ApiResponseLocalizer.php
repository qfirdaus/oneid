<?php
declare(strict_types=1);

namespace OneId\App\Locale;

final class ApiResponseLocalizer
{
    /** @var list<string> */
    private const EXCLUDED_PREFIXES = [
        'ADMIN_2FA_',
        'BOOTSTRAP_',
        'F7_TOTP_',
        'MFA_',
        'ODL_',
        'PREVIEW_',
        'RESYNC_',
        'STEP_UP_',
        'SYNC_',
        'TOTP_',
    ];

    /** @param array<string,mixed> $response
     *  @return array<string,mixed>
     */
    public static function enrich(array $response, string $locale): array
    {
        $code = strtoupper(trim((string) ($response['code'] ?? '')));
        if ($code === '' || self::excluded($code)) {
            return $response;
        }
        $key = trim((string) ($response['translation_key'] ?? ''));
        if ($key === '') {
            $key = self::translationKeyFor($code) ?? '';
        }
        if ($key === '') {
            return $response;
        }
        $response['translation_key'] = $key;
        $response['localized_msg'] = \oneid_translate($key, [], $locale);
        return $response;
    }

    public static function translationKeyFor(string $code): ?string
    {
        $code = strtoupper(trim($code));
        if ($code === '' || self::excluded($code)) {
            return null;
        }
        return match (true) {
            str_starts_with($code, 'AUTH_') => match ($code) {
                'AUTH_USERNAME_REQUIRED' => 'login.required_user',
                'AUTH_PASSWORD_REQUIRED' => 'login.required_password',
                'AUTH_ACCOUNT_SUSPENDED' => 'login.account_suspended',
                'AUTH_LOGIN_SUCCESS' => 'login.success',
                default => 'login.invalid',
            },
            str_starts_with($code, 'ML5_DEFAULT_LOCALE_') => str_ends_with($code, '_LOADED')
                ? 'api.configuration.loaded'
                : (str_ends_with($code, '_UPDATED')
                    ? 'admin.configuration.locale_saved'
                    : (str_ends_with($code, '_UNCHANGED')
                        ? 'admin.configuration.locale_unchanged'
                        : 'admin.configuration.locale_failed')),
            str_starts_with($code, 'ML7_METADATA_') => $code === 'ML7_METADATA_APPROVAL_INVALID'
                ? 'admin.metadata.reason_required'
                : (str_contains($code, 'SAVED')
                    ? 'admin.metadata.saved'
                    : (str_contains($code, 'LOADED') || str_contains($code, 'READY')
                        ? 'admin.metadata.schema_ready'
                        : 'admin.metadata.failed')),
            str_starts_with($code, 'ML7A_') => str_contains($code, 'READY')
                ? 'admin.metadata.content_summary'
                : 'admin.metadata.failed',
            str_starts_with($code, 'LB2_') || str_starts_with($code, 'LB3_')
                || str_starts_with($code, 'LB4_') => match ($code) {
                    'LB4_BANNERS_LOADED' => 'admin.banner.loaded',
                    'LB3_DRAFT_CREATED' => 'admin.banner.draft_created',
                    'LB3_DRAFT_UPDATED' => 'admin.banner.updated',
                    'LB3_BANNER_PUBLISHED' => 'admin.banner.published',
                    'LB3_BANNER_INACTIVATED' => 'admin.banner.inactivated',
                    'LB3_BANNERS_REORDERED' => 'admin.banner.reordered',
                    'LB3_BANNER_ROLLED_BACK' => 'admin.banner.rolled_back',
                    'LB4_SCHEMA_UNAVAILABLE' => 'admin.banner.schema_unavailable',
                    default => 'admin.banner.failed',
                },
            str_starts_with($code, 'SC2_') || str_starts_with($code, 'SC3_')
                || str_starts_with($code, 'SC5_') => self::outcomeKey(
                    $code,
                    'api.configuration.loaded',
                    'api.configuration.updated',
                    'api.configuration.unchanged',
                    'api.configuration.failed'
                ),
            str_starts_with($code, 'SC6_') => self::outcomeKey(
                $code,
                'api.recovery.loaded',
                'api.recovery.updated',
                'api.recovery.unchanged',
                'api.recovery.failed'
            ),
            str_starts_with($code, 'W1_') || str_starts_with($code, 'W3_')
                || str_starts_with($code, 'W4_') || str_starts_with($code, 'W5_')
                || str_starts_with($code, 'WA2_') || str_starts_with($code, 'WA3_')
                || str_starts_with($code, 'WA4_') => self::outcomeKey(
                    $code,
                    'api.application.loaded',
                    'api.application.updated',
                    'api.application.unchanged',
                    'api.application.failed'
                ),
            str_starts_with($code, 'M2_') => self::outcomeKey(
                $code,
                'api.user.loaded',
                'api.user.updated',
                'api.user.unchanged',
                'api.user.failed'
            ),
            str_starts_with($code, 'M3_') => self::outcomeKey(
                $code,
                'api.user.loaded',
                'api.user.updated',
                'api.user.unchanged',
                'api.user.failed'
            ),
            str_starts_with($code, 'AS0_') || str_starts_with($code, 'AS1_') =>
                str_contains($code, 'LOADED') ? 'api.session.loaded' : 'api.session.failed',
            str_starts_with($code, 'UC2_') || str_starts_with($code, 'UC4_')
                || str_starts_with($code, 'UC5_') => str_contains($code, 'CHANGED')
                    ? 'dashboard.password.success'
                    : 'dashboard.password.operation_failed',
            str_starts_with($code, 'FAVOURITE') || $code === 'INVALID_FAVOURITE_REQUEST' =>
                str_contains($code, 'UNAVAILABLE') || str_contains($code, 'INVALID')
                    ? 'api.favourite.failed'
                    : 'api.favourite.updated',
            $code === 'APP_ACCESS_DENIED' => 'api.application.failed',
            $code === 'VALIDATION_FAILED' => 'api.user.failed',
            default => null,
        };
    }

    public static function isExcludedCode(string $code): bool
    {
        return self::excluded(strtoupper(trim($code)));
    }

    private static function outcomeKey(
        string $code,
        string $loaded,
        string $updated,
        string $unchanged,
        string $failed
    ): string {
        if (str_contains($code, 'LOADED')) {
            return $loaded;
        }
        if (str_contains($code, 'UNCHANGED') || str_contains($code, 'ALREADY_')) {
            return $unchanged;
        }
        foreach (['UPDATED', 'CREATED', 'REMOVED', 'RENAMED', 'ARCHIVED', 'SAVED',
            'RESET', 'DEACTIVATED', 'REACTIVATED', 'ALLOWED', 'DENIED', 'UPLIFTED'] as $success) {
            if (str_contains($code, $success)) {
                return $updated;
            }
        }
        return $failed;
    }

    private static function excluded(string $code): bool
    {
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
