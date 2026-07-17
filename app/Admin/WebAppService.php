<?php

namespace OneId\App\Admin;

use Throwable;

final class WebAppService
{
    private const APP_ID_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly object $operation)
    {
    }

    /** @param array<string,mixed> $input @param array<string,mixed>|null $file */
    public function create(array $input, ?array $file, string $uploadDir, string $adminId, string $ipAddress): array
    {
        $correlationId = bin2hex(random_bytes(8));
        $data = $this->normalizeInput($input, false, $correlationId);
        $this->validateActor($adminId, $ipAddress, $correlationId);
        $uploadRequested = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $staged = ['success'=>false,'filename'=>'','staged_path'=>'','message'=>'No file uploaded'];
        if ($uploadRequested) {
            $staged = stage_app_icon_upload($file, dirname(__DIR__, 2).'/storage/runtime/app-icon-staging');
            if (!$staged['success']) {
                throw new WebAppManagementException('WA3_ICON_REJECTED', $correlationId, ['upload_message'=>$staged['message']]);
            }
        }
        $started = false;
        $publishedPath = '';
        try {
            $this->operation->beginTransaction();
            $started = true;
            if (!is_array($this->operation->admin_get_app_category_for_update($data['category_id']))) {
                throw new WebAppManagementException('WA2_CATEGORY_NOT_FOUND', $correlationId);
            }
            $appId = $this->newAppId($correlationId);
            $environment=$this->operation->admin_get_environment();
            $affected = $this->operation->action_add_new_app(
                $appId,
                $data['name'],
                $data['description'],
                $data['url'],
                '',
                $data['category_id'],
                $data['sso_mode']
            );
            if ($affected !== 1) {
                throw new WebAppManagementException('WA3_APP_NOT_CREATED', $correlationId);
            }
            if ($uploadRequested && $this->operation->admin_upsert_app_asset($appId,$staged['filename'],trim($adminId)) < 1) {
                throw new WebAppManagementException('WA4_ASSET_NOT_WRITTEN',$correlationId);
            }
            $iconStatus = $uploadRequested ? 'stored' : 'not_requested';
            $detail = sprintf('admin=%s action=create_app app=%s environment=%s outcome=success icon=%s correlation=%s',trim($adminId),$appId,$environment,$iconStatus,$correlationId);
            if ($this->operation->syslog_record(13, $detail, trim($ipAddress)) !== 1) {
                throw new WebAppManagementException('WA3_AUDIT_NOT_WRITTEN', $correlationId);
            }
            if ($uploadRequested) {
                $publishedPath = publish_staged_app_icon($staged, $uploadDir);
            }
            $this->operation->commit();
            $started = false;
            return [
                'status'=>1,
                'code'=>'WA4_APP_CREATED_ENVIRONMENT_ASSET',
                'app_id'=>$appId,
                'icon_status'=>$iconStatus,
                'app_icon'=>$uploadRequested?'Upload published':'No file uploaded',
                'correlation_id'=>$correlationId,
            ];
        } catch (Throwable $exception) {
            $this->compensate($started, $staged, $publishedPath, $correlationId);
            if ($exception instanceof WebAppManagementException) {
                throw $exception;
            }
            error_log('WA3 app create failed correlation_id='.$correlationId.' exception='.get_class($exception));
            throw new WebAppManagementException('WA3_APP_CREATE_FAILED', $correlationId);
        }
    }

    /** @param array<string,mixed> $input @param array<string,mixed>|null $file */
    public function update(array $input, ?array $file, string $uploadDir, string $adminId, string $ipAddress): array
    {
        $correlationId = bin2hex(random_bytes(8));
        $data = $this->normalizeInput($input, true, $correlationId);
        $this->validateActor($adminId, $ipAddress, $correlationId);
        $uploadRequested = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $staged = ['success'=>false,'filename'=>'','staged_path'=>'','message'=>'No file uploaded'];
        if ($uploadRequested) {
            $staged = stage_app_icon_upload($file, dirname(__DIR__, 2).'/storage/runtime/app-icon-staging');
            if (!$staged['success']) {
                throw new WebAppManagementException('WA3_ICON_REJECTED', $correlationId, ['upload_message'=>$staged['message']]);
            }
        }
        $started = false;
        $publishedPath = '';
        try {
            $this->operation->beginTransaction();
            $started = true;
            if (!is_array($this->operation->admin_get_app_category_for_update($data['category_id']))) {
                throw new WebAppManagementException('WA2_CATEGORY_NOT_FOUND', $correlationId);
            }
            $existing = $this->operation->admin_get_service_provider_for_update($data['app_id']);
            if (!is_array($existing)) {
                throw new WebAppManagementException('WA2_APP_NOT_FOUND', $correlationId);
            }
            if ((int) ($existing['avail_status'] ?? 0) !== 1) {
                throw new WebAppManagementException('WA2_APP_INACTIVE', $correlationId);
            }
            $environment=$this->operation->admin_get_environment();
            $iconStatus = $uploadRequested ? 'stored' : 'retained';
            $affected = $this->operation->admin_update_app_metadata(
                $data['app_id'],
                $data['name'],
                $data['description'],
                $data['url'],
                $data['category_id'],
                $data['sso_mode']
            );
            $assetAffected=0;
            if($uploadRequested){
                $assetAffected=$this->operation->admin_upsert_app_asset($data['app_id'],$staged['filename'],trim($adminId));
                if($assetAffected<1)throw new WebAppManagementException('WA4_ASSET_NOT_WRITTEN',$correlationId);
            }
            if ($affected === 0 && $assetAffected === 0) {
                $this->operation->commit();
                $started = false;
                discard_staged_app_icon($staged);
                return ['status'=>0,'code'=>'WA3_APP_UNCHANGED','icon_status'=>'retained','app_icon'=>'Existing app icon retained','correlation_id'=>$correlationId];
            }
            if ($affected < 0 || $affected > 1) {
                throw new WebAppManagementException('WA3_APP_UPDATE_COUNT_INVALID', $correlationId);
            }
            $detail = sprintf('admin=%s action=update_app app=%s environment=%s outcome=success icon=%s correlation=%s',trim($adminId),$data['app_id'],$environment,$iconStatus,$correlationId);
            if ($this->operation->syslog_record(14, $detail, trim($ipAddress)) !== 1) {
                throw new WebAppManagementException('WA3_AUDIT_NOT_WRITTEN', $correlationId);
            }
            if ($uploadRequested) {
                $publishedPath = publish_staged_app_icon($staged, $uploadDir);
            }
            $this->operation->commit();
            $started = false;
            return [
                'status'=>1,
                'code'=>'WA4_APP_UPDATED_ENVIRONMENT_ASSET',
                'icon_status'=>$iconStatus,
                'app_icon'=>$uploadRequested?'Upload published':'Existing app icon retained',
                'correlation_id'=>$correlationId,
            ];
        } catch (Throwable $exception) {
            $this->compensate($started, $staged, $publishedPath, $correlationId);
            if ($exception instanceof WebAppManagementException) {
                throw $exception;
            }
            error_log('WA3 app update failed correlation_id='.$correlationId.' exception='.get_class($exception));
            throw new WebAppManagementException('WA3_APP_UPDATE_FAILED', $correlationId);
        }
    }

    private function compensate(bool $started, array $staged, string $publishedPath, string $correlationId): void
    {
        if ($started) {
            try {$this->operation->rollback();} catch (Throwable $ignored) {error_log('WA3 rollback failed correlation_id='.$correlationId);}
        }
        discard_staged_app_icon($staged);
        if ($publishedPath !== '' && is_file($publishedPath) && !unlink($publishedPath)) {
            error_log('WA3 published icon compensation failed correlation_id='.$correlationId);
        }
    }

    /** @param array<string,mixed> $input @return array{name:string,description:string,url:string,category_id:int,sso_mode:int,app_id?:string} */
    private function normalizeInput(array $input, bool $editing, string $correlationId): array
    {
        $prefix = $editing ? 'edit_app_' : 'add_new_app_';
        $name = preg_replace('/\s+/u', ' ', trim((string) ($input[$prefix.'name'] ?? ''))) ?? '';
        $description = trim((string) ($input[$prefix.'desc'] ?? ''));
        $url = trim((string) ($input[$prefix.'url'] ?? ''));
        $category = trim((string) ($input[$prefix.'category'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150 || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
            throw new WebAppManagementException('WA2_APP_NAME_INVALID', $correlationId);
        }
        if ($description === '' || mb_strlen($description) > 2000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $description) === 1) {
            throw new WebAppManagementException('WA2_APP_DESCRIPTION_INVALID', $correlationId);
        }
        if (strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new WebAppManagementException('WA2_APP_URL_INVALID', $correlationId);
        }
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new WebAppManagementException('WA2_APP_URL_NOT_ALLOWED', $correlationId);
        }
        $url = rtrim($url, '/');
        if (preg_match('/^\d{1,20}$/', $category) !== 1) {
            throw new WebAppManagementException('WA2_CATEGORY_ID_INVALID', $correlationId);
        }
        $result = [
            'name'=>$name,
            'description'=>$description,
            'url'=>$url,
            'category_id'=>(int) $category,
            'sso_mode'=>isset($input[$editing ? 'app_info_sso_checkbox' : 'add_new_app_sso_checkbox']) ? 1 : 0,
        ];
        if ($editing) {
            $appId = trim((string) ($input['edit_app_id'] ?? ''));
            if ($appId === '' || strlen($appId) > 20 || preg_match('/^[A-Za-z0-9_-]+$/', $appId) !== 1) {
                throw new WebAppManagementException('WA2_APP_ID_INVALID', $correlationId);
            }
            $result['app_id'] = $appId;
        }
        return $result;
    }

    private function validateActor(string $adminId, string $ipAddress, string $correlationId): void
    {
        $adminId = trim($adminId);
        $ipAddress = trim($ipAddress);
        if ($adminId === '' || strlen($adminId) > 20 || preg_match('/^[A-Za-z0-9._@-]+$/', $adminId) !== 1) {
            throw new WebAppManagementException('WA2_ADMIN_ID_INVALID', $correlationId);
        }
        if ($ipAddress === '' || strlen($ipAddress) > 50 || preg_match('/[\x00-\x1F\x7F]/', $ipAddress) === 1) {
            throw new WebAppManagementException('WA2_IP_ADDRESS_INVALID', $correlationId);
        }
    }

    private function newAppId(string $correlationId): string
    {
        $max = strlen(self::APP_ID_ALPHABET) - 1;
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $id = '';
            for ($index = 0; $index < 10; $index++) {
                $id .= self::APP_ID_ALPHABET[random_int(0, $max)];
            }
            if (!$this->operation->admin_app_id_exists($id)) {
                return $id;
            }
        }
        throw new WebAppManagementException('WA2_APP_ID_EXHAUSTED', $correlationId);
    }

    public function archive(string $appId, string $adminId, string $ipAddress): array
    {
        $correlationId = bin2hex(random_bytes(8));
        $appId = trim($appId);
        $adminId = trim($adminId);
        if ($appId === '' || strlen($appId) > 20 || preg_match('/^[A-Za-z0-9_-]+$/', $appId) !== 1) {
            throw new WebAppManagementException('W3_APP_ID_INVALID', $correlationId);
        }
        if ($adminId === '' || strlen($adminId) > 20 || preg_match('/^[A-Za-z0-9._@-]+$/', $adminId) !== 1) {
            throw new WebAppManagementException('W3_ADMIN_ID_INVALID', $correlationId);
        }

        $started = false;
        try {
            $this->operation->beginTransaction();
            $started = true;
            $app = $this->operation->admin_get_service_provider_for_update($appId);
            if (!is_array($app)) {
                throw new WebAppManagementException('W3_APP_NOT_FOUND', $correlationId);
            }
            if ((int) $app['avail_status'] !== 1) {
                throw new WebAppManagementException('W3_APP_ALREADY_INACTIVE', $correlationId);
            }
            if ($this->operation->admin_archive_service_provider($appId) !== 1) {
                throw new WebAppManagementException('W3_APP_NOT_ARCHIVED', $correlationId);
            }

            $removed = [];
            foreach (['acl_group','acl_single','acl_blacklist','user_app_favourite'] as $table) {
                $removed[$table] = $this->operation->admin_delete_app_access_references($table, $appId);
            }
            $detail = sprintf(
                'admin=%s action=archive_app app=%s old_category=%s acl_group=%d acl_single=%d blacklist=%d favourites=%d correlation=%s',
                $adminId,
                $appId,
                (string) $app['sp_group_id'],
                $removed['acl_group'],
                $removed['acl_single'],
                $removed['acl_blacklist'],
                $removed['user_app_favourite'],
                $correlationId
            );
            if ($this->operation->syslog_record(15, $detail, $ipAddress) !== 1) {
                throw new WebAppManagementException('W3_AUDIT_NOT_WRITTEN', $correlationId);
            }
            $this->operation->commit();
            return [
                'status'=>1,
                'code'=>'W3_APP_ARCHIVED',
                'correlation_id'=>$correlationId,
                'removed_references'=>$removed,
            ];
        } catch (Throwable $exception) {
            if ($started) {
                try {
                    $this->operation->rollback();
                } catch (Throwable $ignored) {
                    error_log('W3 app rollback failed correlation_id=' . $correlationId);
                }
            }
            if ($exception instanceof WebAppManagementException) {
                throw $exception;
            }
            error_log('W3 app archive failed correlation_id=' . $correlationId . ' exception=' . get_class($exception));
            throw new WebAppManagementException('W3_APP_OPERATION_FAILED', $correlationId);
        }
    }
}
