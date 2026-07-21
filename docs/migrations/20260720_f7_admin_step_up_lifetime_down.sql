-- Preserve configuration_change_history and syslog history before rollback.
ALTER TABLE sys_config
    DROP CHECK chk_sys_config_admin_step_up_lifetime,
    DROP COLUMN admin_step_up_lifetime_minutes;

-- Event 54 is intentionally retained so historical audit rows remain readable.
