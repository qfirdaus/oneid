<?php
require_once __DIR__ . '/session_security.php';
oneid_start_secure_session();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/src/Exception.php';
require __DIR__ . '/src/PHPMailer.php';
require __DIR__ . '/src/SMTP.php';
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/app/Audit/AuditIdentityResolver.php';
require_once __DIR__ . '/upload_security.php';
require_once __DIR__ . '/device_info.php';
include_once dirname(__DIR__) . '/vendors/spyc-master/Spyc.php';
require_once dirname(__DIR__) . '/vendors/device-detector-master/autoload.php';
require_once __DIR__ . '/external_data_source_API.php';
require_once __DIR__ . '/sync_user_runner.php';
require_once dirname(__DIR__) . '/bootstrap/sync_runtime.php';
require_once dirname(__DIR__) . '/app/User/ManualUserInput.php';
require_once dirname(__DIR__) . '/app/User/ManualUserCreator.php';
require_once dirname(__DIR__) . '/app/User/Contracts/UserResyncApprovalStoreInterface.php';
require_once dirname(__DIR__) . '/app/User/Adapters/SessionUserResyncApprovalStore.php';
require_once dirname(__DIR__) . '/app/User/UserResyncException.php';
require_once dirname(__DIR__) . '/app/User/UserResyncService.php';
require_once dirname(__DIR__) . '/app/User/UserSecurityActionException.php';
require_once dirname(__DIR__) . '/app/User/UserSecurityActionService.php';
require_once dirname(__DIR__) . '/app/User/UserPasswordChangeException.php';
require_once dirname(__DIR__) . '/app/User/UserPasswordChangeService.php';
require_once dirname(__DIR__) . '/app/User/InitialPasswordSetupService.php';
require_once dirname(__DIR__) . '/app/User/UserManagementException.php';
require_once dirname(__DIR__) . '/app/User/UserProfilePolicyService.php';
require_once dirname(__DIR__) . '/app/User/UserAclManagementService.php';
require_once dirname(__DIR__) . '/app/Admin/WebAppManagementException.php';
require_once dirname(__DIR__) . '/app/Admin/WebAppCategoryService.php';
require_once dirname(__DIR__) . '/app/Admin/WebAppService.php';
require_once dirname(__DIR__) . '/app/Admin/SiteApiCodeCipher.php';
require_once dirname(__DIR__) . '/app/Admin/SsoConfigurationException.php';
require_once dirname(__DIR__) . '/app/Admin/SsoConfigurationService.php';
require_once dirname(__DIR__) . '/app/Admin/PasswordRecoveryConfigurationService.php';
require_once dirname(__DIR__) . '/app/Admin/SystemLocaleConfigurationService.php';
require_once dirname(__DIR__) . '/app/Admin/MaintenanceConfigurationService.php';
require_once dirname(__DIR__) . '/app/Admin/UserMfaGlobalPolicyService.php';
require_once dirname(__DIR__) . '/app/Admin/UserMfaCategoryPolicyService.php';
require_once dirname(__DIR__) . '/app/Admin/UserMfaTemporaryExemptionService.php';
require_once dirname(__DIR__) . '/app/Admin/ActiveSessionService.php';
require_once dirname(__DIR__) . '/app/Admin/ActiveSessionRevocationException.php';
require_once dirname(__DIR__) . '/app/Admin/ActiveSessionRevocationConfig.php';
require_once dirname(__DIR__) . '/app/Admin/Adapters/SessionRevocationPreviewStore.php';
require_once dirname(__DIR__) . '/app/Admin/ActiveSessionRevocationService.php';
require_once dirname(__DIR__) . '/app/Admin/UserCategoryReportReference.php';
require_once dirname(__DIR__) . '/app/Admin/AdminReportCatalogue.php';
require_once dirname(__DIR__) . '/app/Admin/AdminReportReference.php';
require_once dirname(__DIR__) . '/app/Auth/SsoTokenLifetimePolicy.php';
require_once dirname(__DIR__) . '/app/Auth/UserPortalSessionService.php';
require_once dirname(__DIR__) . '/app/Auth/AdminStepUpException.php';
require_once dirname(__DIR__) . '/app/Auth/AdminStepUpEmailSenderInterface.php';
require_once dirname(__DIR__) . '/app/Auth/AdminStepUpEmailOtpService.php';
require_once dirname(__DIR__) . '/app/Auth/AdminStepUpPhpMailerSender.php';
require_once dirname(__DIR__) . '/app/Auth/TotpKeyring.php';
require_once dirname(__DIR__) . '/app/Auth/TotpSecretCipher.php';
require_once dirname(__DIR__) . '/app/Auth/Totp.php';
require_once dirname(__DIR__) . '/app/Auth/AdminTotpFactorService.php';
require_once dirname(__DIR__) . '/app/Auth/AdminStepUpTotpService.php';
require_once dirname(__DIR__) . '/app/Auth/AdminMfaPreferenceService.php';
require_once dirname(__DIR__) . '/app/Auth/AdminStepUpPolicyService.php';
require_once dirname(__DIR__) . '/app/Auth/AdminStepUpSessionService.php';
require_once dirname(__DIR__) . '/app/Auth/Admin2faBootstrapService.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserLoginMfaPolicy.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaRuntimeGate.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaWebSecurityGate.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaHttpBoundary.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaAuditWriterInterface.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaSessionRevokerInterface.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaTotpPersistenceInterface.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaTotpException.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/LegacyUserMfaAuditWriter.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/LegacyUserMfaSessionRevoker.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/PdoUserMfaTotpPersistence.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/PdoUserMfaPolicyReader.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaPendingLoginException.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaPendingLoginPersistenceInterface.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaRequestBinding.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaPendingLoginCoordinator.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/PdoUserMfaPendingLoginPersistence.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaPrimaryAuthDecision.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaLoginFinalizerInterface.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/LegacyUserMfaLoginFinalizer.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaEmailSenderInterface.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaEmailOtpException.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaEmailOtpPersistenceInterface.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaOtp.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaRateLimitConfig.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/PdoUserMfaEmailOtpPersistence.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaPhpMailerSender.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaRecoveryEmailSender.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaTotpEmailRecoveryService.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaEmailOtpService.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaTotpPrimitive.php';
require_once dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaTotpService.php';
require_once dirname(__DIR__) . '/app/Mail/OneIdEmailTemplate.php';
require_once dirname(__DIR__) . '/app/Notification/AdminEmailNotificationException.php';
require_once dirname(__DIR__) . '/app/Notification/AdminEmailNotificationRepository.php';
require_once dirname(__DIR__) . '/app/Notification/AdminEmailNotificationDispatcher.php';
require_once dirname(__DIR__) . '/app/Notification/AdminEmailNotificationPdoComposer.php';
require_once dirname(__DIR__) . '/app/Locale/ApiResponseLocalizer.php';
require_once dirname(__DIR__) . '/app/LoginBanner/LoginBannerPersistenceException.php';
require_once dirname(__DIR__) . '/app/LoginBanner/LoginBannerPersistenceInterface.php';
require_once dirname(__DIR__) . '/app/LoginBanner/PdoLoginBannerPersistence.php';
require_once dirname(__DIR__) . '/app/LoginBanner/LoginBannerImageException.php';
require_once dirname(__DIR__) . '/app/LoginBanner/LoginBannerImagePipelineInterface.php';
require_once dirname(__DIR__) . '/app/LoginBanner/LoginBannerImagePipeline.php';
require_once dirname(__DIR__) . '/app/LoginBanner/LoginBannerDomainException.php';
require_once dirname(__DIR__) . '/app/LoginBanner/LoginBannerService.php';
require_once dirname(__DIR__) . '/app/LoginBanner/LoginBannerAdminEndpoint.php';
require_once dirname(__DIR__) . '/app/Maintenance/MaintenanceDeveloperAccessException.php';
require_once dirname(__DIR__) . '/app/Maintenance/MaintenanceDeveloperAccessPolicy.php';
require_once dirname(__DIR__) . '/app/Maintenance/MaintenanceDeveloperAccessRepositoryInterface.php';
require_once dirname(__DIR__) . '/app/Maintenance/PdoMaintenanceDeveloperAccessRepository.php';
require_once dirname(__DIR__) . '/app/Maintenance/MaintenanceDeveloperAccessService.php';
require_once dirname(__DIR__) . '/app/Maintenance/MaintenanceDeveloperAccessAdminEndpoint.php';
require_once dirname(__DIR__) . '/app/Metadata/BilingualMetadataRepository.php';
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\AbstractDeviceParser;

require_once __DIR__ . '/request_security.php';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$dd = new DeviceDetector($userAgent);
$dd->parse();
$detectedDeviceInfo = oneid_format_device_info(
    $dd->getDeviceName(),
    $dd->getBrandName(),
    $dd->getModel(),
    $dd->getClient('name'),
    $dd->getOs('name')
);
$oneidGuardedAction=oneid_guard_q_func_request($_POST,$operation);

if(in_array($oneidGuardedAction,['user_session_status','user_session_renew','user_session_expire'],true)){
  try{
    $service=new \OneId\App\Auth\UserPortalSessionService($operation);
    $user=(string)$_SESSION['login_user'];$ip=(string)($_SERVER['REMOTE_ADDR']??'');
    if($oneidGuardedAction==='user_session_status'){$results=$service->status();}
    elseif($oneidGuardedAction==='user_session_renew'){$results=$service->renew($user,$ip);}
    else{$results=$service->expire($user,$ip);}
  }catch(\RuntimeException $e){
    $known=['USER_SESSION_EXPIRED'=>401,'SESSION_STATUS_UNAVAILABLE'=>503];$code=$e->getMessage();
    if(!isset($known[$code])){$code='SESSION_STATUS_UNAVAILABLE';}
    http_response_code($known[$code]);$results=['status'=>0,'authenticated'=>false,'code'=>$code,'error'=>'OneID portal session request was not completed.'];
  }
  header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($results);return;
}

if(str_starts_with($oneidGuardedAction,'user_mfa_')){
  $mode=(string)oneid_config('ONEID_USER_MFA_MODE','OFF');
  $schemaApply=filter_var(oneid_config('ONEID_USER_MFA_SCHEMA_APPLY_ENABLED','false'),FILTER_VALIDATE_BOOLEAN);
  $authorized=filter_var(oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED','false'),FILTER_VALIDATE_BOOLEAN);
  $gate=new \OneId\App\Auth\UserMfa\UserMfaRuntimeGate($mode,$schemaApply,$authorized);
  try{
    $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $schemaReady=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN('user_login_mfa_policy','user_login_mfa_policy_history','user_mfa_factors','user_mfa_preferences','user_login_mfa_transactions','user_login_mfa_challenges','user_login_mfa_pilot_users')")->fetchColumn()===7;
    $gate->assertRequestAllowed($schemaReady);
    $gate->assertFeatureActive();
    $userMfaPolicies=new \OneId\App\Auth\UserMfa\PdoUserMfaPolicyReader($pdo);
    $userMfaPolicies->assertRuntimeParity($mode);
    if($userMfaPolicies->policy()->mode==='OFF'){
      throw new RuntimeException('USER_MFA_NOT_ACTIVE');
    }
    $userMfaPolicy=$userMfaPolicies->policy();
    $userMfaSelfServiceUser=(string)($_SESSION['login_user']??'');
    $userMfaSelfServiceAllowed=$userMfaPolicies->selfServiceEligible($userMfaSelfServiceUser)
      && ($userMfaPolicy->mode!=='PILOT_ENFORCED'
        || $userMfaPolicies->pilotEligible($userMfaSelfServiceUser));
    if(in_array($oneidGuardedAction,['user_mfa_totp_enroll','user_mfa_totp_confirm','user_mfa_totp_preference','user_mfa_totp_revoke','user_mfa_totp_recovery_email_request','user_mfa_totp_recovery_email_verify'],true)
      && !$userMfaSelfServiceAllowed){
      throw new RuntimeException('USER_MFA_PILOT_ACCESS_REQUIRED');
    }
    if(in_array($oneidGuardedAction,['user_mfa_email_request','user_mfa_email_resend','user_mfa_email_verify','user_mfa_totp_verify_login'],true)){
      $pendingUser=(string)($_SESSION['user_mfa_pending_user']??'');
      $pendingTransaction=(string)($_SESSION['user_mfa_pending_transaction']??'');
      if($pendingUser===''||$pendingTransaction===''||!hash_equals($pendingTransaction,(string)($_POST['transaction_id']??''))){
        throw new RuntimeException('USER_MFA_PENDING_SESSION_INVALID');
      }
      $audit=new \OneId\App\Auth\UserMfa\LegacyUserMfaAuditWriter($operation);
      $session=session_id();$ua=(string)($_SERVER['HTTP_USER_AGENT']??'');$ip=(string)getUserIP();
      $finalizeUserMfaLogin=static function()use($pdo,$audit,$pendingTransaction,$session,$ua,$ip,$operation,$detectedDeviceInfo):array{
        $maintenanceDeveloper=(bool)($_SESSION['user_mfa_pending_developer_maintenance']??false);
        $maintenanceDeveloperGrantId=(int)($_SESSION['user_mfa_pending_developer_grant_id']??0);
        $maintenanceDeveloperGrantVersion=(int)($_SESSION['user_mfa_pending_developer_grant_version']??0);
        $verifyMaintenanceDeveloper=static function()use($pdo,$operation,$maintenanceDeveloper,$maintenanceDeveloperGrantId,$maintenanceDeveloperGrantVersion):array{
          if(!$maintenanceDeveloper){return['allowed'=>false];}
          $maintenance=$operation->get_maintenance_config();
          if(!is_array($maintenance)||!(bool)(\OneId\App\Maintenance\MaintenancePolicy::evaluate($maintenance)['active']??false)){
            throw new RuntimeException('MAINTENANCE_NOT_ACTIVE');
          }
          $service=new \OneId\App\Maintenance\MaintenanceDeveloperAccessService(
            new \OneId\App\Maintenance\PdoMaintenanceDeveloperAccessRepository($pdo)
          );
          $decision=$service->revalidate((string)($_SESSION['user_mfa_pending_user']??''));
          if(!($decision['allowed']??false)
            ||(int)($decision['grant_id']??0)!==$maintenanceDeveloperGrantId
            ||(int)($decision['configuration_version']??0)!==$maintenanceDeveloperGrantVersion){
            throw new RuntimeException('MAINTENANCE_ACCESS_REVALIDATION_FAILED');
          }
          return$decision;
        };
        if($maintenanceDeveloper){$verifyMaintenanceDeveloper();}
        $pendingPersistence=new \OneId\App\Auth\UserMfa\PdoUserMfaPendingLoginPersistence($pdo,$audit);
        $coordinator=new \OneId\App\Auth\UserMfa\UserMfaPendingLoginCoordinator($pendingPersistence);
        $final=$coordinator->finalize(
          $pendingTransaction,$session,$ua,$ip,
          new \OneId\App\Auth\UserMfa\LegacyUserMfaLoginFinalizer($operation,$detectedDeviceInfo)
        );
        $handle=$final['completion_handle'];$userInfo=$operation->get_specific_user_info((string)$final['user_id']);
        if(!is_array($userInfo)){throw new RuntimeException('USER_MFA_USER_SESSION_UNAVAILABLE');}
        $maintenanceAdmin=(bool)($_SESSION['user_mfa_pending_admin_maintenance']??false);unset($_SESSION['user_mfa_pending_admin_maintenance']);
        $maintenanceFactor=(string)($_SESSION['user_mfa_pending_maintenance_factor']??'');unset($_SESSION['user_mfa_pending_maintenance_factor']);
        try{
          oneid_set_configured_sso_cookie($operation,(string)$handle['token']);
          oneid_establish_authenticated_session($userInfo);
          if($maintenanceAdmin){
            if((string)($userInfo['u_type']??'')!=='1'||!in_array($maintenanceFactor,['EMAIL_OTP','TOTP'],true)){
              throw new RuntimeException('MAINTENANCE_MFA_FINALIZATION_INVALID');
            }
            // admin_step_up_grants.correlation_id is CHAR(16), while User MFA
            // uses a 32-character correlation identifier.
            $grantCorrelation=substr((string)$final['correlation_id'],0,16);
            $operation->admin_step_up_revoke_all_active_access_grants((string)$final['user_id']);
            if($operation->admin_step_up_create_grant([
              'grant_id'=>bin2hex(random_bytes(32)),
              'admin_user_id'=>(string)$final['user_id'],
              'session_binding_hash'=>hash('sha256',session_id()),
              'browser_digest'=>hash('sha256',substr($ua,0,1000)),
              'purpose'=>'ADMIN_ACCESS','verified_factor'=>$maintenanceFactor,
              'lifetime_minutes'=>5,'correlation_id'=>$grantCorrelation,
            ])!==1){throw new RuntimeException('MAINTENANCE_GRANT_CREATE_FAILED');}
            if($operation->syslog_record(39,'admin='.(string)$final['user_id'].' action=maintenance_login purpose=ADMIN_ACCESS outcome=verified correlation='.$grantCorrelation,$ip)!==1){
              throw new RuntimeException('MAINTENANCE_LOGIN_AUDIT_FAILED');
            }
            $_SESSION['oneid_maintenance_admin_verified_until']=time()+300;
          }elseif($maintenanceDeveloper){
            if((string)($userInfo['u_type']??'')!=='0'){
              throw new RuntimeException('MAINTENANCE_DEVELOPER_FINALIZATION_INVALID');
            }
            $decision=$verifyMaintenanceDeveloper();
            $_SESSION['oneid_maintenance_developer_grant_id']=(int)$decision['grant_id'];
            $_SESSION['oneid_maintenance_developer_grant_version']=(int)$decision['configuration_version'];
            if($operation->syslog_record(70,'user='.(string)$final['user_id'].' action=maintenance_developer_login outcome=verified grant_id='.(int)$decision['grant_id'].' correlation='.(string)$final['correlation_id'],$ip)!==1){
              throw new RuntimeException('MAINTENANCE_DEVELOPER_LOGIN_AUDIT_FAILED');
            }
          }
        }catch(\Throwable $finalizationFailure){
          $operation->update_specific_token_status((string)$final['user_id'],(string)$handle['token'],0);
          if($maintenanceAdmin){$operation->admin_step_up_revoke_all_active_access_grants((string)$final['user_id']);}
          oneid_clear_local_authenticated_session();
          throw $finalizationFailure;
        }
        unset(
          $_SESSION['user_mfa_pending_user'],$_SESSION['user_mfa_pending_transaction'],
          $_SESSION['user_mfa_pending_developer_maintenance'],
          $_SESSION['user_mfa_pending_developer_grant_id'],
          $_SESSION['user_mfa_pending_developer_grant_version']
        );
        $site=(string)($_SESSION['user_mfa_pending_site_id']??'');unset($_SESSION['user_mfa_pending_site_id']);
        if($maintenanceDeveloper){$site='';}
        $redirect=$maintenanceAdmin?APP_URL.'/admin/dashboard':APP_URL.'/page/dashboard';
        if($site!==''){
          $_POST['site_id']=$site;$allowed=check_specific_sp_allowed($operation,$site);
          if(($allowed['status']??0)==1){$redirect=(string)$allowed['domain'].'?new_sso_cre='.(string)$handle['token'];}
        }
        return['status'=>1,'login_status'=>1,'code'=>'USER_MFA_LOGIN_COMPLETE','redirect_uri'=>$redirect];
      };
      if($oneidGuardedAction==='user_mfa_totp_verify_login'){
        $maintenanceAdmin=(bool)($_SESSION['user_mfa_pending_admin_maintenance']??false);
        $keyring=\OneId\App\Auth\TotpKeyring::fromFile((string)oneid_config('ONEID_TOTP_KEYRING_PATH',''));
        if($maintenanceAdmin){
          // Maintenance access is an administrator security boundary. Verify
          // the Admin Authenticator factor, not the separate User MFA factor.
          $adminTotp=new \OneId\App\Auth\AdminStepUpTotpService(
            $operation,new \OneId\App\Auth\TotpSecretCipher($keyring)
          );
          $adminTotp->verify($pendingUser,'ADMIN_ACCESS',(string)($_POST['code']??''),$session,$ua,$ip);
        }else{
          $sessions=new \OneId\App\Auth\UserMfa\LegacyUserMfaSessionRevoker($operation);
          $totpPersistence=new \OneId\App\Auth\UserMfa\PdoUserMfaTotpPersistence($pdo,$audit,$sessions);
          $primitive=new \OneId\App\Auth\UserMfa\UserMfaTotpPrimitive(new \OneId\App\Auth\TotpSecretCipher($keyring));
          $totpService=new \OneId\App\Auth\UserMfa\UserMfaTotpService($totpPersistence,$primitive,'OneID@UPNM');
          $totpService->verify($pendingUser,(string)($_POST['code']??''));
        }
        (new \OneId\App\Auth\UserMfa\UserMfaPendingLoginCoordinator(
          new \OneId\App\Auth\UserMfa\PdoUserMfaPendingLoginPersistence($pdo,$audit)
        ))->markVerified($pendingTransaction,'TOTP',$session,$ua,$ip);
        if((bool)($_SESSION['user_mfa_pending_admin_maintenance']??false)){$_SESSION['user_mfa_pending_maintenance_factor']='TOTP';}
        $results=$finalizeUserMfaLogin();
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($results);return;
      }
      $emailService=new \OneId\App\Auth\UserMfa\UserMfaEmailOtpService(
        new \OneId\App\Auth\UserMfa\PdoUserMfaEmailOtpPersistence($pdo,$audit),
        new \OneId\App\Auth\UserMfa\UserMfaPhpMailerSender()
      );
      if($oneidGuardedAction==='user_mfa_email_request'||$oneidGuardedAction==='user_mfa_email_resend'){
        $results=$emailService->request($pendingTransaction,$pendingUser,$session,$ua,$ip,(string)($_SESSION['oneid_locale']??'ms'));
      }else{
        $verified=$emailService->verify($pendingTransaction,(string)($_POST['challenge_id']??''),(string)($_POST['code']??''),$session,$ua,$ip);
        if((bool)($_SESSION['user_mfa_pending_admin_maintenance']??false)){$_SESSION['user_mfa_pending_maintenance_factor']='EMAIL_OTP';}
        $results=$finalizeUserMfaLogin();
      }
      header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($results);return;
    }
    if($oneidGuardedAction==='user_mfa_cancel_login'){
      $pendingUser=(string)($_SESSION['user_mfa_pending_user']??'');
      $pendingTransaction=(string)($_SESSION['user_mfa_pending_transaction']??'');
      if($pendingUser===''||$pendingTransaction===''||!hash_equals($pendingTransaction,(string)($_POST['transaction_id']??''))){
        throw new RuntimeException('USER_MFA_PENDING_SESSION_INVALID');
      }
      $audit=new \OneId\App\Auth\UserMfa\LegacyUserMfaAuditWriter($operation);
      (new \OneId\App\Auth\UserMfa\UserMfaPendingLoginCoordinator(
        new \OneId\App\Auth\UserMfa\PdoUserMfaPendingLoginPersistence($pdo,$audit)
      ))->cancel($pendingTransaction,session_id(),(string)($_SERVER['HTTP_USER_AGENT']??''),(string)getUserIP());
      unset(
        $_SESSION['user_mfa_pending_user'],
        $_SESSION['user_mfa_pending_transaction'],
        $_SESSION['user_mfa_pending_site_id'],
        $_SESSION['user_mfa_pending_admin_maintenance'],
        $_SESSION['user_mfa_pending_maintenance_factor'],
        $_SESSION['user_mfa_pending_developer_maintenance'],
        $_SESSION['user_mfa_pending_developer_grant_id'],
        $_SESSION['user_mfa_pending_developer_grant_version']
      );
      session_regenerate_id(true);
      header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
      echo json_encode(['status'=>1,'code'=>'USER_MFA_LOGIN_CANCELLED','redirect_uri'=>APP_URL.'/']);return;
    }
    if(in_array($oneidGuardedAction,['user_mfa_totp_recovery_email_request','user_mfa_totp_recovery_email_verify'],true)){
      if(!oneid_is_authenticated()){throw new RuntimeException('USER_MFA_AUTHENTICATION_REQUIRED');}
      $recovery=new \OneId\App\Auth\UserMfa\UserMfaTotpEmailRecoveryService(
        $pdo,
        new \OneId\App\Auth\UserMfa\UserMfaRecoveryEmailSender()
      );
      $user=(string)$_SESSION['login_user'];$session=session_id();
      $ua=(string)($_SERVER['HTTP_USER_AGENT']??'');$ip=(string)getUserIP();
      $locale=(string)($_SESSION['oneid_locale']??'ms');
      if($oneidGuardedAction==='user_mfa_totp_recovery_email_request'){
        $results=$recovery->request(
          $user,(string)($_POST['current_password']??''),$session,$ua,$ip,$locale
        );
      }else{
        $results=$recovery->verifyAndRevoke(
          $user,(string)($_POST['challenge_id']??''),(string)($_POST['code']??''),
          $session,$ua,$ip,$locale
        );
        oneid_clear_local_authenticated_session();
        $results['redirect_uri']=APP_URL.'/';
      }
      header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($results);return;
    }
    if(in_array($oneidGuardedAction,['user_mfa_totp_enroll','user_mfa_totp_confirm','user_mfa_totp_preference','user_mfa_totp_revoke'],true)){
      if(!oneid_is_authenticated()){throw new RuntimeException('USER_MFA_AUTHENTICATION_REQUIRED');}
      $audit=new \OneId\App\Auth\UserMfa\LegacyUserMfaAuditWriter($operation);
      $sessions=new \OneId\App\Auth\UserMfa\LegacyUserMfaSessionRevoker($operation);
      $persistence=new \OneId\App\Auth\UserMfa\PdoUserMfaTotpPersistence($pdo,$audit,$sessions);
      $keyring=\OneId\App\Auth\TotpKeyring::fromFile((string)oneid_config('ONEID_TOTP_KEYRING_PATH',''));
      $primitive=new \OneId\App\Auth\UserMfa\UserMfaTotpPrimitive(new \OneId\App\Auth\TotpSecretCipher($keyring));
      $service=new \OneId\App\Auth\UserMfa\UserMfaTotpService($persistence,$primitive,'OneID@UPNM');
      $user=(string)$_SESSION['login_user'];$session=session_id();$ua=(string)($_SERVER['HTTP_USER_AGENT']??'');
      if($oneidGuardedAction==='user_mfa_totp_enroll'){
        $account=$operation->get_specific_user_info($user);
        $results=$service->beginEnrollment($user,oneid_totp_account_label('USER',is_array($account)?$account:null),$session,$ua,(string)($_POST['device_label']??'Microsoft Authenticator'));
      }elseif($oneidGuardedAction==='user_mfa_totp_confirm'){
        $service->confirmEnrollment($user,(string)($_POST['factor_id']??''),(string)($_POST['code']??''),$session,$ua);
        $results=['status'=>1,'code'=>'USER_MFA_TOTP_CONFIRMED'];
      }elseif($oneidGuardedAction==='user_mfa_totp_preference'){
        $service->setPreference($user,(string)($_POST['factor']??''));
        $results=['status'=>1,'code'=>'USER_MFA_PREFERENCE_UPDATED'];
      }else{
        $service->verify($user,(string)($_POST['code']??''));
        $service->selfRevoke($user,true,(string)($_POST['reason']??'SELF_SERVICE'));
        oneid_clear_local_authenticated_session();
        $results=['status'=>1,'code'=>'USER_MFA_TOTP_REVOKED','reauthentication_required'=>true,'redirect_uri'=>APP_URL.'/'];
      }
      header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($results);return;
    }
    throw new RuntimeException('USER_MFA_INTEGRATION_NOT_READY');
  }catch(\Throwable $exception){
    $boundary=new \OneId\App\Auth\UserMfa\UserMfaHttpBoundary();
    $results=$boundary->safeError($exception);
    if($results['code']==='USER_MFA_REQUEST_REJECTED'){
      $results['code']=match($exception->getMessage()){
        'USER_MFA_NOT_ACTIVE'=>'USER_MFA_NOT_ACTIVE',
        'USER_MFA_SCHEMA_UNAVAILABLE'=>'USER_MFA_SCHEMA_UNAVAILABLE',
        'USER_MFA_ACTIVATION_NOT_AUTHORIZED'=>'USER_MFA_ACTIVATION_NOT_AUTHORIZED',
        default=>'USER_MFA_REQUEST_REJECTED',
      };
    }
    http_response_code(match($results['code']){
      'USER_MFA_RESEND_COOLDOWN','USER_MFA_RATE_LIMITED'=>429,
      'USER_MFA_PENDING_EXPIRED','USER_MFA_CHALLENGE_EXPIRED'=>410,
      'USER_MFA_VERIFICATION_FAILED','USER_MFA_CHALLENGE_INVALID','USER_MFA_CHALLENGE_REPLAYED','USER_MFA_TOTP_REPLAYED','USER_MFA_TOTP_VERIFY_FAILED'=>422,
      'USER_MFA_RECOVERY_PASSWORD_INVALID','USER_MFA_RECOVERY_CHALLENGE_INVALID','USER_MFA_RECOVERY_FACTOR_UNAVAILABLE'=>422,
      'USER_MFA_NOT_ACTIVE'=>409,
      default=>503,
    });
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($results);return;
  }
}

if(str_starts_with($oneidGuardedAction,'admin_step_up_')||str_starts_with($oneidGuardedAction,'admin_totp_')||in_array($oneidGuardedAction,['admin_mfa_set_preference','admin_2fa_update_lifetime','admin_2fa_bootstrap_enable'],true)){
  $admin=(string)$_SESSION['login_user'];$session=session_id();$ua=(string)($_SERVER['HTTP_USER_AGENT']??'');$ip=(string)($_SERVER['REMOTE_ADDR']??'');
  try{
    $preference=new \OneId\App\Auth\AdminMfaPreferenceService($operation);
    if($oneidGuardedAction==='admin_step_up_status'){$purpose=strtoupper(trim((string)($_POST['purpose']??'ADMIN_ACCESS')));$results=$preference->status($admin);$decision=oneid_admin_step_up_decision($operation,$purpose);$results['purpose']=$purpose;$results['grant_valid']=$decision['allowed']&&($decision['reason']??'')==='STEP_UP_GRANTED';$results['grant_remaining_seconds']=(int)($decision['remaining_seconds']??0);$base=oneid_current_session_deadline_state($operation);$results+=$base;$results['effective_remaining_seconds']=min((int)$base['effective_remaining_seconds'],(int)$results['grant_remaining_seconds']);}
    elseif($oneidGuardedAction==='admin_step_up_renew'){$results=(new \OneId\App\Auth\AdminStepUpSessionService($operation))->renew($admin,$session,$ua,$ip);oneid_refresh_session_activity();oneid_refresh_configured_sso_cookie($operation);$base=oneid_current_session_deadline_state($operation);$results+=$base;$results['effective_remaining_seconds']=min((int)$base['effective_remaining_seconds'],(int)$results['grant_remaining_seconds']);}
    elseif($oneidGuardedAction==='admin_step_up_request_email'){$results=(new \OneId\App\Auth\AdminStepUpEmailOtpService($operation,new \OneId\App\Auth\AdminStepUpPhpMailerSender()))->request($admin,(string)($_POST['purpose']??''),$session,$ua,$ip);}
    elseif($oneidGuardedAction==='admin_step_up_verify_email'){$results=(new \OneId\App\Auth\AdminStepUpEmailOtpService($operation,new \OneId\App\Auth\AdminStepUpPhpMailerSender()))->verify($admin,(string)($_POST['purpose']??''),(string)($_POST['challenge_id']??''),(string)($_POST['code']??''),$session,$ua,$ip);$results+=oneid_complete_step_up_rotation($operation,$results['purpose'],$results['correlation_id']);}
    elseif($oneidGuardedAction==='admin_step_up_verify_totp'){$path=(string)oneid_config('ONEID_TOTP_KEYRING_PATH','');$cipher=new \OneId\App\Auth\TotpSecretCipher(\OneId\App\Auth\TotpKeyring::fromFile($path));$results=(new \OneId\App\Auth\AdminStepUpTotpService($operation,$cipher))->verify($admin,(string)($_POST['purpose']??''),(string)($_POST['code']??''),$session,$ua,$ip);$results+=oneid_complete_step_up_rotation($operation,$results['purpose'],$results['correlation_id']);}
    elseif($oneidGuardedAction==='admin_totp_enroll'){$path=(string)oneid_config('ONEID_TOTP_KEYRING_PATH','');$cipher=new \OneId\App\Auth\TotpSecretCipher(\OneId\App\Auth\TotpKeyring::fromFile($path));$issuer=(string)oneid_config('ONEID_TOTP_ISSUER');$account=$operation->get_specific_user_info($admin);$results=(new \OneId\App\Auth\AdminTotpFactorService($operation,$cipher,null,$issuer))->enroll($admin,(string)($_POST['current_password']??''),$session,$ua,$ip,(string)($_POST['device_label']??'Microsoft Authenticator'));$results['provisioning_uri']=\OneId\App\Auth\Totp::provisioningUri($issuer,oneid_totp_account_label('ADMIN',is_array($account)?$account:null),(string)$results['secret']);}
    elseif($oneidGuardedAction==='admin_totp_confirm'){$path=(string)oneid_config('ONEID_TOTP_KEYRING_PATH','');$cipher=new \OneId\App\Auth\TotpSecretCipher(\OneId\App\Auth\TotpKeyring::fromFile($path));$results=(new \OneId\App\Auth\AdminTotpFactorService($operation,$cipher))->confirm($admin,(int)($_POST['factor_id']??0),(string)($_POST['code']??''),$session,$ua,$ip);}
    elseif($oneidGuardedAction==='admin_totp_revoke'){$results=(new \OneId\App\Auth\AdminTotpFactorService($operation))->revoke($admin,(int)($_POST['factor_id']??0),$_POST['current_password']??null,$session,$ua,$ip,(string)($_POST['reason']??''));}
    elseif($oneidGuardedAction==='admin_mfa_set_preference'){$results=$preference->select($admin,(string)($_POST['factor']??''),$_POST['current_password']??null,$session,$ua,$ip);}
    elseif($oneidGuardedAction==='admin_2fa_update_lifetime'){$results=(new \OneId\App\Auth\AdminStepUpPolicyService($operation))->update($admin,$_POST['lifetime_minutes']??null,$_POST['configuration_version']??null,(string)($_POST['change_reason']??''),$ip);}
    else{$results=(new \OneId\App\Auth\Admin2faBootstrapService($operation))->enable($admin,(string)($_POST['current_password']??''),(int)($_POST['configuration_version']??0),(string)($_POST['change_reason']??''),(string)($_POST['typed_confirmation']??''),(string)($_POST['change_id']??''),$ip);}
  }catch(\OneId\App\Auth\AdminStepUpException $e){http_response_code(422);$results=['status'=>0,'code'=>$e->reason,'error'=>'Admin 2FA request rejected.','correlation_id'=>$e->correlationId];}
  catch(\RuntimeException $e){$cid=bin2hex(random_bytes(8));$known=['F7_TOTP_KEYRING_UNAVAILABLE','F7_TOTP_KEYRING_PERMISSIONS_INVALID','F7_TOTP_KEYRING_FORMAT_INVALID','F7_TOTP_KEYRING_KEY_INVALID','F7_TOTP_KEYRING_ACTIVE_KEY_MISSING','F7_TOTP_SODIUM_UNAVAILABLE'];$code=in_array($e->getMessage(),$known,true)?$e->getMessage():'ADMIN_2FA_SERVICE_UNAVAILABLE';error_log(sprintf('OneID Admin 2FA runtime failure code=%s correlation=%s',$code,$cid));http_response_code(503);$results=['status'=>0,'code'=>$code,'error'=>'Admin 2FA service unavailable.','correlation_id'=>$cid];}
  catch(\Throwable $e){$cid=bin2hex(random_bytes(8));error_log(sprintf('OneID Admin 2FA unexpected failure class=%s correlation=%s',get_class($e),$cid));http_response_code(500);$results=['status'=>0,'code'=>'ADMIN_2FA_INTERNAL_ERROR','error'=>'Admin 2FA request failed.','correlation_id'=>$cid];}
  header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($results);return;
}

// ML6 compatibility layer: enrich scoped JSON responses with a stable
// translation key and localized presentation without removing or rewriting
// the legacy msg/message fields. External Sync and Admin Step-Up codes are
// explicitly excluded by ApiResponseLocalizer.
ob_start(static function (string $buffer): string {
  $trimmed=trim($buffer);
  if($trimmed===''||$trimmed[0]!=='{'){
    return $buffer;
  }
  $decoded=json_decode($trimmed,true);
  if(!is_array($decoded)||array_is_list($decoded)||!isset($decoded['code'])){
    return $buffer;
  }
  $localized=\OneId\App\Locale\ApiResponseLocalizer::enrich($decoded,oneid_current_locale());
  try{
    return json_encode($localized,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  }catch(\JsonException){
    return $buffer;
  }
});

if(str_starts_with($oneidGuardedAction,'admin_login_banner_')){
  try{
    $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $persistence=new \OneId\App\LoginBanner\PdoLoginBannerPersistence($pdo);
    $pipeline=new \OneId\App\LoginBanner\LoginBannerImagePipeline();
    $service=new \OneId\App\LoginBanner\LoginBannerService($persistence,$pipeline);
    $endpoint=new \OneId\App\LoginBanner\LoginBannerAdminEndpoint(
      $persistence,$service,
      strtolower(trim((string)oneid_config('ONEID_ENVIRONMENT',''))),
      dirname(__DIR__).'/storage/runtime/login-banner-staging',
      oneid_public_path('login_banners')
    );
    $results=$endpoint->handle(
      $oneidGuardedAction,$_POST,$_FILES,
      $operation->audit_identifier((string)$_SESSION['login_user']),(string)getUserIP()
    );
    $status=(int)($results['_http_status']??500);unset($results['_http_status']);
    if((int)($results['status']??0)===1&&$oneidGuardedAction!=='admin_login_banner_list'){
      $cid=(string)($results['correlation_id']??'');
      if(preg_match('/^[a-f0-9]{16,32}$/D',$cid)!==1)$cid=bin2hex(random_bytes(8));
      $results['notification_queued']=oneid_queue_admin_activity_notification(
        $operation,'LOGIN_BANNER_CHANGED',(string)($_SESSION['login_user']??''),$cid,
        'banner|'.$oneidGuardedAction.'|'.$cid,
        ['Action'=>$oneidGuardedAction,'Banner ID'=>(string)($results['banner_id']??'Multiple'),'Environment'=>(string)oneid_config('ONEID_ENVIRONMENT',''),'Correlation ID'=>$cid]
      );
    }
  }catch(\Throwable $exception){
    $status=503;$correlation=bin2hex(random_bytes(8));
    error_log('LB4 boundary unavailable correlation='.$correlation.' exception='.get_class($exception));
    $results=['status'=>0,'code'=>'LB4_OPERATION_FAILED','correlation_id'=>$correlation];
  }
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
  echo json_encode($results,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);return;
}

$sys_config = $operation->get_system_config();
$token_timeout = $sys_config['token_timeout'];//24 means 1 day
$sys_config_multisession = $sys_config['multi_session']; //Multi Session
$passwordResetEmailEnabled = $sys_config['password_reset_email_enabled'];

if(isset($_POST['admin_get_default_locale'])||isset($_POST['admin_update_default_locale'])){
  try{
    $service=new \OneId\App\Admin\SystemLocaleConfigurationService($operation);
    $results=isset($_POST['admin_update_default_locale'])
      ?$service->update($_POST['default_locale']??null,$_POST['configuration_version']??null,(string)($_POST['change_reason']??''),(string)$_SESSION['login_user'],(string)getUserIP())
      :$service->status();
    if(($results['code']??'')==='ML5_DEFAULT_LOCALE_UPDATED'){
      $cid=(string)$results['correlation_id'];
      $results['notification_queued']=oneid_queue_admin_activity_notification($operation,'SYSTEM_LOCALE_CHANGED',(string)$_SESSION['login_user'],$cid,'locale|'.(string)$results['configuration_version'],['Default locale'=>(string)$results['default_locale'],'Configuration version'=>(string)$results['configuration_version'],'Correlation ID'=>$cid]);
    }
  }catch(\RuntimeException $exception){
    $known=['ML5_DEFAULT_LOCALE_SCHEMA_UNAVAILABLE','ML5_DEFAULT_LOCALE_INVALID','ML5_DEFAULT_LOCALE_APPROVAL_INVALID','ML5_DEFAULT_LOCALE_STALE'];
    $code=in_array($exception->getMessage(),$known,true)?$exception->getMessage():'ML5_DEFAULT_LOCALE_FAILED';
    http_response_code($code==='ML5_DEFAULT_LOCALE_SCHEMA_UNAVAILABLE'?503:422);
    $results=['status'=>0,'code'=>$code,'translation_key'=>'admin.configuration.locale_failed','msg'=>oneid_translate('admin.configuration.locale_failed'),'correlation_id'=>bin2hex(random_bytes(8))];
  }catch(\Throwable $exception){
    $results=['status'=>0,'code'=>'ML5_DEFAULT_LOCALE_FAILED','translation_key'=>'admin.configuration.locale_failed','msg'=>oneid_translate('admin.configuration.locale_failed'),'correlation_id'=>bin2hex(random_bytes(8))];
    http_response_code(500);
  }
  header('Content-Type: application/json; charset=utf-8');echo json_encode($results);return;
}
$tokenLifetimePolicy = new \OneId\App\Auth\SsoTokenLifetimePolicy();
// echo $userAgent;
// echo json_encode($dd);s
// return;
function generate_random($length){
    $characters = 'abcdefopqrstuvwxyz01234ghijklmn56789';
    $string = '';
 $max = strlen($characters) - 1;
 for ($i = 0; $i < $length; $i++) {
      $string .= $characters[mt_rand(0, $max)];
 }
 return $string;
}

function generate_token(){
  return oneid_generate_sso_token();
}



function generate_random_char($length){
    $characters = 'abcdefopqrstuvwxyz01234ghijklmn56789';
    $string = '';
 $max = strlen($characters) - 1;
 for ($i = 0; $i < $length; $i++) {
      $string .= $characters[mt_rand(0, $max)];
 }
 return $string;
}

function sentence_case($string) { 
    $sentences = preg_split('/([.?!]+)/', $string, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE); 
    $new_string = ''; 
    foreach ($sentences as $key => $sentence) { 
        $new_string .= ($key & 1) == 0? 
            ucfirst(strtolower(trim($sentence))) : 
            $sentence.' '; 
    } 
    return trim($new_string); 
} 

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // If multiple IPs, take the first one
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function oneid_metadata_repository(): \OneId\App\Metadata\BilingualMetadataRepository {
  static $repository;
  if(!$repository instanceof \OneId\App\Metadata\BilingualMetadataRepository){
    $repository=new \OneId\App\Metadata\BilingualMetadataRepository(new PDO(
      DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
    ));
  }
  return $repository;
}

function oneid_admin_email_notification_callback(PDO $pdo): Closure {
  return static fn(string $event,string $user,string $correlation,string $seed,array $details=[]): ?int =>
    \OneId\App\Notification\AdminEmailNotificationPdoComposer::queue(
      $pdo,$event,$user,$correlation,$seed,$details
    );
}

function oneid_admin_email_notification_operation_callback(object $operation): Closure {
  return static fn(string $event,string $user,string $correlation,string $seed,array $details=[]): ?int =>
    \OneId\App\Notification\AdminEmailNotificationComposer::queueUserEvent(
      $operation,$event,$user,$correlation,$seed,$details
    );
}

/** @param array<string,string> $details */
function oneid_queue_sync_admin_notification(
  object $operation,
  string $event,
  string $administratorId,
  string $correlationId,
  string $idempotencySeed,
  array $details
): bool {
  if(trim($administratorId)==='')return false;
  try {
    return \OneId\App\Notification\AdminEmailNotificationComposer::queueUserEvent(
      $operation,$event,$administratorId,$correlationId,$idempotencySeed,$details
    )!==null;
  } catch (\Throwable $notificationException) {
    error_log(sprintf(
      '[ONEID_SYNC_NOTIFICATION] correlation=%s event=%s exception=%s code=NOTIFICATION_QUEUE_FAILED',
      $correlationId,$event,get_class($notificationException)
    ));
    return false;
  }
}

/** @param array<string,string> $details */
function oneid_queue_admin_activity_notification(object $operation,string $event,string $admin,string $correlation,string $seed,array $details): bool {
  if(trim($admin)==='')return false;
  try{return \OneId\App\Notification\AdminEmailNotificationComposer::queueUserEvent($operation,$event,$admin,$correlation,$seed,$details)!==null;}
  catch(\Throwable $exception){error_log(sprintf('[ONEID_ADMIN_ACTIVITY_NOTIFICATION] correlation=%s event=%s exception=%s code=NOTIFICATION_QUEUE_FAILED',$correlation,$event,get_class($exception)));return false;}
}

/** @param array<string,mixed> $result @return array<string,mixed> */
function oneid_notify_application_result(object $operation,array $result,string $action,string $admin): array {
  if((int)($result['status']??0)!==1)return $result;
  $cid=(string)($result['correlation_id']??'');
  if(preg_match('/^[a-f0-9]{16,32}$/D',$cid)!==1)$cid=bin2hex(random_bytes(8));
  $appId=(string)($result['app_id']??'');
  $result['notification_queued']=oneid_queue_admin_activity_notification($operation,'APPLICATION_CHANGED',$admin,$cid,'application|'.$action.'|'.$appId.'|'.$cid,['Action'=>$action,'Application ID'=>$appId===''?'Not applicable':$appId,'Result code'=>(string)($result['code']??'SUCCESS'),'Correlation ID'=>$cid]);
  return $result;
}


//------------ ENd of Global Functions

//------------ String sanitize function
function string_sanitize($s) {
    $result = htmlspecialchars(str_replace("'", '', $s));
    //$result = preg_replace("/^'+|'+$/", "", html_entity_decode($s, ENT_QUOTES));
    return $result;
}
//------------ End of String sanitize

  
    //Check admin login
     if(isset( $_POST['auth'])){
        $results = array();
        $submittedUsername = trim((string) ($_POST['username'] ?? ''));
        $maintenanceAdminLogin=(string)($_POST['maintenance_admin_login']??'')==='1';
        $maintenanceDeveloperLogin=(string)($_POST['maintenance_developer_login']??'')==='1';
        $maintenanceDeveloperDecision=null;
        // Rate limiting must use the network peer. Untrusted forwarded headers
        // would otherwise let a client rotate its apparent source address.
        $loginIp=(string)($_SERVER['REMOTE_ADDR']??'');
        if(filter_var($loginIp,FILTER_VALIDATE_IP)===false){$loginIp='0.0.0.0';}
        $credentialFingerprint=hash('sha256',mb_strtolower($submittedUsername,'UTF-8'));
        if($maintenanceAdminLogin||$maintenanceDeveloperLogin){
          $maintenanceConfig=$operation->get_maintenance_config();
          $maintenanceActive=is_array($maintenanceConfig)
            && (bool)(\OneId\App\Maintenance\MaintenancePolicy::evaluate($maintenanceConfig)['active']??false);
          if(!$maintenanceActive){
            http_response_code(409);
            echo json_encode(['login_status'=>0,'code'=>'MAINTENANCE_NOT_ACTIVE','login_response_msg'=>'Maintenance login is not active.']);
            return;
          }
          if($maintenanceDeveloperLogin&&!oneid_maintenance_developer_access_enabled()){
            http_response_code(404);
            echo json_encode(['login_status'=>0,'code'=>'MAINTENANCE_DEVELOPER_FEATURE_DISABLED','login_response_msg'=>'Maintenance login is not available.']);
            return;
          }
          // A previous administrator session must not satisfy a new
          // maintenance entry. Keep this request, but start MFA from a fresh
          // unauthenticated PHP session after CSRF has already been checked.
          if(oneid_is_authenticated()){
            oneid_clear_local_authenticated_session();
          }
        }
        if($submittedUsername === ''){
          echo json_encode([
            'login_status' => 0,
            'code' => 'AUTH_USERNAME_REQUIRED',
            'translation_key' => 'login.required_user',
            'login_response_msg' => oneid_translate('login.required_user')
          ]);
          return;
        }elseif((string) ($_POST['password'] ?? '') === ''){
          echo json_encode([
            'login_status' => 0,
            'code' => 'AUTH_PASSWORD_REQUIRED',
            'translation_key' => 'login.required_password',
            'login_response_msg' => oneid_translate('login.required_password')
          ]);
          return;
        }else{
          $failureState=$operation->count_recent_login_failures($credentialFingerprint,$loginIp,15);
          if((int)($failureState['credential_ip']??0)>=5||(int)($failureState['ip']??0)>=20){
            $correlation=bin2hex(random_bytes(8));
            $operation->syslog_record(3,"action=login outcome=rejected reason=AUTH_RATE_LIMITED credential_fingerprint=$credentialFingerprint correlation=$correlation",$loginIp);
            http_response_code(429);
            echo json_encode(['login_status'=>0,'code'=>'AUTH_RATE_LIMITED','login_response_msg'=>'Too many login attempts. Please wait 15 minutes and try again.','correlation_id'=>$correlation]);
            return;
          }
          //check_uid
        $results = $operation->func_authenticate($_POST['username'], $_POST['password']);
        if ($results == false){
          //check data2
          $results = $operation->func_authenticate2($_POST['username'], $_POST['password']);
          if ($results != false){
          }else{
            //check data3        
            $results = $operation->func_authenticate3($_POST['username'], $_POST['password']);
            if ($results != false){
            }else{
              //check data3        
              if(trim($_POST['username']) != ""){
                $results = $operation->func_authenticate4($_POST['username'], $_POST['password']);
              }
              
            }
          }
        }
        }

        if($results!==false&&$maintenanceAdminLogin&&(string)($results['u_type']??'')!=='1'){
          $operation->syslog_record(3,"action=maintenance_login outcome=rejected reason=MAINTENANCE_ADMIN_REQUIRED credential_fingerprint=$credentialFingerprint",$loginIp);
          echo json_encode(['login_status'=>0,'code'=>'MAINTENANCE_ADMIN_REQUIRED','login_response_msg'=>'Only authorized administrators may sign in during maintenance.']);
          return;
        }
        if($results!==false&&$maintenanceDeveloperLogin){
          try{
            $maintenanceDeveloperService=new \OneId\App\Maintenance\MaintenanceDeveloperAccessService(
              new \OneId\App\Maintenance\PdoMaintenanceDeveloperAccessRepository(
                new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION])
              )
            );
            $maintenanceDeveloperDecision=$maintenanceDeveloperService->revalidate((string)$results['u_id']);
          }catch(\Throwable){$maintenanceDeveloperDecision=['allowed'=>false];}
          if((string)($results['u_type']??'')!=='0'||!($maintenanceDeveloperDecision['allowed']??false)){
            $operation->syslog_record(3,"action=maintenance_developer_login outcome=rejected reason=MAINTENANCE_ACCESS_DENIED credential_fingerprint=$credentialFingerprint",$loginIp);
            echo json_encode(['login_status'=>0,'code'=>'MAINTENANCE_ACCESS_DENIED','login_response_msg'=>'The credentials or maintenance access are invalid.']);
            return;
          }
        }

        // echo var_dump($results);)
        $array = array();
        if ($results != false){
           //Check user available status
           if($results['avail_status'] == 0){            
              $array['login_status'] = 0;
              $array['code'] = 'AUTH_ACCOUNT_SUSPENDED';
              $array['translation_key'] = 'login.account_suspended';
              $array['login_response_msg'] = oneid_translate('login.account_suspended');
              $operation->syslog_record(1,"User:".$_POST['username']." -> AUTH_ACCOUNT_SUSPENDED",getUserIP());
              echo json_encode($array);
              return;
           }

            // U8: decide the second-factor boundary before any SSO token or
            // authenticated PHP session is created.
            $userMfaMode=(string)oneid_config('ONEID_USER_MFA_MODE','OFF');
            $userMfaAuthorized=filter_var(
              oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED','false'),
              FILTER_VALIDATE_BOOLEAN
            );
            if($userMfaMode!=='OFF'&&!$userMfaAuthorized){
              http_response_code(503);
              echo json_encode([
                'login_status'=>0,
                'code'=>'USER_MFA_ACTIVATION_NOT_AUTHORIZED',
                'login_response_msg'=>'User MFA activation is not authorized.'
              ]);
              return;
            }
            try{
              $userMfaPdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
              $userMfaAudit=new \OneId\App\Auth\UserMfa\LegacyUserMfaAuditWriter($operation);
              $userMfaPolicies=new \OneId\App\Auth\UserMfa\PdoUserMfaPolicyReader($userMfaPdo);
              $pendingCoordinator=new \OneId\App\Auth\UserMfa\UserMfaPendingLoginCoordinator(
                new \OneId\App\Auth\UserMfa\PdoUserMfaPendingLoginPersistence($userMfaPdo,$userMfaAudit)
              );
              if($maintenanceAdminLogin){
                $previousPending=(string)($_SESSION['user_mfa_pending_transaction']??'');
                if(preg_match('/\A[a-f0-9]{64}\z/',$previousPending)===1){
                  try{
                    $pendingCoordinator->cancel($previousPending,session_id(),(string)($_SERVER['HTTP_USER_AGENT']??''),(string)getUserIP());
                  }catch(\Throwable $ignoredPendingCancellation){
                    error_log('Previous maintenance MFA transaction cleanup was not applied: '.get_class($ignoredPendingCancellation));
                  }
                }
                unset(
                  $_SESSION['user_mfa_pending_user'],
                  $_SESSION['user_mfa_pending_transaction'],
                  $_SESSION['user_mfa_pending_site_id'],
                  $_SESSION['user_mfa_pending_admin_maintenance'],
                  $_SESSION['user_mfa_pending_maintenance_factor']
                );
                $userMfaPolicies->assertRuntimeParity($userMfaMode);
                $policy=$userMfaPolicies->policy();
                $adminFactor=$operation->admin_step_up_factor_status((string)$results['u_id']);
                $email=trim((string)($adminFactor['email']??''));
                if(!$policy->enforced()||(int)($adminFactor['admin_2fa_enabled']??0)!==1
                  ||(filter_var($email,FILTER_VALIDATE_EMAIL)===false&&(int)($adminFactor['totp_available']??0)!==1)){
                  throw new RuntimeException('MAINTENANCE_MFA_UNAVAILABLE');
                }
                $operation->admin_step_up_revoke_all_active_access_grants((string)$results['u_id']);
                $userMfaResult=$pendingCoordinator->begin((string)$results['u_id'],'PASSWORD',session_id(),(string)($_SERVER['HTTP_USER_AGENT']??''),(string)getUserIP(),$policy,true,true);
              }elseif($maintenanceDeveloperLogin){
                $previousPending=(string)($_SESSION['user_mfa_pending_transaction']??'');
                if(preg_match('/\A[a-f0-9]{64}\z/',$previousPending)===1){
                  try{$pendingCoordinator->cancel($previousPending,session_id(),(string)($_SERVER['HTTP_USER_AGENT']??''),(string)getUserIP());}
                  catch(\Throwable $ignoredPendingCancellation){error_log('Previous developer maintenance MFA transaction cleanup was not applied: '.get_class($ignoredPendingCancellation));}
                }
                unset(
                  $_SESSION['user_mfa_pending_user'],$_SESSION['user_mfa_pending_transaction'],
                  $_SESSION['user_mfa_pending_site_id'],$_SESSION['user_mfa_pending_admin_maintenance'],
                  $_SESSION['user_mfa_pending_maintenance_factor'],
                  $_SESSION['user_mfa_pending_developer_maintenance'],
                  $_SESSION['user_mfa_pending_developer_grant_id'],
                  $_SESSION['user_mfa_pending_developer_grant_version']
                );
                $userMfaPolicies->assertRuntimeParity($userMfaMode);
                $policy=$userMfaPolicies->policy();
                if($policy->mode==='OFF'||!$policy->emailEnabled){
                  throw new RuntimeException('MAINTENANCE_DEVELOPER_MFA_UNAVAILABLE');
                }
                $forcedPolicy=new \OneId\App\Auth\UserMfa\UserLoginMfaPolicy(
                  'ENFORCED',$policy->scope,$policy->emailEnabled,$policy->totpEnabled,
                  $policy->pendingTtlSeconds,$policy->otpTtlSeconds,$policy->maxAttempts,
                  $policy->resendCooldownSeconds,$policy->hourlySendLimit
                );
                $userMfaResult=$pendingCoordinator->begin((string)$results['u_id'],'PASSWORD',session_id(),(string)($_SERVER['HTTP_USER_AGENT']??''),(string)getUserIP(),$forcedPolicy,true,true);
              }else{
                $userMfaDecision=new \OneId\App\Auth\UserMfa\UserMfaPrimaryAuthDecision($userMfaPolicies,$pendingCoordinator);
                $userMfaResult=$userMfaDecision->afterPasswordAccepted((string)$results['u_id'],session_id(),(string)($_SERVER['HTTP_USER_AGENT']??''),(string)getUserIP(),$userMfaMode);
              }
              if(($userMfaResult['code']??'')==='USER_MFA_REQUIRED'){
                $_SESSION['user_mfa_pending_user']=(string)$results['u_id'];
                $_SESSION['user_mfa_pending_transaction']=(string)$userMfaResult['transaction_id'];
                $_SESSION['user_mfa_pending_site_id']=isset($_POST['site_id'])?(string)$_POST['site_id']:'';
                $_SESSION['user_mfa_pending_admin_maintenance']=(string)($_POST['maintenance_admin_login']??'')==='1';
                $_SESSION['user_mfa_pending_developer_maintenance']=$maintenanceDeveloperLogin;
                if($maintenanceDeveloperLogin){
                  $_SESSION['user_mfa_pending_developer_grant_id']=(int)$maintenanceDeveloperDecision['grant_id'];
                  $_SESSION['user_mfa_pending_developer_grant_version']=(int)$maintenanceDeveloperDecision['configuration_version'];
                }
                echo json_encode([
                  'login_status'=>2,
                  'code'=>'USER_MFA_REQUIRED',
                  'transaction_id'=>$userMfaResult['transaction_id'],
                  'expires_in_seconds'=>$userMfaResult['expires_in_seconds'],
                  'login_response_msg'=>'Additional verification is required.'
                ]);
                return;
              }
            }catch(\Throwable $userMfaException){
              if($maintenanceDeveloperLogin){
                unset(
                  $_SESSION['user_mfa_pending_developer_maintenance'],
                  $_SESSION['user_mfa_pending_developer_grant_id'],
                  $_SESSION['user_mfa_pending_developer_grant_version']
                );
              }
              $userMfaCorrelation=bin2hex(random_bytes(8));
              error_log(sprintf(
                'User MFA primary-auth boundary failed code=%s correlation=%s',
                $userMfaException->getMessage(),
                $userMfaCorrelation
              ));
              http_response_code(503);
              echo json_encode([
                'login_status'=>0,
                'code'=>'USER_MFA_PRIMARY_AUTH_UNAVAILABLE',
                'correlation_id'=>$userMfaCorrelation,
                'login_response_msg'=>'Login verification is temporarily unavailable.'
              ]);
              return;
            }

            //SSO Token Initialize
            $new_refresh_token = generate_token(); //generate new token
            if($sys_config_multisession == 0){
              $operation->update_whole_token_status($results['u_id'],0,'NEW_LOGIN_REPLACED'); //expired all token for specific user
            }
            //
            //Add new token to DB

            $operation->add_new_token($new_refresh_token, $results['u_id'], $detectedDeviceInfo);

            $user_info = $operation->get_specific_user_info($results['u_id']);
            oneid_set_configured_sso_cookie($operation,$new_refresh_token);

            $array['login_status'] = 1;

            oneid_establish_authenticated_session($results);

            if($maintenanceAdminLogin){
                $array['redirect_uri'] = 'admin/dashboard';
            }elseif($maintenanceDeveloperLogin){
                $array['redirect_uri'] = 'page/dashboard';
            }elseif(isset($_POST['site_id'])){
                $resolvedSite = $operation->resolve_site_api_code((string)$_POST['site_id']);
                $check_result = is_array($resolvedSite)
                  ? check_specific_sp_allowed($operation,$resolvedSite['sp_id'])
                  : ['status'=>0,'domain'=>''];
                // echo json_encode($check_result);
                if($check_result['status']==1){                  
                  $array['redirect_uri'] = $check_result['domain'].'?new_sso_cre='.$new_refresh_token; 
                }else{
                  $array['redirect_uri'] = 'page/dashboard';                  
                }
            }else{
                $array['redirect_uri'] = 'page/dashboard';              
            }
            $array['code'] = 'AUTH_LOGIN_SUCCESS';
            $array['translation_key'] = 'login.success';
            $array['login_response_msg'] = oneid_translate('login.success');
            $operation->syslog_record(2,"User: ".$_POST['username']." Logged in -> ".$array['redirect_uri'],getUserIP());
            echo json_encode($array);
        }else{
            $array['login_status'] = 0;
            $array['code'] = 'AUTH_CREDENTIALS_INVALID';
            $array['translation_key'] = 'login.invalid';
            $array['login_response_msg'] = oneid_translate('login.invalid');
            $operation->syslog_record(3,"action=login outcome=rejected reason=AUTH_CREDENTIALS_INVALID credential_fingerprint=$credentialFingerprint",$loginIp);
            echo json_encode($array);
        }
     }

     //First Time Login Check Password had changed or not
      if(isset( $_POST['check_default_password'])){
        $array = array();
        if((int) ($_SESSION['password_change_required'] ?? 0) === 1){
          $userId=(string)($_SESSION['login_user']??'');
          if(($_SESSION['auth_method']??'')==='mydigitalid'){
            $array['result']=oneid_has_valid_mydigitalid_initial_password_grant($userId)?'initial_setup':'mydigitalid_reauth_required';
            if($array['result']==='mydigitalid_reauth_required'){$array['redirect_uri']=APP_URL.'/';}
          }else{$array['result'] = "change_pwd";}
          echo json_encode($array);
        }else{
          $array['result'] = "no";
          echo json_encode($array);
        }
        
      }


     //Admin

      if(isset( $_POST['admin_search_keyword_user'])){
        $results = $operation->admin_search_keyword_user_func($_POST['search_key']);
        //usort($results, 'php_sort_alpahabet');
        // $results = [];
        echo json_encode($results);
      }

      if(isset( $_POST['admin_get_app_all_group'])){
        $sp_group = $operation->get_sp_group();
        $sp_group = oneid_metadata_repository()->localizeGroups($sp_group,oneid_current_locale());
        //usort($results, 'php_sort_alpahabet');
        // $results = [];
        echo json_encode($sp_group);
      }

      if(isset( $_POST['admin_get_all_service_provider'])){
        $rows = $operation->admin_get_active_app_directory_rows();
        $groups = [];
        foreach ($rows as $row) {
          $groupId = (string) $row['sp_group_id'];
          if (!isset($groups[$groupId])) {
            $groups[$groupId] = [
              'sp_group_id' => $row['sp_group_id'],
              'sp_group_name' => $row['sp_group_name'],
              'sp_group_seq' => $row['sp_group_seq'],
              'tabname' => 'AppGroup_' . $row['sp_group_id'] . '_tab',
              'data' => [],
            ];
          }
          $groups[$groupId]['data'][] = [
            'sp_id' => $row['sp_id'],
            'sp_name' => $row['sp_name'],
            'sp_description' => $row['sp_description'],
            'sp_domain' => $row['sp_domain'],
            'sp_image' => $row['sp_image'],
            'sp_sso_support' => $row['sp_sso_support'],
            'production_ready' => (int) $row['production_ready'],
            'sp_group_id' => $row['sp_group_id'],
          ];
        }
        echo json_encode(oneid_metadata_repository()->localizeGroups(array_values($groups),oneid_current_locale()));
      }

      if(isset($_POST['admin_metadata_translation_preview'])){
        echo json_encode(oneid_metadata_repository()->preview());
      }

      if(isset($_POST['admin_get_metadata_translation'])){
        try{
          $result=oneid_metadata_repository()->read(
            (string)($_POST['entity_type']??''),
            (string)($_POST['entity_id']??''),
            (string)($_POST['locale']??'')
          );
          echo json_encode(['status'=>1,'code'=>'ML7_METADATA_TRANSLATION_LOADED','data'=>$result]);
        }catch(\RuntimeException $exception){
          echo json_encode(['status'=>0,'code'=>$exception->getMessage(),'translation_key'=>'admin.metadata.failed','msg'=>oneid_translate('admin.metadata.failed'),'correlation_id'=>bin2hex(random_bytes(8))]);
        }catch(\Throwable $exception){
          $correlation=bin2hex(random_bytes(8));
          error_log('ML7 metadata read failed correlation='.$correlation.' exception='.get_class($exception));
          echo json_encode(['status'=>0,'code'=>'ML7_METADATA_READ_FAILED','translation_key'=>'admin.metadata.failed','msg'=>oneid_translate('admin.metadata.failed'),'correlation_id'=>$correlation]);
        }
      }

      if(isset($_POST['admin_save_metadata_translation'])){
        try{
          $result=oneid_metadata_repository()->save(
            (string)($_POST['entity_type']??''),
            (string)($_POST['entity_id']??''),
            (string)($_POST['locale']??''),
            ['name'=>(string)($_POST['translated_name']??''),'description'=>(string)($_POST['translated_description']??'')],
            (int)($_POST['translation_version']??0),
            $operation->audit_identifier((string)$_SESSION['login_user']),
            (string)($_POST['change_reason']??'')
          );
          $translationKey=($result['code']??'')==='ML7_METADATA_NO_CHANGES'
            ?'admin.metadata.no_changes'
            :'admin.metadata.saved';
          $result['translation_key']=$translationKey;
          $result['msg']=oneid_translate($translationKey);
          if(($result['code']??'')!=='ML7_METADATA_NO_CHANGES'){
            $cid=(string)($result['correlation_id']??bin2hex(random_bytes(8)));
            $result['notification_queued']=oneid_queue_admin_activity_notification($operation,'METADATA_CHANGED',(string)$_SESSION['login_user'],$cid,'metadata|'.(string)($_POST['entity_type']??'').'|'.(string)($_POST['entity_id']??'').'|'.(string)($_POST['locale']??'').'|'.(string)($result['translation_version']??''),['Entity type'=>(string)($_POST['entity_type']??''),'Entity ID'=>(string)($_POST['entity_id']??''),'Locale'=>(string)($_POST['locale']??''),'Translation version'=>(string)($result['translation_version']??''),'Correlation ID'=>$cid]);
          }
          echo json_encode($result);
        }catch(\RuntimeException $exception){
          $code=$exception->getMessage();
          $translationKey=$code==='ML7_METADATA_APPROVAL_INVALID'
            ?'admin.metadata.reason_required'
            :'admin.metadata.failed';
          echo json_encode(['status'=>0,'code'=>$code,'translation_key'=>$translationKey,'msg'=>oneid_translate($translationKey),'correlation_id'=>bin2hex(random_bytes(8))]);
        }catch(\Throwable $exception){
          $correlation=bin2hex(random_bytes(8));
          error_log('ML7 metadata write failed correlation='.$correlation.' exception='.get_class($exception));
          echo json_encode(['status'=>0,'code'=>'ML7_METADATA_WRITE_FAILED','translation_key'=>'admin.metadata.failed','msg'=>oneid_translate('admin.metadata.failed'),'correlation_id'=>$correlation]);
        }
      }

      if(isset( $_POST['admin_get_sso_settings'])){
        try {
          $service = new \OneId\App\Admin\SsoConfigurationService($operation);
          echo json_encode($service->read());
        } catch (\OneId\App\Admin\SsoConfigurationException $exception) {
          echo json_encode([
            'status'=>0,
            'code'=>$exception->reason,
            'message'=>'Authentication policy could not be loaded.',
            'correlation_id'=>$exception->correlationId,
          ]);
        }
      }

      if(isset($_POST['admin_get_user_mfa_global_policy'])){
        try{
          $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
          $service=new \OneId\App\Admin\UserMfaGlobalPolicyService(
            $pdo,
            strtoupper((string)oneid_config('ONEID_USER_MFA_MODE','OFF')),
            filter_var(oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED',false),FILTER_VALIDATE_BOOLEAN),
            filter_var(oneid_config('ONEID_USER_MFA_TOTP_ENABLED',false),FILTER_VALIDATE_BOOLEAN),
            oneid_admin_email_notification_callback($pdo)
          );
          echo json_encode($service->read());
        }catch(\OneId\App\Admin\SsoConfigurationException $e){
          echo json_encode(['status'=>0,'code'=>$e->reason,'correlation_id'=>$e->correlationId]);
        }
      }

      if(isset($_POST['admin_update_user_mfa_global_policy'])){
        try{
          $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
          $service=new \OneId\App\Admin\UserMfaGlobalPolicyService(
            $pdo,
            strtoupper((string)oneid_config('ONEID_USER_MFA_MODE','OFF')),
            filter_var(oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED',false),FILTER_VALIDATE_BOOLEAN),
            filter_var(oneid_config('ONEID_USER_MFA_TOTP_ENABLED',false),FILTER_VALIDATE_BOOLEAN),
            oneid_admin_email_notification_callback($pdo)
          );
          echo json_encode($service->update(
            $_POST['enabled']??null,
            $_POST['configuration_version']??null,
            (string)($_POST['change_reason']??''),
            (string)($_POST['change_reference']??''),
            (string)($_POST['typed_confirmation']??''),
            (string)$_SESSION['login_user'],
            (string)getUserIP()
          ));
        }catch(\OneId\App\Admin\SsoConfigurationException $e){
          echo json_encode(['status'=>0,'code'=>$e->reason,'correlation_id'=>$e->correlationId]);
        }
      }

      if(isset($_POST['admin_get_user_mfa_category_policy'])){
        try{
          $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
          echo json_encode((new \OneId\App\Admin\UserMfaCategoryPolicyService($pdo))->read());
        }catch(\OneId\App\Admin\SsoConfigurationException $e){
          echo json_encode(['status'=>0,'code'=>$e->reason,'correlation_id'=>$e->correlationId]);
        }catch(\Throwable $e){
          echo json_encode(['status'=>0,'code'=>'USER_MFA_CATEGORY_POLICY_UNAVAILABLE','correlation_id'=>bin2hex(random_bytes(8))]);
        }
      }

      if(isset($_POST['admin_update_user_mfa_category_policy'])){
        try{
          $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
          echo json_encode((new \OneId\App\Admin\UserMfaCategoryPolicyService($pdo,oneid_admin_email_notification_callback($pdo)))->update(
            (string)($_POST['category']??''),
            $_POST['enabled']??null,
            $_POST['configuration_version']??null,
            (string)($_POST['change_reason']??''),
            (string)($_POST['change_reference']??''),
            (string)($_POST['typed_confirmation']??''),
            (string)$_SESSION['login_user'],
            (string)getUserIP()
          ));
        }catch(\OneId\App\Admin\SsoConfigurationException $e){
          echo json_encode(['status'=>0,'code'=>$e->reason,'correlation_id'=>$e->correlationId]);
        }
      }

      if(isset($_POST['admin_search_user_mfa_exemptions'])){
        try{
          $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
          echo json_encode((new \OneId\App\Admin\UserMfaTemporaryExemptionService($pdo))->search(
            (string)($_POST['query']??'')
          ));
        }catch(\OneId\App\Admin\SsoConfigurationException $e){
          echo json_encode(['status'=>0,'code'=>$e->reason,'correlation_id'=>$e->correlationId]);
        }catch(\Throwable $e){
          echo json_encode(['status'=>0,'code'=>'USER_MFA_EXEMPTIONS_UNAVAILABLE','correlation_id'=>bin2hex(random_bytes(8))]);
        }
      }

      if(isset($_POST['admin_search_user_mfa_exemption_candidates'])){
        try{
          $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
          echo json_encode((new \OneId\App\Admin\UserMfaTemporaryExemptionService($pdo))->searchCandidates(
            (string)($_POST['query']??'')
          ));
        }catch(\OneId\App\Admin\SsoConfigurationException $e){
          echo json_encode(['status'=>0,'code'=>$e->reason,'correlation_id'=>$e->correlationId]);
        }catch(\Throwable $e){
          echo json_encode(['status'=>0,'code'=>'USER_MFA_EXEMPTION_CANDIDATE_SEARCH_FAILED','correlation_id'=>bin2hex(random_bytes(8))]);
        }
      }

      if(isset($_POST['admin_create_user_mfa_exemption'])){
        try{
          $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
          echo json_encode((new \OneId\App\Admin\UserMfaTemporaryExemptionService($pdo,oneid_admin_email_notification_callback($pdo)))->create(
            (string)($_POST['user_id']??''),
            $_POST['duration_hours']??null,
            (string)($_POST['change_reason']??''),
            (string)($_POST['change_reference']??''),
            (string)($_POST['compensating_control']??''),
            (string)($_POST['typed_confirmation']??''),
            (string)$_SESSION['login_user'],
            (string)getUserIP()
          ));
        }catch(\OneId\App\Admin\SsoConfigurationException $e){
          echo json_encode(['status'=>0,'code'=>$e->reason,'correlation_id'=>$e->correlationId]);
        }
      }

      if(isset($_POST['admin_revoke_user_mfa_exemption'])){
        try{
          $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
          echo json_encode((new \OneId\App\Admin\UserMfaTemporaryExemptionService($pdo,oneid_admin_email_notification_callback($pdo)))->revoke(
            $_POST['exemption_id']??null,
            (string)($_POST['revoke_reason']??''),
            (string)($_POST['typed_confirmation']??''),
            (string)$_SESSION['login_user'],
            (string)getUserIP()
          ));
        }catch(\OneId\App\Admin\SsoConfigurationException $e){
          echo json_encode(['status'=>0,'code'=>$e->reason,'correlation_id'=>$e->correlationId]);
        }
      }

      if(isset($_POST['admin_get_configuration_history'])){
        try{$service=new \OneId\App\Admin\SsoConfigurationService($operation);echo json_encode($service->history(max(1,(int)($_POST['page']??1)),(int)($_POST['page_size']??10)));}
        catch(\Throwable $exception){echo json_encode(['status'=>0,'code'=>'SC3_HISTORY_LOAD_FAILED','correlation_id'=>bin2hex(random_bytes(8))]);}
      }

      if(isset($_POST['admin_get_password_recovery_settings'])){
        try{$service=new \OneId\App\Admin\PasswordRecoveryConfigurationService($operation);echo json_encode($service->read());}
        catch(\OneId\App\Admin\SsoConfigurationException $e){echo json_encode(['status'=>0,'code'=>$e->reason,'message'=>'Password recovery policy could not be loaded.','correlation_id'=>$e->correlationId]);}
      }

      if(isset($_POST['update_password_recovery'])){
        try{$service=new \OneId\App\Admin\PasswordRecoveryConfigurationService($operation,oneid_admin_email_notification_operation_callback($operation));echo json_encode($service->update($_POST['password_reset_email_enabled']??null,(string)$_SESSION['login_user'],getUserIP()));}
        catch(\OneId\App\Admin\SsoConfigurationException $e){echo json_encode(['status'=>0,'code'=>$e->reason,'message'=>'Password recovery policy was not updated.','correlation_id'=>$e->correlationId]);}
      }

      if(isset($_POST['test_password_recovery_email'])){
        $correlation=bin2hex(random_bytes(8));$recipient=trim((string)($_POST['recipient_email']??''));
        if(filter_var($recipient,FILTER_VALIDATE_EMAIL)===false){echo json_encode(['status'=>0,'code'=>'SC6_TEST_EMAIL_INVALID','correlation_id'=>$correlation]);}
        else{$messageId=null;$sent=OTP_EMAIL_Sender('TEST',$recipient,'OneID Administrator',true,$messageId);$safeMessageId=preg_replace('/[^A-Za-z0-9@._<>-]/','',(string)$messageId);$operation->syslog_record(34,'admin='.(string)$_SESSION['login_user'].' action=password_recovery_test_email result='.($sent?'smtp_accepted':'failed').' message_id='.($safeMessageId!==''?$safeMessageId:'unavailable').' correlation='.$correlation,getUserIP());echo json_encode(['status'=>$sent?1:0,'code'=>$sent?'SC6_TEST_EMAIL_SMTP_ACCEPTED':'SC6_TEST_EMAIL_FAILED','delivery_confirmed'=>false,'message_id'=>$safeMessageId!==''?$safeMessageId:null,'correlation_id'=>$correlation]);}
      }

      if(isset($_POST['preview_configuration_update'])){
        try {
          $service = new \OneId\App\Admin\SsoConfigurationService($operation);
          $preview = $service->preview($_POST);
          $previewId = bin2hex(random_bytes(24));
          $_SESSION['sso_policy_preview'] = [
            'id'=>$previewId,'admin'=>(string)$_SESSION['login_user'],
            'expires_at'=>time()+300,'after'=>$preview['after'],'impact'=>$preview['impact'],
            'configuration_version'=>$preview['configuration_version'],'change_reason'=>$preview['change_reason'],
          ];
          $preview['preview_id']=$previewId;
          echo json_encode($preview);
        } catch (\OneId\App\Admin\SsoConfigurationException $exception) {
          $service->recordRejection($exception->reason,(string)$_SESSION['login_user'],(string)getUserIP(),$exception->correlationId,is_scalar($_POST['change_reason']??null)?trim((string)$_POST['change_reason']):null);
          echo json_encode(['status'=>0,'code'=>$exception->reason,'message'=>'Policy preview failed.','correlation_id'=>$exception->correlationId]);
        }
      }

      

      if(isset( $_POST['action_add_new_webapp_category'])){
        try {
          $service = new \OneId\App\Admin\WebAppCategoryService($operation);
          echo json_encode($service->create(
            (string) ($_POST['add_new_webapp_category_name'] ?? ''),
            (string) $_SESSION['login_user'],
            getUserIP()
          ));
        } catch (\OneId\App\Admin\WebAppManagementException $exception) {
          echo json_encode(['status'=>0,'code'=>$exception->reason,'msg'=>'Application category was not created.','correlation_id'=>$exception->correlationId]);
        }
      }


      if(isset( $_POST['action_remove_app_category'])){
        try {
          $service = new \OneId\App\Admin\WebAppCategoryService($operation);
          echo json_encode($service->remove(
            (string) ($_POST['app_category_id'] ?? ''),
            (string) $_SESSION['login_user'],
            getUserIP()
          ));
        } catch (\OneId\App\Admin\WebAppManagementException $exception) {
          echo json_encode([
            'status' => 0,
            'code' => $exception->reason,
            'msg' => 'Application category was not removed.',
            'correlation_id' => $exception->correlationId,
            'context' => $exception->context,
          ]);
        }
      }

      if(isset( $_POST['action_rename_webapp_category'])){
        try {
          $service = new \OneId\App\Admin\WebAppCategoryService($operation);
          echo json_encode($service->rename(
            (string) ($_POST['app_category_id'] ?? ''),
            (string) ($_POST['app_category_name'] ?? ''),
            (string) $_SESSION['login_user'],
            getUserIP()
          ));
        } catch (\OneId\App\Admin\WebAppManagementException $exception) {
          echo json_encode([
            'status'=>0,
            'code'=>$exception->reason,
            'msg'=>'Application category was not renamed.',
            'correlation_id'=>$exception->correlationId,
          ]);
        }
      }


      if(isset($_POST['admin_get_maintenance_configuration'])||isset($_POST['admin_update_maintenance_configuration'])){
        try{$service=new \OneId\App\Admin\MaintenanceConfigurationService($operation,oneid_admin_email_notification_operation_callback($operation));$result=isset($_POST['admin_get_maintenance_configuration'])?$service->read():$service->update($_POST,(string)$_SESSION['login_user'],(string)getUserIP());echo json_encode($result);}
        catch(\OneId\App\Admin\SsoConfigurationException $exception){echo json_encode(['status'=>0,'code'=>$exception->reason,'message'=>'Maintenance configuration was not completed.','correlation_id'=>$exception->correlationId]);}
      }
      if(in_array($oneidGuardedAction,[
        'admin_search_maintenance_developer_candidates',
        'admin_list_maintenance_developer_access',
        'admin_grant_maintenance_developer_access',
        'admin_revoke_maintenance_developer_access',
      ],true)){
        try{
          $maintenanceDeveloperPdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
          $maintenanceDeveloperEndpoint=new \OneId\App\Maintenance\MaintenanceDeveloperAccessAdminEndpoint(
            new \OneId\App\Maintenance\MaintenanceDeveloperAccessService(
              new \OneId\App\Maintenance\PdoMaintenanceDeveloperAccessRepository($maintenanceDeveloperPdo),
              null,
              oneid_admin_email_notification_callback($maintenanceDeveloperPdo)
            ),
            (string)oneid_config('ONEID_TIMEZONE','Asia/Kuala_Lumpur')
          );
          $result=$maintenanceDeveloperEndpoint->handle(
            $oneidGuardedAction,$_POST,(string)$_SESSION['login_user'],(string)($_SERVER['REMOTE_ADDR']??'')
          );
          header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($result);return;
        }catch(\OneId\App\Maintenance\MaintenanceDeveloperAccessException $exception){
          $code=$exception->reason;
          $status=match($code){
            'MAINTENANCE_ACCESS_SEARCH_INVALID','MAINTENANCE_ACCESS_CONFIRMATION_INVALID','MAINTENANCE_ACCESS_TIME_INVALID','MAINTENANCE_ACCESS_WINDOW_INVALID','MAINTENANCE_ACCESS_REASON_INVALID','MAINTENANCE_ACCESS_REFERENCE_INVALID','MAINTENANCE_ACCESS_USER_INVALID','MAINTENANCE_ACCESS_VERSION_INVALID'=>422,
            'MAINTENANCE_ACCESS_ALREADY_ACTIVE','MAINTENANCE_ACCESS_CONFIGURATION_STALE','MAINTENANCE_ACCESS_NOT_ACTIVE'=>409,
            'MAINTENANCE_ACCESS_ADMIN_STEP_UP_REQUIRED','MAINTENANCE_ACCESS_ADMIN_FORBIDDEN','MAINTENANCE_ACCESS_USER_TYPE_FORBIDDEN','MAINTENANCE_ACCESS_ACCOUNT_INACTIVE'=>403,
            default=>503,
          };
          http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
          echo json_encode(['status'=>0,'code'=>$code,'message'=>'Maintenance developer access request was not completed.','correlation_id'=>$exception->correlationId]);return;
        }catch(\Throwable){
          http_response_code(503);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
          echo json_encode(['status'=>0,'code'=>'MAINTENANCE_ACCESS_UNAVAILABLE','message'=>'Maintenance developer access request was not completed.']);return;
        }
      }

      if(isset( $_POST['action_add_new_app'])){
        try {
          $cipher = new \OneId\App\Admin\SiteApiCodeCipher(\OneId\App\Auth\TotpKeyring::fromFile((string)oneid_config('ONEID_TOTP_KEYRING_PATH','')));
          $service = new \OneId\App\Admin\WebAppService($operation,$cipher);
          $result=$service->create($_POST,$_FILES['app_icon']??null,oneid_public_path('public_img'),(string)$_SESSION['login_user'],getUserIP());
          echo json_encode(oneid_notify_application_result($operation,$result,'CREATE',(string)$_SESSION['login_user']));
        } catch (\OneId\App\Admin\WebAppManagementException $exception) {
          echo json_encode(['status'=>0,'code'=>$exception->reason,'msg'=>'Application was not created.','correlation_id'=>$exception->correlationId]);
        } catch (\Throwable $exception) {
          $correlation=bin2hex(random_bytes(8));
          error_log('WA3 app credential initialization failed correlation_id='.$correlation.' exception='.get_class($exception));
          echo json_encode(['status'=>0,'code'=>'WA3_API_CODE_ENCRYPTION_UNAVAILABLE','msg'=>'Application was not created.','correlation_id'=>$correlation]);
        }
      }

      if(isset( $_POST['action_edit_app_info'])){
        try {
          $service = new \OneId\App\Admin\WebAppService($operation);
          $result=$service->update($_POST,$_FILES['app_icon']??null,oneid_public_path('public_img'),(string)$_SESSION['login_user'],getUserIP());
          echo json_encode(oneid_notify_application_result($operation,$result,'UPDATE',(string)$_SESSION['login_user']));
        } catch (\OneId\App\Admin\WebAppManagementException $exception) {
          echo json_encode(['status'=>0,'code'=>$exception->reason,'msg'=>'Application was not updated.','correlation_id'=>$exception->correlationId]);
        }
      }

      if(isset($_POST['admin_rotate_site_api_code'])){
        try{
          $cipher=new \OneId\App\Admin\SiteApiCodeCipher(\OneId\App\Auth\TotpKeyring::fromFile((string)oneid_config('ONEID_TOTP_KEYRING_PATH','')));
          $service=new \OneId\App\Admin\WebAppService($operation,$cipher);
          $result=$service->rotateSiteApiCode((string)($_POST['app_id']??''),(string)($_POST['change_reason']??''),(string)$_SESSION['login_user'],getUserIP());
          echo json_encode(oneid_notify_application_result($operation,$result,'ROTATE_CREDENTIAL',(string)$_SESSION['login_user']));
        }catch(\OneId\App\Admin\WebAppManagementException $exception){
          echo json_encode(['status'=>0,'code'=>$exception->reason,'msg'=>'Site API Code was not regenerated.','correlation_id'=>$exception->correlationId]);
        }
      }


      if(isset( $_POST['action_change_password'])){
        $userId=(string)$_SESSION['login_user'];$ip=(string)getUserIP();$wasForced=(int)($_SESSION['password_change_required']??0)===1;
        if($operation->count_recent_invalid_current_password_attempts($userId,$ip,15)>=5){$correlation=bin2hex(random_bytes(8));$operation->syslog_record(20,'user='.$userId.' outcome=rejected reason=UC4_RATE_LIMITED correlation='.$correlation,$ip);if(!headers_sent())http_response_code(429);echo json_encode(['status'=>0,'code'=>'UC4_RATE_LIMITED','translation_key'=>'dashboard.password.rate_limited','msg'=>oneid_translate('dashboard.password.rate_limited'),'correlation_id'=>$correlation]);return;}
        try{$service=new \OneId\App\User\UserPasswordChangeService($operation);$result=$service->change($userId,(string)($_POST['change_password_current']??''),(string)($_POST['change_password_new']??''),(string)($_POST['change_password_new_reconfirm']??''),$detectedDeviceInfo,$ip,!$wasForced);$token=$result['replacement_token'];unset($result['replacement_token']);
          if($wasForced){oneid_clear_sso_cookie();$_SESSION=[];session_regenerate_id(true);$result['redirect_uri']=APP_URL.'/';}
          else{session_regenerate_id(true);$_SESSION['password_change_required']=0;unset($_SESSION['oneid_csrf_token']);$result['csrf_token']=oneid_csrf_token();oneid_set_configured_sso_cookie($operation,(string)$token);}
          $result['translation_key']='dashboard.password.success';$result['msg']=oneid_translate('dashboard.password.success');echo json_encode($result);}
        catch(\OneId\App\User\UserPasswordChangeException $e){$operation->syslog_record(20,'user='.$_SESSION['login_user'].' outcome=rejected reason='.$e->reason.' correlation='.$e->correlationId,getUserIP());$passwordErrorKey=match($e->reason){'UC2_CONFIRMATION_MISMATCH'=>'dashboard.password.mismatch','UC5_PASSWORD_QUALITY_REJECTED'=>'dashboard.password.quality_rejected','UC2_USER_NOT_ACTIVE'=>'dashboard.password.user_inactive','UC2_CURRENT_PASSWORD_INVALID'=>'dashboard.password.current_invalid','UC2_PASSWORD_REUSE_CURRENT'=>'dashboard.password.reuse_current','UC5_PASSWORD_HISTORY_REUSED'=>'dashboard.password.history_reused',default=>'dashboard.password.operation_failed'};echo json_encode(['status'=>0,'code'=>$e->reason,'translation_key'=>$passwordErrorKey,'msg'=>oneid_translate($passwordErrorKey),'correlation_id'=>$e->correlationId]);}
      }

      if(isset($_POST['action_set_initial_password'])){
        $userId=(string)($_SESSION['login_user']??'');$ip=(string)getUserIP();
        if((int)($_SESSION['password_change_required']??0)!==1||!oneid_has_valid_mydigitalid_initial_password_grant($userId)){
          $correlation=bin2hex(random_bytes(8));$operation->syslog_record(20,'user='.$userId.' outcome=rejected reason=UC6_INITIAL_SETUP_GRANT_INVALID correlation='.$correlation,$ip);
          if(!headers_sent())http_response_code(403);
          echo json_encode(['status'=>0,'code'=>'UC6_INITIAL_SETUP_GRANT_INVALID','translation_key'=>'dashboard.password.mydigitalid_reauth','msg'=>oneid_translate('dashboard.password.mydigitalid_reauth'),'redirect_uri'=>APP_URL.'/','correlation_id'=>$correlation]);return;
        }
        try{$service=new \OneId\App\User\InitialPasswordSetupService($operation);$result=$service->setup($userId,(string)($_POST['change_password_new']??''),(string)($_POST['change_password_new_reconfirm']??''),$ip);oneid_consume_mydigitalid_initial_password_grant();oneid_clear_sso_cookie();$_SESSION=[];session_regenerate_id(true);$result['redirect_uri']=APP_URL.'/';$result['translation_key']='dashboard.password.initial_success';$result['msg']=oneid_translate('dashboard.password.initial_success');echo json_encode($result);}
        catch(\OneId\App\User\UserPasswordChangeException $e){$operation->syslog_record(20,'user='.$userId.' outcome=rejected reason='.$e->reason.' correlation='.$e->correlationId,$ip);$key=match($e->reason){'UC2_CONFIRMATION_MISMATCH'=>'dashboard.password.mismatch','UC5_PASSWORD_QUALITY_REJECTED'=>'dashboard.password.quality_rejected','UC2_USER_NOT_ACTIVE'=>'dashboard.password.user_inactive','UC2_PASSWORD_REUSE_CURRENT'=>'dashboard.password.reuse_current','UC5_PASSWORD_HISTORY_REUSED'=>'dashboard.password.history_reused','UC6_INITIAL_SETUP_NOT_REQUIRED'=>'dashboard.password.initial_not_required',default=>'dashboard.password.operation_failed'};echo json_encode(['status'=>0,'code'=>$e->reason,'translation_key'=>$key,'msg'=>oneid_translate($key),'correlation_id'=>$e->correlationId]);}
      }


      if(isset( $_POST['admin_search_user_account'])){
        $results = $operation->admin_search_user_account($_POST['user_id']);
        // echo "X";
        if(empty($results)){
          //TRy check from external sources if available, 2//External Source
          $results = [];
        }else{ //SSO source 1
          // //0--- delete      
          // $results['source'] = "2";
          // //0--- delete      
        }
        echo json_encode($results);
      }

      if(isset($_POST['admin_preview_odl_shadow'])){
            try {
                $shadowConfig = \OneId\App\Sync\Odl\OdlShadowPreviewConfig::fromPrivateRuntime();
                if (!$shadowConfig->enabled) {
                    throw new \RuntimeException('ODL_SHADOW_PREVIEW_DISABLED');
                }
                $readPdo = new \PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $shadowService = new \OneId\App\Sync\Odl\OdlShadowPreviewService(
                    $shadowConfig,
                    new \OneId\App\Sync\Odl\StaffSource(),
                    new \OneId\App\Sync\Odl\OdlStudentSource(
                        \OneId\App\Sync\Odl\OdlSourceConfig::fromPrivateRuntime()
                    ),
                    new \OneId\App\Sync\Odl\UgStudentSource(),
                    new \OneId\App\Sync\Odl\OdlShadowPreviewReader($readPdo),
                    new \OneId\App\Sync\SourceAware\SourceAwareStudentPlanner(
                        new \OneId\App\Sync\SourceAware\SourceAwareSafetyPolicy()
                    )
                );
                echo json_encode($shadowService->preview());
            } catch (\Throwable $exception) {
                $correlationId = bin2hex(random_bytes(8));
                $known = [
                    'ODL_SHADOW_PREVIEW_DISABLED',
                    'ODL_SHADOW_PREVIEW_FLAG_INVALID',
                    'ODL_SHADOW_BASELINE_INVALID',
                    'ODL_PDO_MYSQL_TLS_UNAVAILABLE',
                    'ODL_SOURCE_CONNECTION_FAILED',
                    'ODL_TLS_NOT_ACTIVE',
                    'ODL_SOURCE_QUERY_FAILED',
                    'ODBC_EXTENSION_UNAVAILABLE',
                    'EXTERNAL_STUDENT_CONNECTION_FAILED',
                    'EXTERNAL_STUDENT_QUERY_FAILED',
                ];
                $code = in_array($exception->getMessage(), $known, true)
                    ? $exception->getMessage()
                    : 'ODL_SHADOW_PREVIEW_FAILED';
                error_log(sprintf(
                    '[ONEID_ODL_SHADOW] correlation=%s exception=%s code=%s',
                    $correlationId,
                    get_class($exception),
                    $code
                ));
                echo json_encode([
                    'status' => 0,
                    'mode' => 'odl_shadow_preview',
                    'can_apply' => false,
                    'code' => $code,
                    'correlation_id' => $correlationId,
                    'mutation_statements' => 0,
                ]);
            }
      }


      if(isset( $_POST['admin_preview_sync_user'])){
            try {
                $syncScope = \OneId\App\Sync\SyncSourceScope::fromCode(
                    trim((string) ($_POST['sync_source_code'] ?? ''))
                );
                $isOdlOperational = $syncScope->sourceCode
                    === \OneId\App\Sync\Odl\OdlStudentSource::SOURCE_CODE;
                $odlOperationalConfig = $isOdlOperational
                    ? \OneId\App\Sync\Odl\OdlOperationalConfig::fromPrivateRuntime()
                    : null;
                $syncPersistence = new \OneId\App\Sync\Adapters\SourceScopedSyncPersistenceAdapter(
                    new \OneId\App\Sync\Adapters\DatabaseSyncPersistenceAdapter($operation),
                    $syncScope->categoryIds,
                    $syncScope->provenanceEnforced
                        ? fn(): array =>
                            $operation->sync_get_active_user_ids_by_source(
                                $syncScope->sourceCode
                            )
                        : null,
                    $syncScope->provenanceEnforced
                        ? fn(): array =>
                            $operation->sync_get_inactive_user_ids_by_source(
                                $syncScope->sourceCode
                            )
                        : null
                );
                $approvalStore = new \OneId\App\Sync\Adapters\SessionSyncApprovalStore();
                $approvalService = new \OneId\App\Sync\SyncApprovalService(
                    $approvalStore,
                    new \OneId\App\Sync\SyncPlanFingerprinter()
                );
                $previewService = new \OneId\App\Sync\SyncPreviewService(
                    $syncScope->source,
                    $syncPersistence,
                    new \OneId\App\Sync\SyncPlanner(
                        new \OneId\App\Sync\Adapters\LegacySyncPolicy(),
                        $syncScope->preserveExistingEmailOnBlank,
                        $isOdlOperational
                    ),
                    300,
                    5.0,
                    new \OneId\App\Sync\SyncSafetyPolicy(
                        requiredSourceCode: $syncScope->sourceCode
                    ),
                    $isOdlOperational
                        ? fn(array $rows) =>
                            $operation->sync_assert_source_snapshot_isolated(
                                $rows,
                                $syncScope->sourceCode
                            )
                        : null
                );
                $baseline = $syncScope->baselineRows;
                $pilotConfig = \OneId\App\Sync\SyncPilotConfig::fromEnvironment();
                $fullConfig = \OneId\App\Sync\SyncFullConfig::fromEnvironment();
                $operationalConfig = \OneId\App\Sync\SyncOperationalConfig::fromEnvironment();
                $runtimeConfig = \OneId\App\Sync\SyncRuntimeConfig::fromEnvironment();
                if (count(array_filter([
                    $pilotConfig->enabled,
                    $fullConfig->enabled,
                    $operationalConfig->enabled,
                ])) > 1) {
                    throw new \RuntimeException('SYNC_MODE_CONFLICT');
                }
                $subsetSelector = $pilotConfig->enabled
                    ? new \OneId\App\Sync\SyncPlanSubsetSelector($pilotConfig)
                    : null;
                $previewResponse = $previewService->previewForApproval(
                    (string) ($_SESSION['login_user'] ?? ''),
                    $baseline,
                    $approvalService,
                    $subsetSelector
                );
                $previewResponse['source_code'] = $syncScope->sourceCode;
                $previewResponse['pilot_apply_available'] = $pilotConfig->enabled
                    && $runtimeConfig->canApply()
                    && ($previewResponse['approval_ready'] ?? false) === true;
                $previewCounts = is_array($previewResponse['counts'] ?? null)
                    ? $previewResponse['counts']
                    : [];
                $operationalDeactivateAdvisory = false;
                if ($operationalConfig->enabled) {
                    $operationalConfig->assertWithinHardLimits($previewCounts);
                    $operationalDeactivateAdvisory =
                        (int) ($previewCounts['Deactivate'] ?? 0)
                            > $operationalConfig->maxDeactivate;
                }
                $previewResponse['operational_large_batch'] = $operationalConfig->enabled
                    && $operationalConfig->isLargeBatch($previewCounts);
                $previewResponse['operational_hard_blocked'] = false;
                $previewResponse['operational_deactivate_advisory'] =
                    $operationalDeactivateAdvisory;
                $previewResponse['operational_thresholds'] = [
                    'warn_new' => $operationalConfig->warnNew,
                    'warn_update' => $operationalConfig->warnUpdate,
                    'warn_reactivate' => $operationalConfig->warnReactivate,
                    'warn_total' => $operationalConfig->warnTotal,
                    'max_deactivate' => $operationalConfig->maxDeactivate,
                ];
                if ($previewResponse['operational_large_batch']) {
                    $previewResponse['warnings'][] = 'Large batch requires exact counts and plan-hash confirmation.';
                }
                if ($operationalDeactivateAdvisory) {
                    $previewResponse['warnings'][] = 'Deactivate count exceeds the advisory threshold; exact confirmation is required.';
                }
                $previewResponse['full_apply_available'] = $fullConfig->enabled
                    && !$pilotConfig->enabled
                    && $runtimeConfig->canApply()
                    && ($previewResponse['approval_ready'] ?? false) === true
                    && $previewCounts === $fullConfig->expectedCounts
                    && hash_equals(
                        $fullConfig->expectedPlanHash,
                        (string) ($previewResponse['plan_hash'] ?? '')
                    );
                $previewResponse['operational_apply_available'] = $operationalConfig->enabled
                    && !$pilotConfig->enabled
                    && !$fullConfig->enabled
                    && $runtimeConfig->canApply()
                    && (!$isOdlOperational
                        || $odlOperationalConfig->canApply())
                    && ($previewResponse['approval_ready'] ?? false) === true
                    && array_sum($previewCounts) > 0;
                if ($previewResponse['operational_apply_available']
                    && $isOdlOperational
                ) {
                    try {
                        $odlOperationalConfig->assertWithinChangeWindow();
                        $odlOperationalConfig->assertApprovedPlan(
                            (int) ($previewResponse['source_rows'] ?? 0),
                            $previewCounts,
                            (string) ($previewResponse['plan_hash'] ?? '')
                        );
                    } catch (\RuntimeException $exception) {
                        $previewResponse['operational_apply_available'] = false;
                        $previewResponse['blocking_codes'][] = $exception->getMessage();
                        $previewResponse['warnings'][] = $exception->getMessage();
                        $previewResponse['risk_level'] = 'blocked';
                    }
                }
                if ($previewResponse['full_apply_available']) {
                    $previewResponse['full_confirmation'] = $fullConfig->confirmationText();
                }
                if ($previewResponse['operational_apply_available']) {
                    $previewResponse['operational_confirmation'] = $operationalConfig->confirmationText(
                        (string) ($previewResponse['plan_hash'] ?? ''),
                        $previewCounts
                    );
                }
                if (!$previewResponse['pilot_apply_available']
                    && !$previewResponse['full_apply_available']
                    && !$previewResponse['operational_apply_available']
                ) {
                    unset($previewResponse['approval_id']);
                }
                echo json_encode($previewResponse);
            } catch (\Throwable $exception) {
                $correlationId = bin2hex(random_bytes(8));
                $knownPreviewCodes = [
                    'ODBC_EXTENSION_UNAVAILABLE',
                    'EXTERNAL_STAFF_CONNECTION_FAILED',
                    'EXTERNAL_STUDENT_CONNECTION_FAILED',
                    'EXTERNAL_STAFF_QUERY_FAILED',
                    'EXTERNAL_STUDENT_QUERY_FAILED',
                    'EMPTY_EXTERNAL_SNAPSHOT',
                    'EXTERNAL_STAFF_EMPTY',
                    'EXTERNAL_STUDENT_EMPTY',
                    'SYNC_SOURCE_INVALID',
                    'SYNC_SOURCE_BASELINE_INVALID',
                    'ODL_OPERATIONAL_PREVIEW_DISABLED',
                    'ODL_OPERATIONAL_FLAG_INVALID',
                    'ODL_OPERATIONAL_FLAG_COMBINATION_INVALID',
                    'SYNC_CROSS_SOURCE_IDENTITY_COLLISION',
                    'SYNC_SOURCE_MEMBERSHIP_CONFLICT',
                    'ODL_OPERATIONAL_EXPECTED_COUNTS_INVALID',
                    'ODL_OPERATIONAL_AUTHORIZATION_INVALID',
                    'ODL_OPERATIONAL_WINDOW_INVALID',
                    'ODL_OPERATIONAL_EXACT_PLAN_MISMATCH',
                    'ODL_OPERATIONAL_OUTSIDE_CHANGE_WINDOW',
                    'ODL_OPERATIONAL_ON_DEMAND_ENVIRONMENT_INVALID',
                    'ODL_MANUAL_OPERATIONAL_ENVIRONMENT_INVALID',
                ];
                $diagnosticCode = in_array($exception->getMessage(), $knownPreviewCodes, true)
                    ? $exception->getMessage()
                    : 'UNEXPECTED_PREVIEW_ERROR';
                error_log(sprintf(
                    '[ONEID_SYNC_PREVIEW] correlation=%s exception=%s code=%s',
                    $correlationId,
                    get_class($exception),
                    $diagnosticCode
                ));
                echo json_encode([
                    'status' => 0,
                    'mode' => 'preview',
                    'can_apply' => false,
                    'code' => 'PREVIEW_FAILED',
                    'msg' => 'External sync preview could not be generated safely.',
                    'correlation_id' => $correlationId,
                ]);
            }
      }

      if(isset( $_POST['admin_apply_operational_sync'])){
            try {
                $syncSourceCode = trim((string) ($_POST['sync_source_code'] ?? ''));
                \OneId\App\Sync\SyncSourceScope::fromCode($syncSourceCode);
                if ($syncSourceCode
                    === \OneId\App\Sync\Odl\OdlStudentSource::SOURCE_CODE
                ) {
                    $odlOperationalConfig =
                        \OneId\App\Sync\Odl\OdlOperationalConfig::fromPrivateRuntime();
                    $odlOperationalConfig->assertApplyEnabled();
                    $odlOperationalConfig->assertWithinChangeWindow();
                }
                $runtimeConfig = \OneId\App\Sync\SyncRuntimeConfig::fromEnvironment();
                $pilotConfig = \OneId\App\Sync\SyncPilotConfig::fromEnvironment();
                $fullConfig = \OneId\App\Sync\SyncFullConfig::fromEnvironment();
                $operationalConfig = \OneId\App\Sync\SyncOperationalConfig::fromEnvironment();
                if ($pilotConfig->enabled || $fullConfig->enabled) {
                    throw new \RuntimeException('SYNC_MODE_CONFLICT');
                }
                $confirmation = is_string($_POST['operational_sync_confirmation'] ?? null)
                    ? trim($_POST['operational_sync_confirmation'])
                    : '';
                $approvalStore = new \OneId\App\Sync\Adapters\SessionSyncApprovalStore();
                $coordinator = (new \OneId\App\Sync\SyncEngineFactory(
                    $operation,
                    $runtimeConfig
                ))->createOperationalCoordinator(
                    $approvalStore,
                    $operationalConfig,
                    $confirmation,
                    $syncSourceCode
                );
                $triggeredBy = (string) ($_SESSION['login_user'] ?? '');
                $approvalId = is_string($_POST['sync_approval_id'] ?? null)
                    ? trim($_POST['sync_approval_id'])
                    : '';
                $summary = $coordinator->run(
                    $approvalId,
                    $triggeredBy,
                    $triggeredBy
                );
                $auditMarkerRecorded = true;
                try {
                    $operation->syslog_record(
                        22,
                        sprintf(
                            'ADMIN_SYNC_OPERATIONAL_SAFE source=%s header=%d new=%d updated=%d deactivated=%d reactivated=%d',
                            $syncSourceCode,
                            $summary->headerId,
                            $summary->new,
                            $summary->updated,
                            $summary->deactivated,
                            $summary->reactivated
                        ),
                        getUserIP()
                    );
                } catch (\Throwable $auditException) {
                    $auditMarkerRecorded = false;
                    error_log(sprintf(
                        '[ONEID_SYNC_OPERATIONAL_AUDIT] header=%d exception=%s code=AUDIT_MARKER_FAILED',
                        $summary->headerId,
                        get_class($auditException)
                    ));
                }
                $correlationId = bin2hex(random_bytes(8));
                $notificationQueued = oneid_queue_sync_admin_notification(
                    $operation,
                    $auditMarkerRecorded ? 'SYNC_COMPLETED' : 'SYNC_WARNING',
                    $triggeredBy,
                    $correlationId,
                    sprintf('operational|%s|%d', $syncSourceCode, $summary->headerId),
                    [
                        'Sync mode' => 'Operational',
                        'Source' => $syncSourceCode,
                        'Header ID' => (string) $summary->headerId,
                        'New' => (string) $summary->new,
                        'Updated' => (string) $summary->updated,
                        'Deactivated' => (string) $summary->deactivated,
                        'Reactivated' => (string) $summary->reactivated,
                        'Audit marker' => $auditMarkerRecorded ? 'Recorded' : 'Warning: not recorded',
                        'Correlation ID' => $correlationId,
                    ]
                );
                echo json_encode([
                    'status' => 1,
                    'code' => $auditMarkerRecorded
                        ? 'SYNC_OPERATIONAL_APPLY_COMPLETED'
                        : 'SYNC_OPERATIONAL_APPLY_COMPLETED_AUDIT_WARNING',
                    'header_id' => $summary->headerId,
                    'source_code' => $syncSourceCode,
                    'audit_marker_recorded' => $auditMarkerRecorded,
                    'notification_queued' => $notificationQueued,
                    'correlation_id' => $correlationId,
                    'counts' => [
                        'New' => $summary->new,
                        'Update' => $summary->updated,
                        'Deactivate' => $summary->deactivated,
                        'Reactivate' => $summary->reactivated,
                    ],
                ]);
            } catch (\Throwable $exception) {
                $correlationId = bin2hex(random_bytes(8));
                $knownOperationalCodes = [
                    'SYNC_APPLY_DISABLED',
                    'SYNC_APPLY_FLAG_INVALID',
                    'SYNC_ENGINE_INVALID',
                    'SYNC_FLAG_COMBINATION_INVALID',
                    'SYNC_MODE_CONFLICT',
                    'SYNC_OPERATIONAL_FLAG_INVALID',
                    'SYNC_OPERATIONAL_WARNING_THRESHOLD_INVALID',
                    'SYNC_OPERATIONAL_DEACTIVATE_LIMIT_INVALID',
                    'SYNC_OPERATIONAL_DISABLED',
                    'SYNC_OPERATIONAL_PLAN_HASH_INVALID',
                    'SYNC_OPERATIONAL_COUNTS_INVALID',
                    'SYNC_OPERATIONAL_DEACTIVATE_LIMIT_EXCEEDED',
                    'SYNC_OPERATIONAL_CONFIRMATION_INVALID',
                    'SYNC_APPROVAL_INVALID',
                    'SYNC_APPROVAL_NOT_AVAILABLE',
                    'SYNC_APPROVAL_EXPIRED',
                    'SYNC_APPROVAL_ADMIN_MISMATCH',
                    'SYNC_APPROVAL_PLAN_MISMATCH',
                    'SYNC_ALREADY_RUNNING',
                    'SYNC_SAFETY_BLOCKED',
                    'SYNC_RECONCILIATION_MISMATCH',
                    'SYNC_DATABASE_WRITE_FAILED',
                    'SYNC_LOG_SOURCE_SCHEMA_UNAVAILABLE',
                    'SYNC_SOURCE_INVALID',
                    'SYNC_SOURCE_BASELINE_INVALID',
                    'ODL_OPERATIONAL_APPLY_DISABLED',
                    'ODL_OPERATIONAL_FLAG_INVALID',
                    'ODL_OPERATIONAL_FLAG_COMBINATION_INVALID',
                    'ODL_OPERATIONAL_EXPECTED_COUNTS_INVALID',
                    'ODL_OPERATIONAL_AUTHORIZATION_INVALID',
                    'ODL_OPERATIONAL_WINDOW_INVALID',
                    'ODL_OPERATIONAL_EXACT_PLAN_MISMATCH',
                    'ODL_OPERATIONAL_OUTSIDE_CHANGE_WINDOW',
                    'ODL_MANUAL_OPERATIONAL_ENVIRONMENT_INVALID',
                ];
                $diagnosticCode = in_array($exception->getMessage(), $knownOperationalCodes, true)
                    ? $exception->getMessage()
                    : 'UNEXPECTED_SYNC_OPERATIONAL_ERROR';
                error_log(sprintf(
                    '[ONEID_SYNC_OPERATIONAL] correlation=%s exception=%s code=%s',
                    $correlationId,
                    get_class($exception),
                    $diagnosticCode
                ));
                $notificationQueued = oneid_queue_sync_admin_notification(
                    $operation,'SYNC_FAILED',(string) ($_SESSION['login_user'] ?? ''),
                    $correlationId,'operational-failed|'.$correlationId,
                    [
                        'Sync mode' => 'Operational',
                        'Source' => trim((string) ($_POST['sync_source_code'] ?? '')),
                        'Diagnostic code' => $diagnosticCode,
                        'Correlation ID' => $correlationId,
                    ]
                );
                echo json_encode([
                    'status' => 0,
                    'code' => $diagnosticCode,
                    'msg' => 'Operational synchronization was not applied.',
                    'correlation_id' => $correlationId,
                    'notification_queued' => $notificationQueued,
                ]);
            }
      }

      if(isset( $_POST['admin_apply_full_sync'])){
            try {
                $syncSourceCode = trim((string) ($_POST['sync_source_code'] ?? ''));
                \OneId\App\Sync\SyncSourceScope::fromCode($syncSourceCode);
                $runtimeConfig = \OneId\App\Sync\SyncRuntimeConfig::fromEnvironment();
                $pilotConfig = \OneId\App\Sync\SyncPilotConfig::fromEnvironment();
                $fullConfig = \OneId\App\Sync\SyncFullConfig::fromEnvironment();
                if ($pilotConfig->enabled) {
                    throw new \RuntimeException('SYNC_MODE_CONFLICT');
                }
                $confirmation = is_string($_POST['full_sync_confirmation'] ?? null)
                    ? trim($_POST['full_sync_confirmation'])
                    : '';
                if (!hash_equals($fullConfig->confirmationText(), $confirmation)) {
                    throw new \RuntimeException('SYNC_FULL_CONFIRMATION_INVALID');
                }
                $approvalStore = new \OneId\App\Sync\Adapters\SessionSyncApprovalStore();
                $coordinator = (new \OneId\App\Sync\SyncEngineFactory(
                    $operation,
                    $runtimeConfig
                ))->createFullCoordinator(
                    $approvalStore,
                    $fullConfig,
                    $syncSourceCode
                );
                $triggeredBy = (string) ($_SESSION['login_user'] ?? '');
                $approvalId = is_string($_POST['sync_approval_id'] ?? null)
                    ? trim($_POST['sync_approval_id'])
                    : '';
                $summary = $coordinator->run(
                    $approvalId,
                    $triggeredBy,
                    $triggeredBy
                );
                $auditMarkerRecorded = true;
                try {
                    $operation->syslog_record(
                        22,
                        sprintf(
                            'ADMIN_SYNC_FULL_SAFE source=%s header=%d new=%d updated=%d deactivated=%d reactivated=%d',
                            $syncSourceCode,
                            $summary->headerId,
                            $summary->new,
                            $summary->updated,
                            $summary->deactivated,
                            $summary->reactivated
                        ),
                        getUserIP()
                    );
                } catch (\Throwable $auditException) {
                    $auditMarkerRecorded = false;
                    error_log(sprintf(
                        '[ONEID_SYNC_FULL_AUDIT] header=%d exception=%s code=AUDIT_MARKER_FAILED',
                        $summary->headerId,
                        get_class($auditException)
                    ));
                }
                $correlationId = bin2hex(random_bytes(8));
                $notificationQueued = oneid_queue_sync_admin_notification(
                    $operation,
                    $auditMarkerRecorded ? 'SYNC_COMPLETED' : 'SYNC_WARNING',
                    $triggeredBy,
                    $correlationId,
                    sprintf('full|%s|%d', $syncSourceCode, $summary->headerId),
                    [
                        'Sync mode' => 'Full',
                        'Source' => $syncSourceCode,
                        'Header ID' => (string) $summary->headerId,
                        'New' => (string) $summary->new,
                        'Updated' => (string) $summary->updated,
                        'Deactivated' => (string) $summary->deactivated,
                        'Reactivated' => (string) $summary->reactivated,
                        'Audit marker' => $auditMarkerRecorded ? 'Recorded' : 'Warning: not recorded',
                        'Correlation ID' => $correlationId,
                    ]
                );
                echo json_encode([
                    'status' => 1,
                    'code' => $auditMarkerRecorded
                        ? 'SYNC_FULL_APPLY_COMPLETED'
                        : 'SYNC_FULL_APPLY_COMPLETED_AUDIT_WARNING',
                    'header_id' => $summary->headerId,
                    'source_code' => $syncSourceCode,
                    'audit_marker_recorded' => $auditMarkerRecorded,
                    'notification_queued' => $notificationQueued,
                    'correlation_id' => $correlationId,
                    'counts' => [
                        'New' => $summary->new,
                        'Update' => $summary->updated,
                        'Deactivate' => $summary->deactivated,
                        'Reactivate' => $summary->reactivated,
                    ],
                ]);
            } catch (\Throwable $exception) {
                $correlationId = bin2hex(random_bytes(8));
                $knownFullCodes = [
                    'SYNC_APPLY_DISABLED',
                    'SYNC_APPLY_FLAG_INVALID',
                    'SYNC_ENGINE_INVALID',
                    'SYNC_FLAG_COMBINATION_INVALID',
                    'SYNC_MODE_CONFLICT',
                    'SYNC_FULL_FLAG_INVALID',
                    'SYNC_FULL_COUNT_INVALID',
                    'SYNC_FULL_PLAN_HASH_INVALID',
                    'SYNC_FULL_EMPTY_SCOPE',
                    'SYNC_FULL_DISABLED',
                    'SYNC_FULL_COUNT_MISMATCH',
                    'SYNC_FULL_PLAN_MISMATCH',
                    'SYNC_FULL_CONFIRMATION_INVALID',
                    'SYNC_APPROVAL_INVALID',
                    'SYNC_APPROVAL_NOT_AVAILABLE',
                    'SYNC_APPROVAL_EXPIRED',
                    'SYNC_APPROVAL_ADMIN_MISMATCH',
                    'SYNC_APPROVAL_PLAN_MISMATCH',
                    'SYNC_ALREADY_RUNNING',
                    'SYNC_SAFETY_BLOCKED',
                    'SYNC_RECONCILIATION_MISMATCH',
                    'SYNC_DATABASE_WRITE_FAILED',
                    'SYNC_LOG_SOURCE_SCHEMA_UNAVAILABLE',
                    'SYNC_SOURCE_INVALID',
                    'SYNC_SOURCE_BASELINE_INVALID',
                ];
                $diagnosticCode = in_array($exception->getMessage(), $knownFullCodes, true)
                    ? $exception->getMessage()
                    : 'UNEXPECTED_SYNC_FULL_ERROR';
                error_log(sprintf(
                    '[ONEID_SYNC_FULL] correlation=%s exception=%s code=%s',
                    $correlationId,
                    get_class($exception),
                    $diagnosticCode
                ));
                $notificationQueued = oneid_queue_sync_admin_notification(
                    $operation,'SYNC_FAILED',(string) ($_SESSION['login_user'] ?? ''),
                    $correlationId,'full-failed|'.$correlationId,
                    [
                        'Sync mode' => 'Full',
                        'Source' => trim((string) ($_POST['sync_source_code'] ?? '')),
                        'Diagnostic code' => $diagnosticCode,
                        'Correlation ID' => $correlationId,
                    ]
                );
                echo json_encode([
                    'status' => 0,
                    'code' => $diagnosticCode,
                    'msg' => 'Full synchronization was not applied.',
                    'correlation_id' => $correlationId,
                    'notification_queued' => $notificationQueued,
                ]);
            }
      }

      if(isset( $_POST['admin_add_sync_user'])){
            try {
                $syncSourceCode = trim((string) ($_POST['sync_source_code'] ?? ''));
                \OneId\App\Sync\SyncSourceScope::fromCode($syncSourceCode);
                $runtimeConfig = \OneId\App\Sync\SyncRuntimeConfig::fromEnvironment();
                $pilotConfig = \OneId\App\Sync\SyncPilotConfig::fromEnvironment();
                $approvalStore = new \OneId\App\Sync\Adapters\SessionSyncApprovalStore();
                $coordinator = (new \OneId\App\Sync\SyncEngineFactory(
                    $operation,
                    $runtimeConfig
                ))->createPilotCoordinator(
                    $approvalStore,
                    $pilotConfig,
                    $syncSourceCode
                );
                $triggeredBy = (string) ($_SESSION['login_user'] ?? '');
                $approvalId = is_string($_POST['sync_approval_id'] ?? null)
                    ? trim($_POST['sync_approval_id'])
                    : '';
                $summary = $coordinator->run(
                    $approvalId,
                    $triggeredBy,
                    $triggeredBy
                );
                $operation->syslog_record(
                    22,
                    sprintf(
                        'ADMIN_SYNC_SAFE source=%s header=%d new=%d updated=%d deactivated=%d reactivated=%d',
                        $syncSourceCode,
                        $summary->headerId,
                        $summary->new,
                        $summary->updated,
                        $summary->deactivated,
                        $summary->reactivated
                    ),
                    getUserIP()
                );
                $correlationId = bin2hex(random_bytes(8));
                $notificationQueued = oneid_queue_sync_admin_notification(
                    $operation,'SYNC_COMPLETED',$triggeredBy,$correlationId,
                    sprintf('pilot|%s|%d', $syncSourceCode, $summary->headerId),
                    [
                        'Sync mode' => 'Pilot',
                        'Source' => $syncSourceCode,
                        'Header ID' => (string) $summary->headerId,
                        'New' => (string) $summary->new,
                        'Updated' => (string) $summary->updated,
                        'Deactivated' => (string) $summary->deactivated,
                        'Reactivated' => (string) $summary->reactivated,
                        'Correlation ID' => $correlationId,
                    ]
                );
                echo json_encode([
                    'status' => 1,
                    'code' => 'SYNC_APPLY_COMPLETED',
                    'header_id' => $summary->headerId,
                    'source_code' => $syncSourceCode,
                    'notification_queued' => $notificationQueued,
                    'correlation_id' => $correlationId,
                    'counts' => [
                        'New' => $summary->new,
                        'Update' => $summary->updated,
                        'Deactivate' => $summary->deactivated,
                        'Reactivate' => $summary->reactivated,
                    ],
                ]);
            } catch (\Throwable $exception) {
                $correlationId = bin2hex(random_bytes(8));
                $knownApplyCodes = [
                    'SYNC_APPLY_DISABLED',
                    'SYNC_APPLY_FLAG_INVALID',
                    'SYNC_ENGINE_INVALID',
                    'SYNC_FLAG_COMBINATION_INVALID',
                    'SYNC_PILOT_FLAG_INVALID',
                    'SYNC_PILOT_LIMIT_INVALID',
                    'SYNC_PILOT_SCOPE_INVALID',
                    'SYNC_PILOT_DESTRUCTIVE_ACTION_FORBIDDEN',
                    'SYNC_PILOT_DISABLED',
                    'SYNC_PILOT_SUBSET_UNAVAILABLE',
                    'SYNC_DATABASE_WRITE_FAILED',
                    'SYNC_LOG_SOURCE_SCHEMA_UNAVAILABLE',
                    'SYNC_APPROVAL_INVALID',
                    'SYNC_APPROVAL_NOT_AVAILABLE',
                    'SYNC_APPROVAL_EXPIRED',
                    'SYNC_APPROVAL_ADMIN_MISMATCH',
                    'SYNC_APPROVAL_PLAN_MISMATCH',
                    'SYNC_ALREADY_RUNNING',
                    'SYNC_SAFETY_BLOCKED',
                    'SYNC_RECONCILIATION_MISMATCH',
                    'SYNC_SOURCE_INVALID',
                    'SYNC_SOURCE_BASELINE_INVALID',
                ];
                $diagnosticCode = in_array($exception->getMessage(), $knownApplyCodes, true)
                    ? $exception->getMessage()
                    : 'UNEXPECTED_SYNC_APPLY_ERROR';
                if ($exception instanceof \OneId\App\Sync\SyncDatabaseStageException) {
                    error_log(sprintf(
                        '[ONEID_SYNC_APPLY] correlation=%s exception=%s code=%s stage=%s sqlstate=%s driver=%d',
                        $correlationId,
                        get_class($exception),
                        $diagnosticCode,
                        $exception->stage,
                        $exception->sqlState,
                        $exception->driverCode
                    ));
                } else {
                    error_log(sprintf(
                        '[ONEID_SYNC_APPLY] correlation=%s exception=%s code=%s',
                        $correlationId,
                        get_class($exception),
                        $diagnosticCode
                    ));
                }
                $notificationQueued = oneid_queue_sync_admin_notification(
                    $operation,'SYNC_FAILED',(string) ($_SESSION['login_user'] ?? ''),
                    $correlationId,'pilot-failed|'.$correlationId,
                    [
                        'Sync mode' => 'Pilot',
                        'Source' => trim((string) ($_POST['sync_source_code'] ?? '')),
                        'Diagnostic code' => $diagnosticCode,
                        'Correlation ID' => $correlationId,
                    ]
                );
                echo json_encode([
                    'status' => 0,
                    'code' => $diagnosticCode,
                    'msg' => 'External sync was not applied.',
                    'correlation_id' => $correlationId,
                    'notification_queued' => $notificationQueued,
                ]);
            }
      }

      if(isset( $_POST['admin_get_get_specific_user_profile_info'])){
        switch($_POST['source']){
          case "1": //SSO Source
            $results = $operation->admin_search_user_account($_POST['user_id']);
            // $results['external_source'] = EXTERNAL_DATA_SOURCE_GET_SPECIFIC_USER($_POST['user_id']);
            
            echo json_encode($results);
          break;
          case "2": //External Source

          // //0--- delete          
          //   $results = $operation->admin_search_user_account($_POST['user_id']);
          //   $results['source'] = "2";
          //   echo json_encode($results);
          // //0-- delete
          break;
        }
      }



      if(isset( $_POST['action_add_new_user_manual_check_user_id'])){
        try {
          $manualInput = \OneId\App\User\ManualUserInput::fromPost($_POST);
          $manualCreator = new \OneId\App\User\ManualUserCreator($operation);
          $manualResult = $manualCreator->create(
            $manualInput,
            (string) ($_SESSION['login_user'] ?? ''),
            (string) getUserIP()
          );
          echo json_encode($manualResult);
        } catch (InvalidArgumentException $exception) {
          echo json_encode([
            'status' => 0,
            'msg' => $exception->getMessage(),
            'code' => 'VALIDATION_FAILED',
            'correlation_id' => '',
          ]);
        }
      }

      if(isset( $_POST['admin_preview_specific_user_resync'])){
        try {
          $resyncService = new \OneId\App\User\UserResyncService(
            $operation,
            'EXTERNAL_DATA_SOURCE_GET_SPECIFIC_USER',
            new \OneId\App\User\Adapters\SessionUserResyncApprovalStore()
          );
          echo json_encode($resyncService->preview(
            (string) ($_POST['user_id'] ?? ''),
            (string) ($_SESSION['login_user'] ?? '')
          ));
        } catch (\OneId\App\User\UserResyncException $exception) {
          echo json_encode([
            'status' => 0,
            'code' => $exception->reason,
            'msg' => 'User resync preview was not prepared.',
            'correlation_id' => $exception->correlationId,
          ]);
        } catch (Throwable $exception) {
          $correlationId = bin2hex(random_bytes(8));
          error_log('User resync preview failed correlation_id=' . $correlationId
            . ' exception=' . get_class($exception));
          echo json_encode([
            'status' => 0,
            'code' => 'RESYNC_PREVIEW_FAILED',
            'msg' => 'User resync preview was not prepared.',
            'correlation_id' => $correlationId,
          ]);
        }
      }

      if(isset( $_POST['admin_apply_specific_user_resync'])){
        try {
          $resyncService = new \OneId\App\User\UserResyncService(
            $operation,
            'EXTERNAL_DATA_SOURCE_GET_SPECIFIC_USER',
            new \OneId\App\User\Adapters\SessionUserResyncApprovalStore()
          );
          echo json_encode($resyncService->apply(
            (string) ($_POST['approval_id'] ?? ''),
            (string) ($_SESSION['login_user'] ?? ''),
            (string) getUserIP()
          ));
        } catch (\OneId\App\User\UserResyncException $exception) {
          echo json_encode([
            'status' => 0,
            'code' => $exception->reason,
            'msg' => 'User resync was not applied.',
            'correlation_id' => $exception->correlationId,
          ]);
        } catch (Throwable $exception) {
          $correlationId = bin2hex(random_bytes(8));
          error_log('User resync apply failed correlation_id=' . $correlationId
            . ' exception=' . get_class($exception));
          echo json_encode([
            'status' => 0,
            'code' => 'RESYNC_APPLY_FAILED',
            'msg' => 'User resync was not applied.',
            'correlation_id' => $correlationId,
          ]);
        }
      }

      if(isset( $_POST['admin_reactivate_user_record'])){
        try {
          $service = new \OneId\App\User\UserSecurityActionService($operation);
          echo json_encode($service->reactivate(
            (string) ($_POST['user_info_id'] ?? ''),
            (string) $_SESSION['login_user'],
            getUserIP()
          ));
        } catch (\OneId\App\User\UserSecurityActionException $exception) {
          echo json_encode([
            'status' => 0,
            'code' => $exception->reason,
            'msg' => 'User was not reactivated.',
            'correlation_id' => $exception->correlationId,
          ]);
        }
      }

      if(isset( $_POST['admin_deactivate_user_record'])){
        try {
          $service = new \OneId\App\User\UserSecurityActionService($operation);
          echo json_encode($service->deactivate(
            (string) ($_POST['user_info_id'] ?? ''),
            (string) $_SESSION['login_user'],
            getUserIP()
          ));
        } catch (\OneId\App\User\UserSecurityActionException $exception) {
          echo json_encode([
            'status' => 0,
            'code' => $exception->reason,
            'msg' => 'User was not deactivated.',
            'correlation_id' => $exception->correlationId,
          ]);
        }
      }

      if(isset( $_POST['action_add_new_category'])){
        $results = $operation->action_add_new_category($_POST['add_new_category_name']);

        $operation->syslog_record(16,$_SESSION['login_user']." -> ".$_POST['add_new_category_name'],getUserIP());
        echo json_encode($results);
      }

      if(isset( $_POST['admin_get_all_user_category'])){
        $results = $operation->admin_get_all_user_category();
        foreach ($results as $i => $ii) {
          $sites = $operation->admin_get_category_site_listing($results[$i]['uc_id']);
          $results[$i]['site_count'] = count($sites);
          $results[$i]['report_ref'] = \OneId\App\Admin\UserCategoryReportReference::issue(
            $_SESSION,
            (string) $_SESSION['login_user'],
            (int) $results[$i]['uc_id']
          );
        }
        echo json_encode($results);
      }

      if(isset($_POST['admin_issue_report_preview'])){
        try{
          $reportKey=trim((string)($_POST['report_key']??''));
          $reference=\OneId\App\Admin\AdminReportReference::issue($_SESSION,(string)$_SESSION['login_user'],$reportKey);
          echo json_encode(['status'=>1,'code'=>'REPORT_REFERENCE_ISSUED','ref'=>$reference,'url'=>'./report_preview.php?ref='.rawurlencode($reference)]);
        }catch(\InvalidArgumentException $exception){
          echo json_encode(['status'=>0,'code'=>$exception->getMessage(),'message'=>'Report is not available.']);
        }
      }

      if(isset( $_POST['admin_get_specific_category_user_listing'])){
        $results = $operation->admin_get_specific_category_user_listing($_POST['uc_id']);
        echo json_encode($results);
      }


      if(isset( $_POST['admin_get_category_site_listing'])){
        $results = $operation->admin_get_category_site_listing($_POST['uc_id']);
        usort($results, 'php_sort_alpahabet');
        echo json_encode($results);
      }

      if(isset( $_POST['admin_get_category_site_listing_add_new_site'])){
        $results = $operation->admin_get_category_site_listing_add_new_site($_POST['uc_id']);
        echo json_encode($results);
      }


      if(isset( $_POST['add_acl_category'])){
        $results = $operation->add_acl_category($_POST['uc_id'],$_POST['sp_id']);

        $operation->syslog_record(28,$_SESSION['login_user']." -> ".$_POST['sp_id']." -> ".$_POST['uc_id'],getUserIP());
        echo json_encode($results);
      }

      if(isset( $_POST['remove_acl_category'])){
        $results = $operation->remove_acl_category($_POST['aclgp_id']);
        $operation->syslog_record(29,$_SESSION['login_user'],getUserIP());
        echo json_encode($results);
      }

      if(isset( $_POST['admin_remove_category'])){
        $results = $operation->admin_remove_category($_POST['uc_id']);

        $operation->syslog_record(17,$_SESSION['login_user']." -> REMOVE ->".$_POST['uc_id'],getUserIP());
        echo json_encode($results);
      }



      if(isset( $_POST['admin_save_user_profile'])){
        try {
          $service = new \OneId\App\User\UserProfilePolicyService($operation);
          echo json_encode($service->save(
            (string) ($_POST['user_id'] ?? ''),
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['category_id'] ?? ''),
            (string) $_SESSION['login_user'],
            getUserIP()
          ));
        } catch (\OneId\App\User\UserManagementException $exception) {
          echo json_encode([
            'status' => 0,
            'code' => $exception->reason,
            'msg' => 'User profile was not saved.',
            'correlation_id' => $exception->correlationId,
          ]);
        }
      }


      if(isset( $_POST['add_new_specific_apps_to_user'])){
        try {
          $service = new \OneId\App\User\UserAclManagementService($operation);
          echo json_encode($service->allow(
            (string) ($_POST['u_id'] ?? ''),
            (string) ($_POST['sp_id'] ?? ''),
            (string) $_SESSION['login_user'],
            getUserIP()
          ));
        } catch (\OneId\App\User\UserManagementException $exception) {
          echo json_encode(['status'=>0,'code'=>$exception->reason,'msg'=>'Application access was not added.','correlation_id'=>$exception->correlationId]);
        }
      }

      if(isset( $_POST['admin_get_specific_service_provider'])){
        $results = $operation->admin_get_specific_service_provider($_POST['sp_id']);
        if(is_array($results)&&!empty($results['code_ciphertext'])){
          try{
            $cipher=new \OneId\App\Admin\SiteApiCodeCipher(\OneId\App\Auth\TotpKeyring::fromFile((string)oneid_config('ONEID_TOTP_KEYRING_PATH','')));
            $results['site_api_code']=$cipher->decrypt($results['code_ciphertext'],$results['code_nonce'],(string)$results['key_version']);
          }catch(\Throwable $exception){$results['api_code_retrieval_error']=1;}
        }
        if(is_array($results)){unset($results['code_ciphertext'],$results['code_nonce'],$results['key_version']);}
        echo json_encode($results);
      }

      if(isset( $_POST['action_remove_app'])){
        try {
          $service = new \OneId\App\Admin\WebAppService($operation);
          $result=$service->archive(
            (string) ($_POST['app_id'] ?? ''),
            (string) $_SESSION['login_user'],
            getUserIP()
          );
          echo json_encode(oneid_notify_application_result($operation,$result,'ARCHIVE',(string)$_SESSION['login_user']));
        } catch (\OneId\App\Admin\WebAppManagementException $exception) {
          echo json_encode([
            'status'=>0,
            'code'=>$exception->reason,
            'msg'=>'Application was not removed.',
            'correlation_id'=>$exception->correlationId,
          ]);
        }
      }

      if(isset($_POST['admin_get_archived_apps'])||isset($_POST['admin_restore_archived_app'])||isset($_POST['admin_purge_archived_app'])){
        try{
          $service=new \OneId\App\Admin\WebAppService($operation);
          if(isset($_POST['admin_get_archived_apps']))$result=$service->archived();
          elseif(isset($_POST['admin_restore_archived_app']))$result=$service->restore((string)($_POST['app_id']??''),(string)($_POST['category_id']??''),(string)$_SESSION['login_user'],getUserIP());
          else $result=$service->purgeArchived((string)($_POST['app_id']??''),(string)($_POST['confirmation']??''),(string)($_POST['reason']??''),(string)$_SESSION['login_user'],getUserIP());
          if(!isset($_POST['admin_get_archived_apps']))$result=oneid_notify_application_result($operation,$result,isset($_POST['admin_restore_archived_app'])?'RESTORE':'PURGE',(string)$_SESSION['login_user']);
          echo json_encode($result);
        }catch(\OneId\App\Admin\WebAppManagementException $exception){echo json_encode(['status'=>0,'code'=>$exception->reason,'correlation_id'=>$exception->correlationId,'context'=>$exception->context]);}
      }

      if(isset( $_POST['admin_get_all_blacklist_record'])){
        $results = $operation->admin_get_all_blacklist_record();
        echo json_encode($results);
      }

      if(isset( $_POST['admin_set_deny_access_record'])){
        try {
          $service = new \OneId\App\User\UserAclManagementService($operation);
          echo json_encode($service->deny(
            (string) ($_POST['user_id'] ?? ''),
            (string) ($_POST['sp_id'] ?? ''),
            (string) $_SESSION['login_user'],
            getUserIP()
          ));
        } catch (\OneId\App\User\UserManagementException $exception) {
          echo json_encode(['status'=>0,'code'=>$exception->reason,'msg'=>'Application access was not denied.','correlation_id'=>$exception->correlationId]);
        }
      }

      
      if(isset( $_POST['update_configuration'])){
        try {
          $approval = $_SESSION['sso_policy_preview'] ?? null;
          $submittedAfter = ['token_timeout'=>(string)($_POST['token_timeout']??''),'multi_session'=>(int)($_POST['sso_settings_multi_session']??-1)];
          $submittedReason=trim((string)($_POST['change_reason']??''));$submittedVersion=(int)($_POST['configuration_version']??0);
          if (!is_array($approval) || !hash_equals((string)($approval['id']??''),(string)($_POST['policy_preview_id']??'')) || (int)($approval['expires_at']??0)<time() || (string)($approval['admin']??'')!==(string)$_SESSION['login_user'] || ($approval['after']??null)!==$submittedAfter || (int)($approval['configuration_version']??0)!==$submittedVersion || !hash_equals((string)($approval['change_reason']??''),$submittedReason)) {
            $correlation=bin2hex(random_bytes(8));$service=new \OneId\App\Admin\SsoConfigurationService($operation);$service->recordRejection('SC5_PREVIEW_INVALID',(string)$_SESSION['login_user'],(string)getUserIP(),$correlation,$submittedReason!==''?$submittedReason:null);
            echo json_encode(['status'=>0,'code'=>'SC5_PREVIEW_INVALID','message'=>'A fresh matching preview is required.','correlation_id'=>$correlation]);
            unset($_SESSION['sso_policy_preview']);
            return;
          }
          unset($_SESSION['sso_policy_preview']);
          $service = new \OneId\App\Admin\SsoConfigurationService($operation,oneid_admin_email_notification_operation_callback($operation));
          echo json_encode($service->update(
            $_POST,
            (string) $_SESSION['login_user'],
            (string) getUserIP(),
            (array)($approval['impact']??[])
          ));
        } catch (\OneId\App\Admin\SsoConfigurationException $exception) {
          $service->recordRejection($exception->reason,(string)$_SESSION['login_user'],(string)getUserIP(),$exception->correlationId,is_scalar($_POST['change_reason']??null)?trim((string)$_POST['change_reason']):null);
          echo json_encode([
            'status'=>0,
            'code'=>$exception->reason,
            'message'=>'Authentication policy was not updated.',
            'correlation_id'=>$exception->correlationId,
          ]);
        }
      }

      

      if(isset( $_POST['admin_uplift_blacklist_record'])){
        try {
          $service = new \OneId\App\User\UserAclManagementService($operation);
          echo json_encode($service->uplift(
            (string) ($_POST['user_id'] ?? ''),
            (string) ($_POST['aclblk_id'] ?? ''),
            (string) $_SESSION['login_user'],
            getUserIP()
          ));
        } catch (\OneId\App\User\UserManagementException $exception) {
          echo json_encode(['status'=>0,'code'=>$exception->reason,'msg'=>'Application deny was not uplifted.','correlation_id'=>$exception->correlationId]);
        }
      }

      if(isset( $_POST['admin_get_all_token_for_specific_user'])){
        $results = $operation->get_all_token_for_specific_user($_SESSION['login_user']);
        $unset_flag = 0;
        foreach ($results as $i => $ii) {
          $results[$i]['device_info'] = oneid_normalize_device_info($results[$i]['device_info'] ?? '');
          //Here will check with the system settings for token timeout
          $tokenEvaluation = $tokenLifetimePolicy->evaluate($results[$i]['token_issued_at'],date("Y-m-d H:i:s"),(float)$token_timeout);
          // echo $results[$i]['token_datetime'].'#'.date("Y-m-d H:i:s").'<br/>';
          // echo $hour_diff."#";
          if($tokenEvaluation['state'] !== \OneId\App\Auth\SsoTokenLifetimePolicy::ACTIVE){
              $unset_flag = 1;
              $operation->update_specific_token_status($_SESSION['login_user'],$results[$i]['token_id'],0,'SECURITY_ACTION'); //expired current browser token for specific browser
              unset($results[$i]);
              continue;
          }
          // echo json_encode(array_values($acl_merged_keyed),JSON_PRETTY_PRINT);
          if(isset($_COOKIE['sso_cre'])) {
            $cookieTokenHash = oneid_token_hash((string) $_COOKIE['sso_cre']);
            if(hash_equals((string) $results[$i]['token_id'], $cookieTokenHash)
              || hash_equals((string) $results[$i]['token_id'], (string) $_COOKIE['sso_cre'])){
              $results[$i]['current_token'] = "1";
            }else{
              $results[$i]['current_token'] = "0";
            }
          }else{
            $results[$i]['current_token'] = "0";
          }
          
        }
        if($unset_flag == 1){
          echo json_encode(array_values($results),JSON_PRETTY_PRINT);
        }else{
          echo json_encode($results);

        }
      }

      if(isset( $_POST['admin_get_all_token_for_all_active_user'])){
        try{
          $rawCookie=(string)($_COOKIE['sso_cre']??'');$decoded=json_decode($rawCookie);
          $currentToken=is_object($decoded)&&isset($decoded->sso_cre)?(string)$decoded->sso_cre:$rawCookie;
          $service=new \OneId\App\Admin\ActiveSessionService($operation,new \OneId\App\Admin\Adapters\SessionRevocationPreviewStore());
          echo json_encode($service->list(
            $_POST,
            (string)$_SESSION['login_user'],
            $currentToken,
            (float)$token_timeout
          ));
        }catch(\InvalidArgumentException $exception){
          echo json_encode(['status'=>0,'code'=>$exception->getMessage(),'message'=>'Active sessions could not be loaded.']);
        }catch(\Throwable $exception){
          $correlation=bin2hex(random_bytes(8));
          error_log('AS0 active session listing failed correlation='.$correlation.' exception='.get_class($exception));
          echo json_encode(['status'=>0,'code'=>'AS0_LIST_FAILED','message'=>'Active sessions could not be loaded.','correlation_id'=>$correlation]);
        }
      }

      if(isset($_POST['admin_get_session_history'])){
        try{require_once dirname(__DIR__).'/app/Admin/SessionHistoryService.php';echo json_encode((new \OneId\App\Admin\SessionHistoryService($operation))->list($_POST));}
        catch(\InvalidArgumentException|\RuntimeException $exception){echo json_encode(['status'=>0,'code'=>$exception->getMessage(),'message'=>'Session history could not be loaded.']);}
        catch(\Throwable $exception){$correlation=bin2hex(random_bytes(8));error_log('SH1 session history failed correlation='.$correlation.' exception='.get_class($exception));echo json_encode(['status'=>0,'code'=>'SH1_HISTORY_FAILED','correlation_id'=>$correlation]);}
      }

      if(isset($_POST['admin_preview_active_session_revocation'])||isset($_POST['admin_apply_active_session_revocation'])){
        try{
          $rawCookie=(string)($_COOKIE['sso_cre']??'');$decoded=json_decode($rawCookie);$currentToken=is_object($decoded)&&isset($decoded->sso_cre)?(string)$decoded->sso_cre:$rawCookie;
          $service=new \OneId\App\Admin\ActiveSessionRevocationService($operation,new \OneId\App\Admin\Adapters\SessionRevocationPreviewStore(),(float)$token_timeout);
          $result=isset($_POST['admin_preview_active_session_revocation'])?$service->preview($_POST,(string)$_SESSION['login_user'],$currentToken):$service->apply($_POST,(string)$_SESSION['login_user'],$currentToken,(string)($_SERVER['REMOTE_ADDR']??''));
          echo json_encode($result);
        }catch(\OneId\App\Admin\ActiveSessionRevocationException $exception){
          $status=in_array($exception->getMessage(),['AS3_FEATURE_DISABLED','AS3_STEP_UP_REQUIRED','AS3_ADMIN_TARGET_BLOCKED','AS3_CURRENT_SESSION_BLOCKED','AS3_STATE_NOT_ALLOWED'],true)?403:409;if(!headers_sent())http_response_code($status);echo json_encode(['status'=>0,'code'=>$exception->getMessage(),'correlation_id'=>$exception->correlationId]);
        }
      }

      if(isset( $_POST['get_specific_user_sp_access_list'])){
                 //get user info
        $user_info = $operation->get_specific_user_info($_POST['u_id']);
        //get category acl 
        $acl_result_group = $operation->specfic_user_get_sp_list_by_group($user_info['u_category']);
        //get category acl 
        $acl_result_single = $operation->specfic_user_get_sp_list_by_specific_sp($_POST['u_id']);
        //get blacklist acl
        $acl_result_blacklist = $operation->specfic_user_get_sp_blacklist($_POST['u_id']);

        //merge & join array
        $acl_merged_keyed = array_unique(array_merge($acl_result_group,$acl_result_single), SORT_REGULAR);
        foreach ($acl_merged_keyed as $k => $kk) {
          $acl_merged_keyed[$k]['status'] = 1;
        }

        //remove any single app from group app
        foreach ($acl_result_blacklist as $i => $ii) {
          foreach ($acl_merged_keyed as $k => $kk) {
            
            if($acl_result_blacklist[$i]['sp_id'] == $acl_merged_keyed[$k]['sp_id']){
                  
                  $acl_merged_keyed[$k]['status'] = 0;
                  $acl_merged_keyed[$k]['aclblk_id'] = $acl_result_blacklist[$i]['aclblk_id'];

                  break;
              }
          }
        }

        echo json_encode(array_values($acl_merged_keyed),JSON_PRETTY_PRINT);
        // echo json_encode($acl_merged_keyed);
      }

      if(isset( $_POST['get_add_new_specific_apps_to_accissible_list'])){
                 //get user info
        $user_info = $operation->get_specific_user_info($_POST['u_id']);
        //get category acl 
        $acl_result_group = $operation->specfic_user_get_sp_list_by_group($user_info['u_category']);
        //get category acl 
        $acl_result_single = $operation->specfic_user_get_sp_list_by_specific_sp($_POST['u_id']);
        //get blacklist acl
        $acl_result_blacklist = $operation->specfic_user_get_sp_blacklist($_POST['u_id']);

        //merge & join array
        $acl_merged_keyed = array_unique(array_merge($acl_result_group,$acl_result_single), SORT_REGULAR);

        //Get all SP list 
        $sp_list = $operation->admin_get_all_service_provider();

        //remove any single app from group app
        foreach ($sp_list as $i => $ii) {
          foreach ($acl_merged_keyed as $k => $kk) {
            
            if($sp_list[$i]['sp_id'] == $acl_merged_keyed[$k]['sp_id']){
                  
                  unset($sp_list[$i]);

                  break;
              }
          }
        } 
        echo json_encode(array_values($sp_list),JSON_PRETTY_PRINT);
      }



     if(isset( $_POST['get_specific_user_app_list'])){
      // echo "X";
      // echo $_SESSION['login_user'];
      //get user info
      $user_info = $operation->get_specific_user_info($_SESSION['login_user']);
      //get category acl 
      $acl_result_group = $operation->specfic_user_get_sp_list_by_group($user_info['u_category']);
      //get category acl 
      $acl_result_single = $operation->specfic_user_get_sp_list_by_specific_sp($_SESSION['login_user']);
      //get blacklist acl
      $acl_result_blacklist = $operation->specfic_user_get_sp_blacklist($_SESSION['login_user']);
    
      //merge & join array
      $acl_merged_keyed = array_unique(array_merge($acl_result_group,$acl_result_single), SORT_REGULAR);


      //remove any single app from group app
      foreach ($acl_result_blacklist as $i => $ii) {
        foreach ($acl_merged_keyed as $k => $kk) {
          if($acl_result_blacklist[$i]['sp_id'] == $acl_merged_keyed[$k]['sp_id']){
                unset($acl_merged_keyed[$k]);
                break;
            }
        }
      }
      
      usort($acl_merged_keyed, 'php_sort_alpahabet');

      foreach ($acl_merged_keyed as $i => $ii) {
        $idp_info = $operation->admin_get_specific_service_provider($acl_merged_keyed[$i]['sp_id']);
        $acl_merged_keyed[$i]['sp_sso_support'] = $idp_info['sp_sso_support'];
      }
      $favouriteIds = array_flip($operation->getUserAppFavouriteIds((string) $_SESSION['login_user']));
      foreach ($acl_merged_keyed as $i => $ii) {
        $acl_merged_keyed[$i]['is_favourite'] = isset($favouriteIds[(string) $ii['sp_id']]) ? 1 : 0;
      }
      $sp_list = array_values($acl_merged_keyed);
      $sp_group = array_unique(array_column($acl_merged_keyed, 'sp_group_id'));
      $sp_group = array_values($sp_group);
      // echo json_encode($sp_group);

      $all_groups_info = [];
      foreach ($sp_group as $gp) {
          
          $sp_group_info = $operation->admin_get_specific_web_app_category_info($gp);
          $sp_group_info['tabname'] = preg_replace('/\s+/', '', $sp_group_info['sp_group_name'])."_".$sp_group_info['sp_group_id']."_tab";
          $data = [];
          foreach ($sp_list as $k => $kk) {
            if($sp_list[$k]['sp_group_id'] == $gp){
              $data[] = $sp_list[$k];
            }
          }

          $sp_group_info['data'] = $data;
          $all_groups_info[] = $sp_group_info;
      }
	  
	  usort($all_groups_info, function($a, $b) {
			return (int)$b['sp_group_seq'] - (int)$a['sp_group_seq'];
		});
      echo json_encode(oneid_metadata_repository()->localizeGroups($all_groups_info,oneid_current_locale()));

        // foreach ($sp_group as $i => $ii) { 
        //   $sp_group[$i]['tabname'] = preg_replace('/\s+/', '', $sp_group[$i]['sp_group_name'])."_".$sp_group[$i]['sp_group_id']."_tab";
        //   $final_result = array();

        //   foreach ($sp_list as $k => $kk) {
        //     if($sp_list[$k]['sp_group_id'] == $sp_list)
        //   }


        //   $results = $operation->admin_get_all_service_provider_byGroup($sp_group[$i]['sp_group_id']);
        //   usort($results, 'php_sort_alpahabet');

        //   $results2 = $operation->admin_get_all_service_provider_non_sso_byGroup($sp_group[$i]['sp_group_id']);
        //   usort($results2, 'php_sort_alpahabet');

        //   $final_result = array_merge($results,$results2);
        //   $sp_group[$i]['data'] = $final_result;
        // }


      // echo json_encode(array_values($acl_merged_keyed),JSON_PRETTY_PRINT);
     }

     if(isset( $_POST['user_set_app_favourite'])){
      $userId = (string) $_SESSION['login_user'];
      $spId = trim((string) ($_POST['sp_id'] ?? ''));
      $enabledRaw = (string) ($_POST['enabled'] ?? '');

      if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $spId)
          || !in_array($enabledRaw, ['0', '1'], true)) {
        http_response_code(422);
        echo json_encode(['status' => 0, 'code' => 'INVALID_FAVOURITE_REQUEST']);
      } elseif (!$operation->supportsUserAppFavourites()) {
        http_response_code(503);
        echo json_encode(['status' => 0, 'code' => 'FAVOURITES_STORAGE_UNAVAILABLE']);
      } elseif ($enabledRaw === '1' && !$operation->userHasEffectiveAppAccess($userId, $spId)) {
        http_response_code(403);
        echo json_encode(['status' => 0, 'code' => 'APP_ACCESS_DENIED']);
      } else {
        $enabled = $enabledRaw === '1';
        $operation->setUserAppFavourite($userId, $spId, $enabled);
        echo json_encode([
          'status' => 1,
          'sp_id' => $spId,
          'is_favourite' => $enabled ? 1 : 0,
        ]);
      }
     }


      function php_sort_alpahabet($a, $b) {
        return strcmp($a["sp_name"], $b["sp_name"]);
      }

    //Preparing for redirect to SP
     if(isset( $_POST['go_to_service_provider'])){
      $result = check_specific_sp_allowed($operation,$_POST['sp_id']);
      echo json_encode($result);
     }

     function check_specific_sp_allowed($operation,$sp_id){
         //get user info
        $user_info = $operation->get_specific_user_info($_SESSION['login_user']);
        //get category acl 
        $acl_result_group = $operation->specfic_user_get_sp_list_by_group($user_info['u_category']);
        //get category acl 
        $acl_result_single = $operation->specfic_user_get_sp_list_by_specific_sp($_SESSION['login_user']);
        //get blacklist acl
        $acl_result_blacklist = $operation->specfic_user_get_sp_blacklist($_SESSION['login_user']);

        //merge & join array
        $acl_merged_keyed = array_unique(array_merge($acl_result_group,$acl_result_single), SORT_REGULAR);


        //remove any single app from group app
        foreach ($acl_result_blacklist as $i => $ii) {
          foreach ($acl_merged_keyed as $k => $kk) {
            if($acl_result_blacklist[$i]['sp_id'] == $acl_merged_keyed[$k]['sp_id']){
                  unset($acl_merged_keyed[$k]);
                  array_values($acl_merged_keyed);
                  break;
              }
          }
        }

        $domain = "";
        $status = 0;
        foreach ($acl_merged_keyed as $m => $mm) {
          if($acl_merged_keyed[$m]['sp_id'] == $sp_id){
            $domain = $acl_merged_keyed[$m]['sp_domain'];
            $status = 1;
            break;
          }
        }
        return array( 'domain' => $domain,
                          'status' => $status);
     }


    if(isset($_POST['action_forgot_password'])||isset($_POST['action_mydigitalid_password_recovery_request'])){
      $correlation = bin2hex(random_bytes(8));
      $authenticatedMyDigitalIdRecovery=isset($_POST['action_mydigitalid_password_recovery_request']);
      if($authenticatedMyDigitalIdRecovery&&((string)($_SESSION['auth_method']??'')!=='mydigitalid'||trim((string)($_SESSION['login_user']??''))==='')){oneid_json_deny(403,'MyDigital ID authentication required','UC7_MYDID_RECOVERY_AUTH_REQUIRED');}
      $identifier = $authenticatedMyDigitalIdRecovery
        ? trim((string)$_SESSION['login_user'])
        : trim((string) ($_POST['forgot_password_id'] ?? ''));
      $uid_result = $identifier !== '' ? $operation->func_search_uid($identifier) : false;
      if (!$uid_result && $identifier !== '') {
        $uid_result = $operation->func_search_uid_pelajar($identifier);
      }

      $outcome = 'not_eligible';
      $auditType = 35;
      unset($_SESSION['password_reset_user'], $_SESSION['password_reset_verified_at']);
      if ($uid_result && (int) $uid_result['avail_status'] === 1 && (int)$passwordResetEmailEnabled===1 && filter_var((string)$uid_result['data5'],FILTER_VALIDATE_EMAIL)!==false) {
        $latestRequest = $operation->otp_latest_request($uid_result['u_id']);
        $cooldownPassed = !$latestRequest
          || strtotime($latestRequest['otp_create_date']) <= (time() - 60);
        $withinDailyLimit = $operation->otp_count_last_day($uid_result['u_id']) < 5;

        if (!$cooldownPassed) {
          $outcome = 'cooldown';
        } elseif (!$withinDailyLimit) {
          $outcome = 'daily_limit';
        } else {
          $otp = generate_otp_code();
          $operation->otp_invalidate_active($uid_result['u_id']);
          if ($operation->otp_create($uid_result['u_id'], $otp) === 1) {
            $_SESSION['password_reset_user'] = $uid_result['u_id'];
            session_write_close();
            $sent = OTP_EMAIL_Sender($otp, $uid_result['data5'], $uid_result['data1']);
            oneid_start_secure_session();
            if ($sent) {
              $outcome = 'smtp_accepted';
              $auditType = 9;
            } else {
              $outcome = 'smtp_failed';
              $operation->otp_invalidate_active($uid_result['u_id']);
              unset($_SESSION['password_reset_user'], $_SESSION['password_reset_verified_at']);
            }
          } else {
            $outcome = 'challenge_create_failed';
          }
        }
      }

      $userForAudit = is_array($uid_result) ? (string) ($uid_result['u_id'] ?? '') : '';
      $operation->syslog_record(
        $auditType,
        sprintf(
          'action=password_recovery_request outcome=%s user=%s identifier_hash=%s correlation=%s',
          $outcome,
          $userForAudit !== '' ? $userForAudit : 'unresolved',
          hash('sha256', strtolower($identifier)),
          $correlation
        ),
        getUserIP()
      );

      echo json_encode([
        'result' => 'true',
        'code' => 'SC6_RECOVERY_REQUEST_ACCEPTED',
        'translation_key' => 'recovery.accepted_generic',
        'correlation_id' => $correlation,
        'delivery_available' => (int)$passwordResetEmailEnabled === 1,
        'msg' => oneid_translate('recovery.accepted_generic')
      ]);
    }

    function generate_otp_code() {
      return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    if(isset($_POST['action_submit_OTP'])||isset($_POST['action_mydigitalid_password_recovery_verify'])){
      $resetUser = (string) ($_SESSION['password_reset_user'] ?? '');
      if(isset($_POST['action_mydigitalid_password_recovery_verify'])&&((string)($_SESSION['auth_method']??'')!=='mydigitalid'||!hash_equals((string)($_SESSION['login_user']??''),$resetUser))){oneid_json_deny(403,'MyDigital ID recovery context invalid','UC7_MYDID_RECOVERY_CONTEXT_INVALID');}
      $submittedOtp = preg_replace('/\D/', '', (string) ($_POST['otp_id'] ?? ''));
      $otp_search_result = $resetUser !== '' ? $operation->otp_check($resetUser) : false;

      if ($otp_search_result && strlen($submittedOtp) === 6
        && password_verify($submittedOtp, (string) $otp_search_result['otp_code'])) {
        $operation->otp_consume($otp_search_result['otp_id']);
        $_SESSION['password_reset_verified_at'] = time();
        echo json_encode([
          'result' => 'true',
          'reset_required' => true,
          'translation_key' => 'otp.verified',
          'msg' => oneid_translate('otp.verified')
        ]);
      } else {
        if ($otp_search_result) {
          $operation->otp_record_failed_attempt($otp_search_result['otp_id']);
        }
        echo json_encode([
          'result'=>'false',
          'translation_key'=>'otp.invalid',
          'msg'=>oneid_translate('otp.invalid')
        ]);
      }
    }

    if(isset($_POST['action_reset_password'])||isset($_POST['action_mydigitalid_password_recovery_reset'])){
      $resetUser = (string) ($_SESSION['password_reset_user'] ?? '');
      $authenticatedMyDigitalIdReset=isset($_POST['action_mydigitalid_password_recovery_reset']);
      if($authenticatedMyDigitalIdReset&&((string)($_SESSION['auth_method']??'')!=='mydigitalid'||!hash_equals((string)($_SESSION['login_user']??''),$resetUser))){oneid_json_deny(403,'MyDigital ID recovery context invalid','UC7_MYDID_RECOVERY_CONTEXT_INVALID');}
      $verifiedAt = (int) ($_SESSION['password_reset_verified_at'] ?? 0);
      if ($resetUser === '' || $verifiedAt === 0 || (time() - $verifiedAt) > 600) {
        oneid_json_deny(403, oneid_translate('password.authorization_expired'));
      }

      $newPassword = (string) ($_POST['reset_password_new'] ?? '');
      $confirmation = (string) ($_POST['reset_password_confirm'] ?? '');
      if($authenticatedMyDigitalIdReset){$newPassword=(string)($_POST['change_password_new']??'');$confirmation=(string)($_POST['change_password_new_reconfirm']??'');}
      if (!hash_equals($newPassword, $confirmation)) {
        echo json_encode([
          'result'=>'false',
          'translation_key'=>'password.confirmation_mismatch',
          'msg'=>oneid_translate('password.confirmation_mismatch')
        ]);
        return;
      }
      list($passwordValid, $passwordMessage) = oneid_validate_new_password($newPassword);
      if (!$passwordValid) {
        $passwordTranslationKey = match ($passwordMessage) {
          'Password must contain at least 12 characters.' => 'password.minimum_length',
          'Password must include uppercase, lowercase, number and symbol.' => 'password.complexity',
          'Password is too common or predictable.' => 'password.too_common',
          'Password must not contain the user ID.' => 'password.contains_user_id',
          default => 'password.reset_failed',
        };
        echo json_encode([
          'result'=>'false',
          'translation_key'=>$passwordTranslationKey,
          'msg'=>oneid_translate($passwordTranslationKey)
        ]);
        return;
      }

      if($authenticatedMyDigitalIdReset){
        $started=false;
        try{
          $operation->beginTransaction();$started=true;
          $user=$operation->get_user_password_change_for_update($resetUser);
          if(!is_array($user)||(int)($user['avail_status']??0)!==1)throw new \RuntimeException('UC2_USER_NOT_ACTIVE');
          $stored=(string)($user['u_password']??'');
          if($stored!==''&&oneid_password_verify($newPassword,$stored))throw new \RuntimeException('UC2_PASSWORD_REUSE_CURRENT');
          foreach($operation->get_password_history_hashes($resetUser,oneid_password_history_limit()) as $historyHash){if(oneid_password_verify($newPassword,(string)$historyHash))throw new \RuntimeException('UC5_PASSWORD_HISTORY_REUSED');}
          if($stored!==''&&$operation->record_password_history($resetUser,$stored)!==1)throw new \RuntimeException('UC5_PASSWORD_HISTORY_WRITE_FAILED');
          if($operation->set_user_password($resetUser,$newPassword,0)!==1)throw new \RuntimeException('UC2_PASSWORD_NOT_CHANGED');
          $operation->prune_password_history($resetUser,oneid_password_history_limit());
          $operation->update_whole_token_status($resetUser,0,'PASSWORD_RESET');
          $operation->otp_invalidate_active($resetUser);
          if($operation->syslog_record(21,'user='.$resetUser.' action=mydigitalid_email_otp_password_reset correlation='.bin2hex(random_bytes(8)),getUserIP())!==1)throw new \RuntimeException('UC2_AUDIT_FAILED');
          $operation->commit();$started=false;
          unset($_SESSION['password_reset_user'],$_SESSION['password_reset_verified_at']);
          oneid_clear_sso_cookie();
          echo json_encode(['result'=>'true','code'=>'UC7_MYDID_PASSWORD_RESET_REAUTH_REQUIRED','translation_key'=>'password.updated','msg'=>oneid_translate('password.updated'),'redirect_uri'=>APP_URL.'/']);return;
        }catch(\Throwable $exception){if($started){try{$operation->rollback();}catch(\Throwable){}}$reason=$exception instanceof \RuntimeException?$exception->getMessage():'UC2_OPERATION_FAILED';echo json_encode(['result'=>'false','code'=>$reason,'translation_key'=>'dashboard.password.operation_failed','msg'=>oneid_translate('dashboard.password.operation_failed')]);return;}
      }

      $operation->set_user_password($resetUser, $newPassword, 0);
      $operation->update_whole_token_status($resetUser, 0, 'PASSWORD_RESET');
      $operation->syslog_record(21, 'Password reset completed for user ID: '.$resetUser, getUserIP());
      unset($_SESSION['password_reset_user'], $_SESSION['password_reset_verified_at']);
      echo json_encode([
        'result'=>'true',
        'translation_key'=>'password.updated',
        'msg'=>oneid_translate('password.updated'),
        'redirect_uri'=>APP_URL.'/'
      ]);
    }




    function OTP_EMAIL_Sender($otp_code,$email,$user_name,$isTest=false,&$messageId=null){
      $locale=oneid_current_locale();
      $email_body=$isTest
        ?\OneId\App\Mail\OneIdEmailTemplate::deliveryTest($user_name,$locale)
        :\OneId\App\Mail\OneIdEmailTemplate::otp(
          $user_name,
          oneid_translate('email.recovery.context'),
          oneid_translate('email.recovery.badge'),
          oneid_translate('email.recovery.headline'),
          oneid_translate('email.recovery.intro'),
          $otp_code,
          null,
          $locale
        );

            $mail = new PHPMailer;
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->isSMTP(); 
            $mail->SMTPDebug = 0; 
            $mail->Timeout = 10;
            $mail->Host = (string) oneid_config('ONEID_SMTP_HOST');
            $mail->Port = (int) oneid_config('ONEID_SMTP_PORT');
            $mail->SMTPSecure = (string) oneid_config('ONEID_SMTP_ENCRYPTION');
            $mail->SMTPAuth = true;
            $mail->Username = oneid_secret('ONEID_SMTP_USERNAME');
            $mail->Password = oneid_secret('ONEID_SMTP_PASSWORD');
            $mail->setFrom(oneid_secret('ONEID_SMTP_USERNAME'), (string) oneid_config('ONEID_SMTP_FROM_NAME'));
            $mail->addAddress($email, $user_name);
            //$mail->addAddress('30saat@gmail.com', 'Nabil');
            $mail->Subject = $isTest
              ?oneid_translate('email.test.subject',[],$locale)
              :oneid_translate('email.recovery.subject',[],$locale);
            $mail->msgHTML($email_body);
            $mail->AltBody = $isTest
              ?\OneId\App\Mail\OneIdEmailTemplate::deliveryTestPlainText($locale)
              :\OneId\App\Mail\OneIdEmailTemplate::otpPlainText(
                oneid_translate('email.recovery.headline'),
                $otp_code,
                $locale
              );
            $sent=(bool)$mail->send();$messageId=$sent?$mail->getLastMessageID():null;return $sent;
    }


    function get_hour_diff($time_start,$time_end){
      return round((strtotime($time_start) - strtotime($time_end))/3600, 1);
    }



      if(isset( $_POST['user_signoff_security_sessions'])){
        $results = $operation->update_specific_token_status($_SESSION['login_user'],$_POST['token_id'],0,'SECURITY_ACTION');
        echo json_encode($results);
      }


            
      if(isset( $_POST['admin_get_audit_range'])){
        list($start, $end) = explode(' - ', $_POST['audit_search_daterange']);
        $parseAuditDate = static function (string $value): ?string {
          foreach (['!d/m/Y', '!m/d/Y', '!Y-m-d'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, trim($value));
            $errors = DateTimeImmutable::getLastErrors();
            if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
              return $parsed->format('Y-m-d');
            }
          }
          return null;
        };
        $startDate = $parseAuditDate($start);
        $endDate = $parseAuditDate($end);
        if ($startDate === null || $endDate === null) {
          echo json_encode([]);
          exit;
        }
        $results = $operation->admin_get_audit_range($startDate,$endDate);
        echo json_encode($results);
      }

      if(isset($_POST['admin_get_sync_sessions'])){
        $results = $operation->sync_get_all_sessions();
        echo json_encode($results);
      }

      if(isset($_POST['admin_get_sync_log_detail'])){
        $ext_head_id = intval($_POST['ext_head_id'] ?? 0);
        $results = $operation->sync_get_change_log_by_session($ext_head_id);
        echo json_encode($results);
      }

  if(isset( $_POST['update_specific_token_datetime'])){
        $cookieToken = (string) ($_COOKIE['sso_cre'] ?? '');
        if ($cookieToken === '') {
          oneid_json_deny(401, 'SSO session token is missing');
        }
        $results = $operation->update_specific_token_datetime($_SESSION['login_user'], $cookieToken);
        require_once __DIR__ . '/SSO_IDP_INC.php';
        // $cookie = json_decode( $_COOKIE["sso_cre"] );
        echo json_encode($results);
      } 
	  
	  
	   if(isset( $_POST['admin_reset_password_user'])){
        try {
          $service = new \OneId\App\User\UserSecurityActionService($operation);
          echo json_encode($service->resetPassword(
            (string) ($_POST['user_id'] ?? ''),
            (string) $_SESSION['login_user'],
            getUserIP()
          ));
        } catch (\OneId\App\User\UserSecurityActionException $exception) {
          echo json_encode([
            'status' => 0,
            'code' => $exception->reason,
            'msg' => 'Password was not reset.',
            'correlation_id' => $exception->correlationId,
          ]);
        }
      }

?>
