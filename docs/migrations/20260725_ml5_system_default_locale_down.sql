ALTER TABLE sys_config
    DROP CHECK chk_sys_config_default_locale,
    DROP COLUMN default_locale;
