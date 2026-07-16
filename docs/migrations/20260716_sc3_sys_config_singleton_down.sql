ALTER TABLE sys_config
    DROP INDEX uq_sys_config_singleton,
    DROP CHECK chk_sys_config_singleton,
    DROP COLUMN singleton_key;
