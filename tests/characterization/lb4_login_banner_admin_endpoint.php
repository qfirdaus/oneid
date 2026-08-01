<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/LoginBanner/LoginBannerPersistenceException.php';
require_once dirname(__DIR__,2).'/app/LoginBanner/LoginBannerPersistenceInterface.php';
require_once dirname(__DIR__,2).'/app/LoginBanner/LoginBannerImageException.php';
require_once dirname(__DIR__,2).'/app/LoginBanner/LoginBannerImagePipelineInterface.php';
require_once dirname(__DIR__,2).'/app/LoginBanner/LoginBannerDomainException.php';
require_once dirname(__DIR__,2).'/app/LoginBanner/LoginBannerService.php';
require_once dirname(__DIR__,2).'/app/LoginBanner/LoginBannerAdminEndpoint.php';

use OneId\App\LoginBanner\LoginBannerAdminEndpoint;
use OneId\App\LoginBanner\LoginBannerPersistenceInterface;
use OneId\App\LoginBanner\LoginBannerService;

final class Lb4Persistence implements LoginBannerPersistenceInterface {
  public function __construct(public bool $ready,public array $rows=[]){ }
  public function schemaStatus():array{return['available'=>$this->ready,'tables'=>[]];}
  public function adminList(string $environment):array{return $this->rows;}
  public function transactional(callable $operation):mixed{return $operation($this);}
  public function publishedForLocale(string $environment,string $locale,string $effectiveAtUtc):array{return[];}
  public function bannerForUpdate(int $bannerId):?array{return null;}
  public function localeAssetsForUpdate(int $bannerId,string $environment):array{return[];}
  public function publishedForUpdate(string $environment):array{return[];}
  public function latestSuccessfulHistoryForUpdate(int $bannerId,string $environment):?array{return null;}
  public function insertBanner(array $banner):int{return 0;}
  public function upsertTranslation(array $translation):int{return 0;}
  public function insertAsset(array $asset):int{return 0;}
  public function assetIdByDigestForUpdate(int $bannerId,string $environment,string $sha256Digest):?int{return null;}
  public function mapLocaleAsset(int $bannerId,string $environment,string $locale,int $assetId,string $actorId):int{return 0;}
  public function updateBannerVersioned(int $bannerId,int $expectedVersion,array $changes,string $actorId):int{return 0;}
  public function updateDraftVersioned(int $bannerId,int $expectedVersion,array $changes,string $actorId):int{return 0;}
  public function recordHistory(array $event):int{return 0;}
}

$service=(new ReflectionClass(LoginBannerService::class))->newInstanceWithoutConstructor();
$make=static fn(Lb4Persistence $db)=>new LoginBannerAdminEndpoint($db,$service,'staging','/tmp/lb4-stage','/tmp/lb4-live');
$checks=[];
$closed=$make(new Lb4Persistence(false))->handle('admin_login_banner_list',[],[],'admin','127.0.0.1');
$checks['missing dormant schema fails closed with service unavailable']=$closed['status']===0&&$closed['code']==='LB4_SCHEMA_UNAVAILABLE'&&$closed['_http_status']===503;
$invalidEnvironment=new LoginBannerAdminEndpoint(new Lb4Persistence(true),$service,'','/tmp/lb4-stage','/tmp/lb4-live');
$environmentFailure=$invalidEnvironment->handle('admin_login_banner_list',[],[],'admin','127.0.0.1');
$checks['missing explicit environment fails closed']=$environmentFailure['code']==='LB4_ENVIRONMENT_UNAVAILABLE'&&$environmentFailure['_http_status']===503;
$rows=[
 ['banner_id'=>7,'banner_key'=>'WELCOME','banner_status'=>'DRAFT','display_order'=>2,'configuration_version'=>3,'locale'=>'ms','alt_text'=>'Selamat datang','fallback_policy'=>'OWN_ASSET','asset_id'=>9,'image_filename'=>'login_banner_a.webp','image_width'=>1600,'image_height'=>800,'byte_size'=>1000,'storage_status'=>'AVAILABLE'],
 ['banner_id'=>7,'banner_key'=>'WELCOME','banner_status'=>'DRAFT','display_order'=>2,'configuration_version'=>3,'locale'=>'en','alt_text'=>'Welcome','fallback_policy'=>'SAME_AS_MS','asset_id'=>9,'image_filename'=>'login_banner_a.webp','image_width'=>1600,'image_height'=>800,'byte_size'=>1000,'storage_status'=>'AVAILABLE'],
];
$loaded=$make(new Lb4Persistence(true,$rows))->handle('admin_login_banner_list',[],[],'admin','127.0.0.1');
$checks['admin list groups BM and English under one banner']=$loaded['status']===1&&count($loaded['items'])===1&&count($loaded['items'][0]['locales'])===2;
$checks['admin response exposes immutable filename but no server path']=($loaded['items'][0]['locales']['ms']['image_filename']??'')==='login_banner_a.webp'&&!str_contains(json_encode($loaded),'/tmp/');
$unknown=$make(new Lb4Persistence(true))->handle('admin_login_banner_delete',[],[],'admin','127.0.0.1');
$checks['unrecognized endpoint action is rejected']=$unknown['status']===0&&$unknown['code']==='LB4_ACTION_NOT_ALLOWED'&&$unknown['_http_status']===422;
$bad=$make(new Lb4Persistence(true))->handle('admin_login_banner_reorder',['items_json'=>'{"bad":1}'],[],'admin','127.0.0.1');
$checks['malformed reorder payload is rejected before mutation']=$bad['status']===0&&$bad['code']==='LB4_REORDER_PAYLOAD_INVALID'&&$bad['_http_status']===422;
$fail=0;foreach($checks as $label=>$ok){echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;if(!$ok)$fail++;}echo'RESULT checks='.count($checks).' failures='.$fail.PHP_EOL;exit($fail===0?0:1);
