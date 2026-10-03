<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/Auth/MobileOidc/LifecycleSchema.php';
use OneId\App\Auth\MobileOidc\LifecycleSchema;
return [
    'CREATE TABLE mobile_oidc_mutex(singleton_id TINYINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB',
    'INSERT INTO mobile_oidc_mutex VALUES(1)',
    'CREATE TABLE mobile_oidc_records(record_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,record_json JSON NOT NULL,updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)) ENGINE=InnoDB',
    ...LifecycleSchema::statements(),
];
