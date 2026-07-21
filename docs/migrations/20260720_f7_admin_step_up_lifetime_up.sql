ALTER TABLE sys_config
    ADD COLUMN admin_step_up_lifetime_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15
        AFTER admin_2fa_enabled,
    ADD CONSTRAINT chk_sys_config_admin_step_up_lifetime
        CHECK (admin_step_up_lifetime_minutes IN (5,10,15,30));

INSERT INTO syslog_event_conf(syslog_event_id,syslog_event_name)
VALUES (54,'ADMIN_STEP_UP_LIFETIME_UPDATED');
