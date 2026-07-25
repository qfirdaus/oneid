ALTER TABLE sys_config
    ADD COLUMN default_locale CHAR(2) NOT NULL DEFAULT 'ms' AFTER admin_step_up_lifetime_minutes,
    ADD CONSTRAINT chk_sys_config_default_locale CHECK (default_locale IN ('ms','en'));
