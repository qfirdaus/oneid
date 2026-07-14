<?php

/**
 * S0 static contracts for admin external sync and manual user creation.
 *
 * These contracts describe the production behavior present when S0 started.
 * Some assertions intentionally preserve known weaknesses so later phases can
 * demonstrate that each behavior changed deliberately.
 */
return [
    'runtime_files' => [
        'admin/dashboard.php',
        'lib/q_func.php',
        'lib/request_security.php',
        'lib/Database.php',
        'lib/sync_user_runner.php',
    ],
    'admin_actions' => [
        'admin_add_sync_user',
        'action_add_new_user_manual_check_user_id',
    ],
    'manual_fields' => [
        'required' => [
            'add_new_manual_user_name',
            'add_new_manual_user_id',
            'add_new_manual_user_category',
        ],
        'optional_email' => 'add_new_user_data5',
    ],
    'runtime_sha256' => [
        'admin/dashboard.php' => '680495db3ceed6b9b181d8f6e11efcc7c060750d8d933f9b11d4196717469994',
        'lib/q_func.php' => 'b44534b888c0809ffc3185f73456a8415663d65871e9c7fa8c07c441f5e6a33b',
        'lib/request_security.php' => '9166ebc1ef94f92e44b6000bc6529d9d1519f1df6261625eaba7c9b7e8efde65',
        'lib/Database.php' => 'f9aff1d051c3cc7aa9d8902b299292fe49e70b34b997a69a2feb931109547658',
        'lib/sync_user_runner.php' => '7a5fbe4e9d176661eda4f7d26449641edef683e77d355f9b9d5e227a9331b9df',
    ],
];
