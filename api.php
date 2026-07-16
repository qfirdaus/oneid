<?php 
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/stateless_jwt.php';
require_once __DIR__ . '/lib/integration_security.php';
require_once __DIR__ . '/app/Auth/SsoTokenLifetimePolicy.php';

header('Content-Type: application/json; charset=utf-8');
oneid_integration_guard('sso_token', 'sso:validate');

ini_set('always_populate_raw_post_data', -1);
$json = file_get_contents('php://input');
//$json = str_replace('"{"', "{", $json);
//$json = str_replace('"}"', "}", $json); 
$data = json_decode($json,true);
if (!is_array($data)) {
	oneid_integration_json_error(400, 'invalid_json', 'A valid JSON request body is required.');
}

$token_timeout = $operation->get_system_config()['token_timeout']; //24 means 1 day
$tokenLifetimePolicy = new \OneId\App\Auth\SsoTokenLifetimePolicy();
// echo json_encode($data);s
switch($data['flag'] ?? null){
	case "1": //check SSO Token RESULT = (1) OK, (0) Invalid
		$API_respond_fields = array();
		//respond_flag = 0-error,1-normal,2-auto reissue token
        $results = $operation->check_token($data['data']['token']);
        if(!$results){
			$API_respond_fields['respond_flag'] = "1"; //0-error,1-normal,2-auto reissue token
			$API_respond_fields['respond'] = "0";
			$API_respond_fields['respond_description'] = "Token not available, login to IDP for new token";
        	echo json_encode($API_respond_fields);
        }else{
	        if(!empty($results['policy_revoke_at']) && strtotime((string)$results['policy_revoke_at'])<=time()){
               if($operation->enforce_due_token_revocation((string)$results['token_id'])===1){
                  $operation->syslog_record(31,'action=lazy_policy_revoke correlation='.(string)($results['policy_revoke_correlation']??''),oneid_integration_client_ip());
               }
               $results['status']=0;
	        }
	        //Here will check with the system settings for token timeout
	        $tokenEvaluation = $tokenLifetimePolicy->evaluate($results['token_issued_at'],date("Y-m-d H:i:s"),(float)$token_timeout);
	        $hour_diff = round(-$tokenEvaluation['age_seconds']/3600, 1);
	        // echo "X";
	        // echo $hour_diff . "XX";
	        if($tokenEvaluation['state'] !== \OneId\App\Auth\SsoTokenLifetimePolicy::FUTURE_INVALID){
	        	//Check timeout
               if($tokenEvaluation['state'] !== \OneId\App\Auth\SsoTokenLifetimePolicy::ACTIVE){
	        		//we have BUFFER OF 1 HOUR (1hr) if the user is still active, 
	        		//token will be reissued , this to cater if user is actively using the system beyond the max token life,
	        		// so system will automatically reissued a new token without user knowing the new token. 
                  if($tokenEvaluation['state'] === \OneId\App\Auth\SsoTokenLifetimePolicy::LEGACY_REFRESH){
	        			//recreate new token
						$API_respond_fields['respond_flag'] = "2"; //0-error,1-normal,2-auto reissue token
						$API_respond_fields['respond'] = "1"; //0-invalid, 1- valid
						$API_respond_fields['respond_description'] = "Token expired, automate kick in to reissue new token. All token will be force expired status = 0 ". $hour_diff;
						$new_refresh_token = generate_token(); //generate new token
						// echo "XX";	
						$operation->update_specific_token_status($results['user_id'],$data['data']['token'],0); //expired current browser token for specific browser

						//Add new token to DB
						$operation->add_new_token($new_refresh_token,$results['user_id'],$results['device_info']);
						$API_respond_fields['respond_new_token'] = $new_refresh_token;
						$user_info = $operation->get_specific_user_info($results['user_id']);
						$API_respond_fields['respond_user_packet'] = $user_info;
			        	echo json_encode($API_respond_fields);
	        		}else{	        			
			        	//Invalid
						$API_respond_fields['respond_flag'] = "1"; //0-error,1-normal,2-auto reissue token
						$API_respond_fields['respond'] = "0"; //0-invalid, 1- valid
						$API_respond_fields['respond_description'] = "Token expired " . $hour_diff . ' ' . $token_timeout;	
						// $API_respond_fields['respond_description'] = "Token expired " . $hour_diff;
			        	echo json_encode($API_respond_fields);
	        		}
	        	}else{
		        	if($results['status'] == 1){ //Check status 0-expired,1-active
		        		switch($data['data']['site_id']){
		        			case "IDP":
				        		//All valid
								$API_respond_fields['respond_flag'] = "1"; //0-error,1-normal,2-auto reissue token
								$API_respond_fields['respond'] = "1"; //0-invalid, 1- valid
								$user_info = $operation->get_specific_user_info($results['user_id']);
								$API_respond_fields['respond_user_packet'] = $user_info;
								$API_respond_fields['respond_description'] = "Token is active and available " . $hour_diff . ' ' . $token_timeout;	

								// $API_respond_fields['respond_description'] = "Token is active and available " . $hour_diff;	
			        			echo json_encode($API_respond_fields);
		        			break;
		        			default:
			        			$site_status = check_specific_sp_allowed($operation,$data['data']['site_id'],$results['user_id']);

				        		if($site_status['status']==1){
					        		//All valid
									$API_respond_fields['respond_flag'] = "1"; //0-error,1-normal,2-auto reissue token
									$API_respond_fields['respond'] = "1"; //0-invalid, 1- valid
									$user_info = $operation->get_specific_user_info($results['user_id']);
									$API_respond_fields['respond_user_packet'] = $user_info;
									$API_respond_fields['respond_description'] = "Token is active and available " . $hour_diff;		        			
				        		}else{		        			
					        		//Token valid, site invalid (blacklist/not allowed)
									$API_respond_fields['respond_flag'] = "1"; //0-error,1-normal,2-auto reissue token
									$API_respond_fields['respond'] = "0"; //0-invalid, 1- valid
									$API_respond_fields['respond_description'] = "Token is active and available but Site not allowed to access " . $hour_diff;
				        		}
					        	echo json_encode($API_respond_fields);
		        			break;
		        		}
		        		
		        	}else{
		        		//INVALID
						$API_respond_fields['respond_flag'] = "1"; //0-error,1-normal,2-auto reissue token
						$API_respond_fields['respond'] = "0"; //0-invalid, 1- valid
						$API_respond_fields['respond_description'] = "Token is active but status deactivate ". $hour_diff;
			        	echo json_encode($API_respond_fields);
		        	}
	        	}
	        }else{
					//INVALID
					$API_respond_fields['respond_flag'] = "1"; //0-error,1-normal,2-auto reissue token
					$API_respond_fields['respond'] = "0"; //0-invalid, 1- valid
					$API_respond_fields['respond_description'] = "Invalid token " . $hour_diff;
					echo json_encode($API_respond_fields);
	        }
        }
	break;
	default:
		// echo "no data";
	break;
}

function generate_token(){
	return oneid_generate_sso_token();
}


function get_hour_diff($time_start,$time_end){

	return round((strtotime($time_start) - strtotime($time_end))/3600, 1);
}


function check_specific_sp_allowed($operation,$sp_id,$user_id){
         //get user info
        $user_info = $operation->get_specific_user_info($user_id);
        //get category acl 
        $acl_result_group = $operation->specfic_user_get_sp_list_by_group($user_info['u_category']);
        //get category acl 
        $acl_result_single = $operation->specfic_user_get_sp_list_by_specific_sp($user_id);
        //get blacklist acl
        $acl_result_blacklist = $operation->specfic_user_get_sp_blacklist($user_id);

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
        return array('status' => $status);
     }

?>
