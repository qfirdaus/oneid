<?php
declare(strict_types=1);

$root=dirname(__DIR__);$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;if(!$ok)$failed++;};
foreach(['admin/dashboard.php','app/Admin/SystemLocaleConfigurationService.php','tests/characterization/ml5_admin_locale.php','tools/ml5_default_locale_schema_rehearsal.php','docs/migrations/20260725_ml5_system_default_locale_up.sql','docs/migrations/20260725_ml5_system_default_locale_down.sql']as$relative){exec('php -l '.escapeshellarg($root.'/'.$relative).' 2>&1',$output,$status);$report($status===0,"source and lint {$relative}");}
$admin=file_get_contents($root.'/admin/dashboard.php')?:'';
$request=file_get_contents($root.'/lib/request_security.php')?:'';
$locale=file_get_contents($root.'/lib/locale.php')?:'';
$up=file_get_contents($root.'/docs/migrations/20260725_ml5_system_default_locale_up.sql')?:'';
$down=file_get_contents($root.'/docs/migrations/20260725_ml5_system_default_locale_down.sql')?:'';
$report(str_contains($admin,"oneid_translate('admin.menu.web_apps')")&&str_contains($admin,"oneid_translate('admin.users.title')")&&str_contains($admin,"oneid_translate('admin.sessions.title')")&&str_contains($admin,"oneid_translate('admin.audit.title')"),'Admin primary surfaces use catalogue keys');
$externalStart=strpos($admin,'id="modal_add_new_user_option"');$externalEnd=strpos($admin,'id="modal_add_new_user_manual"');$external=$externalStart===false||$externalEnd===false?'':substr($admin,$externalStart,$externalEnd-$externalStart);
$report(!str_contains($external,"oneid_translate('admin."),'External Sync modal remains outside ML5 translation');
$report(str_contains($request,"'admin_update_default_locale'")&&str_contains($request,"'SECURITY_CONFIGURATION_CHANGE'"),'default locale update requires security configuration step-up');
$report(str_contains($admin,'id="system_default_locale_reason_preset"')&&str_contains($admin,'value="OTHER"')&&str_contains($admin,"preset==='OTHER'")&&str_contains($admin,'system_default_locale_reason_other'),'default locale offers preset reasons and reveals custom input only for Other');
$report(str_contains($locale,'oneid_system_default_locale()')&&str_contains($locale,'SELECT default_locale FROM sys_config'),'resolver consumes guarded database default with fallback');
$report(str_contains($up,"CHECK (default_locale IN ('ms','en'))")&&str_contains($down,'DROP COLUMN default_locale'),'default locale migration is constrained and reversible');
passthru('php '.escapeshellarg($root.'/tests/characterization/ml5_admin_locale.php'),$status);$report($status===0,'ML5 characterization passes');
passthru('php '.escapeshellarg($root.'/tools/ml5_default_locale_schema_rehearsal.php'),$status);$report($status===0,'ML5 default-locale migration rehearsal passes');
echo"RESULT checks={$checks} failed={$failed}\n";exit($failed===0?0:1);
