<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/lib/upload_security.php';
require_once $root . '/app/Admin/WebAppManagementException.php';
require_once $root . '/app/Auth/TotpKeyring.php';
require_once $root . '/app/Admin/SiteApiCodeCipher.php';
require_once $root . '/app/Admin/WebAppService.php';

use OneId\App\Admin\WebAppManagementException;
use OneId\App\Admin\WebAppService;
use OneId\App\Admin\SiteApiCodeCipher;
use OneId\App\Auth\TotpKeyring;

$keyringFile = sys_get_temp_dir() . '/oneid-wa2-keyring-' . bin2hex(random_bytes(8)) . '.php';
$keyringSource = '<?php return ' . var_export(['active_version'=>'v1','keys'=>['v1'=>base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES))]], true) . ';';
file_put_contents($keyringFile, $keyringSource, LOCK_EX);
chmod($keyringFile, 0600);
$cipher = new SiteApiCodeCipher(TotpKeyring::fromFile($keyringFile));

final class Wa2FakeOperation
{
    public array $created = [];
    public array $updated = [];
    public array $audits = [];
    public array $credential = [];
    public bool $categoryExists = true;
    public bool $idExists = false;
    public bool $started = false;
    public int $addResult = 1;
    public int $updateResult = 1;
    public int $auditResult = 1;
    public int $commits = 0;
    public int $rollbacks = 0;
    public array|false $existing = ['sp_id'=>'APP1','sp_image'=>'app_icon_existing.png','avail_status'=>1];

    public function admin_app_category_exists(int $id): bool { return $this->categoryExists; }
    public function admin_get_app_category_for_update(int $id): array|false { return $this->categoryExists ? ['sp_group_id'=>$id] : false; }
    public function admin_app_id_exists(string $id): bool { return $this->idExists; }
    public function admin_get_environment(): string { return 'local'; }
    public function admin_upsert_app_asset(string $id,string $filename,string $admin): int { return 1; }
    public function admin_get_specific_service_provider(string $id): array|false { return $this->existing; }
    public function admin_get_service_provider_for_update(string $id): array|false { return $this->existing; }
    public function action_add_new_app(...$args): int { $this->created=$args; return $this->addResult; }
    public function admin_rotate_site_api_code(...$args): int { $this->credential=$args; return 1; }
    public function action_edit_app_info(...$args): int { $this->updated=$args; return $this->updateResult; }
    public function admin_update_app_metadata(...$args): int { $this->updated=$args; return $this->updateResult; }
    public function syslog_record(...$args): int { $this->audits[]=$args; return $this->auditResult; }
    public function beginTransaction(): bool { $this->started=true; return true; }
    public function commit(): bool { $this->started=false; $this->commits++; return true; }
    public function rollback(): bool { $this->started=false; $this->rollbacks++; return true; }
}

$validAdd = [
    'add_new_app_name'=>'  Pilot   App ',
    'add_new_app_desc'=>'Controlled application',
    'add_new_app_url'=>'https://pilot.example.test/path/',
    'add_new_app_category'=>'2',
];
$validEdit = [
    'edit_app_id'=>'APP1',
    'edit_app_name'=>'Pilot App',
    'edit_app_desc'=>'Controlled application',
    'edit_app_url'=>'https://pilot.example.test/path',
    'edit_app_category'=>'2',
    'edit_app_production_url'=>'https://pilot.example.test',
    'edit_app_production_ready'=>'1',
];

$reason = static function (callable $call): string {
    try { $call(); } catch (WebAppManagementException $exception) { return $exception->reason; }
    return '';
};
$checks = [];
$op = new Wa2FakeOperation();
$created = (new WebAppService($op,$cipher))->create($validAdd, null, sys_get_temp_dir(), 'ADMIN1', '127.0.0.1');
$checks['valid create is normalized and audited'] = $created['code']==='WA4_APP_CREATED_ENVIRONMENT_ASSET' && $created['icon_status']==='not_requested' && $op->created[1]==='Pilot App' && $op->created[3]==='https://pilot.example.test/path' && count($op->audits)===1;
$checks['create issues encrypted version 1 credential'] = str_starts_with((string)$created['site_api_code'],'oid_sp_') && $created['credential_version']===1 && hash_equals(hash('sha256',$created['site_api_code']),(string)$op->credential[1]) && $op->credential[4]!=='' && $op->credential[5]!=='' && $op->credential[6]==='v1';
$checks['app ID uses expected unambiguous alphabet and length'] = preg_match('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{10}$/', (string) $created['app_id'])===1;
$op = new Wa2FakeOperation();
$updated = (new WebAppService($op))->update($validEdit, null, sys_get_temp_dir(), 'ADMIN1', '127.0.0.1');
$checks['valid edit retains icon and saves production release fields'] = $updated['code']==='WA4_APP_UPDATED_ENVIRONMENT_ASSET' && $updated['icon_status']==='retained' && count($op->updated)===8 && $op->updated[6]===1 && $op->updated[7]==='https://pilot.example.test';
$checks['production-ready requires production URL'] = $reason(fn()=>(new WebAppService(new Wa2FakeOperation(),$cipher))->create(array_replace($validAdd,['add_new_app_production_ready'=>'1']),null,sys_get_temp_dir(),'ADMIN1','127.0.0.1'))==='WA2_PRODUCTION_URL_REQUIRED';
$checks['production URL must use HTTPS'] = $reason(fn()=>(new WebAppService(new Wa2FakeOperation(),$cipher))->create(array_replace($validAdd,['add_new_app_production_url'=>'http://pilot.example.test']),null,sys_get_temp_dir(),'ADMIN1','127.0.0.1'))==='WA2_PRODUCTION_URL_NOT_ALLOWED';
$checks['HTTP URL rejected'] = $reason(fn()=>(new WebAppService(new Wa2FakeOperation(),$cipher))->create(array_replace($validAdd,['add_new_app_url'=>'http://pilot.example.test']),null,sys_get_temp_dir(),'ADMIN1','127.0.0.1'))==='WA2_APP_URL_NOT_ALLOWED';
$checks['URL credentials rejected'] = $reason(fn()=>(new WebAppService(new Wa2FakeOperation(),$cipher))->create(array_replace($validAdd,['add_new_app_url'=>'https://user:pass@pilot.example.test']),null,sys_get_temp_dir(),'ADMIN1','127.0.0.1'))==='WA2_APP_URL_NOT_ALLOWED';
$checks['empty name rejected'] = $reason(fn()=>(new WebAppService(new Wa2FakeOperation(),$cipher))->create(array_replace($validAdd,['add_new_app_name'=>'']),null,sys_get_temp_dir(),'ADMIN1','127.0.0.1'))==='WA2_APP_NAME_INVALID';
$checks['invalid category ID rejected'] = $reason(fn()=>(new WebAppService(new Wa2FakeOperation(),$cipher))->create(array_replace($validAdd,['add_new_app_category'=>'x']),null,sys_get_temp_dir(),'ADMIN1','127.0.0.1'))==='WA2_CATEGORY_ID_INVALID';
$missingCategory = new Wa2FakeOperation(); $missingCategory->categoryExists=false;
$checks['missing category rejected'] = $reason(fn()=>(new WebAppService($missingCategory,$cipher))->create($validAdd,null,sys_get_temp_dir(),'ADMIN1','127.0.0.1'))==='WA2_CATEGORY_NOT_FOUND';
$missingApp = new Wa2FakeOperation(); $missingApp->existing=false;
$checks['missing edit target rejected'] = $reason(fn()=>(new WebAppService($missingApp))->update($validEdit,null,sys_get_temp_dir(),'ADMIN1','127.0.0.1'))==='WA2_APP_NOT_FOUND';
$inactive = new Wa2FakeOperation(); $inactive->existing=['sp_id'=>'APP1','sp_image'=>'','avail_status'=>0];
$checks['inactive edit target rejected'] = $reason(fn()=>(new WebAppService($inactive))->update($validEdit,null,sys_get_temp_dir(),'ADMIN1','127.0.0.1'))==='WA2_APP_INACTIVE';
$checks['invalid actor rejected'] = $reason(fn()=>(new WebAppService(new Wa2FakeOperation(),$cipher))->create($validAdd,null,sys_get_temp_dir(),'bad actor','127.0.0.1'))==='WA2_ADMIN_ID_INVALID';
$failedAdd = new Wa2FakeOperation(); $failedAdd->addResult=0;
$checks['failed create mutation rolls back'] = $reason(fn()=>(new WebAppService($failedAdd,$cipher))->create($validAdd,null,sys_get_temp_dir(),'ADMIN1','127.0.0.1'))==='WA3_APP_NOT_CREATED' && $failedAdd->rollbacks===1 && $failedAdd->commits===0;
$failedAudit = new Wa2FakeOperation(); $failedAudit->auditResult=0;
$checks['failed create audit rolls back'] = $reason(fn()=>(new WebAppService($failedAudit,$cipher))->create($validAdd,null,sys_get_temp_dir(),'ADMIN1','127.0.0.1'))==='WA3_AUDIT_NOT_WRITTEN' && $failedAudit->rollbacks===1 && $failedAudit->commits===0;
$failedEditAudit = new Wa2FakeOperation(); $failedEditAudit->auditResult=0;
$checks['failed edit audit rolls back'] = $reason(fn()=>(new WebAppService($failedEditAudit))->update($validEdit,null,sys_get_temp_dir(),'ADMIN1','127.0.0.1'))==='WA3_AUDIT_NOT_WRITTEN' && $failedEditAudit->rollbacks===1 && $failedEditAudit->commits===0;

$passed=0;
foreach($checks as $label=>$ok){echo ($ok?'PASS':'FAIL').' '.$label."\n";$passed+=$ok?1:0;}
printf("RESULT %d/%d\n",$passed,count($checks));
@unlink($keyringFile);
exit($passed===count($checks)?0:1);
