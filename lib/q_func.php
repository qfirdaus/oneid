<?php
require_once __DIR__ . '/session_security.php';
oneid_start_secure_session();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/src/Exception.php';
require __DIR__ . '/src/PHPMailer.php';
require __DIR__ . '/src/SMTP.php';
require_once __DIR__ . '/config.php';
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
require_once dirname(__DIR__) . '/app/User/UserManagementException.php';
require_once dirname(__DIR__) . '/app/User/UserProfilePolicyService.php';
require_once dirname(__DIR__) . '/app/User/UserAclManagementService.php';
require_once dirname(__DIR__) . '/app/Admin/WebAppManagementException.php';
require_once dirname(__DIR__) . '/app/Admin/WebAppCategoryService.php';
require_once dirname(__DIR__) . '/app/Admin/WebAppService.php';
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\AbstractDeviceParser;

require_once __DIR__ . '/request_security.php';
oneid_guard_q_func_request($_POST);

$sys_config = $operation->get_system_config();
$token_timeout = $sys_config['token_timeout'];//24 means 1 day
$sys_config_multisession = $sys_config['multi_session']; //Multi Session
$sys_config_OTP_email = $sys_config['email_OTP']; //Multi Session
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

function generate_app_ID($length){
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ01234KLMNOPQRSTUVWXYZ56789';
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
        if(trim($_POST['username']) == ""){
        }else{
          //check_uid
        $results = $operation->func_authenticate($_POST['username'], $_POST['password']);
        if ($results != false){
        }else{
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

        // echo var_dump($results);)
        $array = array();
        if ($results != false){
           //Check user available status
           if($results['avail_status'] == 0){            
              $array['login_status'] = 0;
              $array['login_response_msg'] = "Sorry, your account had been suspended. Please contact BTMK for further information.";
              $operation->syslog_record(1,"User:".$_POST['username']."  -> ".$array['login_response_msg'],getUserIP());
              echo json_encode($array);
              return;
           }

            //SSO Token Initialize
            $new_refresh_token = generate_token(); //generate new token
            if($sys_config_multisession == 0){
              $operation->update_whole_token_status($results['u_id'],0); //expired all token for specific user
            }
            //
            //Add new token to DB

            $operation->add_new_token($new_refresh_token, $results['u_id'], $detectedDeviceInfo);

            $user_info = $operation->get_specific_user_info($results['u_id']);
            oneid_set_sso_cookie($new_refresh_token);

            $array['login_status'] = 1;

            oneid_establish_authenticated_session($results);

            if(isset($_POST['site_id'])){
                $check_result = check_specific_sp_allowed($operation,$_POST['site_id']);
                // echo json_encode($check_result);
                if($check_result['status']==1){                  
                  $array['redirect_uri'] = $check_result['domain'].'?new_sso_cre='.$new_refresh_token; 
                }else{
                  $array['redirect_uri'] = 'page/dashboard';                  
                }
            }else{
                $array['redirect_uri'] = 'page/dashboard';              
            }
            $array['login_response_msg'] = "Login Success";
            $operation->syslog_record(2,"User: ".$_POST['username']." Logged in -> ".$array['redirect_uri'],getUserIP());
            echo json_encode($array);
        }else{
            $array['login_status'] = 0;
            $array['login_response_msg'] = "Wrong username / password";
            $operation->syslog_record(3,"User: ".$_POST['username']." -> " .$array['login_response_msg'],getUserIP());
            echo json_encode($array);
        }
     }

     //First Time Login Check Password had changed or not
      if(isset( $_POST['check_default_password'])){
        $array = array();
        if((int) ($_SESSION['password_change_required'] ?? 0) === 1){
          $array['result'] = "change_pwd";
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
            'sp_group_id' => $row['sp_group_id'],
          ];
        }
        echo json_encode(array_values($groups));
      }

      if(isset( $_POST['admin_get_sso_settings'])){
        $results = $operation->get_system_config();
        // $results = [];
        echo json_encode($results);
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


      if(isset( $_POST['action_add_new_app'])){
        $checkbox_status=0; //0 support, 1 not support
        if(isset($_POST['add_new_app_sso_checkbox'])){
          $checkbox_status=1;
        }
        // File upload handling
        $uploadDir = oneid_public_path('public_img');
        $uploadResult = save_app_icon_upload($_FILES['app_icon'] ?? null, $uploadDir);
        $safeFileName = $uploadResult['filename'];
        $app_icon_upload_msg = $uploadResult['message'];


        $sp_id = generate_app_ID(10);
        $results_1 = $operation->action_add_new_app($sp_id,$_POST['add_new_app_name'],$_POST['add_new_app_desc'],str_replace(':/','://', trim(preg_replace('/\/+/', '/',$_POST['add_new_app_url']), '/')),$safeFileName,$_POST['add_new_app_category'],$checkbox_status);

        $operation->syslog_record(13,$_SESSION['login_user'],getUserIP());
        // Prepare a result with extra data
        $results = [
            'status' => $results_1,
            'app_icon' => $app_icon_upload_msg
        ];

        echo json_encode($results);
      }

      if(isset( $_POST['action_edit_app_info'])){
        $uploadDir = oneid_public_path('public_img');
        $safeFileName = sanitize_existing_app_icon($_POST['existing_app_icon'] ?? '');
        if (isset($_FILES['app_icon']) && ($_FILES['app_icon']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = save_app_icon_upload($_FILES['app_icon'], $uploadDir);
            $app_icon_upload_msg = $uploadResult['message'];
            if ($uploadResult['success']) {
                $safeFileName = $uploadResult['filename'];
            }
        } else {
            $app_icon_upload_msg = 'Existing app icon retained';
        }

        $checkbox_status=0; //0 support, 1 not support
        if(isset($_POST['app_info_sso_checkbox'])){
          $checkbox_status=1;
        }
        $results_1 = $operation->action_edit_app_info($_POST['edit_app_id'],$_POST['edit_app_name'],$_POST['edit_app_desc'],str_replace(':/','://',trim(preg_replace('/\/+/', '/', $_POST['edit_app_url']), '/')),$safeFileName,$_POST['edit_app_category'],$checkbox_status);

        $operation->syslog_record(14,$_SESSION['login_user'],getUserIP());
        $results = [
            'status' => $results_1,
            'app_icon' => $app_icon_upload_msg
        ];
        echo json_encode($results);
      }


      if(isset( $_POST['action_change_password'])){

        // echo $_SESSION['login_user'];
        $login_result = $operation->verify_user_password($_SESSION['login_user'], $_POST['change_password_current']);

        if ($login_result != false){
          if($_POST['change_password_new'] == $_POST['change_password_new_reconfirm']){
            list($passwordValid, $passwordMessage) = oneid_validate_new_password($_POST['change_password_new']);
            if (!$passwordValid) {
              echo json_encode(['msg'=>$passwordMessage, 'status'=>0]);
              return;
            }
            $results = $operation->set_user_password($_SESSION['login_user'], $_POST['change_password_new'], 0);
            $_SESSION['password_change_required'] = 0;
            $operation->update_whole_token_status($_SESSION['login_user'], 0);
            $rotatedToken = generate_token();
            $operation->add_new_token($rotatedToken, $_SESSION['login_user'], $detectedDeviceInfo);
            oneid_set_sso_cookie($rotatedToken);
            $operation->syslog_record(21,$_SESSION['login_user'],getUserIP());
            echo json_encode(array( 'msg' => "Password successfully changed",
                          'status' => 1));
          }else{
            $operation->syslog_record(20,$_SESSION['login_user'],getUserIP());
            echo json_encode(array( 'msg' => "New password confirmation does not match.",
                          'status' => 0));
          }
        }else{
          $operation->syslog_record(20,$_SESSION['login_user'],getUserIP());
          echo json_encode(array( 'msg' => "Unable to verify current password",
                          'status' => 0));
        }
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


      if(isset( $_POST['admin_preview_sync_user'])){
            try {
                $approvalStore = new \OneId\App\Sync\Adapters\SessionSyncApprovalStore();
                $approvalService = new \OneId\App\Sync\SyncApprovalService(
                    $approvalStore,
                    new \OneId\App\Sync\SyncPlanFingerprinter()
                );
                $previewService = new \OneId\App\Sync\SyncPreviewService(
                    new \OneId\App\Sync\Adapters\ExternalApiUserSource(),
                    new \OneId\App\Sync\Adapters\DatabaseSyncPersistenceAdapter($operation),
                    new \OneId\App\Sync\SyncPlanner(
                        new \OneId\App\Sync\Adapters\LegacySyncPolicy()
                    ),
                    300,
                    5.0,
                    new \OneId\App\Sync\SyncSafetyPolicy()
                );
                $baseline = $operation->sync_latest_completed_source_rows();
                $pilotConfig = \OneId\App\Sync\SyncPilotConfig::fromEnvironment();
                $runtimeConfig = \OneId\App\Sync\SyncRuntimeConfig::fromEnvironment();
                $subsetSelector = $pilotConfig->enabled
                    ? new \OneId\App\Sync\SyncPlanSubsetSelector($pilotConfig)
                    : null;
                $previewResponse = $previewService->previewForApproval(
                    (string) ($_SESSION['login_user'] ?? ''),
                    $baseline,
                    $approvalService,
                    $subsetSelector
                );
                $previewResponse['pilot_apply_available'] = $pilotConfig->enabled
                    && $runtimeConfig->canApply()
                    && ($previewResponse['approval_ready'] ?? false) === true;
                if (!$previewResponse['pilot_apply_available']) {
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

      if(isset( $_POST['admin_add_sync_user'])){
            try {
                $runtimeConfig = \OneId\App\Sync\SyncRuntimeConfig::fromEnvironment();
                $pilotConfig = \OneId\App\Sync\SyncPilotConfig::fromEnvironment();
                $approvalStore = new \OneId\App\Sync\Adapters\SessionSyncApprovalStore();
                $coordinator = (new \OneId\App\Sync\SyncEngineFactory(
                    $operation,
                    $runtimeConfig
                ))->createPilotCoordinator($approvalStore, $pilotConfig);
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
                        'ADMIN_SYNC_SAFE header=%d new=%d updated=%d deactivated=%d reactivated=%d',
                        $summary->headerId,
                        $summary->new,
                        $summary->updated,
                        $summary->deactivated,
                        $summary->reactivated
                    ),
                    getUserIP()
                );
                echo json_encode([
                    'status' => 1,
                    'code' => 'SYNC_APPLY_COMPLETED',
                    'header_id' => $summary->headerId,
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
                    'SYNC_APPROVAL_INVALID',
                    'SYNC_APPROVAL_NOT_AVAILABLE',
                    'SYNC_APPROVAL_EXPIRED',
                    'SYNC_APPROVAL_ADMIN_MISMATCH',
                    'SYNC_APPROVAL_PLAN_MISMATCH',
                    'SYNC_ALREADY_RUNNING',
                    'SYNC_SAFETY_BLOCKED',
                    'SYNC_RECONCILIATION_MISMATCH',
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
                echo json_encode([
                    'status' => 0,
                    'code' => $diagnosticCode,
                    'msg' => 'External sync was not applied.',
                    'correlation_id' => $correlationId,
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
        }
        echo json_encode($results);
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
        echo json_encode($results);
      }

      if(isset( $_POST['action_remove_app'])){
        try {
          $service = new \OneId\App\Admin\WebAppService($operation);
          echo json_encode($service->archive(
            (string) ($_POST['app_id'] ?? ''),
            (string) $_SESSION['login_user'],
            getUserIP()
          ));
        } catch (\OneId\App\Admin\WebAppManagementException $exception) {
          echo json_encode([
            'status'=>0,
            'code'=>$exception->reason,
            'msg'=>'Application was not removed.',
            'correlation_id'=>$exception->correlationId,
          ]);
        }
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
        $results = $operation->update_configuration($_POST['token_timeout'],$_POST['sso_settings_multi_session'],$_POST['sso_settings_OTP_email']);
        echo json_encode($results);
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
          $hour_diff = get_hour_diff($results[$i]['token_datetime'],date("Y-m-d H:i:s"));
          // echo $results[$i]['token_datetime'].'#'.date("Y-m-d H:i:s").'<br/>';
          // echo $hour_diff."#";
          if($hour_diff <= 0){
            //Check timeout
            if(abs($hour_diff) > $token_timeout){ //Token had pass the token_timeout max life but,
              $unset_flag = 1;
              $operation->update_specific_token_status($_SESSION['login_user'],$results[$i]['token_id'],0); //expired current browser token for specific browser
              unset($results[$i]);
              continue;
            }
          }else{
              $unset_flag = 1;
              $operation->update_specific_token_status($_SESSION['login_user'],$results[$i]['token_id'],0); //expired current browser token for specific browser
              unset($results[$i]);
              continue;
            // echo "x"; 
          //INVALID change token settings to expired
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
        $results = $operation->get_all_token_for_all_active_user();
        $unset_flag = 0;
         foreach ($results as $i => $ii) {
          $results[$i]['device_info'] = oneid_normalize_device_info($results[$i]['device_info'] ?? '');
          //Here will check with the system settings for token timeout
          $hour_diff = get_hour_diff($results[$i]['token_datetime'],date("Y-m-d H:i:s"));
          // echo $results[$i]['token_datetime'].'#'.date("Y-m-d H:i:s").'<br/>';
          // echo $hour_diff."#";
          if($hour_diff <= 0){
            //Check timeout
            if(abs($hour_diff) > $token_timeout){ //Token had pass the token_timeout max life but,
              $unset_flag = 1;
              $operation->update_specific_token_status($results[$i]['user_id'],$results[$i]['token_id'],0); //expired current browser token for specific browser
              unset($results[$i]);
              continue;
            }
          }else{
              $unset_flag = 1;
              $operation->update_specific_token_status($results[$i]['user_id'],$results[$i]['token_id'],0); //expired current browser token for specific browser
              unset($results[$i]);
              continue;
            // echo "x"; 
          //INVALID change token settings to expired
          }
          // echo json_encode(array_values($acl_merged_keyed),JSON_PRETTY_PRINT);
         /*  if(isset($_COOKIE['sso_cre'])) {
            $cookie = json_decode($_COOKIE["sso_cre"]);            
            if($results[$i]['token_id'] == $cookie->sso_cre){
              $results[$i]['current_token'] = "1";
            }else{
              $results[$i]['current_token'] = "0";
            }
          }else{
            $results[$i]['current_token'] = "0";
          }
           */
        } 
        if($unset_flag == 1){
          echo json_encode(array_values($results),JSON_PRETTY_PRINT);
        }else{
          echo json_encode($results);

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
      echo json_encode($all_groups_info);

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


    if(isset( $_POST['action_forgot_password'])){
      $identifier = trim((string) ($_POST['forgot_password_id'] ?? ''));
      $uid_result = $identifier !== '' ? $operation->func_search_uid($identifier) : false;
      if (!$uid_result && $identifier !== '') {
        $uid_result = $operation->func_search_uid_pelajar($identifier);
      }

      unset($_SESSION['password_reset_user'], $_SESSION['password_reset_verified_at']);
      if ($uid_result && (int) $uid_result['avail_status'] === 1) {
        $_SESSION['password_reset_user'] = $uid_result['u_id'];
        $latestRequest = $operation->otp_latest_request($uid_result['u_id']);
        $cooldownPassed = !$latestRequest
          || strtotime($latestRequest['otp_create_date']) <= (time() - 60);
        $withinDailyLimit = $operation->otp_count_last_day($uid_result['u_id']) < 5;

        if ($cooldownPassed && $withinDailyLimit) {
          $otp = generate_otp_code();
          $operation->otp_invalidate_active($uid_result['u_id']);
          if ($operation->otp_create($uid_result['u_id'], $otp) === 1) {
            if ((int) $sys_config_OTP_email === 1) {
              OTP_EMAIL_Sender($otp, $uid_result['data5'], $uid_result['data1']);
            }
            $operation->syslog_record(9, 'Password reset OTP created for user ID: '.$uid_result['u_id'], getUserIP());
          }
        }
      }

      echo json_encode([
        'result' => 'true',
        'msg' => 'If the account is eligible, reset instructions have been sent to its registered email.'
      ]);
    }

    function generate_otp_code() {
      return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    if(isset( $_POST['action_submit_OTP'])){
      $resetUser = (string) ($_SESSION['password_reset_user'] ?? '');
      $submittedOtp = preg_replace('/\D/', '', (string) ($_POST['otp_id'] ?? ''));
      $otp_search_result = $resetUser !== '' ? $operation->otp_check($resetUser) : false;

      if ($otp_search_result && strlen($submittedOtp) === 6
        && password_verify($submittedOtp, (string) $otp_search_result['otp_code'])) {
        $operation->otp_consume($otp_search_result['otp_id']);
        $_SESSION['password_reset_verified_at'] = time();
        echo json_encode([
          'result' => 'true',
          'reset_required' => true,
          'msg' => 'OTP verified. Set a new password to continue.'
        ]);
      } else {
        if ($otp_search_result) {
          $operation->otp_record_failed_attempt($otp_search_result['otp_id']);
        }
        echo json_encode(['result'=>'false', 'msg'=>'Invalid or expired OTP.']);
      }
    }

    if(isset( $_POST['action_reset_password'])){
      $resetUser = (string) ($_SESSION['password_reset_user'] ?? '');
      $verifiedAt = (int) ($_SESSION['password_reset_verified_at'] ?? 0);
      if ($resetUser === '' || $verifiedAt === 0 || (time() - $verifiedAt) > 600) {
        oneid_json_deny(403, 'Password reset authorization expired');
      }

      $newPassword = (string) ($_POST['reset_password_new'] ?? '');
      $confirmation = (string) ($_POST['reset_password_confirm'] ?? '');
      if (!hash_equals($newPassword, $confirmation)) {
        echo json_encode(['result'=>'false', 'msg'=>'Password confirmation does not match.']);
        return;
      }
      list($passwordValid, $passwordMessage) = oneid_validate_new_password($newPassword);
      if (!$passwordValid) {
        echo json_encode(['result'=>'false', 'msg'=>$passwordMessage]);
        return;
      }

      $operation->set_user_password($resetUser, $newPassword, 0);
      $operation->update_whole_token_status($resetUser, 0);
      $operation->syslog_record(21, 'Password reset completed for user ID: '.$resetUser, getUserIP());
      unset($_SESSION['password_reset_user'], $_SESSION['password_reset_verified_at']);
      echo json_encode([
        'result'=>'true',
        'msg'=>'Password updated. Please sign in with the new password.',
        'redirect_uri'=>APP_URL.'/'
      ]);
    }




    function OTP_EMAIL_Sender($otp_code,$email,$user_name){
      
      $html_title = 'Password Reset OTP';
      $html_body_header = 'Tetapan Semula Kata Laluan';
      $html_body_content = '<p>Sila gunakan OTP berikut untuk mengesahkan permintaan tetapan semula kata laluan:</p>';

      $email_body = '
      <!DOCTYPE html>
      <html>
        <head>
          <meta charset="UTF-8" />
          <title>'.$html_title.'</title>
        </head>
        <body style="font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4;">
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
            <tr>
              <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; padding: 30px; border-radius: 8px;">
                  <tr>
                    <td align="center" style="font-size: 24px; font-weight: bold; color: #333333;">
                      '.$html_body_header.'
                    </td>
                  </tr>
                  <tr>
                    <td style="padding-top: 20px; font-size: 16px; color: #555555;">
                      '.$html_body_content.'
                    </td>
                  </tr>
                  <tr>
                    <td align="center" style="padding: 30px 0;">
                      <div style="display: inline-block; padding: 15px 30px; font-size: 28px; letter-spacing: 5px; background-color: #f0f0f0; border-radius: 6px; color: #333333;">
                        <strong>' . $otp_code . '</strong>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td style="font-size: 14px; color: #777777;">
                      OTP sah selama 5 minit dan hanya boleh digunakan sekali.
                    </td>
                  </tr>
                  <tr>
                    <td style="padding-top: 30px; font-size: 14px; color: #999999;">
                      Jika anda tidak membuat permohonan ini, sila abaikan e-mel ini.
                    </td>
                  </tr>
                  <tr>
                    <td style="padding-top: 20px; font-size: 14px; color: #999999;">
                      Terima kasih,<br/>
                      Pentadbir Portal OneID@UPNM<br/>
					  E-mel: ask.oneid@upnm.edu.my
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </body>
      </html>';



            $mail = new PHPMailer;
            $mail->isSMTP(); 
            $mail->SMTPDebug = 0; 
            $mail->Host = (string) oneid_config('ONEID_SMTP_HOST');
            $mail->Port = (int) oneid_config('ONEID_SMTP_PORT');
            $mail->SMTPSecure = (string) oneid_config('ONEID_SMTP_ENCRYPTION');
            $mail->SMTPAuth = true;
            $mail->Username = oneid_secret('ONEID_SMTP_USERNAME');
            $mail->Password = oneid_secret('ONEID_SMTP_PASSWORD');
            $mail->setFrom(oneid_secret('ONEID_SMTP_USERNAME'), (string) oneid_config('ONEID_SMTP_FROM_NAME'));
            $mail->addAddress($email, $user_name);
            //$mail->addAddress('30saat@gmail.com', 'Nabil');
            $mail->Subject = 'OneID@UPNM - OTP Lupa Kata Laluan';
            $mail->msgHTML($email_body); // remove if you do not want to send HTML email
            $mail->AltBody = 'HTML not supported';
            $mail->send();
    }


    function get_hour_diff($time_start,$time_end){
      return round((strtotime($time_start) - strtotime($time_end))/3600, 1);
    }



      if(isset( $_POST['user_signoff_security_sessions'])){
        $results = $operation->update_specific_token_status($_SESSION['login_user'],$_POST['token_id'],0);
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
