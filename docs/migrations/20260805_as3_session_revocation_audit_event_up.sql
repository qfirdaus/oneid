INSERT INTO syslog_event_conf(syslog_event_id,syslog_event_name)
VALUES(66,'ADMIN_ACTIVE_SESSION_REVOKE')
ON DUPLICATE KEY UPDATE syslog_event_name=VALUES(syslog_event_name);
