<?php
declare(strict_types=1);

namespace OneId\App\Admin;

use OneId\App\Locale\LocaleResolver;
use RuntimeException;
use Throwable;

final class SystemLocaleConfigurationService
{
    public function __construct(private readonly object $operation)
    {
    }

    /** @return array<string,mixed> */
    public function status(): array
    {
        $stored = $this->operation->get_system_config();
        $locale = LocaleResolver::valid($stored['default_locale'] ?? null);
        if ($locale === null) {
            throw new RuntimeException('ML5_DEFAULT_LOCALE_SCHEMA_UNAVAILABLE');
        }
        return [
            'status' => 1,
            'code' => 'ML5_DEFAULT_LOCALE_LOADED',
            'translation_key' => 'admin.configuration.locale',
            'default_locale' => $locale,
            'configuration_version' => (int) ($stored['configuration_version'] ?? 0),
        ];
    }

    /** @return array<string,mixed> */
    public function update(mixed $locale, mixed $version, string $reason, string $admin, string $ip): array
    {
        $correlation = bin2hex(random_bytes(8));
        $valid = LocaleResolver::valid($locale);
        $expectedVersion = filter_var($version, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $reason = trim($reason);
        if ($valid === null) {
            throw new RuntimeException('ML5_DEFAULT_LOCALE_INVALID');
        }
        if ($expectedVersion === false || mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw new RuntimeException('ML5_DEFAULT_LOCALE_APPROVAL_INVALID');
        }

        $started = false;
        try {
            $this->operation->beginTransaction();
            $started = true;
            $stored = $this->operation->get_system_config_for_update();
            $before = LocaleResolver::valid($stored['default_locale'] ?? null);
            if ($before === null) {
                throw new RuntimeException('ML5_DEFAULT_LOCALE_SCHEMA_UNAVAILABLE');
            }
            if ((int) ($stored['configuration_version'] ?? 0) !== (int) $expectedVersion) {
                throw new RuntimeException('ML5_DEFAULT_LOCALE_STALE');
            }
            if ($before === $valid) {
                $this->operation->commit();
                $started = false;
                return [
                    'status' => 1,
                    'code' => 'ML5_DEFAULT_LOCALE_UNCHANGED',
                    'translation_key' => 'admin.configuration.locale_unchanged',
                    'msg' => \oneid_translate('admin.configuration.locale_unchanged'),
                    'default_locale' => $valid,
                    'configuration_version' => (int) $expectedVersion,
                    'correlation_id' => $correlation,
                ];
            }
            if ($this->operation->update_default_locale_by_version((int) $stored['id'], $valid, (int) $expectedVersion) !== 1) {
                throw new RuntimeException('ML5_DEFAULT_LOCALE_STALE');
            }
            $this->operation->configuration_history_record([
                'version_before' => (int) $expectedVersion,
                'version_after' => (int) $expectedVersion + 1,
                'actor_id' => $admin,
                'ip_address' => $ip,
                'action_name' => 'UPDATE_SYSTEM_DEFAULT_LOCALE',
                'outcome' => 'SUCCESS',
                'reason_code' => 'ML5_DEFAULT_LOCALE_UPDATED',
                'change_reason' => $reason,
                'before' => ['default_locale' => $before],
                'after' => ['default_locale' => $valid],
                'correlation_id' => $correlation,
            ]);
            $this->operation->commit();
            $started = false;
            return [
                'status' => 1,
                'code' => 'ML5_DEFAULT_LOCALE_UPDATED',
                'translation_key' => 'admin.configuration.locale_saved',
                'msg' => \oneid_translate('admin.configuration.locale_saved'),
                'default_locale' => $valid,
                'configuration_version' => (int) $expectedVersion + 1,
                'correlation_id' => $correlation,
            ];
        } catch (Throwable $exception) {
            if ($started) {
                $this->operation->rollBack();
            }
            throw $exception;
        }
    }
}
