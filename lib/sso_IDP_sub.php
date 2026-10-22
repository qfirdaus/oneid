<?php
//session_start(); // Starting Session
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/integration_security.php';
//---------- SSO Checker
$site_id="IDP";
$IDP_Domain=SSO_IDP_DOMAIN;
// $IDP_Domain="http://172.16.0.14/upnm_sso_live/"; //<--- 20 May 2025 Change here
$SP_current_page = GET_CURRENT_PAGE_URI();
if(!isset($_COOKIE['sso_cre'])) {
  //Check if have new SSO token to be publish to browser
	if(isset($_GET['new_sso_cre'])) {
		//Check if new_sso_cre is valid or not		
		$API_post_fields = array();
		$API_post_fields['flag'] = 1;
		$API_post_fields['data'] = array("site_id"=>$site_id,"token"=>$_GET['new_sso_cre']);		
		$API_REQUEST_RESULT = json_decode(API_REQUEST(json_encode($API_post_fields),SSO_IDP_DOMAIN),true);
		// echo json_encode($API_REQUEST_RESULT);
		// echo "y";
		// return;
		switch($API_REQUEST_RESULT['respond_flag']){
			case "1": //normal
				switch($API_REQUEST_RESULT['respond']){
						case "0": //Invalid
			  			header('Location: '.SSO_IDP_DOMAIN); 
					break;
					case "1": //Valid
						//Set the sso_cre token to cookies
							oneid_set_sso_cookie((string) $_GET['new_sso_cre'], 60 * 30);
			            $_SESSION['user'] = $API_REQUEST_RESULT['respond_user_packet']['u_name'];
			            $_SESSION['login_user']=$API_REQUEST_RESULT['respond_user_packet']['u_id'];
			            $_SESSION['login_status']="true";
			            $_SESSION['login_user_type']=$API_REQUEST_RESULT['respond_user_packet']['u_type'];
					break; 
				}
			break;
				case "2": //Auto Reissue token
			break;
			default:
	  			header('Location: '.SSO_IDP_DOMAIN); 			
			break;
		}
	}else{		
	  //Go to IDP
	  header('Location: '.SSO_IDP_DOMAIN); 
	}
}else{		
		$API_post_fields = array();
		$API_post_fields['flag'] = 1;
		$API_post_fields['data'] = array("site_id"=>$site_id,"token"=>$_COOKIE['sso_cre']);
		$API_REQUEST_RESULT = json_decode(API_REQUEST(json_encode($API_post_fields),SSO_IDP_DOMAIN),true);
		switch($API_REQUEST_RESULT['respond_flag']){
			case "1": //normal
				switch($API_REQUEST_RESULT['respond']){
					case "0": //Invalid
						if(isset($_GET['new_sso_cre'])) { //check ada tak new_sso_cre. kalau ad kite check dulu valid x valid. kalau valid kite use new token
							//Check if new_sso_cre is valid or not		

						// echo "a2";	
							$API_post_fields = array();
							$API_post_fields['flag'] = 1;
							$API_post_fields['data'] = array("site_id"=>$site_id,"token"=>$_GET['new_sso_cre']);		
							$API_REQUEST_RESULT = json_decode(API_REQUEST(json_encode($API_post_fields),SSO_IDP_DOMAIN),true);
							
							switch($API_REQUEST_RESULT['respond_flag']){
								case "1": //normal
									switch($API_REQUEST_RESULT['respond']){
										case "0": //Invalid
											// echo "a1";
								  			header('Location: '.SSO_IDP_DOMAIN); 
										break;
										case "1": //Valid
											//Set the sso_cre token to cookies
												oneid_set_sso_cookie((string) $_GET['new_sso_cre'], 60 * 30);
								            $_SESSION['user'] = $API_REQUEST_RESULT['respond_user_packet']['u_name'];
								            $_SESSION['login_user']=$API_REQUEST_RESULT['respond_user_packet']['u_id'];
								            $_SESSION['login_status']="true";
								            $_SESSION['login_user_type']=$API_REQUEST_RESULT['respond_user_packet']['u_type'];
										break; 
									}
								break;
									case "2": //Auto Reissue token
								break;
								default:
						  			header('Location: '.SSO_IDP_DOMAIN); 			
								break;
							}
						}else{
						// 	echo $_COOKIE['sso_cre'];
						// 	echo json_encode($API_REQUEST_RESULT);
						// echo "a3";	
			  				header('Location: '.SSO_IDP_DOMAIN); 
						}
					break;
					case "1": //Valid
					// echo "XX";
							// echo json_encode($API_REQUEST_RESULT);
						//Update the sso_cre token to cookies
							oneid_set_sso_cookie((string) $_COOKIE['sso_cre'], 60 * 30);
			            $_SESSION['user'] = $API_REQUEST_RESULT['respond_user_packet']['u_name'];
			            $_SESSION['login_user']=$API_REQUEST_RESULT['respond_user_packet']['u_id'];
			            $_SESSION['login_status']="true";
			            $_SESSION['login_user_type']=$API_REQUEST_RESULT['respond_user_packet']['u_type'];
							// echo $_COOKIE['sso_cre'];
					break; 
				}
			break;
			case "2": //Auto Reissue token
			// echo "X";
					oneid_set_sso_cookie((string) $API_REQUEST_RESULT['respond_new_token'], 60 * 30);
				$_SESSION['user'] = $API_REQUEST_RESULT['respond_user_packet']['u_name'];
				$_SESSION['login_user']=$API_REQUEST_RESULT['respond_user_packet']['u_id'];
				$_SESSION['login_status']="true";
				$_SESSION['login_user_type']=$API_REQUEST_RESULT['respond_user_packet']['u_type'];
			break;
			default:
						// echo "a3";
	  			header('Location: '.SSO_IDP_DOMAIN); 			
			break;
		}
}

function API_REQUEST($API_DATA,$SSO_IDP_DOMAIN){
	return oneid_internal_integration_post_json((string) $API_DATA, (string) $SSO_IDP_DOMAIN);
}
//--------- END OF SSO Checker

function GET_CURRENT_PAGE_URI(){
	$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";  
	$CurPageURL = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];  
	return $CurPageURL;  
}

function SSO_logOut_IDP($operation){
	return;
	if (isset($_COOKIE['sso_cre'])) {		
        $results = $operation->check_token($_COOKIE['sso_cre']);
        $operation->update_specific_token_status($results['user_id'],$_COOKIE['sso_cre'],0,'SESSION_EXPIRED'); //expired specific token for specific site & user
		oneid_clear_sso_cookie();
	}	
	header('Location: '.SSO_IDP_DOMAIN); 
}

?>
