INSERT INTO syslog_event_conf(syslog_event_id,syslog_event_name)
VALUES(67,'ADMIN_ACCESS_RENEW')
ON DUPLICATE KEY UPDATE syslog_event_name=VALUES(syslog_event_name);
