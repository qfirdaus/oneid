<?php 
require_once './lib/config.php';
require_once './lib/stateless_jwt.php';

ini_set('always_populate_raw_post_data', -1);
$json = file_get_contents('php://input');
$json = str_replace('"{"', "{", $json);
$json = str_replace('"}"', "}", $json);
$data = json_decode($json,true);

$token_timeout = $operation->get_system_config()['token_timeout']; //24 means 1 day
// echo json_encode($data);s
switch($data['flag']){
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
	        //Here will check with the system settings for token timeout
	        $hour_diff = get_hour_diff($results['token_datetime'],date("Y-m-d H:i:s"));
	        // echo "X";
	        // echo $hour_diff . "XX";
	        if($hour_diff <= 0){
	        	//Check timeout
	        	if(abs($hour_diff) > $token_timeout){ //Token had pass the token_timeout max life but,
	        		//we have BUFFER OF 1 HOUR (1hr) if the user is still active, 
	        		//token will be reissued , this to cater if user is actively using the system beyond the max token life,
	        		// so system will automatically reissued a new token without user knowing the new token. 
	        		if((abs($hour_diff) - $token_timeout) < 1){
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
	$a= str_replace(".", "", uniqid('',true));
    return $a;
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