ALTER TABLE sys_config
    CHANGE COLUMN email_OTP password_reset_email_enabled INT NOT NULL;

INSERT INTO syslog_event_conf(syslog_event_id,syslog_event_name) VALUES
    (33,'ADMIN_PASSWORD_RECOVERY_POLICY_UPDATED'),
    (34,'ADMIN_PASSWORD_RECOVERY_TEST_EMAIL'),
    (35,'PASSWORD_RECOVERY_DELIVERY_FAILED');
