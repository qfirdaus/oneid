<?php

return [
    'user' => [
        'username_env' => 'ONEID_R52_USER_USERNAME',
        'password_env' => 'ONEID_R52_USER_PASSWORD',
        'dashboard' => '/page/dashboard.php',
        'logout' => '/page/logout.php',
        'admin_status' => 403,
    ],
    'admin' => [
        'username_env' => 'ONEID_R52_ADMIN_USERNAME',
        'password_env' => 'ONEID_R52_ADMIN_PASSWORD',
        'dashboard' => '/admin/dashboard.php',
        'logout' => '/admin/logout.php',
        'admin_status' => 200,
    ],
];
