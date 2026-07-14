<?php

/**
 * Static inputs for the M0 User Info modal characterization runner.
 *
 * This file deliberately contains no application bootstrap or runtime calls.
 */
return [
    'runtime_files' => [
        'admin/dashboard.php',
        'lib/q_func.php',
        'lib/request_security.php',
        'lib/Database.php',
        'lib/external_data_source_API.php',
    ],
    'admin_actions' => [
        'admin_get_get_specific_user_profile_info',
        'get_specific_user_sp_access_list',
        'get_add_new_specific_apps_to_accissible_list',
        'admin_change_user_category',
        'admin_resync_specific_user',
        'admin_reset_password_user',
        'admin_deactivate_user_record',
        'admin_reactivate_user_record',
        'add_new_specific_apps_to_user',
        'admin_set_deny_access_record',
        'admin_uplift_blacklist_record',
    ],
];
