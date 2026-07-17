<?php
require_once __DIR__ . '/session_security.php';
oneid_start_secure_session();
require_once __DIR__ . '/config.php';
//---------- SSO Checker
//---Configure this for IDP
$site_id="IDP";
$SSO_SP_LOGIN = "";
$SSO_IDP_DOMAIN = SSO_IDP_DOMAIN;
$SSO_SP_DASHBOARD = SSO_SP_DASHBOARD;
$SP_current_page = GET_CURRENT_PAGE_URI();
function LOCAL_COOKIES_HANDLER(){
	if(isset($_COOKIE['sso_cre'])) {
		$rawCookie = (string) $_COOKIE['sso_cre'];
		$legacyCookie = json_decode($rawCookie);
		$token = is_object($legacyCookie) && isset($legacyCookie->sso_cre)
			? (string) $legacyCookie->sso_cre
			: $rawCookie;
		return (object) [
			'sso_cre' => $token,
			'sso_dt' => date('Y-m-d H:i:s', (int) ($_SESSION['oneid_session_last_activity'] ?? time())),
			'u_id' => (string) ($_SESSION['login_user'] ?? ''),
		];
	}
}
if(!isset($_COOKIE['sso_cre'])) {
  //Check if have new SSO token to be publish to browser
	if(isset($_GET['new_sso_cre'])) {
		//Check if new_sso_cre is valid or not		
		$API_post_fields = array();
		$API_post_fields['flag'] = 1;
		$API_post_fields['data'] = array("site_id"=>$site_id,"token"=>$_GET['new_sso_cre']);		
		$API_REQUEST_RESULT = json_decode(API_REQUEST(json_encode($API_post_fields),$SSO_IDP_DOMAIN),true);
		switch($API_REQUEST_RESULT['respond_flag']){
			case "1": //normal
				switch($API_REQUEST_RESULT['respond']){
					case "0": //Invalid
						if($SSO_IDP_DOMAIN == $SP_current_page){
						}else{
			  				header('Location: '.$SSO_IDP_DOMAIN); 
						}
					break;
					case "1": //Valid
						//Set the sso_cre token to cookies
						COOKIE_SETTER($_GET['new_sso_cre'],$API_REQUEST_RESULT['respond_user_packet']);
			            oneid_establish_authenticated_session($API_REQUEST_RESULT['respond_user_packet']);

						if($SSO_IDP_DOMAIN == $SP_current_page){
							header('Location: '.$SSO_SP_DASHBOARD); 
						}else{
						}
					break; 
				}
			break;
			case "2": //Auto Reissue token
			echo "X";
			break;
			default:
				if($SSO_IDP_DOMAIN == $SP_current_page){
				}else{
	  			header('Location: '.$SSO_IDP_DOMAIN); 	
				}		
			break;
		}
	}else{		
	  //Go to IDP
		if($SSO_IDP_DOMAIN == $SP_current_page){
		}else{	 
			if(isset($_GET['site_id'])){
			}else{
				header('Location: '.$SSO_IDP_DOMAIN);	
			}
		}	 
	}
}else{
		$cookie = LOCAL_COOKIES_HANDLER();
		$API_post_fields = array();
		$API_post_fields['flag'] = 1;
		$API_post_fields['data'] = array("site_id"=>$site_id,"token"=>$cookie->sso_cre);
		$API_REQUEST_RESULT = json_decode(API_REQUEST(json_encode($API_post_fields),$SSO_IDP_DOMAIN),true);
		switch($API_REQUEST_RESULT['respond_flag']){
			case "1": //normal
				switch($API_REQUEST_RESULT['respond']){
					case "0": //Invalid
						if(isset($_GET['new_sso_cre'])) { //check ada tak new_sso_cre. kalau ad kite check dulu valid x valid. kalau valid kite use new token
							//Check if new_sso_cre is valid or not		
							$API_post_fields = array();
							$API_post_fields['flag'] = 1;
							$API_post_fields['data'] = array("site_id"=>$site_id,"token"=>$_GET['new_sso_cre']);		
							$API_REQUEST_RESULT = json_decode(API_REQUEST(json_encode($API_post_fields),$SSO_IDP_DOMAIN),true);
							
							switch($API_REQUEST_RESULT['respond_flag']){
								case "1": //normal
									switch($API_REQUEST_RESULT['respond']){
										case "0": //Invalid
											if($SSO_IDP_DOMAIN == $SP_current_page){
											}else{	  			
								  				header('Location: '.$SSO_IDP_DOMAIN); 
											}	 
										break;
										case "1": //Valid
											//Set the sso_cre token to cookies
											COOKIE_SETTER($_GET['new_sso_cre'],$API_REQUEST_RESULT['respond_user_packet']);								
									            oneid_establish_authenticated_session($API_REQUEST_RESULT['respond_user_packet']);
											if($SSO_IDP_DOMAIN == $SP_current_page){
												header('Location: '.$SSO_SP_DASHBOARD); 
											}else{	  			
											}	 
										break; 
									}
								break;
								case "2": //Auto Reissue token
								// echo "X";
								break;
								default:
									if($SSO_IDP_DOMAIN == $SP_current_page){
									}else{	  	
						  				header('Location: '.$SSO_IDP_DOMAIN); 		
									}	 			
								break;
							}
						}else{
							if($SSO_IDP_DOMAIN == $SP_current_page){
							}else{	  	
			  					header('Location: '.$SSO_IDP_DOMAIN); 	
							}	
						}
					break;
					case "1": //Valid
							// echo json_encode($API_REQUEST_RESULT);
						//Update the sso_cre token to cookies
						COOKIE_SETTER($cookie->sso_cre,$API_REQUEST_RESULT['respond_user_packet']);
			            oneid_establish_authenticated_session($API_REQUEST_RESULT['respond_user_packet']);
			            //Check is there any redirect to service proveder "side_id"
			            if(isset($_GET['site_id'])){
			            	$check_result = GET_CHECK_SPECIFIC_SP_ALLOWED($operation,$_GET['site_id']);
			            	if($check_result['status']=="1"){
			            		header('Location: '.$check_result['domain'].'?new_sso_cre='.$cookie->sso_cre);
			            	}else{			            		
								header('Location: '.$SSO_SP_DASHBOARD); 
			            	}
			            }else{			            	
							if($SSO_IDP_DOMAIN == $SP_current_page){
								header('Location: '.$SSO_SP_DASHBOARD); 
							}else{	  	
							}	
			            }
					break; 
				}
			break;
			case "2": //Auto Reissue token
				COOKIE_SETTER($API_REQUEST_RESULT['respond_new_token'],$API_REQUEST_RESULT['respond_user_packet']);
				oneid_establish_authenticated_session($API_REQUEST_RESULT['respond_user_packet']);
				if($SSO_IDP_DOMAIN == $SP_current_page){
					header('Location: '.$SSO_SP_DASHBOARD); 
				}else{	  	
				}	
			break;
			default:
				if($SSO_IDP_DOMAIN == $SP_current_page){
				}else{
					header	('Location: '.$SSO_IDP_DOMAIN); 	
				}			
			break;
		}
}

function API_REQUEST($API_DATA,$SSO_IDP_DOMAIN){
    $API_URII = $SSO_IDP_DOMAIN.'api.php';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $API_URII);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch,CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: text/plain'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, ($API_DATA));

    $result = curl_exec($ch);

    // also get the error and response code
    $errors = curl_error($ch);
    $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($result === false || $response < 200 || $response >= 300) {
        error_log(sprintf('SSO internal API failed http=%d error=%s', $response, $errors));
        return json_encode(['respond_flag' => '0', 'respond' => '0']);
    }
    return ($result);
}
//--------- END OF SSO Checker



function COOKIE_SETTER($sso_cre,$respond_user_packet){
	oneid_set_sso_cookie((string) $sso_cre);
}
function GET_CURRENT_PAGE_URI(){
	$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";  
	$CurPageURL = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; 
	 
	return strtok($CurPageURL,"?"); 
}

 function GET_CHECK_SPECIFIC_SP_ALLOWED($operation,$sp_id){
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

function SSO_logOut_IDP($operation){
	echo "X";
	return;
	if (isset($_COOKIE['sso_cre'])) {		
		$cookie = LOCAL_COOKIES_HANDLER();
		$results = $operation->check_token($cookie->sso_cre);
		if ($results) {
			$operation->update_specific_token_status($results['user_id'],$cookie->sso_cre,0);
		}
		oneid_clear_sso_cookie();
	}	
	header('Location: '.$SSO_IDP_DOMAIN); 
}

?>
