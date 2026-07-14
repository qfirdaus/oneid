<?php
require_once __DIR__ . '/session_security.php';
oneid_start_secure_session();
require_once __DIR__ . '/config.php';
//---------- SSO Checker
$site_id="IDP";
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
						echo "a1";
							echo $_COOKIE['sso_cre'];
							// echo json_encode($API_REQUEST_RESULT);
			  			// header('Location: '.SSO_IDP_DOMAIN.'?site_id='.$site_id); 
					break;
					case "1": //Valid
						//Set the sso_cre token to cookies
						setcookie('sso_cre', $_GET['new_sso_cre'], time() + (86400 * 30),'/',''); // 86400 = 1 day (this is default 1 day)						
			            $_SESSION['user'] = $API_REQUEST_RESULT['respond_user_packet']['u_name'];
			            $_SESSION['login_user']=$API_REQUEST_RESULT['respond_user_packet']['u_id'];
			            $_SESSION['login_status']="true";
			            $_SESSION['login_user_type']=$API_REQUEST_RESULT['respond_user_packet']['u_type'];
						// echo "valid"; //<--- Redirect to SP main page / if inside user page, remove this line
	  						header('Location: '.SSO_SP_DASHBOARD); 
					break; 
				}
			break;
			case "2": //Auto Reissue token
			echo "X";
			break;
			default:
	  			// header('Location: '.SSO_IDP_DOMAIN.'?site_id='.$site_id); 			
			break;
		}
	}else{		
	  //Go to IDP
	  // header('Location: '.SSO_IDP_DOMAIN.'?site_id='.$site_id); 
	}
}else{		
		$API_post_fields = array();
		$API_post_fields['flag'] = 1;
		$API_post_fields['data'] = array("site_id"=>$site_id,"token"=>$_COOKIE['sso_cre']);
		$API_REQUEST_RESULT = json_decode(API_REQUEST(json_encode($API_post_fields),SSO_IDP_DOMAIN),true);
		// echo $_COOKIE['sso_cre'];
		// return;
		// echo API_REQUEST(json_encode($API_post_fields),SSO_IDP_DOMAIN);
							// echo json_encode($API_REQUEST_RESULT);
							// return;
		switch($API_REQUEST_RESULT['respond_flag']){
			case "1": //normal
						
						// 	echo json_encode($API_REQUEST_RESULT);
						// return;	
				switch($API_REQUEST_RESULT['respond']){
					case "0": //Invalid
						if(isset($_GET['new_sso_cre'])) { //check ada tak new_sso_cre. kalau ad kite check dulu valid x valid. kalau valid kite use new token
							//Check if new_sso_cre is valid or not		

							$API_post_fields = array();
							$API_post_fields['flag'] = 1;
							$API_post_fields['data'] = array("site_id"=>$site_id,"token"=>$_GET['new_sso_cre']);		
							$API_REQUEST_RESULT = json_decode(API_REQUEST(json_encode($API_post_fields),SSO_IDP_DOMAIN),true);
							
							switch($API_REQUEST_RESULT['respond_flag']){
								case "1": //normal
									switch($API_REQUEST_RESULT['respond']){
										case "0": //Invalid
											// echo "a1";
								  			// header('Location: '.SSO_IDP_DOMAIN.'?site_id='.$site_id); 
										break;
										case "1": //Valid
											//Set the sso_cre token to cookies
											setcookie('sso_cre', $_GET['new_sso_cre'], time() + (86400 * 30),'/',''); // 86400 = 1 day (this is default 1 day)
								            $_SESSION['user'] = $API_REQUEST_RESULT['respond_user_packet']['u_name'];
								            $_SESSION['login_user']=$API_REQUEST_RESULT['respond_user_packet']['u_id'];
								            $_SESSION['login_status']="true";
								            $_SESSION['login_user_type']=$API_REQUEST_RESULT['respond_user_packet']['u_type'];
											// echo "valid"; //<--- Redirect to SP main page / if inside user page, remove this lines
	  										// header('Location: '.SSO_SP_DASHBOARD); 		  	
						  						header('Location: '.SSO_SP_DASHBOARD); 
										break; 
									}
								break;
								case "2": //Auto Reissue token
								echo "X";
								break;
								default:
						  			// header('Location: '.SSO_IDP_DOMAIN.'?site_id='.$site_id); 			
								break;
							}
						}else{
						// 	echo $_COOKIE['sso_cre'];
						// 	echo json_encode($API_REQUEST_RESULT);
						// echo "a3";	
			  				// header('Location: '.SSO_IDP_DOMAIN.'?site_id='.$site_id); 
						}
					break;
					case "1": //Valid
							// echo json_encode($API_REQUEST_RESULT);
						//Update the sso_cre token to cookies
						setcookie('sso_cre', $_COOKIE['sso_cre'], time() + (86400 * 30),'/',''); // 86400 = 1 day (this is default 1 day)	
			            $_SESSION['user'] = $API_REQUEST_RESULT['respond_user_packet']['u_name'];
			            $_SESSION['login_user']=$API_REQUEST_RESULT['respond_user_packet']['u_id'];
			            $_SESSION['login_status']="true";
			            $_SESSION['login_user_type']=$API_REQUEST_RESULT['respond_user_packet']['u_type'];			
						// echo $_COOKIE['sso_cre'] . '(valid)';
						// echo $SP_current_page;
							header('Location: '.SSO_SP_DASHBOARD); 
					break; 
				}
			break;
			case "2": //Auto Reissue token
			// echo "X";
				setcookie('sso_cre', $API_REQUEST_RESULT['respond_new_token'], time() + (86400 * 30),'/',''); // 86400 = 1 day (this is default 1 day)
				$_SESSION['user'] = $API_REQUEST_RESULT['respond_user_packet']['u_name'];
				$_SESSION['login_user']=$API_REQUEST_RESULT['respond_user_packet']['u_id'];
				$_SESSION['login_status']="true";
				$_SESSION['login_user_type']=$API_REQUEST_RESULT['respond_user_packet']['u_type'];
				// echo "reissue token - ";
				// echo $_COOKIE['sso_cre'];

							header('Location: '.SSO_SP_DASHBOARD); 
			break;
			default:
						// echo "a3";
	  			// header('Location: '.SSO_IDP_DOMAIN.'?site_id='.$site_id); 			
			break;
		}
}

function API_REQUEST($API_DATA,$SSO_IDP_DOMAIN){
    $API_URII = SSO_IDP_DOMAIN.'api.php';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $API_URII);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch,CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: text/plain'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, ($API_DATA));

    $result = curl_exec($ch);

    // also get the error and response code
    $errors = curl_error($ch);
    $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($result);
}
//--------- END OF SSO Checker

function GET_CURRENT_PAGE_URI(){
	$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";  
	$CurPageURL = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];  
	return $CurPageURL;  
}

function SSO_logOut_IDP($operation){
	echo "X";
	return;
	if (isset($_COOKIE['sso_cre'])) {		
        $results = $operation->check_token($_COOKIE['sso_cre']);
        $operation->update_specific_token_status($results['user_id'],$_COOKIE['sso_cre'],0); //expired specific token for specific site & user
    	unset($_COOKIE['sso_cre']); 
    	setcookie('sso_cre', null, -1, '/'); 
	}	
	header('Location: '.SSO_IDP_DOMAIN); 
}

?>
