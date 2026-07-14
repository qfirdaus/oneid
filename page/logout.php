<?php
require_once __DIR__ . '/../lib/session_security.php';
oneid_start_secure_session();
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/SSO_IDP_INC.php';
require_once __DIR__ . '/../app/Auth/LogoutHandler.php';

\OneId\App\Auth\LogoutHandler::handle($operation, SSO_IDP_DOMAIN);
