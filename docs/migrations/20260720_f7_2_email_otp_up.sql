ALTER TABLE admin_step_up_challenges
    ADD COLUMN sent_at DATETIME NULL AFTER created_at,
    ADD KEY idx_admin_step_up_challenge_rate
        (admin_user_id, purpose, created_at),
    ADD KEY idx_admin_step_up_challenge_ip_rate
        (requesting_ip, purpose, created_at);

INSERT INTO syslog_event_conf(syslog_event_id,syslog_event_name) VALUES
    (37,'ADMIN_2FA_REQUESTED'),
    (38,'ADMIN_2FA_SENT'),
    (39,'ADMIN_2FA_VERIFIED'),
    (40,'ADMIN_2FA_FAILED'),
    (41,'ADMIN_2FA_EXPIRED'),
    (42,'ADMIN_2FA_RATE_LIMITED'),
    (43,'ADMIN_2FA_DELIVERY_FAILED');
