DELETE FROM syslog_event_conf
WHERE (syslog_event_id = 68 AND syslog_event_name = 'USER_PORTAL_SESSION_EXPIRED')
   OR (syslog_event_id = 69 AND syslog_event_name = 'USER_PORTAL_SESSION_RENEWED')
   OR (syslog_event_id = 70 AND syslog_event_name = 'USER_PORTAL_SESSION_ENDED');
