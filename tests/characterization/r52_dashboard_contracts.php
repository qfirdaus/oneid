<?php

return [
    [
        'name' => 'user dashboard',
        'file' => 'page/dashboard.php',
        'loc' => 1261,
        'q_func_calls' => 7,
        'function_declarations' => 15,
        'script_tags' => 12,
        'fragments' => [
            'oneid_require_authenticated_page();',
            "include __DIR__ . '/const/top.php'",
            "url: '../lib/q_func'",
            'window.location.href = "logout";',
            'action_change_password',
            'update_specific_token_datetime',
        ],
    ],
    [
        'name' => 'admin dashboard',
        'file' => 'admin/dashboard.php',
        'loc' => 3905,
        'q_func_calls' => 41,
        'function_declarations' => 62,
        'script_tags' => 18,
        'fragments' => [
            'oneid_require_admin_page();',
            "include __DIR__ . '/const/top.php'",
            "url: '../lib/q_func'",
            'admin_add_sync_user',
            'admin_get_sync_sessions',
            'admin_get_sync_log_detail',
            'window.open("./user_list.php?category_id=',
        ],
    ],
    [
        'name' => 'admin user list',
        'file' => 'admin/user_list.php',
        'loc' => 132,
        'q_func_calls' => 0,
        'function_declarations' => 0,
        'script_tags' => 0,
        'fragments' => [
            'oneid_require_admin_page();',
            "require_once __DIR__ . '/../lib/request_security.php'",
            'category_id',
            'category_name',
        ],
    ],
];
