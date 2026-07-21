DROP TABLE IF EXISTS admin_step_up_grants;
DROP TABLE IF EXISTS admin_step_up_challenges;
DROP TABLE IF EXISTS admin_mfa_preferences;
DROP TABLE IF EXISTS admin_mfa_factors;

ALTER TABLE sys_config
    DROP CHECK chk_sys_config_admin_2fa_enabled,
    DROP COLUMN admin_2fa_enabled;
