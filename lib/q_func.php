<?php
session_start(); // Starting Session
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';
require_once './config.php';
include_once '../vendors/spyc-master/Spyc.php';
require_once '../vendors/device-detector-master/autoload.php';
require_once './external_data_source_API.php';
require_once './sync_user_runner.php';
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\AbstractDeviceParser;

$sys_config = $operation->get_system_config();
$token_timeout = $sys_config['token_timeout'];//24 means 1 day
$sys_config_multisession = $sys_config['multi_session']; //Multi Session
$sys_config_OTP_email = $sys_config['email_OTP']; //Multi Session
$userAgent = $_SERVER['HTTP_USER_AGENT']; // change this to the useragent you want to parse
$dd = new DeviceDetector($userAgent);
$dd->parse();
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
  $a= str_replace(".", "", uniqid('',true));
    return $a;
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
        $results = $operation->func_authenticate($_POST['username'],md5($_POST['password']));
        if ($results != false){
        }else{
          //check data2
          $results = $operation->func_authenticate2($_POST['username'],md5($_POST['password']));
          if ($results != false){
          }else{
            //check data3        
            $results = $operation->func_authenticate3($_POST['username'],md5($_POST['password']));
            if ($results != false){
            }else{
              //check data3        
              if(trim($_POST['username']) != ""){
                $results = $operation->func_authenticate4($_POST['username'],md5($_POST['password']));
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

            $detector_brand = $dd->getBrandName();
            $detector_device = $dd->getDeviceName();
            $operation->add_new_token($new_refresh_token,$results['u_id'],$detector_device . ' ('.$detector_brand.')');

            $user_info = $operation->get_specific_user_info($results['u_id']);
            $cookieData = array_merge( array( "sso_dt" => date('Y-m-d H:i:s'), "sso_cre" => $new_refresh_token), $user_info );
            setcookie('sso_cre', json_encode($cookieData), time() + (60 * 30),'/',''); // 86400 = 1 day (this is default 1 day)

            $array['login_status'] = 1;

            $_SESSION['user'] = $results['data1'];
            $_SESSION['login_user']=$results['u_id'];
            $_SESSION['login_status']="true";
            $_SESSION['login_user_type']=$results['u_type'];

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
        $user_info = $operation->get_specific_user_info_withpassword($_SESSION['login_user']);
        $array = array();
        $data_challange = "";
        if(trim($user_info['data3']) != ""){//staff
          $data_challange = $user_info['data4'];
		  $array['tst3'] = "Staff";
        }else{//student
          $data_challange = $user_info['data2'];
		  $array['tst3'] = "Stuudent";
        }
        if(md5($data_challange) == $user_info['u_password']){
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
        $sp_group = $operation->get_sp_group();
        foreach ($sp_group as $i => $ii) { 
          $sp_group[$i]['tabname'] = preg_replace('/\s+/', '', $sp_group[$i]['sp_group_name'])."_".$sp_group[$i]['sp_group_id']."_tab";
          $final_result = array();
          $results = $operation->admin_get_all_service_provider_byGroup($sp_group[$i]['sp_group_id']);
          usort($results, 'php_sort_alpahabet');

          $results2 = $operation->admin_get_all_service_provider_non_sso_byGroup($sp_group[$i]['sp_group_id']);
          usort($results2, 'php_sort_alpahabet');

          $final_result = array_merge($results,$results2);
          $sp_group[$i]['data'] = $final_result;
        }

        // Separate arrays
        $group0 = [];
        $otherGroups = [];
        foreach ($sp_group as $group) {
            if ($group['sp_group_id'] === 0) {
                $group0[] = $group;
            } else {
                $otherGroups[] = $group;
            }
        }

        $final = array_merge($otherGroups, $group0);

        
        // $results = [];
        echo json_encode($final);
      }

      if(isset( $_POST['admin_get_sso_settings'])){
        $results = $operation->get_system_config();
        // $results = [];
        echo json_encode($results);
      }

      

      if(isset( $_POST['action_add_new_webapp_category'])){
        $operation->syslog_record(11,$_SESSION['login_user'],getUserIP());
        $results = $operation->action_add_new_webapp_category($_POST['add_new_webapp_category_name']);
        echo json_encode($results);
      }


      if(isset( $_POST['action_remove_app_category'])){
        //get list of apps under the category
        $app_list = array();
        $results = $operation->admin_get_all_service_provider_byGroup($_POST['app_category_id']);
        $results2 = $operation->admin_get_all_service_provider_non_sso_byGroup($_POST['app_category_id']);
        $app_list = array_merge($results,$results2);

        foreach ($app_list as $i => $ii) {
          $operation->reset_web_app_category($app_list[$i]['sp_id']);
        }
        $final = $operation->action_remove_app_category($_POST['app_category_id']);
        $operation->syslog_record(12,$_SESSION['login_user'],getUserIP());
        echo json_encode($final);
        //echo json_encode($results);
      }


      if(isset( $_POST['action_add_new_app'])){
        $checkbox_status=0; //0 support, 1 not support
        if(isset($_POST['add_new_app_sso_checkbox'])){
          $checkbox_status=1;
        }
        // File upload handling
        $app_icon_upload_msg  = "";
        $safeFileName = "";
        $uploadDir = '../public_img/';
        if (isset($_FILES['app_icon']) && $_FILES['app_icon']['error'] == 0) {
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            // Optional: rename file
            $ext = pathinfo($_FILES['app_icon']['name'], PATHINFO_EXTENSION);
            $safeFileName = 'app_icon_' . time() . '.' . strtolower($ext);
            $targetPath = $uploadDir . $safeFileName;

            if (move_uploaded_file($_FILES['app_icon']['tmp_name'], $targetPath)) {
                $app_icon_upload_msg= 'Upload Success';
            } else {
                $app_icon_upload_msg= 'Failed to move uploaded file';
            }
        } else {
            $app_icon_upload_msg= 'No file uploaded or upload error';
        }


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
        $app_icon_upload_msg  = "";
        $safeFileName = "";
        $uploadDir = '../public_img/';
        if (isset($_FILES['app_icon']) && $_FILES['app_icon']['error'] == 0) {
            // Save the new uploaded file
            if (!file_exists($uploadDir)) {
                  mkdir($uploadDir, 0755, true);
              }
              // Optional: rename file
              $ext = pathinfo($_FILES['app_icon']['name'], PATHINFO_EXTENSION);
              $safeFileName = 'app_icon_' . time() . '.' . strtolower($ext);
              $targetPath = $uploadDir . $safeFileName;

              if (move_uploaded_file($_FILES['app_icon']['tmp_name'], $targetPath)) {
                  $app_icon_upload_msg= 'Upload Success';
              } else {
                  $app_icon_upload_msg= 'Failed to move uploaded file';
              }
        } elseif (!empty($_POST['existing_app_icon'])) {
            // Reuse the previous file
            $safeFileName = $_POST['existing_app_icon'];
        } else {
            // No icon provided at all
            $safeFileName = ''; // or handle as error
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
        $login_result = $operation->func_authenticate($_SESSION['login_user'],md5($_POST['change_password_current']));

        if ($login_result != false){
          if($_POST['change_password_new'] == $_POST['change_password_new_reconfirm']){
            $results = $operation->action_change_password($_SESSION['login_user'],md5($_POST['change_password_new']));
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


      if(isset( $_POST['admin_add_sync_user'])){
            try {
                $triggered_by = $_SESSION['login_user'] ?? '';
                $header_info = run_admin_sync_user($operation, $triggered_by);
                $operation->syslog_record(22,'User: '.$triggered_by.' ADMIN_SYNC_USER session='.$header_info['ext_head_id'].' new='.$header_info['New'].' updated='.$header_info['Update'].' deactivated='.$header_info['Deactivate'].' reactivated='.$header_info['Reactivate'],getUserIP());
                echo json_encode($header_info);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
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
        $results = $operation->get_specific_user_info($_POST['add_new_manual_user_id']);
        // echo !empty($results);
        // return;
        if(!empty($results)){          
          echo json_encode(array( 'msg' => "User ID already in used. Please change another user id.",
                          'status' => 0));

        }else{         
          $operation->action_add_new_user($_POST['add_new_manual_user_id'],$_POST['add_new_manual_user_category'],md5($_POST['add_new_manual_user_id']),$_POST['add_new_manual_user_name'],$_POST['add_new_user_data2'],$_POST['add_new_user_data3'],$_POST['add_new_manual_user_id'],$_POST['add_new_user_data5'],$_POST['add_new_user_data6'],$_POST['add_new_user_data7'],$_POST['add_new_user_data8'],$_POST['add_new_user_data9'],$_POST['add_new_user_data10'],$_POST['add_new_user_data11'],$_POST['add_new_user_data12'],hash("sha256",$_POST['add_new_manual_user_name']));

          $operation->syslog_record(23,$_SESSION['login_user']." -> ".$_POST['add_new_manual_user_id'],getUserIP());
          echo json_encode(array( 'msg' => "User successfully added.",
                          'status' => 1));

        }
      }

      if(isset( $_POST['admin_resync_specific_user'])){
        $user_info = $operation->admin_search_user_account($_POST['user_id']);
        $results = SAMPLE_DATA_SOURCE_GET_SPECIFIC_USER($_POST['user_id']);
        // $results = EXTERNAL_DATA_SOURCE_GET_SPECIFIC_USER($_POST['user_id'])[0];
        $operation->syslog_record(24,$_SESSION['login_user']." -> ".$_POST['user_id'],getUserIP());


        $new_data_hash = hash('sha256',$results['data1'].$results['data2'].$results['data3'].$results['data4'].$results['data5'].$results['data6'].$results['data7'].$results['data8'].$results['data9'].$results['data10'].$results['data11'].$results['data12']);

        if($user_info['u_changes_hash']!=$new_data_hash){
          $operation->admin_update_specific_user_info_all_data($_POST['user_id'],$results['data1'],$results['data2'],$results['data3'],$results['data4'],$results['data5'],$results['data6'],$results['data7'],$results['data8'],$results['data9'],$results['data10'],$results['data11'],$results['data12'],$new_data_hash);
          echo json_encode(array('status' => 1));
        }else{
          echo json_encode(array('status' => 0));
        }
      }

      if(isset( $_POST['admin_reactivate_user_record'])){
        $user_info = $operation->admin_search_user_account($_POST['user_info_id']);
        $results = $operation->admin_update_user_status($_POST['user_info_id'],1);

        $operation->syslog_record(26,$_SESSION['login_user']." -> ".$_POST['user_info_id'],getUserIP());
        echo json_encode(array( 'source_status' => 1, //1-sso, 2-external
                          'status' => 1));
      }

      if(isset( $_POST['admin_deactivate_user_record'])){
        $user_info = $operation->admin_search_user_account($_POST['user_info_id']);
        $results = $operation->admin_update_user_status($_POST['user_info_id'],0);

        $operation->syslog_record(25,$_SESSION['login_user']." -> ".$_POST['user_info_id'],getUserIP());
        echo json_encode(array( 'source_status' => 1, //1-sso, 2-external
                          'status' => 1));
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



      if(isset( $_POST['admin_change_user_category'])){
        // $user_info = $operation->get_specific_user_info('90');/
        $user_info = $operation->get_specific_user_info($_POST['user_id']);
        if(!empty($user_info)){

          if($_POST['category_id'] == 9){
            $results = $operation->admin_change_user_category($_POST['user_id'],$_POST['category_id'],1);

            $operation->syslog_record(18,$_SESSION['login_user']." -> Grant Admin To -> ".$_POST['user_id'] . " " . $user_info['data1'],getUserIP());
            echo json_encode($results);
          }else{
            $results = $operation->admin_change_user_category($_POST['user_id'],$_POST['category_id'],0);
            $operation->syslog_record(18,$_SESSION['login_user']." -> Change User Category -> ".$_POST['user_id'] . " " . $user_info['data1'],getUserIP());
            echo json_encode($results);
          }
        }else{
          //Get info from external source
          echo json_encode($results);
        }
      }


      if(isset( $_POST['add_new_specific_apps_to_user'])){
        $results = $operation->add_new_specific_apps_to_user($_POST['u_id'],$_POST['sp_id']);
        echo json_encode($results);
      }

      if(isset( $_POST['admin_get_specific_service_provider'])){
        $results = $operation->admin_get_specific_service_provider($_POST['sp_id']);
        echo json_encode($results);
      }

      if(isset( $_POST['action_remove_app'])){

        $results = $operation->action_update_app_status($_POST['app_id'],0);
        $operation->remove_acl_category_all_by_sp_id($_POST['app_id']);
        $operation->syslog_record(29,$_SESSION['login_user'],getUserIP());
        echo json_encode($results);
      }

      if(isset( $_POST['admin_get_all_blacklist_record'])){
        $results = $operation->admin_get_all_blacklist_record($_POST['sp_id']);
        echo json_encode($results);
      }

      if(isset( $_POST['admin_set_deny_access_record'])){
        $results = $operation->admin_set_deny_access_record($_POST['sp_id'],$_POST['user_id']);
        echo json_encode($results);
      }

      
      if(isset( $_POST['update_configuration'])){
        $results = $operation->update_configuration($_POST['token_timeout'],$_POST['sso_settings_multi_session'],$_POST['sso_settings_OTP_email']);
        echo json_encode($results);
      }

      

      if(isset( $_POST['admin_uplift_blacklist_record'])){
        $results = $operation->admin_uplift_blacklist_record($_POST['aclblk_id']);
        echo json_encode($results);
      }

      if(isset( $_POST['admin_get_all_token_for_specific_user'])){
        $results = $operation->get_all_token_for_specific_user($_SESSION['login_user']);
        $unset_flag = 0;
        foreach ($results as $i => $ii) {
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
            $cookie = json_decode($_COOKIE["sso_cre"]);            
            if($results[$i]['token_id'] == $cookie->sso_cre){
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
        # code...
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
        if(isset( $_POST['forgot_password_id'])){
          $uid_result= $operation->func_search_uid($_POST['forgot_password_id']);
          if(!empty($uid_result)){
            //find any available TAC Code
             //9 March 2026: replace
			 // $otp_search_result = $operation->otp_check($_POST['forgot_password_id']);
			 //with
            $otp_search_result = $operation->otp_check($uid_result['u_id']);
            $otp = generate_otp_code();
            if(!empty($otp_search_result)){
              $update_result= $operation->otp_update_otp_create_date($otp_search_result['otp_id']);
              if($update_result==1){
                echo json_encode(array( 'result' => "true",'msg' => "Check your email. Use the last OTP sent.", 'u_id' => $uid_result['u_id']));
                $operation->syslog_record(9,"User: ".$uid_result['data1']." -> OTP Forgot Password Created",getUserIP());
              }else{
                if($sys_config_OTP_email == 1){
                  OTP_EMAIL_Sender($otp,$uid_result['data5'],$uid_result['data1']);
                }
                echo json_encode(array( 'result' => "true",'msg' => "OTP Sent to your email. Check your inbox.", 'u_id' => $uid_result['u_id']));
                $operation->syslog_record(9,"User: ".$uid_result['data1']." -> OTP Forgot Password Created",getUserIP());
              }
            }else{
              //9 March 2026: replace
			  //$create_otp_result = $operation->otp_create($_POST['forgot_password_id'],$otp);
			  //with
			  $create_otp_result = $operation->otp_create($uid_result['u_id'],$otp);
              if($create_otp_result==1){
                if($sys_config_OTP_email == 1){
                  OTP_EMAIL_Sender($otp,$uid_result['data5'],$uid_result['data1']);
                }
                echo json_encode(array( 'result' => "true",'msg' => "OTP Sent to your email. Check your inbox.", 'u_id' => $uid_result['u_id']));
                $operation->syslog_record(9,"User: ".$uid_result['data1']." -> OTP Forgot Password Created",getUserIP());

              }else{
                echo json_encode(array( 'result' => "false",'msg' => "Sorry, we're unable to create OTP at this moment, please try again"));
              }
            }
          }else{
			  
			//---new func
			$uid_result= $operation->func_search_uid_pelajar($_POST['forgot_password_id']);
			 // echo json_encode($uid_result);
			 // return;
			if(!empty($uid_result)){
            //find any available TAC Code
            $otp_search_result = $operation->otp_check($uid_result['u_id']);
            $otp = generate_otp_code();
            if(!empty($otp_search_result)){
              $update_result= $operation->otp_update_otp_create_date($otp_search_result['otp_id']);
              if($update_result==1){
                echo json_encode(array( 'result' => "true",'msg' => "Check your email. Use the last OTP sent.", 'u_id' => $uid_result['u_id']));
                $operation->syslog_record(9,"User: ".$uid_result['data1']." -> OTP Forgot Password Created",getUserIP());
              }else{
                if($sys_config_OTP_email == 1){
                  OTP_EMAIL_Sender($otp,$uid_result['data5'],$uid_result['data1']);
                }
                echo json_encode(array( 'result' => "true",'msg' => "OTP Sent to your email. Check your inbox.", 'u_id' => $uid_result['u_id']));
                $operation->syslog_record(9,"User: ".$uid_result['data1']." -> OTP Forgot Password Created",getUserIP());
              }
            }else{
              $create_otp_result = $operation->otp_create($uid_result['u_id'],$otp);
              if($create_otp_result==1){
                if($sys_config_OTP_email == 1){
                  OTP_EMAIL_Sender($otp,$uid_result['data5'],$uid_result['data1']);
                }
                echo json_encode(array( 'result' => "true",'msg' => "OTP Sent to your email. Check your inbox.", 'u_id' => $uid_result['u_id']));
                $operation->syslog_record(9,"User: ".$uid_result['data1']." -> OTP Forgot Password Created",getUserIP());

              }else{
                echo json_encode(array( 'result' => "false",'msg' => "Sorry, we're unable to create OTP at this moment, please try again"));
              }
            }
          }else{
			   echo json_encode(array( 'result' => "false",'msg' => "Sorry, we're unable to find any NIRC or Passport ID matched."));
		  }
			
			  
			  
           
          }
        }else{
			
			
          echo json_encode(array( 'result' => "false",'msg' => "Please key in NIRC or Passpord ID"));
        }
        
        //check if
        //$results = $operation->update_specific_token_status($_SESSION['login_user'],$_POST['token_id'],0);
        //echo json_encode($results);
      }

    function generate_otp_code() {
      return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }


     if(isset( $_POST['action_submit_OTP'])){
        $otp_search_result = $operation->otp_check($_POST['u_id']);
        if(!empty($otp_search_result)){
          if($otp_search_result['otp_code']==$_POST['otp_id']){
            //-- Get specific user
            $user_info = $operation->func_search_uid($_POST['u_id']);
            if(!empty($user_info)){
              //-- Reset password to md5 ic
              //$operation->action_change_password($_POST['u_id'],md5($user_info['data4']));
			  //Bug fixed 23/2/2026 - By Nana
			  //Issues when student reset password, it cant login. password are mixed up
			  if(trim($user_info['data3']) != ""){
					// Staff
					$reset_password = md5($user_info['data4']);
				} else {
					// Student
					$reset_password = md5($user_info['data2']);
				}
				$operation->action_change_password($_POST['u_id'], $reset_password);
              //-- lg user in to dashboard

            $array = array();
            if ($user_info != false){
               //Check user available status
               if($user_info['avail_status'] == 0){            
                  $array['login_status'] = 0;
                  $array['login_response_msg'] = "Sorry, your account had been suspended. Please contact BTMK for further information.";
                  $operation->syslog_record(1,"User:".$user_info['u_id']."  -> ".$array['login_response_msg'],getUserIP());
                  echo json_encode($array);
                  return;
               }
                //SSO Token Initialize
                $new_refresh_token = generate_token(); //generate new token
                if($sys_config_multisession == 0){
                  $operation->update_whole_token_status($user_info['u_id'],0); //expired all token for specific user
                }
                //
                //Add new token to DB
                $detector_brand = $dd->getBrandName();
                $detector_device = $dd->getDeviceName();
                $operation->add_new_token($new_refresh_token,$user_info['u_id'],$detector_device . ' ('.$detector_brand.')');
                $user_info = $operation->get_specific_user_info($user_info['u_id']);
                $cookieData = array_merge( array( "sso_dt" => date('Y-m-d H:i:s'), "sso_cre" => $new_refresh_token), $user_info );
                setcookie('sso_cre', json_encode($cookieData), time() + (60 * 30),'/',''); // 86400 = 1 day (this is default 1 day)
                $array['login_status'] = 1;
                $array['result'] = "true";
                $_SESSION['user'] = $user_info['data1'];
                $_SESSION['login_user']=$user_info['u_id'];
                $_SESSION['login_status']="true";
                $_SESSION['login_user_type']=$user_info['u_type'];
                $array['redirect_uri'] = 'page/dashboard'; 
                $array['login_response_msg'] = "Login Success";
                $operation->syslog_record(2,"User: ".$user_info['u_id']." Logged in From RESET -> ".$array['redirect_uri'],getUserIP());
                echo json_encode($array);
            }else{
                $array['login_status'] = 0;
                $array['login_response_msg'] = "Wrong username / password";
                $operation->syslog_record(3,"User: ".$user_info['u_id']." -> " .$array['login_response_msg'],getUserIP());
                echo json_encode($array);
            }


              //----






            }else{
              echo json_encode(array( 'result' => "false",'msg' => "Invalid User."));
            }

          }else{
            echo json_encode(array( 'result' => "false",'msg' => "Invalid OTP. Please try again."));
          }
        }else{
          echo json_encode(array( 'result' => "false",'msg' => "OTP Expired. Please request new OTP."));
        }
     }




    function OTP_EMAIL_Sender($otp_code,$email,$user_name){
      
      $html_title = 'Password Reset OTP';
      $html_body_header = 'Tetapan Semula Kata Laluan';
      $html_body_content = '<p>Kata laluan semasa anda ialah nombor Kad Pengenalan (No. K/P) tanpa tanda -.</p><p>Sila gunakan OTP berikut untuk pengesahan:</p>';

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
                      OTP sah selama 1 minit.
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
            $mail->Host = "smtp.office365.com"; 
            $mail->Port = "587"; // typically 587 
            $mail->SMTPSecure = 'tls'; // ssl is depracated
            $mail->SMTPAuth = true;
            $mail->Username = "sysadmin@upnm.edu.my";
            $mail->Password = "aPPs2019";
            $mail->setFrom("sysadmin@upnm.edu.my", "sysadmin@upnm");
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
        $startDate = date('Y-m-d', strtotime($start)); // "2016-01-01"
        $endDate   = date('Y-m-d', strtotime($end));   // "2016-01-31"
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
        $results = $operation->update_specific_token_datetime($_POST['u_id'],$_POST['token_id']);
        require_once './SSO_IDP_INC.php';
        // $cookie = json_decode( $_COOKIE["sso_cre"] );
        echo json_encode($results);
      } 
	  
	  
	   if(isset( $_POST['admin_reset_password_user'])){
		   
        $user_info = $operation->get_specific_user_info($_POST['user_id']);
		switch($user_info['u_category']){
                    case "2":
                      //$user_category = 2;
					  $password = md5($user_info['data4']);
                    break;
                    case "3":
                      //$user_category = 3;
					  $password = md5($user_info['data4']);
                    break;
                    case "10":
                      //$user_category = 10;
					  $password = md5($user_info['data2']);
                    break;
                    case "10":
                      //$user_category = 10;
					  $password = md5($user_info['data2']);
                    break;
                    case "11":
                      //$user_category =11;
					  $password = md5($user_info['data4']);
                    break;
                    case "12":
                      //$user_category =12;
					  $password = md5($user_info['data4']);
                    break;
                    default:
                      //$user_category = 0;     
					  $password = md5("SOS");                 
                    break;
                  }  
		$results = $operation->action_change_password($_POST['user_id'],$password);
        echo json_encode($results);
      }

?>