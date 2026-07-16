DELETE FROM syslog_event_conf WHERE syslog_event_id IN (33,34,35);
ALTER TABLE sys_config
    CHANGE COLUMN password_reset_email_enabled email_OTP INT NOT NULL;
