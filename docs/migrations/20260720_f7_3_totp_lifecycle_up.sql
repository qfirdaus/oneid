ALTER TABLE admin_mfa_factors
    ADD COLUMN enrollment_session_hash CHAR(64) NOT NULL AFTER correlation_id,
    ADD COLUMN enrollment_browser_digest CHAR(64) NOT NULL AFTER enrollment_session_hash,
    ADD COLUMN last_used_time_step BIGINT UNSIGNED NULL AFTER last_used_at,
    ADD KEY idx_admin_mfa_factor_replay (admin_user_id, factor_status, last_used_time_step);

INSERT INTO syslog_event_conf(syslog_event_id,syslog_event_name) VALUES
    (44,'ADMIN_TOTP_ENROLLED'),
    (45,'ADMIN_TOTP_CONFIRMED'),
    (46,'ADMIN_TOTP_VERIFIED'),
    (47,'ADMIN_TOTP_FAILED'),
    (48,'ADMIN_TOTP_REVOKED'),
    (49,'ADMIN_TOTP_RECOVERY_USED');
