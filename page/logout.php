<?php
session_start(); // Starting Session
require_once '../lib/config.php';
require_once '../lib/SSO_IDP_INC.php';

	if (isset($_COOKIE['sso_cre'])) {		
        $operation->update_whole_token_status(LOCAL_COOKIES_HANDLER()->u_id,0); //expired specific token for specific site & user
    	unset($_COOKIE['sso_cre']); 
    	setcookie('sso_cre', null, -1, '/'); 
	}	
	header('Location: https://oneid.local/'); 
	// header('Location: http://localhost/upnm_sso_live/'); 
?>