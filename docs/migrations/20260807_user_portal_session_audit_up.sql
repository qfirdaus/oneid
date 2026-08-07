INSERT INTO syslog_event_conf (syslog_event_id, syslog_event_name)
VALUES
    (68, 'USER_PORTAL_SESSION_EXPIRED'),
    (69, 'USER_PORTAL_SESSION_RENEWED'),
    (70, 'USER_PORTAL_SESSION_ENDED')
ON DUPLICATE KEY UPDATE syslog_event_name = VALUES(syslog_event_name);
