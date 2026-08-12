<?php

declare(strict_types=1);
require_once dirname(__DIR__).'/lib/config.php';

$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{
    $checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);
};

$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$schema=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sp_api_credential'")->fetchColumn();
$report($schema===1,'credential table is installed');
$apps=$pdo->query("SELECT sp_id FROM sp_list WHERE avail_status=1 AND sp_id<>'IDP' ORDER BY sp_id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
$report(count($apps)===2,'two active apps are available for an isolated rotation test');

if(count($apps)===2){
    [$rotatedApp,$legacyApp]=$apps;
    $newCode='oid_sp_'.rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
    $operation->beginTransaction();
    try{
        $operation->admin_rotate_site_api_code($rotatedApp,hash('sha256',$newCode),'...'.substr($newCode,-4),'CONTRACT');
        $resolvedNew=$operation->resolve_site_api_code($newCode);
        $resolvedOld=$operation->resolve_site_api_code($rotatedApp);
        $resolvedLegacy=$operation->resolve_site_api_code($legacyApp);
        $report(is_array($resolvedNew)&&$resolvedNew['sp_id']===$rotatedApp,'new code resolves to the rotated app');
        $report($resolvedOld===false,'old code is immediately rejected for the rotated app');
        $report(is_array($resolvedLegacy)&&$resolvedLegacy['sp_id']===$legacyApp,'unrotated app legacy code remains valid');
    }finally{
        $operation->rollback();
    }
}

$service=(string)file_get_contents(dirname(__DIR__).'/app/Admin/WebAppService.php');
$api=(string)file_get_contents(dirname(__DIR__).'/api.php');
$ui=(string)file_get_contents(dirname(__DIR__).'/admin/dashboard.php');
$report(str_contains($service,"random_bytes(32)")&&str_contains($service,"hash('sha256',\$raw)"),'rotation generates a strong code and stores its hash');
$report(str_contains($api,'resolve_site_api_code'),'SSO API resolves Site API Code before ACL checks');
$report(str_contains($ui,'Generate New Site API Code')&&str_contains($ui,'admin_rotate_site_api_code'),'admin Web Apps UI exposes per-app rotation');
$rotationCss=(string)file_get_contents(dirname(__DIR__).'/public/dist/css/oneid-site-api-rotation.css');
$report(substr_count($ui,'<div class="rotation-reason-dialog__choice')>=7&&str_contains($ui,'site_api_rotation_other_reason'),'rotation reason uses seven non-button selectable cards with an inline Other option');
$report(str_contains($rotationCss,'.rotation-reason-dialog__choices')&&str_contains($rotationCss,'.rotation-reason-dialog__choice.is-selected')&&str_contains($rotationCss,'.rotation-reason-dialog__notice'),'rotation reason SweetAlert has dedicated selected-state styling');
$report(str_contains($ui,'id="btn_copy_site_api_code"')&&str_contains($ui,'id="btn_rotate_site_api_code"')&&str_contains($ui,'id="edit_app_code"'),'copy, Site API Code and generate controls share one input group');
$report(str_contains($ui,"confirmButtonText:'Generate New Code'")&&str_contains($ui,'execute_site_api_code_rotation(appId,reason)')&&!str_contains($ui,'confirm_site_api_code_rotation'),'reason selection and code generation use one SweetAlert');
$report(str_contains($ui,'copy_generated_site_api_code')&&str_contains($ui,"navigator.clipboard.writeText(newCode)")&&str_contains($rotationCss,'.site-api-code-result__copy'),'generated code result provides a styled copy control and one-time recovery guidance');

printf("RESULT checks=%d failed=%d\n",$checks,$failed);
exit($failed===0?0:1);
