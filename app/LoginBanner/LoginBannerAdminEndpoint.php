<?php

declare(strict_types=1);

namespace OneId\App\LoginBanner;

use Throwable;

final class LoginBannerAdminEndpoint
{
    private const ACTIONS = [
        'admin_login_banner_list',
        'admin_login_banner_create_draft',
        'admin_login_banner_publish',
        'admin_login_banner_inactivate',
        'admin_login_banner_reorder',
        'admin_login_banner_rollback',
    ];

    public function __construct(
        private readonly LoginBannerPersistenceInterface $persistence,
        private readonly LoginBannerService $service,
        private readonly string $environment,
        private readonly string $stagingDirectory,
        private readonly string $publishedDirectory
    ) {
    }

    /** @param array<string,mixed> $post @param array<string,mixed> $files @return array<string,mixed> */
    public function handle(
        string $action,
        array $post,
        array $files,
        string $actorId,
        string $ipAddress
    ): array {
        $correlation = bin2hex(random_bytes(8));
        try {
            if (!in_array($action, self::ACTIONS, true)) {
                throw new LoginBannerDomainException('LB4_ACTION_NOT_ALLOWED', $correlation);
            }
            if (preg_match('/^[a-z][a-z0-9_-]{1,31}$/D', $this->environment) !== 1) {
                throw new LoginBannerDomainException('LB4_ENVIRONMENT_UNAVAILABLE', $correlation);
            }
            $schema = $this->persistence->schemaStatus();
            if (($schema['available'] ?? false) !== true) {
                throw new LoginBannerDomainException('LB4_SCHEMA_UNAVAILABLE', $correlation);
            }
            if ($action === 'admin_login_banner_list') {
                return [
                    'status' => 1,
                    'code' => 'LB4_BANNERS_LOADED',
                    'environment' => $this->environment,
                    'items' => $this->groupRows($this->persistence->adminList($this->environment)),
                    'correlation_id' => $correlation,
                    '_http_status' => 200,
                ];
            }
            $reason = trim((string) ($post['change_reason'] ?? ''));
            $result = match ($action) {
                'admin_login_banner_create_draft' => $this->service->createDraft(
                    $post,
                    [
                        'ms' => is_array($files['banner_image_ms'] ?? null) ? $files['banner_image_ms'] : null,
                        'en' => is_array($files['banner_image_en'] ?? null) ? $files['banner_image_en'] : null,
                    ],
                    filter_var($post['same_image_for_english'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    $this->environment,
                    $this->stagingDirectory,
                    $this->publishedDirectory,
                    $actorId,
                    $ipAddress,
                    $reason
                ),
                'admin_login_banner_publish' => $this->service->publish(
                    (int) ($post['banner_id'] ?? 0),
                    (int) ($post['expected_version'] ?? 0),
                    $this->environment,
                    $actorId,
                    $ipAddress,
                    $reason
                ),
                'admin_login_banner_inactivate' => $this->service->inactivate(
                    (int) ($post['banner_id'] ?? 0),
                    (int) ($post['expected_version'] ?? 0),
                    $this->environment,
                    $actorId,
                    $ipAddress,
                    $reason
                ),
                'admin_login_banner_reorder' => $this->service->reorder(
                    $this->decodeReorderItems($post['items_json'] ?? ''),
                    $this->environment,
                    $actorId,
                    $ipAddress,
                    $reason
                ),
                'admin_login_banner_rollback' => $this->service->rollback(
                    (int) ($post['banner_id'] ?? 0),
                    (int) ($post['expected_version'] ?? 0),
                    $this->environment,
                    $actorId,
                    $ipAddress,
                    $reason
                ),
                default => throw new LoginBannerDomainException('LB4_ACTION_NOT_ALLOWED', $correlation),
            };
            $result['_http_status'] = 200;
            return $result;
        } catch (LoginBannerDomainException $exception) {
            return $this->failure(
                $exception->reason,
                $exception->correlationId !== '' ? $exception->correlationId : $correlation,
                $this->statusFor($exception->reason)
            );
        } catch (LoginBannerImageException $exception) {
            return $this->failure($exception->getMessage(), $correlation, 422);
        } catch (LoginBannerPersistenceException $exception) {
            return $this->failure('LB4_PERSISTENCE_FAILED', $correlation, 503);
        } catch (Throwable $exception) {
            error_log('LB4 endpoint failed correlation=' . $correlation . ' exception=' . get_class($exception));
            return $this->failure('LB4_OPERATION_FAILED', $correlation, 500);
        }
    }

    /** @return list<array{banner_id:int,expected_version:int,display_order:int}> */
    private function decodeReorderItems(mixed $json): array
    {
        if (!is_string($json) || strlen($json) > 4096) {
            throw new LoginBannerDomainException('LB4_REORDER_PAYLOAD_INVALID');
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new LoginBannerDomainException('LB4_REORDER_PAYLOAD_INVALID');
        }
        $items = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                throw new LoginBannerDomainException('LB4_REORDER_PAYLOAD_INVALID');
            }
            $items[] = [
                'banner_id' => (int) ($item['banner_id'] ?? 0),
                'expected_version' => (int) ($item['expected_version'] ?? 0),
                'display_order' => (int) ($item['display_order'] ?? 0),
            ];
        }
        return $items;
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function groupRows(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $bannerId = (int) ($row['banner_id'] ?? 0);
            if ($bannerId < 1) {
                continue;
            }
            if (!isset($items[$bannerId])) {
                $items[$bannerId] = [
                    'banner_id' => $bannerId,
                    'banner_key' => (string) ($row['banner_key'] ?? ''),
                    'banner_status' => (string) ($row['banner_status'] ?? ''),
                    'display_order' => (int) ($row['display_order'] ?? 0),
                    'starts_at_utc' => $row['starts_at_utc'] ?? null,
                    'ends_at_utc' => $row['ends_at_utc'] ?? null,
                    'configuration_version' => (int) ($row['configuration_version'] ?? 0),
                    'updated_by' => (string) ($row['updated_by'] ?? ''),
                    'updated_at' => (string) ($row['updated_at'] ?? ''),
                    'locales' => [],
                ];
            }
            $locale = (string) ($row['locale'] ?? '');
            if (in_array($locale, ['ms', 'en'], true)) {
                $items[$bannerId]['locales'][$locale] = [
                    'alt_text' => (string) ($row['alt_text'] ?? ''),
                    'fallback_policy' => (string) ($row['fallback_policy'] ?? ''),
                    'asset_id' => isset($row['asset_id']) ? (int) $row['asset_id'] : null,
                    'image_filename' => (string) ($row['image_filename'] ?? ''),
                    'width' => isset($row['image_width']) ? (int) $row['image_width'] : null,
                    'height' => isset($row['image_height']) ? (int) $row['image_height'] : null,
                    'byte_size' => isset($row['byte_size']) ? (int) $row['byte_size'] : null,
                    'storage_status' => (string) ($row['storage_status'] ?? ''),
                ];
            }
        }
        return array_values($items);
    }

    /** @return array<string,mixed> */
    private function failure(string $code, string $correlation, int $httpStatus): array
    {
        return [
            'status' => 0,
            'code' => preg_match('/^LB[0-9]_[A-Z0-9_]{1,60}$/D', $code) === 1
                ? $code
                : 'LB4_OPERATION_FAILED',
            'correlation_id' => $correlation,
            '_http_status' => $httpStatus,
        ];
    }

    private function statusFor(string $reason): int
    {
        return match ($reason) {
            'LB4_SCHEMA_UNAVAILABLE', 'LB4_ENVIRONMENT_UNAVAILABLE' => 503,
            'LB3_BANNER_NOT_FOUND' => 404,
            'LB3_BANNER_STALE', 'LB3_STATE_TRANSITION_INVALID',
            'LB3_ACTIVE_BANNER_LIMIT' => 409,
            default => 422,
        };
    }
}
