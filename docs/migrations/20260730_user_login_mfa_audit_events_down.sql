DELETE FROM syslog_event_conf
WHERE (syslog_event_id=55 AND syslog_event_name='USER_MFA_PRIMARY_AUTH_PENDING')
   OR (syslog_event_id=56 AND syslog_event_name='USER_MFA_EMAIL_CHALLENGE')
   OR (syslog_event_id=57 AND syslog_event_name='USER_MFA_EMAIL_VERIFY')
   OR (syslog_event_id=58 AND syslog_event_name='USER_MFA_TOTP_VERIFY')
   OR (syslog_event_id=59 AND syslog_event_name='USER_MFA_LOGIN_COMPLETE')
   OR (syslog_event_id=60 AND syslog_event_name='USER_MFA_FACTOR_ENROLL')
   OR (syslog_event_id=61 AND syslog_event_name='USER_MFA_FACTOR_REVOKE')
   OR (syslog_event_id=62 AND syslog_event_name='USER_MFA_PREFERENCE_CHANGE')
   OR (syslog_event_id=63 AND syslog_event_name='USER_MFA_ADMIN_RECOVERY')
   OR (syslog_event_id=64 AND syslog_event_name='USER_MFA_POLICY_CHANGE')
   OR (syslog_event_id=65 AND syslog_event_name='USER_MFA_RETENTION_PURGE');
