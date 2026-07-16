ALTER TABLE token_tbl
    ADD COLUMN policy_revoke_at DATETIME NULL AFTER token_issued_at,
    ADD COLUMN policy_revoke_correlation VARCHAR(32) NULL AFTER policy_revoke_at,
    ADD INDEX idx_token_policy_revoke (status, policy_revoke_at);

INSERT INTO syslog_event_conf(syslog_event_id,syslog_event_name) VALUES
    (30,'ADMIN_SSO_POLICY_REVOCATION_SCHEDULED'),
    (31,'SSO_POLICY_TOKEN_REVOKED'),
    (32,'ADMIN_SSO_POLICY_REVOCATION_CANCELLED');
