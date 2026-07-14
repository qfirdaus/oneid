<?php

return [
    'interfaces' => [
        \OneId\App\Sync\Contracts\ExternalUserSourceInterface::class => [
            'fetchAll',
        ],
        \OneId\App\Sync\Contracts\InitialPasswordFactoryInterface::class => [
            'createHash',
        ],
        \OneId\App\Sync\Contracts\SyncPolicyInterface::class => [
            'excludedUserIds',
            'categoryIdFor',
        ],
        \OneId\App\Sync\Contracts\SyncPersistenceInterface::class => [
            'begin',
            'commit',
            'rollback',
            'createHeader',
            'activeUsers',
            'inactiveUserIds',
            'deactivateUser',
            'updateUser',
            'updateHeaderStatus',
            'stageExternalUser',
            'insertExternalUser',
            'markStagedUser',
            'appendChanges',
            'updateSummary',
            'header',
        ],
    ],
    'production_files' => [
        'lib/sync_user_runner.php',
        'lib/q_func.php',
        'page/dashboard.php',
        'admin/dashboard.php',
    ],
    'legacy_sync_runner_sha256' => '7a5fbe4e9d176661eda4f7d26449641edef683e77d355f9b9d5e227a9331b9df',
];
